<?php

namespace App\Domain\Tenancy\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * BelongsToTenantScope — global scope de leitura por tenant (R2.4).
 *
 * Aplica automaticamente `WHERE {table}.tenant_id = app('tenant_id')` em toda
 * query de models que usam o trait BelongsToTenant.
 *
 * SALVAGUARDAS (todas no método apply):
 *   1. Skip em CLI: migrations, seeders, artisan, queue workers não filtram
 *      — precisam ver dados de todos tenants (backfills, snapshots, manutenção).
 *   2. Skip quando container vazio: rotas públicas, master global, ou qualquer
 *      ponto onde ResolveTenant ainda não resolveu. Preserva comportamento
 *      atual de rotas que não deveriam ter tenant.
 *   3. Skip quando tenant_id do container é null (master global explícito).
 *
 * COLUNA QUALIFICADA:
 *   Usamos `qualifyColumn('tenant_id')` → `{table}.tenant_id`. Evita
 *   ambiguidade em JOINs (ex: animal_events.tenant_id vs animals.tenant_id).
 *
 * BYPASS EXPLÍCITO:
 *   Em casos legítimos (relatório master, auditoria cross-tenant, backfill
 *   polimórfico), usar:
 *     Model::withoutGlobalScope(BelongsToTenantScope::class)->...
 */
class BelongsToTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // 1. CLI sempre bypassa — migrations/seeders/artisan/queue não filtram
        if (app()->runningInConsole()) {
            return;
        }

        // 2. Container sem tenant_id — rotas públicas, master global, ou boot incompleto
        if (! app()->bound('tenant_id')) {
            return;
        }

        // 3. tenant_id resolvido como null (master global declarado explicitamente)
        $tenantId = app('tenant_id');
        if ($tenantId === null) {
            return;
        }

        // 4. Aplica filtro com coluna qualificada (seguro em JOINs)
        $builder->where(
            $model->qualifyColumn('tenant_id'),
            $tenantId
        );
    }
}
