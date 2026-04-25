<?php

namespace App\Http\Controllers\Master;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Models\User;
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
    public function index(Request $request): Response
    {
        // Cache de 5min (refresh=1 força). Antes: ~6 queries (tenants, ultimosTenants,
        // cobrancas selectRaw, usuarios) em CADA hit. Após: 0 queries no hit cache.
        if ($request->query('refresh') === '1') {
            Cache::forget(BillingCache::masterKpisKey());
        }

        $payload = Cache::remember(
            BillingCache::masterKpisKey(),
            BillingCache::TTL_MASTER_KPIS,
            fn () => $this->buildPayload(),
        );

        return Inertia::render('Master/Dashboard', $payload);
    }

    /**
     * Cálculo dos KPIs — só roda em cache miss (1x a cada 5min, no pior caso).
     * Mantém todas queries agrupadas em selectRaw para minimizar round-trips.
     */
    private function buildPayload(): array
    {
        // Tenants: totais + estado
        $tenants = Tenant::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as ativos,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inativos
        ")->first();

        // Últimos tenants cadastrados — top 5
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

        // Cobranças: pendentes + atrasadas + pagas no mês.
        //
        // CORREÇÃO de bug crítico: as queries usavam status em PORTUGUÊS
        // ('pendente'/'paga'), mas o banco armazena em INGLÊS ('pending',
        // 'paid', 'overdue'). Por isso o card "Recebido no mês" mostrava
        // sempre R$ 0,00 mesmo com cobranças pagas reais.
        //
        // Regras canônicas (alinhadas com InvoiceController + Cobrancas/Index.vue):
        //   pending  → ainda dentro do prazo
        //   overdue  → vencida (data_vencimento < hoje)
        //   paid     → quitada (data_pagamento NOT NULL)
        //   cancelled→ cancelada
        //
        // "Pendentes" no card agrupa pending + overdue (toda cobrança em aberto).
        // "Atrasadas" = subset overdue.
        // "Recebido no mês" usa data_pagamento, NÃO created_at/data_vencimento.
        $inicioMes = now()->startOfMonth()->toDateString();
        $fimMes    = now()->endOfMonth()->toDateString();
        $cobrancas = Invoice::selectRaw("
            SUM(CASE WHEN status IN ('pending', 'overdue') THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as atrasadas,
            SUM(CASE WHEN status = 'paid' AND data_pagamento BETWEEN ? AND ? THEN valor ELSE 0 END) as total_pago_mes,
            SUM(CASE WHEN status IN ('pending', 'overdue') THEN valor ELSE 0 END) as total_pendente
        ", [$inicioMes, $fimMes])->first();

        // Usuários totais na plataforma (masters + tenants)
        $usuarios = User::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END) as masters,
            SUM(CASE WHEN tenant_id IS NOT NULL THEN 1 ELSE 0 END) as clientes
        ")->first();

        return [
            'kpis' => [
                'tenants' => [
                    'total' => (int) $tenants->total,
                    'ativos' => (int) $tenants->ativos,
                    'inativos' => (int) $tenants->inativos,
                ],
                'cobrancas' => [
                    'pendentes' => (int) ($cobrancas->pendentes ?? 0),
                    'atrasadas' => (int) ($cobrancas->atrasadas ?? 0),
                    'total_pago_mes' => (float) ($cobrancas->total_pago_mes ?? 0),
                    'total_pendente' => (float) ($cobrancas->total_pendente ?? 0),
                ],
                'usuarios' => [
                    'total' => (int) $usuarios->total,
                    'masters' => (int) $usuarios->masters,
                    'clientes' => (int) $usuarios->clientes,
                ],
            ],
            'ultimosTenants' => $ultimosTenants,
        ];
    }
}
