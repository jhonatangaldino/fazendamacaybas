<?php

namespace App\Models\Vehicle;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleEvent extends Model
{
    protected $table = 'vehicle_events';

    protected $fillable = [
        'vehicle_id', 'tipo', 'data', 'hora', 'medidor',
        'litros', 'preco_litro', 'valor_total', 'combustivel', 'posto',
        'severidade', 'titulo',
        'responsavel', 'partner_id', 'observacoes', 'created_by',
    ];

    protected $casts = [
        'data' => 'date',
        'medidor' => 'decimal:1',
        'litros' => 'decimal:3',
        'preco_litro' => 'decimal:4',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
