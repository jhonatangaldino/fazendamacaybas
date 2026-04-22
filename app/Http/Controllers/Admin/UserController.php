<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = User::query()
            ->with('roles:id,name,description')
            ->when($request->search, fn ($qq) => $qq->where(function ($w) use ($request) {
                $w->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->role, fn ($qq) => $qq->whereHas('roles', fn ($r) => $r->where('name', $request->role)))
            ->when($request->status === 'ativos', fn ($qq) => $qq->where('is_active', true))
            ->when($request->status === 'inativos', fn ($qq) => $qq->where('is_active', false))
            ->orderBy('name');

        return Inertia::render('Admin/Users/Index', [
            'users' => $q->paginate(20)->withQueryString()->through(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'cargo' => $u->cargo,
                'is_active' => $u->is_active,
                'last_login_at' => $u->last_login_at,
                'roles' => $u->roles->map(fn ($r) => ['name' => $r->name, 'description' => $r->description]),
            ]),
            'filters' => $request->only(['search', 'role', 'status']),
            'roles' => Role::orderBy('name')->get(['id', 'name', 'description']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => null,
            'roles' => Role::orderBy('name')->get(['id', 'name', 'description']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'password' => ['required', Password::defaults()],
            'is_active' => ['boolean'],
            'must_change_password' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = User::create([
            ...collect($data)->except('password', 'roles')->all(),
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles($data['roles'] ?? []);

        return redirect()->route('admin.users.index')->with('success', 'Usuário criado com sucesso.');
    }

    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'cpf' => $user->cpf,
                'telefone' => $user->telefone,
                'cargo' => $user->cargo,
                'is_active' => $user->is_active,
                'must_change_password' => $user->must_change_password,
                'roles' => $user->roles->pluck('name'),
            ],
            'roles' => Role::orderBy('name')->get(['id', 'name', 'description']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'cpf' => ['nullable', 'string', 'max:14'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', Password::defaults()],
            'is_active' => ['boolean'],
            'must_change_password' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $payload = collect($data)->except('password', 'roles')->all();
        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);
        $user->syncRoles($data['roles'] ?? []);

        return redirect()->route('admin.users.index')->with('success', 'Usuário atualizado.');
    }

    public function destroy(User $user)
    {
        if ($user->hasRole('admin_master')) {
            return back()->with('error', 'Não é possível excluir um Admin Master.');
        }

        $user->delete();

        return back()->with('success', 'Usuário excluído.');
    }

    public function resetPassword(User $user)
    {
        $novaSenha = 'Macaybas@'.now()->format('Y');
        $user->update([
            'password' => Hash::make($novaSenha),
            'must_change_password' => true,
        ]);

        return back()->with('success', "Senha resetada. Senha temporária: {$novaSenha} — o usuário será obrigado a trocá-la no próximo login.");
    }
}
