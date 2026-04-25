<?php

namespace App\Domain\Tenancy\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * BelongsToFarmScope — global scope de leitura por fazenda.
 *
 * Aplica automaticamente `WHERE {table}.farm_id = app('farm_id')` em toda
 * query de models que usam o trait BelongsToFarm.
 *
 * Pareia com BelongsToTenantScope: o tenant continua filtrando primeiro,
 * a farm filtra DENTRO do tenant. Resultado em queries operacionais:
 *   WHERE tenant_id = X AND farm_id = Y
 *
 * SALVAGUARDAS:
 *   1. CLI bypassa (migrations, seeders, queue workers).
 *   2. Container sem `farm_id` bypassa (master global, rotas públicas,
 *      bootstrap incompleto). Master nunca tem `farm_id` bindado a menos
 *      que esteja impersonando — neste caso o EnforceFarm já resolve.
 *   3. `farm_id = null` no container bypassa (defesa adicional).
 *
 * BYPASS EXPLÍCITO:
 *   Para relatórios consolidados que precisam ver todas as fazendas do tenant:
 *     Model::withoutGlobalScope(BelongsToFarmScope::class)->...
 *   Tenant scope continua aplicado, então segurança cross-tenant não vaza.
 */
class BelongsToFarmScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole()) {
            return;
        }
        if (! app()->bound('farm_id')) {
            return;
        }
        $farmId = app('farm_id');
        if ($farmId === null) {
            return;
        }
        $builder->where(
            $model->qualifyColumn('farm_id'),
            $farmId
        );
    }
}
