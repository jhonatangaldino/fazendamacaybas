<?php

namespace App\Models\Financial;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\BelongsToFarm;
use App\Models\Category;
use App\Models\CostCenter;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FinancialTransaction extends Model
{
    use LogsActivity, SoftDeletes;
    use BelongsToTenant, BelongsToFarm;

    protected static function booted(): void
    {
        $invalidate = fn (FinancialTransaction $m) => \App\Services\Metrics\MetricsCache::forgetForTenant((int) $m->tenant_id, 'financial');
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }

    protected $fillable = [
        'account_id', 'category_id', 'cost_center_id', 'partner_id', 'recurrence_id',
        'parent_transaction_id', 'parcela_atual', 'total_parcelas',
        'tipo', 'descricao', 'observacoes', 'valor', 'data_vencimento', 'data_pagamento',
        'status', 'forma_pagamento', 'numero_documento', 'created_by',
        'tenant_id', 'farm_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
        'parcela_atual' => 'integer',
        'total_parcelas' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tipo', 'descricao', 'valor', 'status', 'data_vencimento', 'data_pagamento'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FinancialTransactionAttachment::class, 'transaction_id');
    }

    public function parcelaPai(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_transaction_id');
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(self::class, 'parent_transaction_id');
    }

    public function isParcelado(): bool
    {
        return ! is_null($this->total_parcelas) && $this->total_parcelas > 1;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeReceitas($q)
    {
        return $q->where('tipo', 'receita');
    }

    public function scopeDespesas($q)
    {
        return $q->where('tipo', 'despesa');
    }

    public function scopePendentes($q)
    {
        return $q->where('status', 'pendente');
    }

    public function scopePagas($q)
    {
        return $q->where('status', 'pago');
    }
}
