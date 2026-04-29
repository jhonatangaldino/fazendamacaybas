<?php

namespace App\Models\Agricultural;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\LogsAtividade;

class Harvest extends Model
{
    use BelongsToTenant, BelongsToFarm, LogsAtividade;

    protected static function booted(): void
    {
        $invalidate = fn (Harvest $m) => \App\Services\Metrics\MetricsCache::forgetForTenant((int) $m->tenant_id, 'agricola');
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }

    protected $fillable = [
        'planting_id', 'data_colheita', 'quantidade_colhida', 'unidade',
        'produtividade_por_ha', 'valor_total', 'observacoes',
        'tenant_id', 'farm_id',
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
