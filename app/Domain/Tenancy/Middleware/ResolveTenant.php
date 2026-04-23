<?php

namespace App\Domain\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * ResolveTenant — R2.1
 *
 * Resolve o tenant_id do usuário autenticado e registra no container da aplicação,
 * tornando-o acessível via `app('tenant_id')` em qualquer ponto da request.
 *
 * Comportamento (R2.1):
 *   - Se há usuário autenticado com tenant_id preenchido → seta container
 *   - Se há usuário autenticado com tenant_id = NULL (master global) → não seta
 *   - Se não há usuário autenticado (rotas públicas) → não seta
 *   - NUNCA bloqueia request, NUNCA lança exception
 *
 * Este middleware é idempotente e seguro em rotas públicas — apenas não faz
 * nada quando não há contexto para resolver. Pode ser aplicado no grupo web
 * sem risco.
 *
 * Implicação downstream:
 *   - Trait BelongsToTenant usa app('tenant_id') no creating() para forçar
 *     o valor em novos registros (R2.3).
 *   - Detector de tenancy (R1.5) para de logar `retrieved_without_context`
 *     quando o container passa a ter o valor — comportamento esperado.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user !== null && $user->tenant_id !== null) {
            app()->instance('tenant_id', (int) $user->tenant_id);
        }

        return $next($request);
    }
}
