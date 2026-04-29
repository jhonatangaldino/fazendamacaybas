<?php

namespace App\Services\Metrics;

use App\Models\Agricultural\Field;
use App\Models\Agricultural\FieldApplication;
use App\Models\Agricultural\Harvest;
use App\Models\Agricultural\Planting;
use App\Models\Agricultural\Season;

/**
 * AgricolaMetrics — fonte única de verdade para KPIs do módulo agrícola.
 *
 * Substitui as fórmulas hoje em:
 *   - Admin\Agricultural\AgriculturalController::index (Hub)
 *   - Admin\Agricultural\AgriculturalController::fields/plantings/harvests (Listas)
 *   - Admin\DashboardController::buildPayload (Painel)
 *
 * Decisões de produto registradas (ver METRICS-DESIGN.md):
 *   - "Talhões ativos" = Field.is_active=true E não soft-deleted (default).
 *   - "Hectares totais" = SUM(area_ha) só dos talhões ativos.
 *     Talhão desativado (motivo: virou pasto, foi vendido etc.) NÃO conta.
 *   - "Plantios ativos" = status=em_andamento (ainda crescendo no campo).
 *     Colhido/perdido/cancelado NÃO conta.
 *   - "Safras em andamento" = Season.is_active=true.
 *   - "Aplicações no mês" = COUNT(FieldApplication WHERE data_aplicacao
 *     BETWEEN [inicio_mes, fim_mes]).
 *   - "Custo por hectare" = SUM(custo_real OR custo_previsto) / SUM(area_plantada_ha)
 *     dos plantings ativos. Filtrável por crop_id.
 */
class AgricolaMetrics extends BaseMetrics
{
    public const MODULE = 'agricola';

    /** Total de talhões ATIVOS (is_active=true, não soft-deleted). */
    public function totalTalhoes(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "total_talhoes." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => $this->scope(Field::query(), $tenantId, $farmId)
                ->where('is_active', true)
                ->count()
        );
    }

    /**
     * Total de hectares — SOMA(area_ha) dos talhões ATIVOS.
     *
     * Bug corrigido: AgriculturalController::index somava TODOS os talhões
     * (sum no Collection sem filtro is_active), inflando área de fazendas
     * que desativaram talhões. Aqui só ativos.
     */
    public function totalHectares(?int $tenantId = null, ?int $farmId = null): float
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "total_hectares." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => (float) $this->scope(Field::query(), $tenantId, $farmId)
                ->where('is_active', true)
                ->sum('area_ha')
        );
    }

    /** Plantios em andamento (status=em_andamento). */
    public function plantiosAtivos(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "plantios_ativos." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => $this->scope(Planting::query(), $tenantId, $farmId)
                ->where('status', 'em_andamento')
                ->count()
        );
    }

    /** Safras em andamento (is_active=true). */
    public function safrasEmAndamento(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "safras_ativas." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => $this->scope(Season::query(), $tenantId, $farmId)
                ->where('is_active', true)
                ->count()
        );
    }

    /** Aplicações no período (FieldApplication.data_aplicacao). */
    public function aplicacoesNoMes(?int $tenantId = null, ?int $farmId = null): int
    {
        return $this->aplicacoesNoPeriodo('mes_atual', $tenantId, $farmId);
    }

    /** Aplicações no período arbitrário. */
    public function aplicacoesNoPeriodo(string|array $period = 'mes_atual', ?int $tenantId = null, ?int $farmId = null): int
    {
        [$from, $to] = $this->period($period);
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "aplicacoes_periodo." . MetricsCache::suffix($tenantId, $farmId, $from->toDateString() . '_' . $to->toDateString()), MetricsCache::TTL_FAST,
            fn () => $this->scope(FieldApplication::query(), $tenantId, $farmId)
                ->whereBetween('data_aplicacao', [$from->toDateString(), $to->toDateString()])
                ->count()
        );
    }

    /**
     * Custo por hectare — média ponderada dos plantings ATIVOS.
     *
     * Fórmula:
     *   SUM(COALESCE(custo_real, custo_previsto, 0)) / SUM(area_plantada_ha)
     *
     * Filtrável por crop_id. Retorna 0.0 quando não há áreas plantadas
     * (evita divisão por zero).
     *
     * Decisão de produto: usa custo_real quando disponível; senão cai pra
     * custo_previsto. Plantio sem nenhum dos dois entra como 0 (não distorce).
     */
    public function custoPorHectare(?int $tenantId = null, ?int $cropId = null, ?int $farmId = null): float
    {
        $tenantId = $this->resolveTenantId($tenantId);
        $extra = $cropId ? "crop{$cropId}" : null;

        return $this->remember(self::MODULE, "custo_por_hectare." . MetricsCache::suffix($tenantId, $farmId, $extra), MetricsCache::TTL_STANDARD,
            function () use ($tenantId, $farmId, $cropId) {
                $base = $this->scope(Planting::query(), $tenantId, $farmId)
                    ->where('status', 'em_andamento');

                if ($cropId) {
                    $base->where('crop_id', $cropId);
                }

                $row = (clone $base)
                    ->selectRaw('SUM(COALESCE(custo_real, custo_previsto, 0)) as custo_total, SUM(area_plantada_ha) as area_total')
                    ->first();

                $area = (float) ($row->area_total ?? 0);
                if ($area <= 0) {
                    return 0.0;
                }
                return round((float) ($row->custo_total ?? 0) / $area, 2);
            }
        );
    }

    /**
     * Resumo agregado — uma única query para o Hub. Reduz round-trips.
     */
    public function resumoHub(?int $tenantId = null, ?int $farmId = null): array
    {
        return [
            'total_talhoes'      => $this->totalTalhoes($tenantId, $farmId),
            'total_hectares'     => $this->totalHectares($tenantId, $farmId),
            'plantios_ativos'    => $this->plantiosAtivos($tenantId, $farmId),
            'safras_andamento'   => $this->safrasEmAndamento($tenantId, $farmId),
            'aplicacoes_mes'     => $this->aplicacoesNoMes($tenantId, $farmId),
        ];
    }

    /**
     * Colheitas no período — soma valor_total e quantidade por unidade.
     * Usado em Painel "Recebido de colheita no mês".
     */
    public function colheitasNoPeriodo(string|array $period = 'mes_atual', ?int $tenantId = null, ?int $farmId = null): array
    {
        [$from, $to] = $this->period($period);
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "colheitas_periodo." . MetricsCache::suffix($tenantId, $farmId, $from->toDateString() . '_' . $to->toDateString()), MetricsCache::TTL_STANDARD,
            function () use ($tenantId, $farmId, $from, $to) {
                $base = $this->scope(Harvest::query(), $tenantId, $farmId)
                    ->whereBetween('data_colheita', [$from->toDateString(), $to->toDateString()]);

                return [
                    'count' => (clone $base)->count(),
                    'valor_total' => (float) (clone $base)->sum('valor_total'),
                ];
            }
        );
    }
}
