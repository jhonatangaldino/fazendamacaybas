<?php

namespace App\Models\Stock;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\BelongsToFarm;
use App\Models\Financial\FinancialTransaction;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToTenant, BelongsToFarm;

    protected static function booted(): void
    {
        $invalidate = fn (StockMovement $m) => \App\Services\Metrics\MetricsCache::forgetForTenant((int) $m->tenant_id, 'estoque');
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }

    protected $fillable = [
        'item_id', 'warehouse_id', 'partner_id', 'tipo', 'motivo',
        'data', 'quantidade', 'valor_unitario', 'valor_total',
        'numero_documento', 'transaction_id', 'observacoes', 'created_by',
        'tenant_id', 'farm_id',
    ];

    protected $casts = [
        'data' => 'date',
        'quantidade' => 'decimal:4',
        'valor_unitario' => 'decimal:4',
        'valor_total' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
