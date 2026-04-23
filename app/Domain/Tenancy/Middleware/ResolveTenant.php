<?php

namespace App\Domain\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * ResolveTenant — R2.1 + M5
 *
 * Resolve `app('tenant_id')` de acordo com o tipo do request.
 *
 * 3 BRANCHES (ordem de precedência):
 *
 *   1. TENANT USER — user.tenant_id preenchido
 *      → `app('tenant_id')` = user.tenant_id
 *      Comportamento original (R2.1). Nunca alterado por impersonação:
 *      tenant user tem tenant_id no DB, independente da sessão.
 *
 *   2. MASTER IMPERSONANDO — user.tenant_id NULL + session('impersonation')
 *      → `app('tenant_id')` = session['impersonation']['tenant_id']
 *      Entrega M5. Só ativa se:
 *        - user é master (tenant_id NULL)
 *        - session tem chave 'impersonation'
 *        - impersonator_user_id da session bate com o user atual
 *          (defesa contra session hijacking entre masters)
 *
 *   3. MASTER PURO — user.tenant_id NULL + sem session
 *      → não binda
 *      `BelongsToTenantScope` bypassa → master vê tudo na área platform.
 *
 * NÃO BLOQUEIA, NÃO LANÇA EXCEPTION.
 * Se session de impersonação existir mas tenant_id for inválido/deletado,
 * downstream (EnforceFarm, queries) cuida — não é papel deste middleware
 * validar integridade do DB por request.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // BRANCH 1 — tenant user: autoritativo sobre qualquer session
        if ($user->tenant_id !== null) {
            app()->instance('tenant_id', (int) $user->tenant_id);

            return $next($request);
        }

        // BRANCH 2 — master impersonando
        $imp = $request->session()->get('impersonation');
        if (is_array($imp)
            && isset($imp['tenant_id'], $imp['impersonator_user_id'])
            && (int) $imp['impersonator_user_id'] === (int) $user->id) {

            app()->instance('tenant_id', (int) $imp['tenant_id']);

            return $next($request);
        }

        // BRANCH 3 — master puro: deixa sem bind (scope R2.4 bypassa)
        return $next($request);
    }
}
