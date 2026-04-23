<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * M8.C — limpeza final de permissions legadas.
 *
 * REMOVE:
 *  1. Todas as permissions que NÃO começam com `platform.` ou `operational.`
 *     (as 143 antigas — cms.*, rebanho.*, financeiro.*, etc.)
 *  2. Bindings role_has_permissions caem automaticamente via FK CASCADE do Spatie
 *
 * CONSOLIDA INVARIANTE:
 *  3. `admin_master` perde qualquer `operational.*` (herdada em M8.A por
 *     ter as antigas operacionais) — master é PURAMENTE platform.
 *     Durante impersonação, Gate::before (M8.B) libera operational.*
 *     dinamicamente — admin_master não precisa tê-las no DB.
 *  4. Roles operacionais (dono_fazenda etc.) perdem qualquer `platform.*`
 *     (ganhas em M8.A via users.*/fazendas.*/dashboard.view/roles.*)
 *     — tenant users são PURAMENTE operational.
 *
 * RESET:
 *  5. forgetCachedPermissions() ao final.
 *
 * ROLLBACK (down):
 *  Parcial. Recria as 143 permissions antigas VAZIAS (sem bindings em
 *  role_has_permissions). Para reversão completa funcional, fazer:
 *    php artisan migrate:rollback --step=3
 *  Isso desfaz M8.C, M8.B (código via git revert) e M8.A, restaurando
 *  o estado pré-M8 — mas a forma correta é git revert das 3 commits
 *  M8.* + seeder RoleAndPermissionSeeder para repopular.
 *
 * PRÉ-REQUISITOS (validados em M8.B):
 *  - routes/web.php: 0 permission: antigas (apenas comentários)
 *  - AdminLayout.vue: 0 perm: antigas
 *  - UserController.php: 0 can() antigas
 *  - AppServiceProvider: Gate::before ativo para impersonação
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. Remove permissions antigas ───────────────────────────
        // Critério: qualquer permission que NÃO comece com platform. ou operational.
        $legacy = Permission::where('guard_name', 'web')
            ->where('name', 'not like', 'platform.%')
            ->where('name', 'not like', 'operational.%')
            ->get();

        $legacyNames = $legacy->pluck('name')->all();

        foreach ($legacy as $perm) {
            // Spatie remove bindings via FK cascade
            $perm->delete();
        }

        // ─── 2. admin_master perde operational.* ─────────────────────
        $adminMaster = Role::where('name', 'admin_master')
            ->where('guard_name', 'web')
            ->first();

        if ($adminMaster) {
            $opPerms = $adminMaster->permissions()
                ->where('name', 'like', 'operational.%')
                ->get();

            foreach ($opPerms as $p) {
                $adminMaster->revokePermissionTo($p);
            }
        }

        // ─── 3. Roles operacionais perdem platform.* ─────────────────
        $operationalRoles = Role::where('guard_name', 'web')
            ->where('name', '!=', 'admin_master')
            ->get();

        foreach ($operationalRoles as $role) {
            $platPerms = $role->permissions()
                ->where('name', 'like', 'platform.%')
                ->get();

            foreach ($platPerms as $p) {
                $role->revokePermissionTo($p);
            }
        }

        // ─── 4. Reset cache Spatie ───────────────────────────────────
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── 5. Log informativo (aparece no migrate output) ──────────
        // (não há suporte nativo a "output" em migrations, mas o count
        //  fica acessível via \DB::table('migrations') audit se precisar)
    }

    public function down(): void
    {
        // ROLLBACK PARCIAL: recria nomes das permissions antigas (sem bindings).
        // Para reversão FUNCIONAL completa, usar git revert dos commits M8.*
        // + re-executar RoleAndPermissionSeeder.

        $legacyNames = $this->legacyPermissionNames();

        foreach ($legacyNames as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web']
            );
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Lista das 143 permissions antigas (snapshot do estado pré-M8).
     * Usada apenas em down() para reversão parcial.
     */
    private function legacyPermissionNames(): array
    {
        return [
            // agricola (20)
            'agricola.view', 'agricola.create', 'agricola.update', 'agricola.delete',
            'agricola.talhoes.view', 'agricola.talhoes.create', 'agricola.talhoes.update', 'agricola.talhoes.delete',
            'agricola.plantios.view', 'agricola.plantios.create', 'agricola.plantios.update', 'agricola.plantios.delete',
            'agricola.colheitas.view', 'agricola.colheitas.create', 'agricola.colheitas.update', 'agricola.colheitas.delete',
            'agricola.aplicacoes.view', 'agricola.aplicacoes.create', 'agricola.aplicacoes.update', 'agricola.aplicacoes.delete',
            // cms (17)
            'cms.view', 'cms.create', 'cms.update', 'cms.delete', 'cms.publish',
            'cms.pages.view', 'cms.pages.create', 'cms.pages.update', 'cms.pages.delete',
            'cms.menus.view', 'cms.menus.create', 'cms.menus.update', 'cms.menus.delete',
            'cms.settings.view', 'cms.settings.create', 'cms.settings.update', 'cms.settings.delete',
            // dashboard (4)
            'dashboard.view', 'dashboard.create', 'dashboard.update', 'dashboard.delete',
            // documentos (4)
            'documentos.view', 'documentos.create', 'documentos.update', 'documentos.delete',
            // estoque (16)
            'estoque.view', 'estoque.create', 'estoque.update', 'estoque.delete',
            'estoque.itens.view', 'estoque.itens.create', 'estoque.itens.update', 'estoque.itens.delete',
            'estoque.movimentos.view', 'estoque.movimentos.create', 'estoque.movimentos.update', 'estoque.movimentos.delete',
            'estoque.armazens.view', 'estoque.armazens.create', 'estoque.armazens.update', 'estoque.armazens.delete',
            // fazendas (4)
            'fazendas.view', 'fazendas.create', 'fazendas.update', 'fazendas.delete',
            // financeiro (21)
            'financeiro.view', 'financeiro.create', 'financeiro.update', 'financeiro.delete', 'financeiro.approve',
            'financeiro.contas.view', 'financeiro.contas.create', 'financeiro.contas.update', 'financeiro.contas.delete',
            'financeiro.transacoes.view', 'financeiro.transacoes.create', 'financeiro.transacoes.update', 'financeiro.transacoes.delete',
            'financeiro.recorrencias.view', 'financeiro.recorrencias.create', 'financeiro.recorrencias.update', 'financeiro.recorrencias.delete',
            'financeiro.relatorios.view', 'financeiro.relatorios.create', 'financeiro.relatorios.update', 'financeiro.relatorios.delete',
            // funcionarios (12)
            'funcionarios.view', 'funcionarios.create', 'funcionarios.update', 'funcionarios.delete',
            'funcionarios.cadastro.view', 'funcionarios.cadastro.create', 'funcionarios.cadastro.update', 'funcionarios.cadastro.delete',
            'funcionarios.tarefas.view', 'funcionarios.tarefas.create', 'funcionarios.tarefas.update', 'funcionarios.tarefas.delete',
            // maquinas (12)
            'maquinas.view', 'maquinas.create', 'maquinas.update', 'maquinas.delete',
            'maquinas.veiculos.view', 'maquinas.veiculos.create', 'maquinas.veiculos.update', 'maquinas.veiculos.delete',
            'maquinas.manutencoes.view', 'maquinas.manutencoes.create', 'maquinas.manutencoes.update', 'maquinas.manutencoes.delete',
            // parceiros (4)
            'parceiros.view', 'parceiros.create', 'parceiros.update', 'parceiros.delete',
            // rebanho (16)
            'rebanho.view', 'rebanho.create', 'rebanho.update', 'rebanho.delete',
            'rebanho.animais.view', 'rebanho.animais.create', 'rebanho.animais.update', 'rebanho.animais.delete',
            'rebanho.lotes.view', 'rebanho.lotes.create', 'rebanho.lotes.update', 'rebanho.lotes.delete',
            'rebanho.eventos.view', 'rebanho.eventos.create', 'rebanho.eventos.update', 'rebanho.eventos.delete',
            // relatorios (4)
            'relatorios.view', 'relatorios.create', 'relatorios.update', 'relatorios.delete',
            // roles (4)
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            // users (5)
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.reset_password',
        ];
    }
};
