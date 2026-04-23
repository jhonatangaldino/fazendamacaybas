<?php

namespace App\Models\Agricultural;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Field extends Model
{
    use SoftDeletes;
    use BelongsToTenant;

    protected $fillable = [
        'farm_id', 'codigo', 'nome', 'area_ha', 'tipo_solo', 'descricao',
        'localizacao', 'latitude', 'longitude', 'is_active',
        'tenant_id',
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
