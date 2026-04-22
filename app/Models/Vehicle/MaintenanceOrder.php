<?php

namespace App\Models\Vehicle;

use App\Models\Financial\FinancialTransaction;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceOrder extends Model
{
    protected $fillable = [
        'vehicle_id', 'partner_id', 'tipo', 'descricao', 'data_prevista', 'data_realizada',
        'medidor', 'valor_pecas', 'valor_servico', 'valor_total', 'status',
        'transaction_id', 'observacoes', 'created_by',
    ];

    protected $casts = [
        'data_prevista' => 'date',
        'data_realizada' => 'date',
        'medidor' => 'decimal:2',
        'valor_pecas' => 'decimal:2',
        'valor_servico' => 'decimal:2',
        'valor_total' => 'decimal:2',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
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
