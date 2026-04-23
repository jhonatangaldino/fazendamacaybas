<?php

namespace App\Http\Controllers\Master;

use App\Domain\Billing\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Models\Farm;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FarmController (Master area) — M4
 *
 * Gestão de fazendas PELO MASTER, sempre aninhadas a um tenant.
 * URL pattern: /master/tenants/{tenant}/fazendas/{farm?}
 *
 * Segurança em camadas:
 *   1. `auth` + `enforce.master` no grupo → só master autenticado chega aqui.
 *   2. Route `scopeBindings()` → Laravel resolve `{farm}` via
 *      `$tenant->farms()->findOrFail($id)`, retornando 404 se a farm
 *      pertencer a outro tenant. Zero vazamento cross-tenant.
 *   3. `withTenantContext()` — bind temporário de `app('tenant_id')`
 *      durante write operations. Aproveita:
 *        - R2.3 enforceTenantOnCreating: força tenant_id do container
 *        - R2.5 enforceTenantOnUpdating: reverte qualquer tentativa
 *          de alterar tenant_id via mass-assignment
 *      Ou seja: mesmo que um payload malicioso injete tenant_id=99, o
 *      registro salvo terá tenant_id = $tenant->id (vindo da URL).
 *   4. `tenant_id` NUNCA entra no array validado — vem só da URL. Master
 *      não pode "mover" farm entre tenants por design (operação exigiria
 *      migração de dados relacionados).
 *
 * Leitura (index/edit):
 *   Master não tem `app('tenant_id')` bindado, então o `BelongsToTenantScope`
 *   R2.4 bypassa. Filtramos manualmente por `where('tenant_id', $tenant->id)`.
 *   Trashed farms não aparecem (Farm usa SoftDeletes).
 */
class FarmController extends Controller
{
    /**
     * Executa um callback com `app('tenant_id')` bindado temporariamente.
     *
     * Garante restauração do estado mesmo se o callback lançar exceção —
     * `tenant_farms` (cache R2.6) também é limpo para evitar leitura stale
     * entre operações master num mesmo request hipotético.
     */
    private function withTenantContext(Tenant $tenant, Closure $fn): mixed
    {
        app()->instance('tenant_id', $tenant->id);

        try {
            return $fn();
        } finally {
            app()->forgetInstance('tenant_id');
            if (app()->bound('tenant_farms')) {
                app()->forgetInstance('tenant_farms');
            }
        }
    }

    public function index(Tenant $tenant): Response
    {
        $farms = Farm::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('is_active')
            ->orderBy('nome')
            ->get(['id', 'nome', 'cidade', 'estado', 'is_active', 'created_at', 'tenant_id']);

        return Inertia::render('Master/Farms/Index', [
            'tenant' => $this->presentTenant($tenant),
            'farms' => $farms->map(fn ($f) => [
                'id' => $f->id,
                'nome' => $f->nome,
                'cidade' => $f->cidade,
                'estado' => $f->estado,
                'is_active' => (bool) $f->is_active,
                'created_at' => $f->created_at?->format('d/m/Y H:i'),
            ])->values(),
        ]);
    }

    public function create(Tenant $tenant): Response
    {
        return Inertia::render('Master/Farms/Form', [
            'tenant' => $this->presentTenant($tenant),
            'farm' => null,
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        // tenant_id: SEMPRE da URL, nunca do payload. R2.3 enforce confirma
        // ao gravar (via bind) — defesa em profundidade contra mass-assignment.
        $validated['tenant_id'] = $tenant->id;
        $validated['is_active'] = $request->boolean('is_active', true);

        $farm = $this->withTenantContext($tenant, fn () => Farm::create($validated));

        return redirect()
            ->route('master.tenants.farms.index', $tenant)
            ->with('success', 'Fazenda "'.$farm->nome.'" criada.');
    }

    public function edit(Tenant $tenant, Farm $farm): Response
    {
        return Inertia::render('Master/Farms/Form', [
            'tenant' => $this->presentTenant($tenant),
            'farm' => [
                'id' => $farm->id,
                'nome' => $farm->nome,
                'cidade' => $farm->cidade,
                'estado' => $farm->estado,
                'is_active' => (bool) $farm->is_active,
            ],
        ]);
    }

    public function update(Request $request, Tenant $tenant, Farm $farm): RedirectResponse
    {
        // scopeBindings já garantiu $farm->tenant_id === $tenant->id.
        // Ainda assim, o bind abaixo aciona R2.5 e reverte silenciosamente
        // qualquer alteração indevida de tenant_id no payload.

        $validated = $this->validatePayload($request);
        $validated['is_active'] = $request->boolean('is_active', $farm->is_active);

        $this->withTenantContext($tenant, fn () => $farm->update($validated));

        return redirect()
            ->route('master.tenants.farms.index', $tenant)
            ->with('success', 'Fazenda "'.$farm->nome.'" atualizada.');
    }

    public function toggle(Tenant $tenant, Farm $farm): RedirectResponse
    {
        $this->withTenantContext(
            $tenant,
            fn () => $farm->update(['is_active' => ! $farm->is_active])
        );

        $msg = $farm->fresh()->is_active
            ? 'Fazenda "'.$farm->nome.'" ativada.'
            : 'Fazenda "'.$farm->nome.'" desativada.';

        return back()->with('success', $msg);
    }

    /**
     * Regras compartilhadas store/update. `tenant_id` DELIBERADAMENTE AUSENTE
     * — vem apenas da URL via route-model binding (ver comentário do método).
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'size:2'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'nome.required' => 'Informe o nome da fazenda.',
            'estado.size' => 'UF deve ter 2 letras (ex.: MG, SP).',
        ]);
    }

    private function presentTenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'nome' => $tenant->nome,
            'slug' => $tenant->slug,
            'is_active' => (bool) $tenant->is_active,
        ];
    }
}
