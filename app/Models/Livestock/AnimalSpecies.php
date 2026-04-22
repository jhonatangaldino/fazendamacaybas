<?php

namespace App\Models\Livestock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnimalSpecies extends Model
{
    protected $table = 'animal_species';

    protected $fillable = ['nome', 'slug', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function breeds(): HasMany
    {
        return $this->hasMany(AnimalBreed::class, 'species_id');
    }

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class, 'species_id');
    }
}
