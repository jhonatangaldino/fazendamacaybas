<?php

namespace App\Http\Controllers\Master;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\PlanFeatures;
use App\Http\Controllers\Controller;
use App\Support\BillingCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PlanController — M6
 *
 * CRUD de planos SaaS. Escopo mínimo conforme brief: nome, preço, período,
 * ativo, features. Campos extras do schema (max_farms, max_users, sort_order)
 * preservados com defaults sensatos — serão expostos na UI quando virarem
 * negócio (limites de uso, ordenação comercial).
 *
 * Segurança herdada do grupo /master: auth + enforce.master.
 */
class PlanController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::query()
            ->orderBy('sort_order')
            ->orderBy('nome')
            ->get(['id', 'slug', 'nome', 'preco_mensal', 'max_farms', 'max_users', 'features', 'is_active']);

        return Inertia::render('Master/Planos/Index', [
            'plans' => $plans->map(fn ($p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'nome' => $p->nome,
                'preco_mensal' => (float) $p->preco_mensal,
                'max_farms' => $p->max_farms,
                'max_users' => $p->max_users,
                'features' => is_array($p->features) ? $p->features : [],
                'is_active' => (bool) $p->is_active,
            ])->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Master/Planos/Form', [
            'plan' => null,
            'features_catalog' => PlanFeatures::catalogForFrontend(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $validated['slug'] = Str::slug($validated['slug']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['features'] = $this->normalizeFeatures($request->input('features', []));
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $plan = Plan::create($validated);

        return redirect()
            ->route('master.planos.index')
            ->with('success', 'Plano "'.$plan->nome.'" criado.');
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('Master/Planos/Form', [
            'plan' => [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'nome' => $plan->nome,
                'preco_mensal' => (float) $plan->preco_mensal,
                'max_farms' => $plan->max_farms,
                'max_users' => $plan->max_users,
                // Sanitiza para garantir que só features do catálogo cheguem ao front,
                // mesmo se o banco ficou com lixo de versões anteriores (text livre).
                'features' => PlanFeatures::sanitize(
                    is_array($plan->features) ? $plan->features : []
                ),
                'is_active' => (bool) $plan->is_active,
            ],
            'features_catalog' => PlanFeatures::catalogForFrontend(),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $this->validatePayload($request, $plan);
        $validated['slug'] = Str::slug($validated['slug']);
        $validated['is_active'] = $request->boolean('is_active', $plan->is_active);
        $validated['features'] = $this->normalizeFeatures($request->input('features', []));

        $plan->update($validated);

        // Edição de plano (especialmente features) afeta TODOS os tenants
        // que o usam. Invalida cache `tenantFeatures` deles para que o
        // menu reflita a mudança no próximo carregamento (sem esperar TTL).
        BillingCache::forgetForPlan($plan->id);

        return redirect()
            ->route('master.planos.index')
            ->with('success', 'Plano "'.$plan->nome.'" atualizado.');
    }

    public function toggle(Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        // Toggle de ativo/inativo pode bloquear acesso → invalida cache
        // dos tenants para refletir imediatamente.
        BillingCache::forgetForPlan($plan->id);

        $msg = $plan->is_active
            ? 'Plano "'.$plan->nome.'" ativado.'
            : 'Plano "'.$plan->nome.'" desativado.';

        return back()->with('success', $msg);
    }

    private function validatePayload(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'max:120', 'alpha_dash',
                Rule::unique('plans', 'slug')->ignore($plan?->id),
            ],
            'preco_mensal' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'max_farms' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'max_users' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'nome.required' => 'Informe o nome do plano.',
            'slug.required' => 'Informe o slug.',
            'slug.alpha_dash' => 'Slug aceita apenas letras, números, hífen e underline.',
            'slug.unique' => 'Já existe um plano com esse slug.',
            'preco_mensal.required' => 'Informe o preço mensal (pode ser 0).',
            'preco_mensal.numeric' => 'Preço deve ser um número.',
            'preco_mensal.min' => 'Preço não pode ser negativo.',
        ]);
    }

    /**
     * Normaliza features filtrando contra o catálogo PlanFeatures.
     * Qualquer chave fora do catálogo é DESCARTADA — defesa contra payload
     * adulterado (DevTools, extensões maliciosas) ou drift entre versões.
     */
    private function normalizeFeatures($input): array
    {
        if (! is_array($input)) {
            return [];
        }
        return PlanFeatures::sanitize(
            collect($input)
                ->map(fn ($v) => is_string($v) ? trim($v) : '')
                ->filter(fn ($v) => $v !== '')
                ->values()
                ->all()
        );
    }
}
