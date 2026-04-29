<?php

namespace App\Services\Metrics;

use App\Models\Vehicle\MaintenanceOrder;
use App\Models\Vehicle\Vehicle;

/**
 * MaquinasMetrics — fonte única de verdade para KPIs do módulo de máquinas.
 *
 * Substitui as fórmulas hoje em:
 *   - Admin\Vehicle\VehicleController (Lista + Hub Máquinas)
 *   - Admin\DashboardController::buildPayload (Painel)
 *
 * Decisões de produto registradas:
 *   - "Veículos ativos" = Vehicle.is_active=true (não soft-deleted).
 *   - "Em manutenção" = COUNT(DISTINCT vehicle_id) entre MaintenanceOrder
 *     com status in (agendada, em_andamento). Distinct: 1 veículo com 3 OS
 *     abertas conta 1 vez como "em manutenção".
 *   - "Manutenções no mês" = COUNT(MaintenanceOrder) com data_realizada
 *     OU data_prevista no período (preferimos realizada quando preenchida).
 *     Decisão: usamos `data_realizada` quando NOT NULL, senão `data_prevista`.
 *     Para o mês atual, isso conta tudo que foi feito + tudo que está agendado
 *     pra este mês.
 *   - "Custo manutenção no mês" = SUM(valor_total) de OS com data_realizada
 *     no período. Apenas REALIZADAS contam (gasto efetivo).
 */
class MaquinasMetrics extends BaseMetrics
{
    public const MODULE = 'maquinas';

    /** Veículos ATIVOS (is_active=true). */
    public function totalVeiculosAtivos(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "veiculos_ativos." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => $this->scope(Vehicle::query(), $tenantId, $farmId)
                ->where('is_active', true)
                ->count()
        );
    }

    /**
     * Veículos EM MANUTENÇÃO — distinct vehicle_id de OS com status
     * IN (agendada, em_andamento).
     *
     * Diferente de "manutenções abertas" (que conta cada OS).
     */
    public function emManutencao(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "veiculos_em_manutencao." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => $this->scope(MaintenanceOrder::query(), $tenantId, $farmId)
                ->whereIn('status', ['agendada', 'em_andamento'])
                ->distinct('vehicle_id')
                ->count('vehicle_id')
        );
    }

    /**
     * Manutenções no período — usa `data_realizada` quando NOT NULL,
     * senão `data_prevista`. Pega tudo que foi feito ou está pra este mês.
     */
    public function manutencoesNoMes(?int $tenantId = null, ?int $farmId = null): int
    {
        return $this->manutencoesNoPeriodo('mes_atual', $tenantId, $farmId);
    }

    public function manutencoesNoPeriodo(string|array $period = 'mes_atual', ?int $tenantId = null, ?int $farmId = null): int
    {
        [$from, $to] = $this->period($period);
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "manutencoes_periodo." . MetricsCache::suffix($tenantId, $farmId, $from->toDateString() . '_' . $to->toDateString()), MetricsCache::TTL_FAST,
            fn () => $this->scope(MaintenanceOrder::query(), $tenantId, $farmId)
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('data_realizada', [$from->toDateString(), $to->toDateString()])
                      ->orWhere(function ($qq) use ($from, $to) {
                          $qq->whereNull('data_realizada')
                             ->whereBetween('data_prevista', [$from->toDateString(), $to->toDateString()]);
                      });
                })
                ->count()
        );
    }

    /**
     * Custo de manutenção no período — SUM(valor_total) de OS REALIZADAS.
     *
     * Decisão: só conta gasto efetivo (data_realizada NOT NULL).
     * OS agendadas para o mês mas ainda não realizadas NÃO entram —
     * elas são previsão, não custo realizado.
     */
    public function custoManutencaoNoMes(?int $tenantId = null, ?int $farmId = null): float
    {
        return $this->custoManutencaoNoPeriodo('mes_atual', $tenantId, $farmId);
    }

    public function custoManutencaoNoPeriodo(string|array $period = 'mes_atual', ?int $tenantId = null, ?int $farmId = null): float
    {
        [$from, $to] = $this->period($period);
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "custo_manutencao_periodo." . MetricsCache::suffix($tenantId, $farmId, $from->toDateString() . '_' . $to->toDateString()), MetricsCache::TTL_STANDARD,
            fn () => (float) $this->scope(MaintenanceOrder::query(), $tenantId, $farmId)
                ->whereNotNull('data_realizada')
                ->whereBetween('data_realizada', [$from->toDateString(), $to->toDateString()])
                ->sum('valor_total')
        );
    }

    /** Manutenções abertas — count de OS em agendada+em_andamento (não distinct). */
    public function manutencoesAbertas(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "manutencoes_abertas." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => $this->scope(MaintenanceOrder::query(), $tenantId, $farmId)
                ->whereIn('status', ['agendada', 'em_andamento'])
                ->count()
        );
    }

    /** Resumo agregado para Hub/Painel. */
    public function resumo(?int $tenantId = null, ?int $farmId = null): array
    {
        return [
            'veiculos_ativos'    => $this->totalVeiculosAtivos($tenantId, $farmId),
            'em_manutencao'      => $this->emManutencao($tenantId, $farmId),
            'manutencoes_abertas'=> $this->manutencoesAbertas($tenantId, $farmId),
            'manutencoes_mes'    => $this->manutencoesNoMes($tenantId, $farmId),
            'custo_mes'          => $this->custoManutencaoNoMes($tenantId, $farmId),
        ];
    }
}
