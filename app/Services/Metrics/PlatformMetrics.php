<?php

namespace App\Services\Metrics;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * PlatformMetrics — fonte única de verdade para KPIs do MASTER (plataforma SaaS).
 *
 * Substitui as fórmulas hoje em:
 *   - Master\MasterDashboardController::buildPayload (Dashboard master)
 *   - Master\InvoiceController::index (Lista de cobranças — totais)
 *   - Services\AlertsService (Badges/Alerts master)
 *
 * Decisões de produto registradas (corrige bug ALTA-2):
 *
 *   - "Faturas overdue" CANÔNICO = TODAS as faturas com status=overdue,
 *     INDEPENDENTE de tipo (mensal ou avulsa).
 *
 *     JUSTIFICATIVA: a tela "Lista de cobranças" mostra TODAS as overdue
 *     pro master agir; o dashboard tem que refletir o mesmo número visível
 *     na lista. AlertsService usava só `tipo=mensal` pra determinar SEVERIDADE
 *     do badge (avulsa não bloqueia acesso) — mas isso é regra de SEVERIDADE,
 *     não de CONTAGEM. Aqui contamos tudo; o método separado
 *     `cobrancasOverduePlano()` permite que AlertsService continue usando
 *     o critério mais estrito pra severidade.
 *
 *   - "MRR" = Monthly Recurring Revenue. Soma `plan.preco_mensal` de
 *     subscriptions com status IN (active, grace).
 *     Subscriptions canceladas/inadimplentes não contam (não rendem).
 *
 *   - "Inadimplência" = % de faturas overdue / total de faturas.
 *     Considera tudo que existe (não filtra por mês). Se total=0, retorna 0.
 *
 *   - "Tenants ativos/inativos" = is_active boolean. Não cruza com subscription
 *     status (master pode desativar tenant manualmente).
 */
class PlatformMetrics extends BaseMetrics
{
    public const MODULE = 'platform';

    /** Total de tenants ATIVOS (is_active=true). */
    public function totalTenantsAtivos(): int
    {
        return $this->remember(self::MODULE, 'tenants_ativos', MetricsCache::TTL_FAST,
            fn () => Tenant::where('is_active', true)->count()
        );
    }

    /** Total de tenants INATIVOS. */
    public function totalTenantsInativos(): int
    {
        return $this->remember(self::MODULE, 'tenants_inativos', MetricsCache::TTL_FAST,
            fn () => Tenant::where('is_active', false)->count()
        );
    }

    /** Total geral. */
    public function totalTenants(): int
    {
        return $this->remember(self::MODULE, 'tenants_total', MetricsCache::TTL_FAST,
            fn () => Tenant::count()
        );
    }

    /**
     * MRR — Monthly Recurring Revenue.
     *
     * Fórmula: SUM(plans.preco_mensal) JOIN subscriptions
     *   WHERE subscriptions.status IN ('active', 'grace')
     *
     * Decisão: subscriptions com status=overdue NÃO entram (não estão
     * pagando — não rendem MRR no momento). Quando regularizam, voltam
     * para active (reconciled em InvoiceController::reconcileSubscription).
     */
    public function mrrMensal(): float
    {
        return $this->remember(self::MODULE, 'mrr', MetricsCache::TTL_SLOW,
            fn () => (float) Subscription::query()
                ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                ->whereIn('subscriptions.status', ['active', 'grace'])
                ->sum('plans.preco_mensal')
        );
    }

    /** ARR = MRR × 12. */
    public function arrAnual(): float
    {
        return round($this->mrrMensal() * 12, 2);
    }

    /**
     * Cobranças PENDENTES — pending + overdue (toda cobrança em aberto).
     *
     * Inclui ambos os tipos (mensal + avulsa) — alinhado com
     * `MasterDashboard.cobrancas.pendentes`.
     */
    public function cobrancasPendentes(): int
    {
        return $this->remember(self::MODULE, 'cobrancas_pendentes', MetricsCache::TTL_FAST,
            fn () => Invoice::whereIn('status', ['pending', 'overdue'])->count()
        );
    }

    /**
     * Faturas OVERDUE (CANÔNICO) — todas as faturas vencidas.
     *
     * Inclui mensal + avulsa. Alinha:
     *   - MasterDashboard "Atrasadas" (já usava todas)
     *   - InvoiceController::index totals.overdue (usa todas)
     *
     * Decisão: para SEVERIDADE de alerta/badge, use cobrancasOverduePlano()
     * (que filtra só tipo=mensal — bloqueia acesso). Aqui é só CONTAGEM
     * pra dashboard.
     */
    public function faturasOverdue(): int
    {
        return $this->remember(self::MODULE, 'cobrancas_overdue', MetricsCache::TTL_FAST,
            fn () => Invoice::where('status', 'overdue')->count()
        );
    }

    /**
     * Faturas overdue do PLANO (mensal) — usado por AlertsService pra decidir
     * severidade do badge (avulsa não bloqueia acesso).
     */
    public function cobrancasOverduePlano(): int
    {
        return $this->remember(self::MODULE, 'cobrancas_overdue_plano', MetricsCache::TTL_FAST,
            fn () => Invoice::where('status', 'overdue')
                ->where('tipo', 'mensal')
                ->count()
        );
    }

    /**
     * Inadimplência — % de faturas overdue / total de faturas.
     *
     * Retorna 0.0 quando não há faturas (evita div/0).
     * Considera todo histórico (não filtra por mês).
     */
    public function inadimplencia(): float
    {
        return $this->remember(self::MODULE, 'inadimplencia', MetricsCache::TTL_STANDARD,
            function () {
                $row = Invoice::selectRaw("
                    SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
                    COUNT(*) as total
                ")->first();

                $total = (int) ($row->total ?? 0);
                if ($total === 0) return 0.0;
                $overdue = (int) ($row->overdue ?? 0);
                return round(($overdue / $total) * 100, 2);
            }
        );
    }

    /** Recebido no mês — soma valor de invoices PAGAS no mês. */
    public function recebidoNoMes(): float
    {
        return $this->remember(self::MODULE, 'recebido_mes', MetricsCache::TTL_FAST,
            fn () => (float) Invoice::where('status', 'paid')
                ->whereBetween('data_pagamento', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('valor')
        );
    }

    /** Total a receber — soma valor de pending + overdue. */
    public function totalPendente(): float
    {
        return $this->remember(self::MODULE, 'total_pendente', MetricsCache::TTL_FAST,
            fn () => (float) Invoice::whereIn('status', ['pending', 'overdue'])->sum('valor')
        );
    }

    /** Faturas aguardando validação manual (paid_pending_review). */
    public function faturasAguardandoValidacao(): int
    {
        return $this->remember(self::MODULE, 'cobrancas_aguardando_validacao', MetricsCache::TTL_FAST,
            fn () => Invoice::where('status', 'paid_pending_review')->count()
        );
    }

    /**
     * Resumo de usuários: total/masters/clientes.
     */
    public function totalUsuarios(): array
    {
        return $this->remember(self::MODULE, 'usuarios', MetricsCache::TTL_STANDARD,
            function () {
                $row = User::selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END) as masters,
                    SUM(CASE WHEN tenant_id IS NOT NULL THEN 1 ELSE 0 END) as clientes
                ")->first();

                return [
                    'total'    => (int) ($row->total ?? 0),
                    'masters'  => (int) ($row->masters ?? 0),
                    'clientes' => (int) ($row->clientes ?? 0),
                ];
            }
        );
    }

    /** Resumo agregado pra dashboard master. */
    public function resumoDashboard(): array
    {
        return [
            'tenants' => [
                'total'    => $this->totalTenants(),
                'ativos'   => $this->totalTenantsAtivos(),
                'inativos' => $this->totalTenantsInativos(),
            ],
            'cobrancas' => [
                'pendentes'      => $this->cobrancasPendentes(),
                'atrasadas'      => $this->faturasOverdue(),
                'total_pago_mes' => $this->recebidoNoMes(),
                'total_pendente' => $this->totalPendente(),
            ],
            'usuarios' => $this->totalUsuarios(),
            'mrr'      => $this->mrrMensal(),
            'arr'      => $this->arrAnual(),
            'inadimplencia' => $this->inadimplencia(),
        ];
    }

    /** Invalida todo cache do master (chamado em CRUDs de Invoice/Tenant/Plan/Subscription). */
    public function forgetMasterCache(): void
    {
        $keys = [
            'tenants_ativos', 'tenants_inativos', 'tenants_total',
            'mrr',
            'cobrancas_pendentes', 'cobrancas_overdue', 'cobrancas_overdue_plano',
            'inadimplencia', 'recebido_mes', 'total_pendente',
            'cobrancas_aguardando_validacao', 'usuarios', 'master_kpis',
        ];
        foreach ($keys as $k) {
            Cache::forget(MetricsCache::key(self::MODULE, $k));
        }
    }
}
