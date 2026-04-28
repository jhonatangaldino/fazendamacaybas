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
        // 2026-04-28 · vinculação direta a species (cadastro de lote sem Animal)
        'species_id',
        // RN4 · gestão agregada (aves/peixes/abelhas)
        'gestao_modo', 'quantidade_inicial', 'quantidade_atual',
        // Auditoria 2026-04-27 · campos do lote agregado (massa)
        'peso_medio_kg', 'data_inicio', 'data_fim',
        'partner_id_aquisicao', 'valor_aquisicao', 'custo_unitario',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'quantidade_inicial' => 'decimal:2',
        'quantidade_atual' => 'decimal:2',
        'peso_medio_kg' => 'decimal:2',
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'valor_aquisicao' => 'decimal:2',
        'custo_unitario' => 'decimal:4',
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

    public function species(): BelongsTo
    {
        return $this->belongsTo(AnimalSpecies::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
