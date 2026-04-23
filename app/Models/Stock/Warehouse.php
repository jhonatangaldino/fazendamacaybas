<?php

namespace App\Models\Stock;

use App\Domain\Billing\Models\Tenant;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    protected $fillable = ['farm_id', 'nome', 'localizacao', 'responsavel', 'is_active', 'tenant_id'];

    protected $casts = ['is_active' => 'boolean'];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
