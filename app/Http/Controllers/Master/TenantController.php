<?php

namespace App\Http\Controllers\Master;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Cms\Services\LandingScaffoldService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Status "pronto para uso" por tenant: tem mapa (qualquer dos 4 campos
        // landing.map.*) OU site.descricao configurado como OVERRIDE do
        // cliente (não global herdado). Fallback global não conta — a ideia
        // é diferenciar clientes que o master ainda precisa configurar.
        $readyKeys = [
            'landing.map.endereco',
            'landing.map.latitude',
            'landing.map.longitude',
            'landing.map.google_embed',
            'site.descricao',
        ];
        $readyTenantIds = DB::table('settings')
            ->whereIn('key', $readyKeys)
            ->whereNotNull('tenant_id')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->pluck('tenant_id')
            ->unique()
            ->flip()
            ->toArray();

        return Inertia::render('Master/Tenants/Index', [
            'tenants' => $tenants->map(fn ($t) => [
                'id' => $t->id,
                'nome' => $t->nome,
                'slug' => $t->slug,
                'is_active' => (bool) $t->is_active,
                'is_ready' => isset($readyTenantIds[$t->id]),
                'landing_url' => url('/c/' . $t->slug),
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
     *
     * Flash `created_tenant` leva URL + mensagem pronta de entrega para a
     * Index renderizar o card "Página pronta para uso".
     */
    public function store(Request $request, LandingScaffoldService $scaffold): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        // Slug: defensivo — Str::slug normaliza caso cliente envie acentos
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
            ->with('created_tenant', [
                'id' => $tenant->id,
                'nome' => $tenant->nome,
                'slug' => $tenant->slug,
                'landing_url' => url('/c/' . $tenant->slug),
                'delivery_message' => $this->buildDeliveryMessage($tenant),
            ]);
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
                'email' => $tenant->email,
                'telefone' => $tenant->telefone,
                'cidade' => $tenant->cidade,
                'estado' => $tenant->estado,
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
            'email' => ['nullable', 'email', 'max:150'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'size:2'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'nome.required' => 'Informe o nome do cliente.',
            'slug.required' => 'Informe o slug.',
            'slug.alpha_dash' => 'Slug aceita apenas letras, números, hífen e underline.',
            'slug.unique' => 'Já existe um cliente com esse slug.',
            'email.email' => 'E-mail inválido.',
            'estado.size' => 'UF deve ter 2 letras (ex.: MG).',
        ]);
    }

    /**
     * Texto padrão de entrega para o master copiar/colar ao comunicar
     * o cliente de que a página dele está no ar. Formato simples, sem
     * branding específico — o master ajusta manualmente se quiser.
     */
    private function buildDeliveryMessage(Tenant $tenant): string
    {
        $url = url('/c/' . $tenant->slug);

        return "Olá! Sua página já está disponível em:\n"
            . $url . "\n"
            . "Você pode editar acessando o painel.";
    }
}
