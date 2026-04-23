<?php

namespace App\Models;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuUsage extends Model
{
    use BelongsToTenant;

    protected $table = 'menu_usage';

    protected $fillable = ['user_id', 'menu_key', 'hits', 'last_used_at', 'tenant_id'];

    protected $casts = [
        'hits' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
