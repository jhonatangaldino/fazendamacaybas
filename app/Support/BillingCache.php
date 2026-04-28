<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * BillingCache — chaves de cache + invalidação centralizada.
 *
 * Por quê: o plano Hostinger limita 500 conexões MySQL/hora. Em request
 * autenticada, antes do cache havia 6+ queries SÓ no Inertia share (alerts,
 * menuBadges, tenantFeatures, permissions, settings...). Multiplicado por
 * navegação típica (~30 reqs/min × 60min = 1800 reqs/h × 6 queries = 10800
 * queries/h só no share, sem contar controllers). Throttle inevitável.
 *
 * Aqui: todos os get/forget centralizados. Quando uma ação relevante muda
 * (criar invoice, marcar paga, mudar plano, criar tarefa), chama-se um único
 * `BillingCache::forgetForTenant($tid)` que limpa TUDO daquele tenant.
 *
 * TTLs:
 *   alerts/menuBadges → 5min   (precisa refletir ações relativamente rápido)
 *   tenantFeatures    → 15min  (muda só quando master altera plano)
 *   master_kpis       → 5min   (visão SaaS, agregada)
 *   tenant_dashboard  → 5min   (já existia em 90s; subimos para 300s)
 */
class BillingCache
{
    public const TTL_ALERTS         = 300;   // 5 min
    public const TTL_FEATURES       = 900;   // 15 min
    public const TTL_MASTER_KPIS    = 300;   // 5 min
    public const TTL_TENANT_DASH    = 300;   // 5 min (tenant dashboard subiu de 90s)

    public static function alertsKey(?int $tenantId, ?int $farmId = null): string
    {
        // Inclui farm_id na chave: tenants multi-farm precisam de cache
        // separado por fazenda (badges/alertas filtram por farm).
        $farm = $farmId ? '.f' . $farmId : '';
        return 'bcache.alerts.t' . ($tenantId ?? 'master') . $farm . ':' . today()->toDateString();
    }

    public static function menuBadgesKey(?int $tenantId, ?int $farmId = null): string
    {
        $farm = $farmId ? '.f' . $farmId : '';
        return 'bcache.badges.t' . ($tenantId ?? 'master') . $farm . ':' . today()->toDateString();
    }

    public static function tenantFeaturesKey(int $tenantId): string
    {
        return 'bcache.features.t' . $tenantId;
    }

    public static function masterKpisKey(): string
    {
        return 'bcache.master_kpis:' . today()->toDateString();
    }

    public static function tenantDashboardKey(int $tenantId, ?int $farmId, ?int $userId): string
    {
        return 'bcache.dashboard.t' . $tenantId . '.f' . ($farmId ?? 0) . '.u' . ($userId ?? 0);
    }

    /**
     * Limpa TUDO que pode ter mudado por uma ação no tenant.
     *   • Invoice criada/paga/revertida → tenant alerts, master alerts/kpis
     *   • Subscription atualizada (plano)→ tenantFeatures, alerts
     *   • Tarefa criada/concluída       → tenant alerts/badges/dashboard
     *
     * Idempotente. Custo desprezível (forget em cache file = unlink).
     */
    public static function forgetForTenant(int $tenantId): void
    {
        Cache::forget(self::alertsKey($tenantId));
        Cache::forget(self::menuBadgesKey($tenantId));
        Cache::forget(self::tenantFeaturesKey($tenantId));
        Cache::forget(self::masterKpisKey());
        Cache::forget(self::alertsKey(null));        // master
        Cache::forget(self::menuBadgesKey(null));    // master
        // Dashboard tenant é por (tenant, farm, user). Sem registry de farm/user
        // ativos, deixamos o TTL natural (5min). Para refresh imediato pós-ação,
        // a query string `?refresh=1` continua válida.
    }

    /** Limpa apenas alerts/badges/kpis do master (ações puramente plataforma). */
    public static function forgetMaster(): void
    {
        Cache::forget(self::alertsKey(null));
        Cache::forget(self::menuBadgesKey(null));
        Cache::forget(self::masterKpisKey());
    }
}
