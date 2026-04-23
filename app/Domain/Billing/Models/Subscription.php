<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Subscription — assinatura ativa de um tenant.
 *
 * Relação 1-para-1 com tenant (garantido por UNIQUE em subscriptions.tenant_id).
 * Histórico de mudanças de plano fica em `meta` (JSON).
 * O tenant id=1 Macaybas tem subscription vitalícia (current_period_end = NULL)
 * semeada em R1.3.
 */
class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'started_at',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'meta',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'meta' => 'array',
    ];

    /* ───── Relacionamentos ───── */

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
