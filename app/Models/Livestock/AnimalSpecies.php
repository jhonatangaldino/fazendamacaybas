<?php

namespace App\Models\Livestock;

use App\Domain\Billing\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnimalSpecies extends Model
{
    protected $table = 'animal_species';

    protected $fillable = ['nome', 'slug', 'is_active', 'gestao', 'profile', 'allowed_events', 'tenant_id'];

    protected $casts = [
        'is_active' => 'boolean',
        'allowed_events' => 'array',
    ];

    public function breeds(): HasMany
    {
        return $this->hasMany(AnimalBreed::class, 'species_id');
    }

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class, 'species_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
