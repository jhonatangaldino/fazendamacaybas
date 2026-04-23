<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * M8.A — Criação aditiva de permissions platform.* e operational.*.
 *
 * ESTRATÉGIA:
 *   - 100% ADITIVA: nenhuma permission antiga é removida
 *   - Cria as novas permissions (platform.* e operational.*)
 *   - Popula roles existentes aditivamente com as novas equivalentes
 *   - Sistema continua funcional com as antigas (nenhum middleware consultado
 *     mudou — isso fica para M8.B)
 *
 * MAPEAMENTO:
 *   - 10 famílias operacionais (agricola, documentos, estoque, financeiro,
 *     funcionarios, maquinas, parceiros, rebanho, relatorios, dashboard):
 *     mirror 1:1 com prefixo `operational.` (preserva granularidade existente)
 *   - cms.* → consolida em platform.cms.{view,manage,publish}
 *   - fazendas.* → platform.farms.{view,manage}
 *   - roles.* → platform.roles.{view,manage}
 *   - users.* → DOBRA em platform.users.* (master) + operational.usuarios.* (tenant)
 *   - dashboard.view → DOBRA em platform + operational
 *   - Novas puras (9): platform.tenants/plans/billing/impersonation/settings —
 *     não espelham nada, vão direto para admin_master
 *
 * IDEMPOTÊNCIA:
 *   - Permission::firstOrCreate: nunca duplica
 *   - Role::hasPermissionTo check antes de givePermissionTo: nunca duplica bind
 *   - forgetCachedPermissions() ao final: limpa cache do Spatie
 *
 * ROLLBACK (down):
 *   - Deleta todas permissions com prefixo platform.* ou operational.*
 *   - Spatie remove bindings em role_has_permissions via FK cascade
 *   - Permissions antigas permanecem intocadas (como sempre estiveram)
 */
return new class extends Migration
{
    /** Famílias que viram operational.X.* (mirror 1:1 com prefixo) */
    private array $operationalFamilies = [
        'agricola', 'documentos', 'estoque', 'financeiro', 'funcionarios',
        'maquinas', 'parceiros', 'rebanho', 'relatorios',
    ];

    /** Permissions novas de platform sem espelho antigo (9) */
    private array $platformNewPure = [
        'platform.tenants.view',
        'platform.tenants.manage',
        'platform.plans.view',
        'platform.plans.manage',
        'platform.billing.view',
        'platform.billing.manage',
        'platform.impersonation.start',
        'platform.impersonation.stop',
        'platform.settings.manage',
    ];

    public function up(): void
    {
        // ─── 1. Cria todas as novas permissions (idempotente) ────────
        $allNew = $this->collectAllNewPermissionNames();
        foreach ($allNew as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // ─── 2. Popula roles existentes aditivamente ─────────────────
        $mapping = $this->buildMapping();
        foreach (Role::where('guard_name', 'web')->with('permissions')->get() as $role) {
            $this->addNewPermissionsToRole($role, $mapping);
        }

        // ─── 3. admin_master recebe as 9 novas puras de platform ─────
        $adminMaster = Role::where('name', 'admin_master')
            ->where('guard_name', 'web')
            ->first();

        if ($adminMaster) {
            foreach ($this->platformNewPure as $name) {
                $perm = Permission::where('name', $name)
                    ->where('guard_name', 'web')
                    ->first();
                if ($perm && ! $adminMaster->hasPermissionTo($perm)) {
                    $adminMaster->givePermissionTo($perm);
                }
            }
        }

        // ─── 4. Reset cache Spatie ───────────────────────────────────
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Remove todas novas permissions (bindings caem via FK cascade)
        Permission::where('guard_name', 'web')
            ->where(function ($q) {
                $q->where('name', 'like', 'platform.%')
                  ->orWhere('name', 'like', 'operational.%');
            })
            ->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Constrói a lista completa de novas permissions a criar.
     */
    private function collectAllNewPermissionNames(): array
    {
        $names = [];

        // Platform — catálogo fixo (21 permissions)
        $platformFixed = [
            'platform.dashboard.view',
            'platform.cms.view',
            'platform.cms.manage',
            'platform.cms.publish',
            'platform.farms.view',
            'platform.farms.manage',
            'platform.roles.view',
            'platform.roles.manage',
            'platform.users.view',
            'platform.users.manage',
            'platform.users.reset_password',
        ];
        $names = array_merge($names, $platformFixed, $this->platformNewPure);

        // Operational — mirror 1:1 das antigas das 9 famílias
        foreach (Permission::where('guard_name', 'web')->pluck('name') as $old) {
            $family = explode('.', $old)[0];
            if (in_array($family, $this->operationalFamilies, true)) {
                $names[] = 'operational.' . $old;
            }
        }

        // Operational — dashboard (espelho parcial)
        $names[] = 'operational.dashboard.view';
        $names[] = 'operational.dashboard.create';
        $names[] = 'operational.dashboard.update';
        $names[] = 'operational.dashboard.delete';

        // Operational — usuarios (espelho de users.* em pt-BR)
        $names[] = 'operational.usuarios.view';
        $names[] = 'operational.usuarios.create';
        $names[] = 'operational.usuarios.update';
        $names[] = 'operational.usuarios.delete';
        $names[] = 'operational.usuarios.reset_password';

        return array_values(array_unique($names));
    }

    /**
     * Mapa antigo → [novas]. Uma permission antiga pode gerar 1 ou 2 novas.
     */
    private function buildMapping(): array
    {
        $mapping = [];

        // Operational families: mirror 1:1 com prefixo
        foreach (Permission::where('guard_name', 'web')->pluck('name') as $old) {
            $family = explode('.', $old)[0];
            if (in_array($family, $this->operationalFamilies, true)) {
                $mapping[$old] = ['operational.' . $old];
            }
        }

        // Dashboard ambíguo
        $mapping['dashboard.view']   = ['platform.dashboard.view', 'operational.dashboard.view'];
        $mapping['dashboard.create'] = ['operational.dashboard.create'];
        $mapping['dashboard.update'] = ['operational.dashboard.update'];
        $mapping['dashboard.delete'] = ['operational.dashboard.delete'];

        // Users ambíguo — dobra
        $mapping['users.view']           = ['platform.users.view', 'operational.usuarios.view'];
        $mapping['users.create']         = ['platform.users.manage', 'operational.usuarios.create'];
        $mapping['users.update']         = ['platform.users.manage', 'operational.usuarios.update'];
        $mapping['users.delete']         = ['platform.users.manage', 'operational.usuarios.delete'];
        $mapping['users.reset_password'] = ['platform.users.reset_password', 'operational.usuarios.reset_password'];

        // CMS — consolida em platform.cms.{view,manage,publish}
        foreach (Permission::where('name', 'like', 'cms.%')
            ->where('guard_name', 'web')->pluck('name') as $old) {

            if ($old === 'cms.publish') {
                $mapping[$old] = ['platform.cms.publish'];
            } elseif ($old === 'cms.view' || str_ends_with($old, '.view')) {
                $mapping[$old] = ['platform.cms.view'];
            } else {
                // create/update/delete em qualquer sub-área consolida em manage
                $mapping[$old] = ['platform.cms.manage'];
            }
        }

        // Fazendas → platform.farms.{view,manage}
        $mapping['fazendas.view']   = ['platform.farms.view'];
        $mapping['fazendas.create'] = ['platform.farms.manage'];
        $mapping['fazendas.update'] = ['platform.farms.manage'];
        $mapping['fazendas.delete'] = ['platform.farms.manage'];

        // Roles → platform.roles.{view,manage}
        $mapping['roles.view']   = ['platform.roles.view'];
        $mapping['roles.create'] = ['platform.roles.manage'];
        $mapping['roles.update'] = ['platform.roles.manage'];
        $mapping['roles.delete'] = ['platform.roles.manage'];

        return $mapping;
    }

    /**
     * Adiciona ao role todas as novas permissions correspondentes às antigas
     * que ele já possui. Idempotente via hasPermissionTo().
     */
    private function addNewPermissionsToRole(Role $role, array $mapping): void
    {
        $toGive = [];

        foreach ($role->permissions as $oldPerm) {
            $mapped = $mapping[$oldPerm->name] ?? [];
            foreach ($mapped as $newName) {
                $toGive[] = $newName;
            }
        }

        foreach (array_unique($toGive) as $newName) {
            $newPerm = Permission::where('name', $newName)
                ->where('guard_name', 'web')
                ->first();

            if ($newPerm && ! $role->hasPermissionTo($newPerm)) {
                $role->givePermissionTo($newPerm);
            }
        }
    }
};
