<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Services\TemporaryPasswordService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $isMaster = $request->user()->hasRole('admin_master');

        // CRÍTICO multi-tenant: User NÃO usa BelongsToTenant trait (decisão
        // arquitetural pra suportar master sem tenant). Resultado: sem filtro
        // explícito, /admin/users vazava usuários de TODOS os clientes.
        // Detectado pelo dono: master impersonando "Fazenda Demonstração" via
        // usuários da "Fazenda Macaybas". FIX: filtrar pelo tenant_id ATIVO
        // (do user logado, ou do tenant impersonado quando master).
        $tenantIdAtivo = (int) (app()->bound('tenant_id') ? app('tenant_id') : ($request->user()->tenant_id ?? 0));

        $q = User::query()
            ->with('roles:id,name,short_name,description')
            ->where('tenant_id', $tenantIdAtivo)
            ->when($request->search, fn ($qq) => $qq->where(function ($w) use ($request) {
                $w->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->role, fn ($qq) => $qq->whereHas('roles', fn ($r) => $r->where('name', $request->role)))
            ->when($request->status === 'ativos', fn ($qq) => $qq->where('is_active', true))
            ->when($request->status === 'inativos', fn ($qq) => $qq->where('is_active', false))
            // Não-masters não enxergam usuários admin_master (e tenant não tem masters mesmo)
            ->when(! $isMaster, fn ($qq) => $qq->whereDoesntHave('roles', fn ($r) => $r->where('name', 'admin_master')))
            ->orderBy('name');

        return Inertia::render('Admin/Users/Index', [
            'users' => $q->paginate(20)->withQueryString()->through(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'cargo' => $u->cargo,
                'avatar_url' => $u->avatarUrl(),
                'is_active' => $u->is_active,
                'last_login_at' => $u->last_login_at,
                // Senha temporária visível ATÉ o user trocar (must_change_password=true).
                // Plaintext já vem descriptografado do cast 'encrypted'.
                'temp_password' => $u->temporaryPasswordIsVisible() ? $u->temp_password_plaintext : null,
                'temp_password_expired' => $u->temporaryPasswordIsExpired(),
                'temp_password_expires_at' => $u->password_expires_at,
                'must_change_password' => $u->must_change_password,
                'roles' => $u->roles->map(fn ($r) => [
                    'name' => $r->name,
                    'short_name' => $r->short_name ?: ucfirst(str_replace('_', ' ', $r->name)),
                    'description' => $r->description,
                ]),
            ]),
            'filters' => $request->only(['search', 'role', 'status']),
            'roles' => $this->availableRoles(),
            // Uso vs limite do plano — UI exibe "5 de 10" + bloqueia "Novo usuário"
            // quando atinge o teto.
            'plan_info' => $this->planUsageInfo($request),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => null,
            'roles' => $this->availableRoles(),
            'available_farms' => $this->farmsDoTenantAtivo($request),
            'plan_info' => $this->planUsageInfo($request),
        ]);
    }

    public function store(Request $request, TemporaryPasswordService $tempPassword)
    {
        // Cadastro de usuário NÃO recebe senha do form. Sistema gera senha
        // temporária aleatória de 8 caracteres alfanuméricos, envia por email
        // e força troca no primeiro login.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
            // Farms permitidas (subset do tenant). Vazio = acesso global ao tenant.
            'farm_ids' => ['nullable', 'array'],
            'farm_ids.*' => ['integer', 'exists:farms,id'],
        ]);

        // Não-masters não podem atribuir o perfil admin_master
        if (! $request->user()->hasRole('admin_master') && in_array('admin_master', $data['roles'] ?? [], true)) {
            return back()->with('error', 'Você não tem permissão para atribuir o perfil Admin Master.');
        }

        $criador = $request->user();
        if ($criador->tenant_id === null) {
            return back()->with('error', 'Master não pode criar usuários por esta tela. Use a tela de Usuários do cliente em /master/tenants/{id}/usuarios.');
        }

        // Limite de usuários do plano — controle por tenant.
        // plan.max_users = 0 significa ilimitado.
        $tenant = $criador->tenant()->with('plan:id,max_users,nome')->first();
        $maxUsers = $tenant?->plan?->max_users ?? 0;
        if ($maxUsers > 0) {
            $usuariosAtivos = User::where('tenant_id', $criador->tenant_id)
                ->where('is_active', true)
                ->count();
            if ($usuariosAtivos >= $maxUsers) {
                return back()->with('error',
                    "Limite de usuários do plano \"{$tenant->plan->nome}\" atingido ({$usuariosAtivos}/{$maxUsers}). "
                    . "Para adicionar mais usuários, faça upgrade do plano ou desative usuários existentes."
                );
            }
        }

        // Garante que farms escolhidas pertencem ao tenant do criador (defesa
        // contra payload manipulado tentando atribuir farm de outro cliente).
        $farmIdsRaw = $data['farm_ids'] ?? [];
        $farmIds = empty($farmIdsRaw) ? [] : \App\Models\Farm::where('tenant_id', $criador->tenant_id)
            ->whereIn('id', $farmIdsRaw)
            ->pluck('id')
            ->all();

        // Cria o user SEM senha definitiva. O service depois preenche password
        // (hash), temp_password_plaintext e password_expires_at.
        $user = User::create([
            ...collect($data)->except(['roles', 'farm_ids'])->all(),
            'password' => Hash::make(\Illuminate\Support\Str::random(40)), // placeholder, será sobrescrito
            'tenant_id' => $criador->tenant_id,
            'current_farm_id' => $criador->current_farm_id,
        ]);

        $user->syncRoles($data['roles'] ?? []);
        // Vincula farms permitidas (vazio = acesso global)
        $user->farms()->sync($farmIds);

        // Gera senha temporária + dispara email de boas-vindas.
        // Falha de email é tolerada (admin vê senha em tela como fallback).
        $senhaTemp = $tempPassword->issueWelcome($user);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Usuário criado. Senha temporária: {$senhaTemp} — também enviada para {$user->email}. Expira em ".TemporaryPasswordService::VALIDITY_HOURS." horas.");
    }

    public function edit(Request $request, User $user)
    {
        // Defesa multi-tenant: usuário só edita usuário do mesmo tenant.
        $tenantIdAtivo = (int) (app()->bound('tenant_id') ? app('tenant_id') : ($request->user()->tenant_id ?? 0));
        if ($user->tenant_id !== $tenantIdAtivo) {
            abort(403, 'Você não tem acesso a este usuário.');
        }

        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'cpf' => $user->cpf,
                'telefone' => $user->telefone,
                'cargo' => $user->cargo,
                'avatar_url' => $user->avatarUrl(),
                'is_active' => $user->is_active,
                'must_change_password' => $user->must_change_password,
                'roles' => $user->roles->pluck('name'),
                'farm_ids' => $user->farmsPermitidas(),
            ],
            'roles' => $this->availableRoles(),
            'available_farms' => $this->farmsDoTenantAtivo($request),
            'plan_info' => $this->planUsageInfo($request),
        ]);
    }

    public function update(Request $request, User $user)
    {
        // Não-masters não podem editar um admin_master
        if ($user->hasRole('admin_master') && ! $request->user()->hasRole('admin_master')) {
            return back()->with('error', 'Você não tem permissão para editar este usuário.');
        }

        // Defesa multi-tenant
        $tenantIdAtivo = (int) (app()->bound('tenant_id') ? app('tenant_id') : ($request->user()->tenant_id ?? 0));
        if ($user->tenant_id !== $tenantIdAtivo) {
            abort(403, 'Você não tem acesso a este usuário.');
        }

        // Edição NÃO aceita senha. Para reset/regen, usar resetPassword endpoint.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user->id)],
            'cpf' => ['nullable', 'string', 'max:14'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'farm_ids' => ['nullable', 'array'],
            'farm_ids.*' => ['integer', 'exists:farms,id'],
        ]);

        if (! $request->user()->hasRole('admin_master') && in_array('admin_master', $data['roles'] ?? [], true)) {
            return back()->with('error', 'Você não tem permissão para atribuir o perfil Admin Master.');
        }

        // Reativar user inativo encara limite do plano
        if ($user->is_active === false && ($data['is_active'] ?? false) === true) {
            $tenant = $user->tenant()->with('plan:id,max_users,nome')->first();
            $maxUsers = $tenant?->plan?->max_users ?? 0;
            if ($maxUsers > 0) {
                $usuariosAtivos = User::where('tenant_id', $user->tenant_id)
                    ->where('is_active', true)
                    ->where('id', '!=', $user->id)
                    ->count();
                if ($usuariosAtivos >= $maxUsers) {
                    return back()->with('error',
                        "Não pode reativar — limite do plano \"{$tenant->plan->nome}\" atingido ({$usuariosAtivos}/{$maxUsers})."
                    );
                }
            }
        }

        // Sanitiza farm_ids — só farms do tenant
        $farmIdsRaw = $data['farm_ids'] ?? [];
        $farmIds = empty($farmIdsRaw) ? [] : \App\Models\Farm::where('tenant_id', $user->tenant_id)
            ->whereIn('id', $farmIdsRaw)
            ->pluck('id')
            ->all();

        $user->update(collect($data)->except(['roles', 'farm_ids'])->all());
        $user->syncRoles($data['roles'] ?? []);
        $user->farms()->sync($farmIds);

        return redirect()->route('admin.users.index')->with('success', 'Usuário atualizado.');
    }

    /**
     * Lista de farms do tenant ATIVO (criador). Defesa multi-tenant — só
     * mostra farms do próprio cliente.
     */
    private function farmsDoTenantAtivo(Request $request): array
    {
        $tenantIdAtivo = (int) (app()->bound('tenant_id') ? app('tenant_id') : ($request->user()->tenant_id ?? 0));
        return \App\Models\Farm::where('tenant_id', $tenantIdAtivo)
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn ($f) => ['id' => $f->id, 'nome' => $f->nome])
            ->all();
    }

    /**
     * Info do plano sobre uso de usuários — pra o Form mostrar "5 de 10 usuários"
     * e impedir cadastro quando atinge o teto.
     */
    private function planUsageInfo(Request $request): array
    {
        $tenantIdAtivo = (int) (app()->bound('tenant_id') ? app('tenant_id') : ($request->user()->tenant_id ?? 0));
        $tenant = \App\Domain\Billing\Models\Tenant::with('plan:id,max_users,nome')->find($tenantIdAtivo);
        $maxUsers = $tenant?->plan?->max_users ?? 0; // 0 = ilimitado
        $usuariosAtivos = User::where('tenant_id', $tenantIdAtivo)
            ->where('is_active', true)
            ->count();
        return [
            'max_users' => $maxUsers,
            'usuarios_ativos' => $usuariosAtivos,
            'plano_nome' => $tenant?->plan?->nome,
            'limite_atingido' => $maxUsers > 0 && $usuariosAtivos >= $maxUsers,
        ];
    }

    public function destroy(User $user)
    {
        if ($user->hasRole('admin_master')) {
            return back()->with('error', 'Não é possível excluir um Admin Master.');
        }

        $user->delete();

        return back()->with('success', 'Usuário excluído.');
    }

    /**
     * Reset/regenera senha temporária — gera nova de 8 chars + reenvia email.
     * Usado pelo botão "Reenviar credenciais" / "Regenerar senha" na UI.
     */
    public function resetPassword(User $user, TemporaryPasswordService $tempPassword)
    {
        $novaSenha = $tempPassword->regenerate($user);

        return back()->with(
            'success',
            "Nova senha temporária: {$novaSenha} — também enviada para {$user->email}. Expira em ".TemporaryPasswordService::VALIDITY_HOURS." horas."
        );
    }

    /**
     * Upload/substituição do avatar. Retorna JSON para atualização imediata na UI.
     */
    public function uploadAvatar(Request $request, User $user): JsonResponse
    {
        $validator = validator($request->all(), [
            'file' => [
                'required',
                'file',
                'max:5120',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => $validator->errors()->first('file') ?: 'Arquivo inválido.',
            ], 422);
        }

        // Autorização: só admin_master/dono_fazenda editam de outros,
        // usuário comum só pode editar o próprio avatar
        if (! $this->canEditAvatarOf($request->user(), $user)) {
            return response()->json([
                'ok' => false,
                'message' => 'Sem permissão para alterar o avatar deste usuário.',
            ], 403);
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension()) ?: $file->guessExtension();
        $filename = 'user-'.$user->id.'-'.Str::random(8).'.'.$ext;
        $path = $file->storeAs('avatars', $filename, 'public');

        // Apaga avatar anterior
        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->update(['avatar_path' => $path]);

        return response()->json([
            'ok' => true,
            'message' => 'Avatar atualizado.',
            'avatar_url' => $user->fresh()->avatarUrl(),
            'path' => $path,
        ]);
    }

    public function removeAvatar(Request $request, User $user): JsonResponse
    {
        if (! $this->canEditAvatarOf($request->user(), $user)) {
            return response()->json([
                'ok' => false,
                'message' => 'Sem permissão.',
            ], 403);
        }

        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->update(['avatar_path' => null]);

        return response()->json([
            'ok' => true,
            'message' => 'Avatar removido.',
        ]);
    }

    protected function canEditAvatarOf(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) return true;
        if ($actor->hasRole('admin_master')) return true;
        if ($actor->hasRole('dono_fazenda')) return true;
        if ($actor->can('operational.usuarios.update')) return true;

        return false;
    }

    /**
     * Perfis que o usuário atual pode atribuir. Não-masters nunca veem admin_master.
     */
    protected function availableRoles()
    {
        $actor = request()->user();

        return Role::query()
            ->when($actor && ! $actor->hasRole('admin_master'), fn ($qq) => $qq->where('name', '!=', 'admin_master'))
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'description']);
    }
}
