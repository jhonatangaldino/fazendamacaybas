<?php

namespace App\Models;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarcodeLookup extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'code', 'user_id', 'found_local', 'source',
        'http_status_off', 'http_status_upc',
        'nome_sugerido', 'marca_sugerida', 'nota_diagnostica', 'attempts_json',
        'tenant_id', 'farm_id',
    ];

    protected $casts = [
        'found_local' => 'boolean',
        'attempts_json' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
