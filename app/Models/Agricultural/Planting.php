<?php

namespace App\Models\Agricultural;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Planting extends Model
{
    use SoftDeletes;
    use BelongsToTenant;

    protected $fillable = [
        'field_id', 'crop_id', 'season_id', 'data_plantio', 'previsao_colheita',
        'area_plantada_ha', 'custo_previsto', 'custo_real', 'status', 'observacoes',
        'tenant_id', 'farm_id',
    ];

    protected $casts = [
        'data_plantio' => 'date',
        'previsao_colheita' => 'date',
        'area_plantada_ha' => 'decimal:4',
        'custo_previsto' => 'decimal:2',
        'custo_real' => 'decimal:2',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function harvests(): HasMany
    {
        return $this->hasMany(Harvest::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
