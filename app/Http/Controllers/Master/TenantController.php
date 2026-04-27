<?php

namespace App\Http\Controllers\Master;

use App\Domain\Auth\Services\TemporaryPasswordService;
use App\Domain\Billing\Models\Tenant;
use App\Domain\Cms\Services\LandingScaffoldService;
use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        // Onboarding ATÔMICO: tenant + scaffold landing + fazenda padrão +
        // usuário dono + senha temporária. Tudo em uma transação: ou o
        // cliente fica 100% pronto pra usar, ou nada é criado.
        //
        // Correção do GAP de fechamento: sem isso, master precisava
        // IMPERSONAR o tenant, criar a farm, criar o user e comunicar
        // credenciais à parte — fluxo incompleto. Agora a mensagem de
        // entrega já contém email + senha prontas pro primeiro acesso.
        $senhaTemporaria = null;
        $donoEmail = null;

        $tenant = DB::transaction(function () use ($validated, $scaffold, &$senhaTemporaria, &$donoEmail) {
            $tenant = Tenant::create($validated);

            // Scaffold da landing (pages + sections + menus)
            $scaffold->scaffold($tenant);

            // Fazenda padrão — nome igual ao do tenant. O dono pode
            // renomear / adicionar outras fazendas depois pelo painel.
            $farm = Farm::create([
                'tenant_id' => $tenant->id,
                'nome' => $tenant->nome,
                'is_active' => true,
            ]);

            // Usuário dono — usa o email informado no form como
            // credencial de primeiro acesso. Senha temporária 8 chars
            // alfanuméricos gerada pelo TemporaryPasswordService que
            // também envia email de boas-vindas (noreply@) com a senha.
            if (! empty($tenant->email)) {
                $donoEmail = $tenant->email;

                $user = new User();
                $user->tenant_id = $tenant->id;
                $user->current_farm_id = $farm->id;
                $user->name = $tenant->nome;
                $user->email = $tenant->email;
                $user->password = Hash::make(Str::random(40)); // placeholder
                $user->is_active = true;
                $user->email_verified_at = now();
                $user->save();

                $user->assignRole('dono_fazenda');

                // Service preenche password (hash da temp), temp_password_plaintext,
                // password_expires_at e dispara email de boas-vindas.
                $senhaTemporaria = app(TemporaryPasswordService::class)->issueWelcome($user);
            }

            return $tenant;
        });

        return redirect()
            ->route('master.tenants.index')
            ->with('created_tenant', [
                'id' => $tenant->id,
                'nome' => $tenant->nome,
                'slug' => $tenant->slug,
                'landing_url' => url('/c/' . $tenant->slug),
                'dono_email' => $donoEmail,
                'senha_temporaria' => $senhaTemporaria,
                'admin_url' => url('/admin'),
                'delivery_message' => $this->buildDeliveryMessage($tenant, $donoEmail, $senhaTemporaria),
            ]);
    }

    /**
     * @deprecated Use TemporaryPasswordService::issueWelcome() ou regenerate().
     * Mantido apenas como fallback histórico durante a transição.
     */
    private function gerarSenhaTemporaria(): string
    {
        return \App\Support\PasswordGenerator::make();
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
     * Listagem de usuários DE UM TENANT — permite ao master saber quem
     * tem acesso em cada cliente sem precisar impersonar. Valor operacional:
     * quando o cliente liga dizendo "esqueci a senha", o master já tem a
     * lista de emails + pode resetar a senha direto.
     */
    public function users(Tenant $tenant): Response
    {
        $users = User::where('tenant_id', $tenant->id)
            ->with('roles:id,name')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_active' => (bool) $u->is_active,
                'must_change_password' => (bool) $u->must_change_password,
                'last_login_at' => $u->last_login_at?->format('d/m/Y H:i'),
                'roles' => $u->roles->pluck('name')->all(),
                // Senha temp visível enquanto user não trocou
                'temp_password' => $u->temporaryPasswordIsVisible() ? $u->temp_password_plaintext : null,
                'temp_password_expired' => $u->temporaryPasswordIsExpired(),
                'temp_password_expires_at' => $u->password_expires_at?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            ]);

        // Papéis disponíveis para atribuir — exclui admin_master (só master real)
        $rolesDisponiveis = \Spatie\Permission\Models\Role::where('name', '!=', 'admin_master')
            ->orderBy('name')
            ->pluck('name');

        return Inertia::render('Master/Tenants/Users', [
            'tenant' => [
                'id' => $tenant->id,
                'nome' => $tenant->nome,
                'slug' => $tenant->slug,
                'is_active' => (bool) $tenant->is_active,
            ],
            'users' => $users,
            'rolesDisponiveis' => $rolesDisponiveis,
        ]);
    }

    /**
     * Endpoint inline (JSON) — cria um usuário dentro de um tenant.
     * Gera senha temporária forte que o master copia e entrega. Usuário
     * criado com must_change_password=true — força troca no 1º login.
     */
    public function storeUserInline(Request $request, Tenant $tenant): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')],
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        // Impede criação de admin_master em tenant (só via seed)
        if ($data['role'] === 'admin_master') {
            abort(422, 'Não é permitido criar usuário admin_master em um cliente.');
        }

        $farmId = \App\Models\Farm::where('tenant_id', $tenant->id)->where('is_active', true)->value('id');

        $user = new User();
        $user->tenant_id = $tenant->id;
        $user->current_farm_id = $farmId;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make(Str::random(40)); // placeholder
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        $user->assignRole($data['role']);

        // Service gera senha temp + envia email de boas-vindas
        $senha = app(TemporaryPasswordService::class)->issueWelcome($user);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => true,
            'must_change_password' => true,
            'last_login_at' => null,
            'roles' => [$data['role']],
            // Senha temp retornada UMA VEZ — master copia pra enviar ao cliente
            'senha_temporaria' => $senha,
            'admin_url' => url('/admin'),
        ]);
    }

    /**
     * Reset de senha pelo master — gera nova senha temporária, retorna uma
     * vez ao master copiar, e marca must_change_password=true. Cliente faz
     * login com a temp e o sistema força troca. Sem depender de SMTP / email.
     */
    public function resetUserPassword(Tenant $tenant, User $user): RedirectResponse
    {
        abort_if($user->tenant_id !== $tenant->id, 404);

        // Regenera senha + envia email de "senha temporária atualizada"
        $novaSenha = app(TemporaryPasswordService::class)->regenerate($user);

        return back()->with('success', 'Senha resetada e enviada por email.')
            ->with('reset_password_result', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'senha_temporaria' => $novaSenha,
                'admin_url' => url('/admin'),
            ]);
    }

    /**
     * Ativa/desativa usuário do tenant — útil para cortar acesso de
     * funcionário que saiu da fazenda sem precisar impersonar.
     */
    public function toggleUser(Tenant $tenant, User $user): RedirectResponse
    {
        abort_if($user->tenant_id !== $tenant->id, 404);

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active
            ? 'Usuário reativado.'
            : 'Usuário desativado (sem acesso ao sistema).');
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
     * Texto padrão de entrega — inclui credenciais de primeiro acesso
     * quando disponíveis. Formato whatsapp-ready (plain text, copiar-colar).
     *
     * Quando $donoEmail ou $senhaTemporaria estão nulos (tenant cadastrado
     * sem email), cai no formato legado (só URL da landing) — master
     * resolve as credenciais depois via /admin/usuarios após impersonar.
     */
    private function buildDeliveryMessage(Tenant $tenant, ?string $donoEmail = null, ?string $senhaTemporaria = null): string
    {
        $landing = url('/c/' . $tenant->slug);
        $admin = url('/admin');

        if ($donoEmail && $senhaTemporaria) {
            return "Olá! Seu sistema Fazenda Macaybas está pronto.\n\n"
                . "🔐 ACESSO AO PAINEL\n"
                . $admin . "\n"
                . "E-mail: " . $donoEmail . "\n"
                . "Senha temporária: " . $senhaTemporaria . "\n"
                . "(Ao entrar pela primeira vez, o sistema vai pedir para você trocar a senha.)\n\n"
                . "🌐 SUA PÁGINA PÚBLICA\n"
                . $landing . "\n\n"
                . "Qualquer dúvida é só chamar.";
        }

        return "Olá! Sua página já está disponível em:\n"
            . $landing . "\n"
            . "Você pode editar acessando o painel.";
    }
}
