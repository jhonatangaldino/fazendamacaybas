<?php

namespace App\Http\Controllers\Master;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Metrics\PlatformMetrics;
use App\Support\BillingCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard da plataforma SaaS — KPIs reais agora que todos os módulos
 * (tenants, planos, cobranças, impersonação, CMS por cliente) estão entregues.
 *
 * As queries são agregadas (COUNT/SUM/CASE) — 1 query por card, impacto
 * baixo no rate-limit MySQL Hostinger. Cacheado não é necessário porque
 * o master acessa com frequência muito baixa (1-5x/dia tipicamente).
 */
class MasterDashboardController extends Controller
{
    public function index(Request $request, PlatformMetrics $metrics): Response
    {
        // Refresh manual (?refresh=1) força invalidação do cache do PlatformMetrics
        // E do cache legado BillingCache (preservado para compat com rotas antigas
        // que ainda lêem essa chave).
        if ($request->query('refresh') === '1') {
            Cache::forget(BillingCache::masterKpisKey());
            $metrics->forgetMasterCache();
        }

        $payload = $this->buildPayload($metrics);

        return Inertia::render('Master/Dashboard', $payload);
    }

    /**
     * Cálculo dos KPIs — delega ao PlatformMetrics (fonte canônica).
     *
     * Bug ALTA-2 RESOLVIDO: antes este controller contava overdue=todas, mas
     * AlertsService contava só `tipo=mensal` (gerando inconsistência entre
     * dashboard e alertas/badges). Agora ambos consultam PlatformMetrics —
     * `faturasOverdue()` (todas, para dashboard) e `cobrancasOverduePlano()`
     * (só mensais, para severidade do badge).
     *
     * Status canônicos (en — alinhado com Invoice + Cobrancas/Index.vue):
     *   pending  → ainda dentro do prazo
     *   overdue  → vencida (data_vencimento < hoje)
     *   paid     → quitada
     */
    private function buildPayload(PlatformMetrics $metrics): array
    {
        $resumo = $metrics->resumoDashboard();

        // Últimos tenants cadastrados — top 5 (lista, não KPI)
        $ultimosTenants = Tenant::orderByDesc('id')
            ->limit(5)
            ->get(['id', 'nome', 'slug', 'is_active', 'created_at'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'nome' => $t->nome,
                'slug' => $t->slug,
                'is_active' => (bool) $t->is_active,
                'created_at' => $t->created_at?->format('d/m/Y'),
            ]);

        return [
            'kpis' => [
                'tenants'   => $resumo['tenants'],
                'cobrancas' => $resumo['cobrancas'],
                'usuarios'  => $resumo['usuarios'],
                'mrr'       => $resumo['mrr'],
                'inadimplencia' => $resumo['inadimplencia'],
            ],
            'ultimosTenants' => $ultimosTenants,
        ];
    }
}
