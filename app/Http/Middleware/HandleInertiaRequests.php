<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Billing\PlanFeatures;
use App\Models\Farm;
use App\Models\Livestock\Animal;
use App\Models\Livestock\AnimalSpecies;
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
            // Espécies do catálogo (TODAS as ativas) pra renderizar submenu de
            // Rebanho. Mostra mesmo as espécies sem animais ainda — assim o
            // master/dono pode clicar em qualquer uma pra fazer o primeiro
            // cadastro daquela espécie. Animals_count vem como 0 quando vazio
            // (frontend mostra badge "0" como cinza pra distinguir).
            'tenantSpecies' => function () use ($request) {
                $effectiveTenantId = $this->effectiveTenantId($request);
                if ($effectiveTenantId === null) return [];
                return Cache::remember(
                    "tenant_species_with_count.{$effectiveTenantId}",
                    now()->addMinutes(10),
                    function () use ($effectiveTenantId) {
                        // withoutGlobalScopes pra não restringir species pelo tenant
                        // (catálogo é "global", animals é que tem tenant_id).
                        // SQL bruto no count pra evitar BelongsToTenantScope na
                        // relação animals que travava resultado em vazio.
                        $species = AnimalSpecies::withoutGlobalScopes()
                            ->where('is_active', true)
                            ->orderBy('nome')
                            ->get(['id', 'nome', 'slug', 'gestao', 'profile']);

                        // Animais individuais (gestao=individual)
                        $countsIndividual = \DB::table('animals')
                            ->select('species_id', \DB::raw('COUNT(*) as cnt'))
                            ->where('status', 'ativo')
                            ->where('tenant_id', $effectiveTenantId)
                            ->whereIn('species_id', $species->pluck('id'))
                            ->groupBy('species_id')
                            ->pluck('cnt', 'species_id');

                        // Cabeças em lotes agregados (gestao=lote — Ave/Peixe).
                        // CRÍTICO: filtrar por tenant_id — \DB::table() é Query Builder
                        // direto, NÃO aplica BelongsToTenantScope. Sem este where()
                        // os lotes de TODOS os tenants eram somados (vazamento de
                        // dados entre clientes — bug detectado pelo usuário).
                        $countsAgregado = \DB::table('animal_lots')
                            ->select('species_id', \DB::raw('COALESCE(SUM(quantidade_atual), 0) as cnt'))
                            ->where('is_active', true)
                            ->where('tenant_id', $effectiveTenantId)
                            ->whereIn('species_id', $species->where('gestao', 'lote')->pluck('id'))
                            ->groupBy('species_id')
                            ->pluck('cnt', 'species_id');

                        return $species->map(fn ($s) => [
                            'id' => $s->id,
                            'nome' => $s->nome,
                            'slug' => $s->slug,
                            'gestao' => $s->gestao,
                            'profile' => $s->profile,
                            // Para gestao=lote, conta SOMA de quantidade_atual dos lotes;
                            // para individual, conta animals.
                            'animals_count' => $s->gestao === 'lote'
                                ? (int) ($countsAgregado[$s->id] ?? 0)
                                : (int) ($countsIndividual[$s->id] ?? 0),
                        ])->all();
                    }
                );
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
                // Respeita farm_id atual: tenant multi-farm vê alertas só da fazenda
                // selecionada, evitando vazamento entre fazendas (bug detectado por
                // PO 2026-04-28: Filial vazia mostrava "1 conta vence hoje" da Sede).
                $farmId = $user->current_farm_id;
                $key = BillingCache::alertsKey($effectiveTenantId, $farmId);
                return Cache::remember($key, BillingCache::TTL_ALERTS, function () use ($effectiveTenantId, $farmId) {
                    $service = app(AlertsService::class);
                    return $effectiveTenantId
                        ? $service->forTenant($effectiveTenantId, $farmId)
                        : $service->forMaster();
                });
            },
            'menuBadges' => function () use ($request) {
                $user = $request->user();
                if (! $user) return [];
                $effectiveTenantId = $this->effectiveTenantId($request);
                $farmId = $user->current_farm_id;
                $key = BillingCache::menuBadgesKey($effectiveTenantId, $farmId);
                return Cache::remember($key, BillingCache::TTL_ALERTS, function () use ($effectiveTenantId, $farmId) {
                    $service = app(AlertsService::class);
                    return $effectiveTenantId
                        ? $service->menuBadgesForTenant($effectiveTenantId, $farmId)
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
