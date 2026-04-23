<?php

namespace App\Models\Livestock;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimalBreed extends Model
{
    use BelongsToTenant;

    protected $table = 'animal_breeds';

    protected $fillable = ['species_id', 'nome', 'is_active', 'tenant_id'];

    protected $casts = ['is_active' => 'boolean'];

    public function species(): BelongsTo
    {
        return $this->belongsTo(AnimalSpecies::class, 'species_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
