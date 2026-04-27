<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RouteHostGate — restringe rotas a contextos específicos resolvidos pelo
 * RouteByHost. Usado em route groups:
 *
 *   Route::middleware('route.host:master_landing')->group(...);
 *   Route::middleware('route.host:app,tenant_app,tenant_domain')->group(...);
 *
 * Múltiplos contextos aceitos via vírgula (lista OR).
 *
 * Se o contexto atual NÃO está na lista permitida → 404 (não 403:
 * preferimos não revelar a existência de outras rotas no host errado).
 *
 * Após reestruturação 2026-04-27, usado para:
 *   - /admin/*, /master/*, /login (sem /c/) → só em context 'app'
 *   - /c/{slug}/* → só em context 'tenant_app' ou 'tenant_domain'
 *   - / (landing) → só em context 'master_landing' ou 'tenant_domain'
 */
class RouteHostGate
{
    public function handle(Request $request, Closure $next, string ...$allowedContexts): Response
    {
        $current = $request->attributes->get('request_context', 'unknown');

        // expand "a,b,c" se passado como string única
        $allowed = [];
        foreach ($allowedContexts as $arg) {
            foreach (explode(',', $arg) as $a) {
                $a = trim($a);
                if ($a !== '') $allowed[] = $a;
            }
        }

        if (! in_array($current, $allowed, true)) {
            abort(404);
        }

        return $next($request);
    }
}
