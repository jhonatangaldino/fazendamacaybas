<?php

namespace App\Models\Task;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['checklist_id', 'descricao', 'is_done', 'done_at', 'done_by', 'order_column', 'tenant_id'];

    protected $casts = [
        'is_done' => 'boolean',
        'done_at' => 'datetime',
        'order_column' => 'integer',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
