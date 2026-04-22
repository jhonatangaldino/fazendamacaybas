<?php

namespace App\Models\Agricultural;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Harvest extends Model
{
    protected $fillable = [
        'planting_id', 'data_colheita', 'quantidade_colhida', 'unidade',
        'produtividade_por_ha', 'valor_total', 'observacoes',
    ];

    protected $casts = [
        'data_colheita' => 'date',
        'quantidade_colhida' => 'decimal:4',
        'produtividade_por_ha' => 'decimal:4',
        'valor_total' => 'decimal:2',
    ];

    public function planting(): BelongsTo
    {
        return $this->belongsTo(Planting::class);
    }
}
