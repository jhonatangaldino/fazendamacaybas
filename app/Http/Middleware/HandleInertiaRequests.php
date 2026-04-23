<?php

namespace App\Http\Middleware;

use App\Models\Farm;
use App\Models\MenuUsage;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
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
                'user' => fn () => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'cargo' => $request->user()->cargo,
                    'avatar' => $request->user()->avatarUrl(),
                    'must_change_password' => $request->user()->must_change_password,
                    'roles' => $request->user()->getRoleNames(),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                ] : null,
            ],
            // Mapa route_name → hits_snapshot para o usuário corrente.
            // Importante: usamos o snapshot (congelado às 3h via comando menu:snapshot),
            // não o hits em tempo real — assim a ordem da sidebar NÃO muda durante o uso.
            'menuUsage' => fn () => $request->user()
                ? MenuUsage::where('user_id', $request->user()->id)
                    ->pluck('hits_snapshot', 'menu_key')
                    ->toArray()
                : [],
            // Agregado global (também do snapshot) — fallback para usuários novos sem histórico pessoal.
            'menuUsageGlobal' => fn () => $request->user()
                ? MenuUsage::selectRaw('menu_key, SUM(hits_snapshot) as total')
                    ->groupBy('menu_key')
                    ->pluck('total', 'menu_key')
                    ->toArray()
                : [],
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
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
