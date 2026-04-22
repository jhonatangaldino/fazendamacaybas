<?php

namespace App\Models\Agricultural;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Field extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'farm_id', 'codigo', 'nome', 'area_ha', 'tipo_solo', 'descricao',
        'localizacao', 'latitude', 'longitude', 'is_active',
    ];

    protected $casts = [
        'area_ha' => 'decimal:4',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_active' => 'boolean',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function plantings(): HasMany
    {
        return $this->hasMany(Planting::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(FieldApplication::class);
    }
}
