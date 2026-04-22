<?php

namespace App\Http\Middleware;

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
            // Mapa route_name → hits para o usuário corrente; usado pela sidebar para
            // ordenar dinamicamente os itens da seção "Operação" (mais usados no topo).
            'menuUsage' => fn () => $request->user()
                ? MenuUsage::where('user_id', $request->user()->id)
                    ->pluck('hits', 'menu_key')
                    ->toArray()
                : [],
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
