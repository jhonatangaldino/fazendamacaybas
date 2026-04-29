<?php

namespace App\Models\Livestock;

use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\BelongsToFarm;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\LogsAtividade;

/**
 * Localização física onde um animal se encontra.
 *
 * É o **PASTO, piquete, curral, baia, tanque ou galpão** — lugar físico,
 * não grupo lógico. Distinto de AnimalLot (que representa agrupamento).
 *
 * Exemplos reais:
 *   - "Pasto das palmeiras" (tipo=pasto, 20 ha)
 *   - "Piquete Norte" (tipo=piquete, 3 ha)
 *   - "Curral de manejo" (tipo=curral)
 *   - "Tanque A" (tipo=tanque, piscicultura)
 *
 * Um mesmo lote pode passar por várias localizações (rotação);
 * uma mesma localização pode receber animais de vários lotes.
 */
class AnimalLocation extends Model
{
    use BelongsToTenant, BelongsToFarm, LogsAtividade;

    protected $table = 'animal_locations';

    protected $fillable = [
        'tenant_id', 'farm_id',
        'codigo', 'nome', 'tipo',
        'area_ha', 'capacidade_ua',
        'observacoes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'area_ha' => 'decimal:2',
        'capacidade_ua' => 'decimal:2',
    ];

    /** Tipos válidos (espelho do enum da UI). */
    public const TIPOS = [
        'pasto' => 'Pasto',
        'piquete' => 'Piquete',
        'curral' => 'Curral',
        'baia' => 'Baia',
        'tanque' => 'Tanque',
        'galpao' => 'Galpão',
        'outro' => 'Outro',
    ];

    public function scopeAtivos($q)
    {
        return $q->where('is_active', true);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class, 'location_id');
    }
}
