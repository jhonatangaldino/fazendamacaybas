<?php

namespace App\Models\Stock;

use App\Models\Financial\FinancialTransaction;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'item_id', 'warehouse_id', 'partner_id', 'tipo', 'motivo',
        'data', 'quantidade', 'valor_unitario', 'valor_total',
        'numero_documento', 'transaction_id', 'observacoes', 'created_by',
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
}
