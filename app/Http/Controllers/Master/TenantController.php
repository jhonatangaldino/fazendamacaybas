<?php

namespace App\Http\Controllers\Master;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Cms\Services\LandingScaffoldService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TenantController — M3
 *
 * Primeiro CRUD funcional da área master. Gerencia os tenants (clientes)
 * da plataforma SaaS. Escopo intencionalmente MÍNIMO (nome, slug, is_active)
 * — billing, planos, cobrança e fazendas ficam para M4+.
 *
 * Já protegido por `auth` + `enforce.master` ao nível do grupo — qualquer
 * acesso não-master nunca chega aqui. Não há verificação adicional de
 * permissions nesta fase (todos os masters podem tudo); granularidade
 * virá em M8 (platform.tenants.manage etc).
 *
 * Modelo Tenant (App\Domain\Billing\Models\Tenant) já tem:
 *   - `nome`, `slug`, `is_active` em $fillable
 *   - cast de `is_active` como boolean
 *   - UNIQUE constraint em `slug` no DB (R1.1)
 */
class TenantController extends Controller
{
    /**
     * Listagem. Ativos primeiro, ordem alfabética dentro de cada grupo.
     * Não pagina em M3 — lista total deve caber numa tela até atingirmos
     * dezenas de tenants; paginação entra quando isso virar realidade.
     */
    public function index(): Response
    {
        $tenants = Tenant::query()
            ->orderByDesc('is_active')
            ->orderBy('nome')
            ->get(['id', 'nome', 'slug', 'is_active', 'created_at', 'updated_at']);

        return Inertia::render('Master/Tenants/Index', [
            'tenants' => $tenants->map(fn ($t) => [
                'id' => $t->id,
                'nome' => $t->nome,
                'slug' => $t->slug,
                'is_active' => (bool) $t->is_active,
                'created_at' => $t->created_at?->format('d/m/Y H:i'),
                'updated_at' => $t->updated_at?->format('d/m/Y H:i'),
            ])->values(),
        ]);
    }

    /**
     * Tela de criação — form em branco.
     */
    public function create(): Response
    {
        return Inertia::render('Master/Tenants/Form', [
            'tenant' => null,
        ]);
    }

    /**
     * Persiste um novo tenant + scaffold da landing padrão.
     *
     * O scaffold cria 1 página "home" com 6 seções + 2 menus (header/footer)
     * já pertencentes ao cliente recém-criado. Idempotente — rodar 2x no
     * mesmo cliente é no-op (útil para re-provisionamento manual).
     */
    public function store(Request $request, LandingScaffoldService $scaffold): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        // Slug: se veio vazio (validado required, mas defensivo), gera de nome
        $validated['slug'] = Str::slug($validated['slug']);

        // Default active=true se não informado; respeitamos o check explícito.
        $validated['is_active'] = $request->boolean('is_active', true);

        // `status` é NOT NULL na tabela (R1.1). Default 'active' até M6.
        $validated['status'] = 'active';

        $tenant = Tenant::create($validated);

        // Scaffold imediato — cliente novo já tem landing funcional em
        // /c/{slug} sem exigir intervenção manual no CMS.
        $scaffold->scaffold($tenant);

        return redirect()
            ->route('master.tenants.index')
            ->with('success', 'Cliente "'.$tenant->nome.'" criado com landing padrão.');
    }

    /**
     * Tela de edição. Route model binding injeta o Tenant pelo id.
     */
    public function edit(Tenant $tenant): Response
    {
        return Inertia::render('Master/Tenants/Form', [
            'tenant' => [
                'id' => $tenant->id,
                'nome' => $tenant->nome,
                'slug' => $tenant->slug,
                'is_active' => (bool) $tenant->is_active,
            ],
        ]);
    }

    /**
     * Atualiza um tenant existente.
     */
    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $this->validatePayload($request, $tenant);

        $validated['slug'] = Str::slug($validated['slug']);
        $validated['is_active'] = $request->boolean('is_active', $tenant->is_active);

        $tenant->update($validated);

        return redirect()
            ->route('master.tenants.index')
            ->with('success', 'Tenant "'.$tenant->nome.'" atualizado.');
    }

    /**
     * Alterna is_active do tenant. Endpoint dedicado — evita submissão
     * de form inteiro só para mudar um booleano.
     *
     * Observação de segurança: M3 permite desativar qualquer tenant,
     * inclusive o que tem users ativos operando. Salvaguardas de
     * integridade (bloqueio + confirmação dupla) ficam para M6 quando
     * entrarem billing/suspensão automatizada.
     */
    public function toggle(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['is_active' => ! $tenant->is_active]);

        $msg = $tenant->is_active
            ? 'Tenant "'.$tenant->nome.'" ativado.'
            : 'Tenant "'.$tenant->nome.'" desativado.';

        return back()->with('success', $msg);
    }

    /**
     * Regras de validação compartilhadas entre store e update.
     * Unique no slug ignora o próprio tenant em update.
     */
    private function validatePayload(Request $request, ?Tenant $tenant = null): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'alpha_dash', // a-z0-9_- (Str::slug normaliza acentos depois)
                Rule::unique('tenants', 'slug')->ignore($tenant?->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'nome.required' => 'Informe o nome do tenant.',
            'slug.required' => 'Informe o slug.',
            'slug.alpha_dash' => 'Slug aceita apenas letras, números, hífen e underline.',
            'slug.unique' => 'Já existe um tenant com esse slug.',
        ]);
    }
}
