<?php

namespace App\Domain\Billing\Models;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tenant — conta cliente do SaaS Macaybas ERP.
 *
 * Cada tenant representa uma empresa/pessoa que contrata o sistema.
 * Pode ter 1 ou N fazendas, 1 subscription ativa e N invoices no histórico.
 * O tenant id=1 é a Fazenda Macaybas (semeado em R1.3).
 *
 * NOTA R1.4: este model é apenas preparação estrutural. Nenhum scope global
 * ou trait de tenancy está aplicado ainda — isso fica para R1.5/R2.
 */
class Tenant extends Model
{
    protected $table = 'tenants';

    protected $fillable = [
        'nome',
        'slug',
        'documento',
        'email',
        'telefone',
        'plan_id',
        'status',
        'trial_ends_at',
        'is_active',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /* ───── Relacionamentos ───── */

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function farms(): HasMany
    {
        return $this->hasMany(Farm::class);
    }
}
