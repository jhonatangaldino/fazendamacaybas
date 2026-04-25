<?php

namespace App\Http\Middleware;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Billing\PlanFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnforceFeature — bloqueia acesso a módulos não inclusos no plano do tenant.
 *
 * REGRAS:
 *   1. Não autenticado          → passa (auth anterior cuida)
 *   2. Master puro (sem imp.)   → passa (master ignora plano)
 *   3. Master impersonando      → checa contra tenant impersonado
 *   4. Tenant comum             → checa contra próprio plano
 *   5. Rota CORE (sem feature)  → passa (Hub, Dashboard, Faturas, Usuários...)
 *
 * Quando bloqueado: redirect para `admin.feature-not-available` com query
 * `?feature=xxx`, que renderiza tela explicativa.
 */
class EnforceFeature
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) return $next($request);

        // Master puro (não impersonando) ignora bloqueio
        $impersonation = $request->session()->get('impersonation');
        $impersonatedTenantId = (is_array($impersonation) && ! empty($impersonation['tenant_id']))
            ? (int) $impersonation['tenant_id']
            : null;

        if ($user->tenant_id === null && $impersonatedTenantId === null) {
            return $next($request);
        }

        $tenantId = $user->tenant_id ?? $impersonatedTenantId;
        if ($tenantId === null) return $next($request);

        // Resolve feature exigida pela rota corrente
        $routeName = $request->route()?->getName();
        $required = PlanFeatures::featureForRoute($routeName);
        if ($required === null) return $next($request); // rota CORE

        // Carrega tenant + plan + subscription para checagem
        $tenant = Tenant::with(['plan', 'subscription.plan'])->find($tenantId);
        if ($tenant === null) return $next($request);

        if ($tenant->hasFeature($required)) {
            return $next($request);
        }

        // Bloqueia: redireciona para tela explicativa.
        // Evita loop quando a própria tela bloqueia exige a feature (não é o caso aqui,
        // pois admin.feature-not-available é CORE, sem prefixo mapeado).
        return redirect()
            ->route('admin.feature-not-available', ['feature' => $required]);
    }
}
