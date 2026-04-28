<?php

namespace App\Services\Metrics;

use App\Models\Financial\FinancialAccount;
use App\Models\Financial\FinancialTransaction;

/**
 * FinancialMetrics — fonte única de verdade para KPIs do módulo financeiro.
 *
 * Substitui as fórmulas duplicadas hoje em:
 *   - Admin\DashboardController::buildPayload (Painel)
 *   - Admin\Financial\FinancialIndexController (Hub)
 *   - Admin\Financial\FinancialTransactionController (Lista)
 *   - Admin\ReportController (Relatório)
 *   - Services\AlertsService (Badges/Alerts)
 *
 * Decisões de produto registradas (ver METRICS-DESIGN.md):
 *   - Atrasadas = SÓ DESPESAS pendentes com vencimento < hoje.
 *     Receita "atrasada" é problema do cliente que nos paga, não nossa
 *     pendência operacional — não conta como atrasada do nosso lado.
 *   - "Vencidas" e "Vencendo hoje" são MUTUAMENTE EXCLUSIVAS:
 *       atrasadas: data_vencimento < hoje
 *       vencendo:  data_vencimento = hoje
 *     Soma fecha = total de despesas que precisam atenção AGORA.
 *   - "A pagar (próximos N dias)" inclui hoje ATÉ hoje+N (filtro >= hoje).
 *     Antes Hub aceitava vencimentos passados — corrigido aqui.
 */
class FinancialMetrics extends BaseMetrics
{
    public const MODULE = 'financial';

    /**
     * Receitas pagas no período (R$).
     *
     * Fórmula canônica:
     *   FinancialTransaction
     *     .where(tipo='receita')
     *     .where(status='pago')
     *     .whereBetween(data_pagamento, [from, to])
     *     .sum(valor)
     *
     * Cache: TTL_FAST (5min). Invalidado em FinancialTransaction::saved.
     */
    public function receitasNoPeriodo(string|array $period = 'mes_atual', ?int $tenantId = null, ?int $farmId = null): float
    {
        [$from, $to] = $this->period($period);
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "receitas_periodo." . MetricsCache::suffix($tenantId, $farmId, $from->toDateString() . '_' . $to->toDateString()), MetricsCache::TTL_FAST,
            fn () => (float) $this->scope(FinancialTransaction::query(), $tenantId, $farmId)
                ->where('tipo', 'receita')
                ->where('status', 'pago')
                ->whereBetween('data_pagamento', [$from, $to])
                ->sum('valor')
        );
    }

    /** Despesas pagas no período (R$). Mesma fórmula com tipo=despesa. */
    public function despesasNoPeriodo(string|array $period = 'mes_atual', ?int $tenantId = null, ?int $farmId = null): float
    {
        [$from, $to] = $this->period($period);
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "despesas_periodo." . MetricsCache::suffix($tenantId, $farmId, $from->toDateString() . '_' . $to->toDateString()), MetricsCache::TTL_FAST,
            fn () => (float) $this->scope(FinancialTransaction::query(), $tenantId, $farmId)
                ->where('tipo', 'despesa')
                ->where('status', 'pago')
                ->whereBetween('data_pagamento', [$from, $to])
                ->sum('valor')
        );
    }

    /** Saldo do período = receitas - despesas. Não cacheia (deriva). */
    public function saldoNoPeriodo(string|array $period = 'mes_atual', ?int $tenantId = null, ?int $farmId = null): float
    {
        return $this->receitasNoPeriodo($period, $tenantId, $farmId)
             - $this->despesasNoPeriodo($period, $tenantId, $farmId);
    }

    /**
     * Saldo total das contas ativas.
     *
     * Fórmula: FinancialAccount.where(is_active=true).sum(saldo_atual)
     */
    public function saldoTotalContas(?int $tenantId = null, ?int $farmId = null): float
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "saldo_contas." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => (float) $this->scope(FinancialAccount::query(), $tenantId, $farmId)
                ->where('is_active', true)
                ->sum('saldo_atual')
        );
    }

    /**
     * Total a pagar — despesas pendentes vencendo no intervalo [hoje, hoje+N].
     *
     * Bug corrigido (Hub vs Painel): antes Hub aceitava vencimentos passados.
     * Aqui forçamos `>= hoje` — vencidas vão pra `atrasadas()`.
     *
     * Retorna ['count' => int, 'valor' => float, 'lista' => Collection].
     */
    public function aPagar(int $proximosNDias = 30, ?int $tenantId = null, ?int $farmId = null): array
    {
        $tenantId = $this->resolveTenantId($tenantId);
        $hoje = today();
        $limite = $hoje->copy()->addDays($proximosNDias);

        return $this->remember(self::MODULE, "a_pagar_{$proximosNDias}d." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            function () use ($tenantId, $farmId, $hoje, $limite) {
                $base = $this->scope(FinancialTransaction::query(), $tenantId, $farmId)
                    ->where('tipo', 'despesa')
                    ->where('status', 'pendente')
                    ->whereBetween('data_vencimento', [$hoje, $limite]);

                $lista = (clone $base)
                    ->orderBy('data_vencimento')
                    ->limit(10)
                    ->get(['id', 'descricao', 'valor', 'data_vencimento', 'status']);

                return [
                    'count' => (clone $base)->count(),
                    'valor' => (float) (clone $base)->sum('valor'),
                    'lista' => $lista,
                ];
            }
        );
    }

    /** Total a receber — receitas pendentes vencendo no intervalo [hoje, hoje+N]. */
    public function aReceber(int $proximosNDias = 30, ?int $tenantId = null, ?int $farmId = null): array
    {
        $tenantId = $this->resolveTenantId($tenantId);
        $hoje = today();
        $limite = $hoje->copy()->addDays($proximosNDias);

        return $this->remember(self::MODULE, "a_receber_{$proximosNDias}d." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            function () use ($tenantId, $farmId, $hoje, $limite) {
                $base = $this->scope(FinancialTransaction::query(), $tenantId, $farmId)
                    ->where('tipo', 'receita')
                    ->where('status', 'pendente')
                    ->whereBetween('data_vencimento', [$hoje, $limite]);

                $lista = (clone $base)
                    ->orderBy('data_vencimento')
                    ->limit(10)
                    ->get(['id', 'descricao', 'valor', 'data_vencimento', 'status']);

                return [
                    'count' => (clone $base)->count(),
                    'valor' => (float) (clone $base)->sum('valor'),
                    'lista' => $lista,
                ];
            }
        );
    }

    /**
     * Atrasadas — APENAS despesas pendentes com vencimento < hoje.
     *
     * Decisão de produto: receita "atrasada" não conta — é o cliente que
     * está atrasado pagando, não o tenant. KPI é sobre dívida operacional.
     *
     * Bug corrigido: Painel/Hub anteriores contavam receita+despesa.
     * AlertsService já contava só despesa — alinhamos com AlertsService.
     */
    public function atrasadas(?int $tenantId = null, ?int $farmId = null): array
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "atrasadas." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            function () use ($tenantId, $farmId) {
                $base = $this->scope(FinancialTransaction::query(), $tenantId, $farmId)
                    ->where('tipo', 'despesa')
                    ->where('status', 'pendente')
                    ->whereDate('data_vencimento', '<', today());

                return [
                    'count' => (clone $base)->count(),
                    'valor' => (float) (clone $base)->sum('valor'),
                ];
            }
        );
    }

    /**
     * Vencendo hoje — despesas pendentes com vencimento = hoje.
     * Disjunto de `atrasadas()`.
     */
    public function vencendoHoje(?int $tenantId = null, ?int $farmId = null): array
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "vencendo_hoje." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            function () use ($tenantId, $farmId) {
                $base = $this->scope(FinancialTransaction::query(), $tenantId, $farmId)
                    ->where('tipo', 'despesa')
                    ->where('status', 'pendente')
                    ->whereDate('data_vencimento', today());

                return [
                    'count' => (clone $base)->count(),
                    'valor' => (float) (clone $base)->sum('valor'),
                ];
            }
        );
    }

    /**
     * Soma de atrasadas + vencendo hoje — "atenção imediata".
     * Usado no badge do menu (Financeiro) — antes essa fórmula divergia
     * entre AlertsService::menuBadgesForTenant (`<=today`) e AlertsService::forTenant
     * (separado em `<today` + `=today`). Aqui unificamos.
     */
    public function badgeAtencaoFinanceira(?int $tenantId = null, ?int $farmId = null): int
    {
        return $this->atrasadas($tenantId, $farmId)['count']
             + $this->vencendoHoje($tenantId, $farmId)['count'];
    }

    /**
     * Despesas por categoria no período. Retorna Collection<['categoria','total']>.
     * Inclui 'Sem categoria' para lançamentos sem category_id.
     */
    public function despesasPorCategoria(string|array $period = 'mes_atual', ?int $tenantId = null, ?int $farmId = null): \Illuminate\Support\Collection
    {
        [$from, $to] = $this->period($period);
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "despesas_categoria." . MetricsCache::suffix($tenantId, $farmId, $from->toDateString() . '_' . $to->toDateString()), MetricsCache::TTL_STANDARD,
            fn () => $this->scope(FinancialTransaction::query(), $tenantId, $farmId)
                ->leftJoin('categories', 'financial_transactions.category_id', '=', 'categories.id')
                ->where('financial_transactions.tipo', 'despesa')
                ->where('financial_transactions.status', 'pago')
                ->whereBetween('financial_transactions.data_pagamento', [$from, $to])
                ->select(\DB::raw("COALESCE(categories.nome, 'Sem categoria') as categoria"), \DB::raw('SUM(financial_transactions.valor) as total'))
                ->groupBy('categoria')
                ->orderByDesc('total')
                ->get()
        );
    }

    /** Receitas por categoria. Fecha gap (Relatório anterior só agrupava despesas). */
    public function receitasPorCategoria(string|array $period = 'mes_atual', ?int $tenantId = null, ?int $farmId = null): \Illuminate\Support\Collection
    {
        [$from, $to] = $this->period($period);
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "receitas_categoria." . MetricsCache::suffix($tenantId, $farmId, $from->toDateString() . '_' . $to->toDateString()), MetricsCache::TTL_STANDARD,
            fn () => $this->scope(FinancialTransaction::query(), $tenantId, $farmId)
                ->leftJoin('categories', 'financial_transactions.category_id', '=', 'categories.id')
                ->where('financial_transactions.tipo', 'receita')
                ->where('financial_transactions.status', 'pago')
                ->whereBetween('financial_transactions.data_pagamento', [$from, $to])
                ->select(\DB::raw("COALESCE(categories.nome, 'Sem categoria') as categoria"), \DB::raw('SUM(financial_transactions.valor) as total'))
                ->groupBy('categoria')
                ->orderByDesc('total')
                ->get()
        );
    }
}
