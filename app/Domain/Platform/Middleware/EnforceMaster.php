<?php

namespace App\Domain\Platform\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnforceMaster — M1
 *
 * Guarda do grupo /master/* (área da plataforma SaaS).
 *
 * Autoriza EXCLUSIVAMENTE:
 *   - user autenticado
 *   - user.tenant_id === NULL  (não pertence a nenhum cliente)
 *   - user possui role `admin_master` (Spatie)
 *
 * Qualquer outro caso → abort(403). Sem redirect. Redirecionar daqui
 * para /admin criaria risco de loop com o EnsureTenantUser (que redireciona
 * master para /master). O 403 é explícito, inequívoco, sem efeito colateral.
 *
 * Não aplicado no grupo /admin/*. Não altera ResolveTenant nem EnforceFarm.
 *
 * EVOLUÇÃO FUTURA (M5):
 *   Durante impersonação, o master permanece com user.tenant_id NULL e role
 *   admin_master — continua passando por este middleware. A impersonação
 *   afeta apenas o contexto de tenant_id no container (ResolveTenant), não
 *   a identidade do user no DB. Portanto este middleware permanece estável.
 */
class EnforceMaster
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Defesa: auth deveria ter interceptado. Não confia.
        if ($user === null) {
            abort(403);
        }

        // Pertence a algum tenant → não é master
        if ($user->tenant_id !== null) {
            abort(403);
        }

        // Sem role admin_master → não é master (estado corrompido ou escalação)
        if (! $user->hasRole('admin_master')) {
            abort(403);
        }

        return $next($request);
    }
}
