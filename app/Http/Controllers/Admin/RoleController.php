<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['users', 'permissions'])
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'is_system' => (bool) $r->is_system,
                'users_count' => $r->users_count,
                'permissions_count' => $r->permissions_count,
                'permissions' => $r->permissions->pluck('name'),
            ]);

        // Permissões agrupadas por módulo para UI de checkboxes
        $permissions = Permission::orderBy('module')->orderBy('name')->get()
            ->groupBy('module')
            ->map(fn ($group, $module) => [
                'module' => $module ?: 'geral',
                'items' => $group->map(fn (Permission $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->description,
                ])->values(),
            ])->values();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:roles,name'],
            'description' => ['required', 'string', 'max:200'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [
            'name.regex' => 'O nome deve conter apenas letras minúsculas, números e sublinhado.',
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'description' => $data['description'],
            'is_system' => false,
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return back()->with('success', 'Perfil criado.');
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['required', 'string', 'max:200'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        // Perfis de sistema não podem ter nome alterado (mas podem ter descrição e permissões)
        if ($role->is_system && $role->name !== $data['name']) {
            return back()->with('error', 'Não é possível renomear um perfil de sistema.');
        }

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        // Admin master tem todas as permissões — força todas
        if ($role->name === 'admin_master') {
            $role->syncPermissions(Permission::all());
        }

        return back()->with('success', 'Perfil atualizado.');
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'Não é possível excluir um perfil de sistema.');
        }

        if ($role->users_count ?? DB::table('model_has_roles')->where('role_id', $role->id)->count() > 0) {
            return back()->with('error', 'Há usuários vinculados a este perfil. Reatribua-os antes de excluir.');
        }

        $role->delete();

        return back()->with('success', 'Perfil excluído.');
    }
}
