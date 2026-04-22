<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    protected $fillable = [
        'nome', 'tipo', 'banco', 'agencia', 'conta',
        'saldo_inicial', 'saldo_atual', 'is_active', 'observacoes',
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
}
