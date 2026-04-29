<?php

namespace App\Models\Agricultural;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\LogsAtividade;

class Season extends Model
{
    use BelongsToTenant, LogsAtividade;

    protected static function booted(): void
    {
        $invalidate = fn (Season $m) => \App\Services\Metrics\MetricsCache::forgetForTenant((int) $m->tenant_id, 'agricola');
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }

    protected $fillable = ['nome', 'data_inicio', 'data_fim', 'is_active', 'tenant_id'];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
