<?php

namespace App\Http\Controllers\Master;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\BillingCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SubscriptionController — M6
 *
 * Gerencia a assinatura de UM tenant. Relação 1:1 (UNIQUE em subscriptions.tenant_id).
 *
 * Ações:
 *   show()   — exibe plano atual, status, datas; lista planos ativos para troca
 *   update() — atribui/troca o plano do tenant (cria Subscription se não existir)
 *   cancel() — marca canceled_at + status='canceled'
 *
 * NÃO BLOQUEIA ACESSO DO TENANT em M6. Cancelar subscription só marca
 * o estado — enforcement (ex.: barrar login do tenant com plano cancelado)
 * entra em fase futura de billing guard. Aqui é apenas registro administrativo.
 */
class SubscriptionController extends Controller
{
    public function show(Tenant $tenant): Response
    {
        $subscription = $tenant->subscription()->with('plan')->first();
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('nome')
            ->get(['id', 'nome', 'preco_mensal']);

        return Inertia::render('Master/Subscription/Show', [
            'tenant' => [
                'id' => $tenant->id,
                'nome' => $tenant->nome,
                'is_active' => (bool) $tenant->is_active,
            ],
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'plan_nome' => $subscription->plan?->nome,
                'plan_preco' => $subscription->plan ? (float) $subscription->plan->preco_mensal : null,
                'status' => $subscription->status,
                'started_at' => $subscription->started_at?->format('d/m/Y'),
                'trial_ends_at' => $subscription->trial_ends_at?->format('d/m/Y'),
                'current_period_start' => $subscription->current_period_start?->format('d/m/Y'),
                'current_period_end' => $subscription->current_period_end?->format('d/m/Y'),
                'canceled_at' => $subscription->canceled_at?->format('d/m/Y H:i'),
            ] : null,
            'plans' => $plans->map(fn ($p) => [
                'id' => $p->id,
                'nome' => $p->nome,
                'preco_mensal' => (float) $p->preco_mensal,
            ])->values(),
        ]);
    }

    /**
     * Atribui/troca plano. Cria Subscription se não existir (tenant sem
     * assinatura prévia). Idempotente — chamada com mesmo plan_id não quebra.
     */
    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'status' => ['required', 'string', 'in:active,trial,overdue,canceled'],
            'current_period_start' => ['nullable', 'date'],
            'current_period_end' => ['nullable', 'date', 'after_or_equal:current_period_start'],
        ], [
            'plan_id.required' => 'Selecione um plano.',
            'plan_id.exists' => 'Plano informado não existe.',
            'status.in' => 'Status inválido.',
        ]);

        $subscription = $tenant->subscription()->first();

        $payload = [
            'plan_id' => $validated['plan_id'],
            'status' => $validated['status'],
            'current_period_start' => $validated['current_period_start'] ?? null,
            'current_period_end' => $validated['current_period_end'] ?? null,
        ];

        if ($subscription === null) {
            // Primeira assinatura do tenant
            Subscription::create(array_merge($payload, [
                'tenant_id' => $tenant->id,
                'started_at' => now(),
            ]));
            $msg = 'Assinatura criada para '.$tenant->nome.'.';
        } else {
            // Troca/ajuste de plano ou status
            // Se estava cancelada e volta para active/trial: limpa canceled_at
            if ($subscription->status === 'canceled' && $validated['status'] !== 'canceled') {
                $payload['canceled_at'] = null;
            }
            $subscription->update($payload);
            $msg = 'Assinatura de '.$tenant->nome.' atualizada.';
        }

        // Mantém tenants.plan_id sincronizado com subscriptions.plan_id.
        // O campo legado tenants.plan_id é usado em alguns lugares antigos
        // (ex.: relação direta tenant->plan). Mantemos sincronizado pra evitar
        // inconsistência onde admin via "Plano X" mas master via "Plano Y".
        if ($tenant->plan_id !== $validated['plan_id']) {
            $tenant->update(['plan_id' => $validated['plan_id']]);
        }

        // Troca de plano altera as features disponíveis para o tenant.
        // Invalida cache `tenantFeatures` para o menu refletir imediatamente.
        BillingCache::forgetForTenant($tenant->id);

        return redirect()
            ->route('master.tenants.subscription.show', $tenant)
            ->with('success', $msg);
    }

    /**
     * Cancela assinatura. Idempotente — se já cancelada, só refresca o timestamp.
     */
    public function cancel(Tenant $tenant): RedirectResponse
    {
        $subscription = $tenant->subscription()->first();

        if ($subscription === null) {
            return back()->with('error', 'Este tenant não tem assinatura para cancelar.');
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        // Cancelamento pode bloquear features no futuro → invalida cache
        // do tenant para o menu já refletir o novo estado.
        BillingCache::forgetForTenant($tenant->id);

        return back()->with('success', 'Assinatura de '.$tenant->nome.' cancelada.');
    }
}
