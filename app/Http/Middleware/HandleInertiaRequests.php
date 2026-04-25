<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Billing\PlanFeatures;
use App\Models\Farm;
use App\Models\MenuUsage;
use App\Models\Setting;
use App\Services\AlertsService;
use App\Support\BillingCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Tenant efetivo para escopo de alertas/badges:
     *   • user.tenant_id se houver
     *   • OU session('impersonation.tenant_id') se master impersonando
     *   • null para master puro
     */
    private function effectiveTenantId(Request $request): ?int
    {
        $user = $request->user();
        if ($user && $user->tenant_id) {
            return (int) $user->tenant_id;
        }
        $imp = $request->session()->get('impersonation');
        if (is_array($imp) && ! empty($imp['tenant_id'])) {
            return (int) $imp['tenant_id'];
        }
        return null;
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'locale' => config('app.locale'),
                'timezone' => config('app.timezone'),
                'url' => config('app.url'),
            ],
            'auth' => [
                'user' => function () use ($request) {
                    $user = $request->user();
                    if (! $user) return null;

                    // Durante impersonação master → tenant: o master deve ver o
                    // sistema EXATAMENTE como o tenant veria, com menu completo
                    // e Hub completo. Como o user logado continua sendo master
                    // (sem role operational.*), exporamos TODAS as permissões
                    // do sistema. O auditor sabe quem está operando via
                    // ImpersonationAudit + banner âmbar persistente.
                    $isImpersonating = $user->tenant_id === null
                        && is_array($request->session()->get('impersonation'))
                        && ! empty($request->session()->get('impersonation.tenant_id'));

                    $permissions = $isImpersonating
                        ? \Spatie\Permission\Models\Permission::pluck('name')
                        : $user->getAllPermissions()->pluck('name');

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'cargo' => $user->cargo,
                        'avatar' => $user->avatarUrl(),
                        'must_change_password' => $user->must_change_password,
                        'roles' => $user->getRoleNames(),
                        'permissions' => $permissions,
                    ];
                },
            ],
            // Mapa route_name → hits_snapshot para o usuário corrente.
            // Importante: usamos o snapshot (congelado às 3h via comando menu:snapshot),
            // não o hits em tempo real — assim a ordem da sidebar NÃO muda durante o uso.
            //
            // OTIMIZAÇÃO (Hostinger 500 conn/h): snapshot só atualiza 1x/dia às 3h.
            // Cache de 1h é seguro — usuário novo aguarda até 1h pra aparecer no
            // próprio menu, mas isso vale 0 queries/hora em uso normal.
            'menuUsage' => fn () => $request->user()
                ? Cache::remember(
                    "menu_usage_user_{$request->user()->id}",
                    now()->addHour(),
                    fn () => MenuUsage::where('user_id', $request->user()->id)
                        ->pluck('hits_snapshot', 'menu_key')
                        ->toArray()
                )
                : [],
            // Agregado global também cacheado (é o mesmo para todos os usuários).
            'menuUsageGlobal' => fn () => $request->user()
                ? Cache::remember(
                    'menu_usage_global',
                    now()->addHour(),
                    fn () => MenuUsage::selectRaw('menu_key, SUM(hits_snapshot) as total')
                        ->groupBy('menu_key')
                        ->pluck('total', 'menu_key')
                        ->toArray()
                )
                : [],
            // M5 — Sessão de impersonação ativa (banner global).
            // Retorna null quando:
            //   - não há session('impersonation')
            //   - ou a session tem chave mas o tenant foi removido/inexistente
            // O banner some automaticamente nesses casos.
            'impersonation' => function () use ($request) {
                $imp = $request->session()->get('impersonation');
                if (! is_array($imp) || empty($imp['tenant_id'])) {
                    return null;
                }
                $tenant = Tenant::find($imp['tenant_id']);
                if (! $tenant) {
                    return null;
                }
                return [
                    'tenant_id' => (int) $tenant->id,
                    'tenant_nome' => $tenant->nome,
                    'started_at' => $imp['started_at'] ?? null,
                ];
            },

            // R2.6 — Contexto de fazenda ativo.
            // `currentFarm` é {id, nome} quando EnforceFarm resolveu (grupo admin autenticado).
            // `availableFarms` é listado apenas se houver >1 — consumido pelo topbar para
            // decidir se renderiza o badge/dropdown (regra UX: zero fricção com 1 fazenda).
            // Ambos usam o cache de request `tenant_farms` populado pelo EnforceFarm,
            // então não há query adicional por request.
            'currentFarm' => function () use ($request) {
                if (! app()->bound('farm_id')) {
                    return null;
                }
                $farmId = (int) app('farm_id');
                $farms = app()->bound('tenant_farms') ? app('tenant_farms') : collect();
                $farm = $farms->firstWhere('id', $farmId);
                if (! $farm) {
                    return null;
                }
                return ['id' => (int) $farm->id, 'nome' => $farm->nome];
            },
            'availableFarms' => function () use ($request) {
                if (! app()->bound('tenant_farms')) {
                    return [];
                }
                $farms = app('tenant_farms');
                if ($farms->count() <= 1) {
                    return []; // topbar oculta com 1 fazenda
                }
                return $farms->map(fn ($f) => [
                    'id' => (int) $f->id,
                    'nome' => $f->nome,
                ])->values()->all();
            },
            'settings' => fn () => [
                'logo' => Setting::getValue('site.logo'),
                'favicon' => Setting::getValue('site.favicon'),
                'nome' => Setting::getValue('site.nome', 'Fazenda Macaybas'),
                'cor_primaria' => Setting::getValue('tema.cor_primaria', '#166534'),
            ],
            // Alertas globais — agora cacheados por 5min (BillingCache::TTL_ALERTS).
            // Antes: 4-6 queries em TODA request /admin /master.
            // Depois: 0 queries no cache hit (5min). Invalida em ações relevantes
            // via BillingCache::forgetForTenant() chamado por InvoiceController etc.
            'alerts' => function () use ($request) {
                $user = $request->user();
                if (! $user) return [];
                $effectiveTenantId = $this->effectiveTenantId($request);
                $key = BillingCache::alertsKey($effectiveTenantId);
                return Cache::remember($key, BillingCache::TTL_ALERTS, function () use ($effectiveTenantId) {
                    $service = app(AlertsService::class);
                    return $effectiveTenantId
                        ? $service->forTenant($effectiveTenantId)
                        : $service->forMaster();
                });
            },
            'menuBadges' => function () use ($request) {
                $user = $request->user();
                if (! $user) return [];
                $effectiveTenantId = $this->effectiveTenantId($request);
                $key = BillingCache::menuBadgesKey($effectiveTenantId);
                return Cache::remember($key, BillingCache::TTL_ALERTS, function () use ($effectiveTenantId) {
                    $service = app(AlertsService::class);
                    return $effectiveTenantId
                        ? $service->menuBadgesForTenant($effectiveTenantId)
                        : $service->menuBadgesForMaster();
                });
            },
            // Features do plano do tenant — muda raramente (só quando master altera).
            // Cache 15min, invalida via BillingCache em SubscriptionController::update.
            'tenantFeatures' => function () use ($request) {
                $effectiveTenantId = $this->effectiveTenantId($request);
                if ($effectiveTenantId === null) return null;
                $key = BillingCache::tenantFeaturesKey($effectiveTenantId);
                return Cache::remember($key, BillingCache::TTL_FEATURES, function () use ($effectiveTenantId) {
                    $tenant = Tenant::with(['plan', 'subscription.plan'])->find($effectiveTenantId);
                    if ($tenant === null) return null;
                    $features = $tenant->planFeatures();
                    return empty($features) ? null : $features;
                });
            },
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'warning' => fn () => $request->session()->get('warning'),
                // Payload estruturado emitido por TenantController@store para
                // a tela Master/Tenants/Index renderizar o card "Página pronta
                // para uso" após criação de cliente. Um-shot via session flash.
                'created_tenant' => fn () => $request->session()->get('created_tenant'),
                // Resultado de reset de senha no Master — senha temp exibida 1 vez.
                'reset_password_result' => fn () => $request->session()->get('reset_password_result'),
                // Contexto pós-evento do rebanho — permite wizards mostrarem
                // impacto (ex.: "Este lote agora tem 12 animais") além de
                // apenas confirmar o registro. Emitido por AnimalController::storeEvent.
                'event_contexto' => fn () => $request->session()->get('event_contexto'),
                // Contexto pós-ajuste de estoque — saldo anterior/atual no armazém.
                'ajuste_contexto' => fn () => $request->session()->get('ajuste_contexto'),
                // Contexto pós lançamento financeiro — totais do mês + saldo após a ação.
                'financeiro_contexto' => fn () => $request->session()->get('financeiro_contexto'),
                // Contexto pós evento em LOTE (vacinação/medicação/observação em massa).
                'event_batch_contexto' => fn () => $request->session()->get('event_batch_contexto'),
                // Contexto pós VENDA adaptativa (modo, total, unidade, valor) — wizard exibe sucesso.
                'venda_contexto' => fn () => $request->session()->get('venda_contexto'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
