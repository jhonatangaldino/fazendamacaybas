<?php

namespace App\Models\Financial;

use App\Domain\Billing\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialRecurrence extends Model
{
    protected $fillable = [
        'account_id', 'category_id', 'partner_id', 'tipo', 'descricao', 'valor',
        'periodicidade', 'dia_vencimento', 'data_inicio', 'data_fim', 'parcelas_geradas', 'is_active',
        'tenant_id', 'farm_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'recurrence_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
