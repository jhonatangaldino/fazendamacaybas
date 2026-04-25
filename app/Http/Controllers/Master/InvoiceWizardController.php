<?php

namespace App\Http\Controllers\Master;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Tenant;
use App\Domain\Billing\Services\InvoiceGenerationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * InvoiceWizardController — fluxo guiado "Gerar Faturas".
 *
 * Wizard de 5 passos no Master:
 *   1. Cliente   — seleciona o tenant
 *   2. Tipo      — mensal | unica
 *   3. Período   — mês/ano início e mês/ano fim
 *   4. Resumo    — preview gerado pelo InvoiceGenerationService::preview()
 *   5. Confirmar — POST para store() → persiste invoices
 *
 * Endpoints:
 *   GET  /master/cobrancas/gerar           → tela do wizard (carrega tenants+planos)
 *   POST /master/cobrancas/gerar/preview   → JSON do preview (passo 4)
 *   POST /master/cobrancas/gerar           → persiste e redireciona para listagem
 */
class InvoiceWizardController extends Controller
{
    public function create(): Response
    {
        // Lista todos os tenants ativos com seu plano atual (para auto-preencher passo 3)
        $tenants = Tenant::where('is_active', true)
            ->with(['plan:id,nome,slug,preco_mensal', 'subscription.plan:id,nome,slug,preco_mensal'])
            ->orderBy('nome')
            ->get(['id', 'nome', 'slug', 'plan_id'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'nome' => $t->nome,
                'slug' => $t->slug,
                // Prioriza plano da subscription (sempre mais atualizado), cai para plan_id direto
                'plano' => $t->subscription?->plan
                    ? [
                        'id' => $t->subscription->plan->id,
                        'nome' => $t->subscription->plan->nome,
                        'preco_mensal' => (float) $t->subscription->plan->preco_mensal,
                    ]
                    : ($t->plan ? [
                        'id' => $t->plan->id,
                        'nome' => $t->plan->nome,
                        'preco_mensal' => (float) $t->plan->preco_mensal,
                    ] : null),
            ])
            ->values();

        $planos = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('nome')
            ->get(['id', 'nome', 'slug', 'preco_mensal'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'nome' => $p->nome,
                'slug' => $p->slug,
                'preco_mensal' => (float) $p->preco_mensal,
            ])
            ->values();

        return Inertia::render('Master/Cobrancas/Wizard', [
            'tenants' => $tenants,
            'planos' => $planos,
        ]);
    }

    /**
     * Preview do passo 4 — calcula sem persistir.
     * Retornado como JSON para que o front consuma via fetch sem reload.
     */
    public function preview(Request $request, InvoiceGenerationService $service): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $plan = Plan::findOrFail($validated['plan_id']);

        $preview = $service->preview(
            plan: $plan,
            tipo: $validated['tipo'],
            mesIni: $validated['mes_inicio'],
            anoIni: $validated['ano_inicio'],
            mesFim: $validated['mes_fim'],
            anoFim: $validated['ano_fim'],
        );

        return response()->json($preview);
    }

    /**
     * Persistência — passo 5 (Confirmar).
     */
    public function store(Request $request, InvoiceGenerationService $service): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $tenant = Tenant::findOrFail($validated['tenant_id']);
        $plan = Plan::findOrFail($validated['plan_id']);

        if ($validated['tipo'] === 'mensal') {
            $criadas = $service->gerarMensal(
                tenant: $tenant,
                plan: $plan,
                mesIni: $validated['mes_inicio'],
                anoIni: $validated['ano_inicio'],
                mesFim: $validated['mes_fim'],
                anoFim: $validated['ano_fim'],
            );

            $msg = $criadas->count() === 0
                ? 'Nenhuma fatura nova foi gerada (todos os meses já tinham fatura).'
                : "Geradas {$criadas->count()} fatura(s) mensais para {$tenant->nome}.";
        } else {
            $invoice = $service->gerarUnica(
                tenant: $tenant,
                plan: $plan,
                mesIni: $validated['mes_inicio'],
                anoIni: $validated['ano_inicio'],
                mesFim: $validated['mes_fim'],
                anoFim: $validated['ano_fim'],
            );
            $msg = "Fatura única #{$invoice->numero} gerada para {$tenant->nome} (R$ ".number_format((float) $invoice->valor, 2, ',', '.').').';
        }

        return redirect()
            ->route('master.cobrancas.index')
            ->with('success', $msg);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'tipo' => ['required', 'in:mensal,unica'],
            'mes_inicio' => ['required', 'integer', 'min:1', 'max:12'],
            'ano_inicio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mes_fim' => ['required', 'integer', 'min:1', 'max:12'],
            'ano_fim' => ['required', 'integer', 'min:2020', 'max:2100'],
        ], [
            'tenant_id.required' => 'Selecione o cliente.',
            'plan_id.required' => 'Selecione o plano.',
            'tipo.in' => 'Tipo inválido (mensal ou unica).',
            'mes_inicio.required' => 'Informe o mês inicial.',
            'ano_inicio.required' => 'Informe o ano inicial.',
            'mes_fim.required' => 'Informe o mês final.',
            'ano_fim.required' => 'Informe o ano final.',
        ]);
    }
}
