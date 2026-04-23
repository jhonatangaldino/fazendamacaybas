<?php

namespace App\Models\Financial;

use App\Domain\Billing\Models\Tenant;
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

    protected $fillable = [
        'account_id', 'category_id', 'cost_center_id', 'partner_id', 'recurrence_id',
        'tipo', 'descricao', 'observacoes', 'valor', 'data_vencimento', 'data_pagamento',
        'status', 'forma_pagamento', 'numero_documento', 'created_by',
        'tenant_id', 'farm_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
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
