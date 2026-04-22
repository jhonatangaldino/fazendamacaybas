<?php

namespace App\Models\Agricultural;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldApplication extends Model
{
    protected $fillable = [
        'field_id', 'planting_id', 'data_aplicacao', 'tipo', 'produto',
        'quantidade', 'unidade', 'valor_total', 'responsavel', 'observacoes',
    ];

    protected $casts = [
        'data_aplicacao' => 'date',
        'quantidade' => 'decimal:4',
        'valor_total' => 'decimal:2',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function planting(): BelongsTo
    {
        return $this->belongsTo(Planting::class);
    }
}
