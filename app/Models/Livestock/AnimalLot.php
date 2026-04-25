<?php

namespace App\Models\Livestock;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\BelongsToFarm;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnimalLot extends Model
{
    use BelongsToTenant, BelongsToFarm;

    protected $table = 'animal_lots';

    protected $fillable = [
        'farm_id', 'codigo', 'nome', 'descricao', 'finalidade', 'is_active', 'tenant_id',
        // RN4 · gestão agregada (aves/peixes/abelhas)
        'gestao_modo', 'quantidade_inicial', 'quantidade_atual',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'quantidade_inicial' => 'decimal:2',
        'quantidade_atual' => 'decimal:2',
    ];

    /** Helper: este lote é gerido como agregado (sem 1 row por animal)? */
    public function isAgregado(): bool
    {
        return $this->gestao_modo === 'agregada';
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class, 'lot_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
