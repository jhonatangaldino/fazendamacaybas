<?php

namespace App\Models\Financial;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\LogsAtividade;

class FinancialAccount extends Model
{
    use BelongsToTenant, BelongsToFarm, LogsAtividade;

    protected $fillable = [
        'nome', 'tipo', 'banco', 'agencia', 'conta',
        'saldo_inicial', 'saldo_atual', 'is_active', 'observacoes',
        'tenant_id', 'farm_id',
    ];

    protected $casts = [
        'saldo_inicial' => 'decimal:2',
        'saldo_atual' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'account_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
