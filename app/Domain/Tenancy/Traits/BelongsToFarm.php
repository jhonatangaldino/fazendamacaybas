<?php

namespace App\Domain\Tenancy\Traits;

use App\Domain\Tenancy\Scopes\BelongsToFarmScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait BelongsToFarm — isolamento operacional por fazenda.
 *
 * Pareia com BelongsToTenant. Quando aplicado em um model com coluna `farm_id`:
 *
 *   • LEITURA: aplica BelongsToFarmScope automaticamente — toda query é
 *     filtrada por `farm_id = app('farm_id')` (além do filtro de tenant).
 *
 *   • CREATING: força `farm_id` ao valor do container — protege contra
 *     mass-assignment cross-farm via request body.
 *
 *   • UPDATING: bloqueia mudança de `farm_id` (reverte para o valor original).
 *     Defesa em profundidade contra reassociação acidental ou maliciosa.
 *
 * QUANDO NÃO INTERVÉM:
 *   • CLI (migrations/seeders precisam ver tudo)
 *   • Container sem `farm_id` (master, rotas públicas, sem contexto)
 *
 * USO:
 *   class Animal extends Model {
 *       use BelongsToTenant, BelongsToFarm;
 *   }
 *
 * REGRA DE PROJETO:
 *   Aplicar APENAS em models que representam dados operacionais de uma
 *   fazenda específica (animais, estoque, talhões, etc.). NÃO aplicar em:
 *   - Catálogos do tenant (raças, espécies, categorias)
 *   - Parceiros (tenant inteiro compartilha fornecedores)
 *   - Usuários (alocação por fazenda fica em tabela pivot, não no User)
 */
trait BelongsToFarm
{
    public static function bootBelongsToFarm(): void
    {
        static::addGlobalScope(new BelongsToFarmScope());

        static::creating(function (Model $model) {
            static::enforceFarmOnCreating($model);
        });

        static::updating(function (Model $model) {
            static::enforceFarmOnUpdating($model);
        });
    }

    /**
     * Força farm_id ao valor do container no creating, se ainda não foi setado
     * ou se foi setado para outro valor (defesa contra mass-assignment).
     */
    protected static function enforceFarmOnCreating(Model $model): void
    {
        if (! app()->bound('farm_id')) {
            return;
        }
        $containerFarm = app('farm_id');
        if ($containerFarm === null) {
            return;
        }
        $model->farm_id = (int) $containerFarm;
    }

    /**
     * Bloqueia mudança de farm_id em update — restaura ao valor do container.
     * Update normal (sem mexer em farm_id) passa direto sem overhead.
     */
    protected static function enforceFarmOnUpdating(Model $model): void
    {
        if (! app()->bound('farm_id')) {
            return;
        }
        $containerFarm = app('farm_id');
        if ($containerFarm === null) {
            return;
        }
        if (! $model->isDirty('farm_id')) {
            return;
        }
        $model->farm_id = (int) $containerFarm;
    }
}
