<?php

namespace App\Models\Livestock;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnimalLot extends Model
{
    protected $table = 'animal_lots';

    protected $fillable = ['farm_id', 'codigo', 'nome', 'descricao', 'finalidade', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class, 'lot_id');
    }
}
