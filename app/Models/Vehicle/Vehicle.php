<?php

namespace App\Models\Vehicle;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'farm_id', 'tipo', 'nome', 'marca', 'modelo', 'ano_fabricacao', 'ano_modelo',
        'placa', 'renavam', 'chassi', 'cor', 'combustivel',
        'medidor', 'medidor_atual', 'valor_aquisicao', 'data_aquisicao',
        'is_active', 'observacoes',
    ];

    protected $casts = [
        'medidor_atual' => 'decimal:2',
        'valor_aquisicao' => 'decimal:2',
        'data_aquisicao' => 'date',
        'is_active' => 'boolean',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class);
    }
}
