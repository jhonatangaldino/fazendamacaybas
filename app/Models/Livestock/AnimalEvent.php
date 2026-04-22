<?php

namespace App\Models\Livestock;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimalEvent extends Model
{
    protected $table = 'animal_events';

    protected $fillable = [
        'animal_id', 'lot_id', 'tipo', 'data', 'peso', 'vacina', 'medicamento',
        'dose', 'via_aplicacao', 'responsavel', 'valor', 'partner_id',
        'lot_origem_id', 'lot_destino_id', 'observacoes', 'created_by',
    ];

    protected $casts = [
        'data' => 'date',
        'peso' => 'decimal:2',
        'dose' => 'decimal:3',
        'valor' => 'decimal:2',
    ];

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(AnimalLot::class, 'lot_id');
    }

    public function lotOrigem(): BelongsTo
    {
        return $this->belongsTo(AnimalLot::class, 'lot_origem_id');
    }

    public function lotDestino(): BelongsTo
    {
        return $this->belongsTo(AnimalLot::class, 'lot_destino_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
