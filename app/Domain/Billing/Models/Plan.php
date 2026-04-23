<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan — catálogo de planos SaaS.
 *
 * Planos iniciais: Essencial, Profissional, Empresarial (preços e features
 * parametrizados no seeder / UI de admin master).
 * Plano id=1 "Essencial" é semeado em R1.3 como plano do tenant zero.
 */
class Plan extends Model
{
    protected $table = 'plans';

    protected $fillable = [
        'slug',
        'nome',
        'preco_mensal',
        'max_farms',
        'max_users',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'preco_mensal' => 'decimal:2',
        'max_farms' => 'integer',
        'max_users' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /* ───── Relacionamentos ───── */

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
