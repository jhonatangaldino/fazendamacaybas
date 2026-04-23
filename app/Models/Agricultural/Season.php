<?php

namespace App\Models\Agricultural;

use App\Domain\Billing\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Season extends Model
{
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
