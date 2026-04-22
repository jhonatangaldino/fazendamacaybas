<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Perfis + permissões iniciais.
     *
     * Perfis:
     *  - admin_master       → acesso total (você)
     *  - dono_fazenda       → operação completa (seu pai)
     *  - gerente            → operação + relatórios, sem admin
     *  - financeiro         → apenas módulos financeiros + relatórios
     *  - veterinario        → rebanho + documentos sanitários
     *  - agronomo           → agrícola + estoque (insumos)
     *  - administrativo     → documentos + funcionários + tarefas
     *  - funcionario        → tarefas + lançamentos básicos
     *  - auditor            → só leitura
     *  - visitante          → acesso restrito a poucas áreas
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        DB::transaction(function () {
            $modules = [
                'dashboard', 'users', 'roles',
                'cms', 'cms.pages', 'cms.menus', 'cms.settings',
                'financeiro', 'financeiro.contas', 'financeiro.transacoes', 'financeiro.recorrencias', 'financeiro.relatorios',
                'rebanho', 'rebanho.animais', 'rebanho.lotes', 'rebanho.eventos',
                'agricola', 'agricola.talhoes', 'agricola.plantios', 'agricola.colheitas', 'agricola.aplicacoes',
                'estoque', 'estoque.itens', 'estoque.movimentos', 'estoque.armazens',
                'maquinas', 'maquinas.veiculos', 'maquinas.manutencoes',
                'funcionarios', 'funcionarios.cadastro', 'funcionarios.tarefas',
                'documentos',
                'relatorios',
                'parceiros',
                'fazendas',
            ];

            $actions = ['view', 'create', 'update', 'delete'];

            $allPermissions = [];
            foreach ($modules as $module) {
                foreach ($actions as $action) {
                    $name = "{$module}.{$action}";
                    $perm = Permission::firstOrCreate(
                        ['name' => $name, 'guard_name' => 'web'],
                        ['module' => explode('.', $module)[0], 'description' => ucfirst($action).' em '.$module]
                    );
                    $allPermissions[] = $perm;
                }
            }

            // Permissões extras
            Permission::firstOrCreate(['name' => 'cms.publish', 'guard_name' => 'web'], ['module' => 'cms', 'description' => 'Publicar alterações do CMS']);
            Permission::firstOrCreate(['name' => 'financeiro.approve', 'guard_name' => 'web'], ['module' => 'financeiro', 'description' => 'Aprovar lançamentos']);
            Permission::firstOrCreate(['name' => 'users.reset_password', 'guard_name' => 'web'], ['module' => 'users', 'description' => 'Resetar senha de usuários']);

            // ================ ADMIN MASTER ================
            $adminMaster = Role::firstOrCreate(
                ['name' => 'admin_master', 'guard_name' => 'web'],
                ['short_name' => 'Admin', 'description' => 'Administrador Master — controle total do sistema', 'is_system' => true]
            );
            if (! $adminMaster->short_name) $adminMaster->update(['short_name' => 'Admin']);
            $adminMaster->syncPermissions(Permission::all());

            // ================ DONO DA FAZENDA ================
            $donoFazenda = Role::firstOrCreate(
                ['name' => 'dono_fazenda', 'guard_name' => 'web'],
                ['short_name' => 'Dono', 'description' => 'Dono da Fazenda — visão e controle total da operação', 'is_system' => true]
            );
            if (! $donoFazenda->short_name) $donoFazenda->update(['short_name' => 'Dono']);
            // Dono NÃO mexe em roles/permissions, nem no CMS/site institucional (só admin_master), nem em reset de senha.
            $donoPerms = Permission::where(function ($q) {
                $q->where('name', 'not like', 'roles.%')
                    ->where('name', 'not like', 'cms%')
                    ->where('name', '!=', 'users.reset_password');
            })->get();
            $donoFazenda->syncPermissions($donoPerms);

            // ================ GERENTE ================
            $gerente = Role::firstOrCreate(
                ['name' => 'gerente', 'guard_name' => 'web'],
                ['short_name' => 'Gerente', 'description' => 'Gerente — operação completa, sem administração do sistema', 'is_system' => true]
            );
            if (! $gerente->short_name) $gerente->update(['short_name' => 'Gerente']);
            $gerente->syncPermissions(Permission::whereIn('module', [
                'dashboard', 'financeiro', 'rebanho', 'agricola', 'estoque', 'maquinas',
                'funcionarios', 'documentos', 'relatorios', 'parceiros', 'fazendas',
            ])->get());

            // ================ FINANCEIRO ================
            $financeiro = Role::firstOrCreate(
                ['name' => 'financeiro', 'guard_name' => 'web'],
                ['short_name' => 'Financeiro', 'description' => 'Equipe financeira', 'is_system' => true]
            );
            if (! $financeiro->short_name) $financeiro->update(['short_name' => 'Financeiro']);
            $financeiro->syncPermissions(Permission::whereIn('module', [
                'dashboard', 'financeiro', 'parceiros', 'relatorios', 'documentos',
            ])->get());

            // ================ VETERINÁRIO ================
            $veterinario = Role::firstOrCreate(
                ['name' => 'veterinario', 'guard_name' => 'web'],
                ['short_name' => 'Veterinário', 'description' => 'Veterinário — rebanho + sanidade', 'is_system' => true]
            );
            if (! $veterinario->short_name) $veterinario->update(['short_name' => 'Veterinário']);
            $veterinario->syncPermissions(Permission::whereIn('module', [
                'dashboard', 'rebanho', 'estoque', 'documentos',
            ])->get());

            // ================ AGRÔNOMO ================
            $agronomo = Role::firstOrCreate(
                ['name' => 'agronomo', 'guard_name' => 'web'],
                ['short_name' => 'Agrônomo', 'description' => 'Agrônomo — produção agrícola + insumos', 'is_system' => true]
            );
            if (! $agronomo->short_name) $agronomo->update(['short_name' => 'Agrônomo']);
            $agronomo->syncPermissions(Permission::whereIn('module', [
                'dashboard', 'agricola', 'estoque', 'documentos',
            ])->get());

            // ================ ADMINISTRATIVO ================
            $administrativo = Role::firstOrCreate(
                ['name' => 'administrativo', 'guard_name' => 'web'],
                ['short_name' => 'Administrativo', 'description' => 'Equipe administrativa — documentos, funcionários, tarefas', 'is_system' => true]
            );
            if (! $administrativo->short_name) $administrativo->update(['short_name' => 'Administrativo']);
            $administrativo->syncPermissions(Permission::whereIn('module', [
                'dashboard', 'funcionarios', 'documentos', 'parceiros',
            ])->get());

            // ================ FUNCIONÁRIO ================
            $funcionario = Role::firstOrCreate(
                ['name' => 'funcionario', 'guard_name' => 'web'],
                ['short_name' => 'Funcionário', 'description' => 'Funcionário operacional', 'is_system' => true]
            );
            if (! $funcionario->short_name) $funcionario->update(['short_name' => 'Funcionário']);
            $funcionario->syncPermissions(Permission::whereIn('name', [
                'dashboard.view',
                'rebanho.view', 'rebanho.eventos.view', 'rebanho.eventos.create',
                'agricola.view', 'agricola.aplicacoes.view', 'agricola.aplicacoes.create',
                'estoque.view', 'estoque.movimentos.view', 'estoque.movimentos.create',
                'maquinas.view', 'maquinas.manutencoes.view',
                'funcionarios.tarefas.view', 'funcionarios.tarefas.update',
            ])->get());

            // ================ AUDITOR ================
            $auditor = Role::firstOrCreate(
                ['name' => 'auditor', 'guard_name' => 'web'],
                ['short_name' => 'Auditor', 'description' => 'Auditor — somente leitura', 'is_system' => true]
            );
            if (! $auditor->short_name) $auditor->update(['short_name' => 'Auditor']);
            $auditor->syncPermissions(Permission::where('name', 'like', '%.view')->get());

            // ================ VISITANTE ================
            $visitante = Role::firstOrCreate(
                ['name' => 'visitante', 'guard_name' => 'web'],
                ['short_name' => 'Visitante', 'description' => 'Visitante — acesso restrito ao dashboard', 'is_system' => false]
            );
            if (! $visitante->short_name) $visitante->update(['short_name' => 'Visitante']);
            $visitante->syncPermissions(Permission::whereIn('name', ['dashboard.view'])->get());
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
