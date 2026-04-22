<?php

namespace App\Models\Livestock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimalBreed extends Model
{
    protected $table = 'animal_breeds';

    protected $fillable = ['species_id', 'nome', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function species(): BelongsTo
    {
        return $this->belongsTo(AnimalSpecies::class, 'species_id');
    }
}
