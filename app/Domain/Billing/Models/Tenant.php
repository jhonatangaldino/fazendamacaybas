<?php

namespace App\Domain\Billing\Models;

use App\Models\Cms\Menu as CmsMenu;
use App\Models\Cms\Page as CmsPage;
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

    protected static function booted(): void
    {
        // Tenant ativação/criação afeta KPIs master (count de tenants ativos etc).
        $invalidate = fn (Tenant $m) => \App\Services\Metrics\MetricsCache::forgetMaster();
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }

    protected $fillable = [
        'nome',
        'slug',
        'documento',
        'email',
        'telefone',
        'cidade',
        'estado',
        'endereco',
        'latitude',
        'longitude',
        'plan_id',
        'status',
        'trial_ends_at',
        'is_active',
        'is_master_tenant',
        'domains',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'is_active' => 'boolean',
        'is_master_tenant' => 'boolean',
        'domains' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
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

    /* ───── CMS por cliente (CMS.A) ───── */

    public function pages(): HasMany
    {
        return $this->hasMany(CmsPage::class, 'tenant_id');
    }

    public function menus(): HasMany
    {
        return $this->hasMany(CmsMenu::class, 'tenant_id');
    }

    /* ───── Multi-host (reestruturação 2026-04-27) ───── */

    /**
     * Tenant marcado como "master" — único na base. A landing pública do
     * domínio raiz (fazendamacaybas.com.br) renderiza o CMS desse tenant.
     *
     * Usar via Tenant::master() para obter a instância. Cache estático
     * para evitar query repetida no mesmo request.
     */
    private static ?self $masterCache = null;

    public static function master(): ?self
    {
        if (self::$masterCache !== null) return self::$masterCache;
        self::$masterCache = static::query()->where('is_master_tenant', true)->first();
        return self::$masterCache;
    }

    /**
     * Reset do cache (útil entre testes ou após mudança via Artisan).
     */
    public static function clearMasterCache(): void
    {
        self::$masterCache = null;
    }

    /**
     * Scope: tenants com domínio próprio cadastrado.
     */
    public function scopeWithDomains($query)
    {
        return $query->whereNotNull('domains')->where('domains', '!=', '[]');
    }

    /**
     * Verifica se este tenant aceita requests de um host específico.
     * Compara case-insensitive e ignora "www." se aplicável.
     */
    public function acceptsHost(string $host): bool
    {
        $domains = $this->domains ?? [];
        if (empty($domains)) return false;
        $host = strtolower(trim($host));
        $hostNoWww = preg_replace('/^www\./', '', $host);
        foreach ($domains as $d) {
            $d = strtolower(trim($d));
            if ($d === $host || $d === $hostNoWww) return true;
            if (preg_replace('/^www\./', '', $d) === $hostNoWww) return true;
        }
        return false;
    }

    /**
     * Encontra um tenant que aceite o host dado (por domínio próprio).
     * Retorna null se nenhum tenant tem esse host nas suas listas.
     */
    public static function findByDomain(string $host): ?self
    {
        $host = strtolower(trim($host));
        // MySQL JSON_CONTAINS é razoavelmente rápido com índice em column
        // mas a base atualmente é pequena; query sequencial é suficiente.
        return static::withDomains()->get()->first(fn ($t) => $t->acceptsHost($host));
    }

    /* ───── Features comerciais (catálogo PlanFeatures) ───── */

    /**
     * Retorna as feature keys habilitadas pelo plano vigente do tenant.
     * Prioriza subscription.plan (sempre mais atualizado que tenant.plan_id).
     *
     * Compatibilidade com tenants legados (sem features no plano):
     *   plan.features NULL ou [] → trata como "TUDO liberado" (não quebra
     *   nada que já existia). Para restringir, master deve preencher.
     */
    public function planFeatures(): array
    {
        $plan = $this->subscription?->plan ?? $this->plan;
        if ($plan === null) return []; // sem plano → sem features
        $features = $plan->features;
        if (! is_array($features)) return [];
        return \App\Domain\Billing\PlanFeatures::sanitize($features);
    }

    /**
     * Verifica se o tenant tem acesso à feature dada.
     *
     * Regra:
     *   - Plano sem `features` definido (NULL/[]) → ASSUME TUDO liberado.
     *     Isso preserva backward-compat para planos antigos sem catálogo.
     *   - Plano com `features` array → exige que `key` esteja na lista.
     */
    public function hasFeature(string $key): bool
    {
        $plan = $this->subscription?->plan ?? $this->plan;
        if ($plan === null) return true;
        $features = $plan->features;
        // Plano sem catálogo → libera (legado)
        if (! is_array($features) || empty($features)) return true;
        return in_array($key, $features, true);
    }
}
