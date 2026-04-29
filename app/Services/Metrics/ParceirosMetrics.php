<?php

namespace App\Services\Metrics;

use App\Models\Financial\FinancialTransaction;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;

/**
 * ParceirosMetrics — fonte única de verdade para KPIs de parceiros
 * (clientes/fornecedores).
 *
 * Substitui as fórmulas hoje em:
 *   - Admin\PartnerController (Lista)
 *   - Hub Cadastros (futuro)
 *
 * Decisões de produto registradas:
 *   - "Total ativos" = COUNT(Partner WHERE is_active=true AND not soft-deleted).
 *   - "Clientes" = tipo IN (cliente, ambos).
 *   - "Fornecedores" = tipo IN (fornecedor, ambos).
 *     "Ambos" entra em ambas listas (não é XOR).
 *   - "Vendas no mês por parceiro" = sum(FinancialTransaction.valor) onde
 *     tipo=receita + status=pago + partner_id=X + data_pagamento no mês.
 *     Retorna top N parceiros para evitar payload pesado.
 */
class ParceirosMetrics extends BaseMetrics
{
    public const MODULE = 'parceiros';

    /** Parceiros ATIVOS (is_active=true). */
    public function totalAtivos(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "total_ativos." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_STANDARD,
            fn () => Partner::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->count()
        );
    }

    /** Clientes — tipo IN (cliente, ambos). */
    public function clientes(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "clientes." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_STANDARD,
            fn () => Partner::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereIn('tipo', ['cliente', 'ambos'])
                ->count()
        );
    }

    /** Fornecedores — tipo IN (fornecedor, ambos). */
    public function fornecedores(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "fornecedores." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_STANDARD,
            fn () => Partner::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereIn('tipo', ['fornecedor', 'ambos'])
                ->count()
        );
    }

    /**
     * Vendas no mês por parceiro — top N. Retorna Collection<{partner_id,
     * partner_nome, total_valor}>.
     *
     * Fórmula: sum(financial_transactions.valor) WHERE tipo=receita
     * AND status=pago AND data_pagamento no mês AND partner_id NOT NULL.
     */
    public function vendasMesPorParceiro(int $topN = 10, ?int $tenantId = null, ?int $farmId = null): \Illuminate\Support\Collection
    {
        $tenantId = $this->resolveTenantId($tenantId);
        [$from, $to] = $this->period('mes_atual');

        return $this->remember(self::MODULE, "vendas_mes_top.{$topN}." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_STANDARD,
            fn () => $this->scope(FinancialTransaction::query(), $tenantId, $farmId)
                ->leftJoin('partners', 'financial_transactions.partner_id', '=', 'partners.id')
                ->where('financial_transactions.tipo', 'receita')
                ->where('financial_transactions.status', 'pago')
                ->whereNotNull('financial_transactions.partner_id')
                ->whereBetween('financial_transactions.data_pagamento', [$from, $to])
                ->select(
                    'financial_transactions.partner_id',
                    'partners.nome as partner_nome',
                    DB::raw('SUM(financial_transactions.valor) as total_valor')
                )
                ->groupBy('financial_transactions.partner_id', 'partners.nome')
                ->orderByDesc('total_valor')
                ->limit($topN)
                ->get()
        );
    }

    /** Resumo agregado. */
    public function resumo(?int $tenantId = null, ?int $farmId = null): array
    {
        return [
            'total_ativos'  => $this->totalAtivos($tenantId, $farmId),
            'clientes'      => $this->clientes($tenantId, $farmId),
            'fornecedores'  => $this->fornecedores($tenantId, $farmId),
        ];
    }
}
