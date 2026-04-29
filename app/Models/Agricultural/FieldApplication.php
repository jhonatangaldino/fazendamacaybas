<?php

namespace App\Models\Agricultural;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\LogsAtividade;

class FieldApplication extends Model
{
    use BelongsToTenant, BelongsToFarm, LogsAtividade;

    protected static function booted(): void
    {
        $invalidate = fn (FieldApplication $m) => \App\Services\Metrics\MetricsCache::forgetForTenant((int) $m->tenant_id, 'agricola');
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }

    protected $fillable = [
        'field_id', 'planting_id', 'data_aplicacao', 'tipo', 'produto',
        'quantidade', 'unidade', 'valor_total', 'responsavel', 'observacoes',
        'tenant_id', 'farm_id',
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
