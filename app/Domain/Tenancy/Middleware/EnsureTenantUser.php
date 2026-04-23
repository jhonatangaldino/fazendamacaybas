<?php

namespace App\Domain\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureTenantUser — M1
 *
 * Guarda SUAVE do grupo /admin/*.
 *
 * Responsabilidade:
 *   Se um MASTER (user.tenant_id === NULL) tenta acessar qualquer rota admin,
 *   redireciona silenciosamente para /master/dashboard. Master não opera
 *   diretamente no tenant area — ele usa impersonação (M5) quando precisa
 *   agir em nome de um tenant.
 *
 * Por que redirect, não 403:
 *   - Master logado é um usuário legítimo da plataforma, só está no lugar errado.
 *   - 403 aqui seria hostil: o usuário é autorizado, só precisa ir para onde pertence.
 *   - O redirect leva para /master/dashboard, onde `EnforceMaster` autoriza o acesso.
 *
 * Aplicado no grupo /admin/* ANTES do EnforceFarm:
 *   stack: auth → tenant.user.only → enforce.farm → permission:*
 *
 * Master puro sai do pipeline antes do EnforceFarm tocar em farms/tenant_farms
 * — economiza query e evita qualquer interação com contexto tenant.
 *
 * Usuário não autenticado:
 *   Não intervém — o middleware `auth` anterior já terá redirecionado.
 *   Defesa em profundidade: retorna next() sem efeito para null.
 *
 * EVOLUÇÃO FUTURA (M5):
 *   Durante impersonação, o master ainda tem user.tenant_id NULL. Ou este
 *   middleware passa a consultar `session('impersonating_tenant_id')`, ou
 *   adicionamos uma rota /admin/impersonation especial na whitelist — a
 *   decisão ficará para M5 após validação dos mecanismos de sessão.
 */
class EnsureTenantUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Não autenticado: deixa o middleware `auth` lidar
        if ($user === null) {
            return $next($request);
        }

        // Tenant user normal: passa direto
        if ($user->tenant_id !== null) {
            return $next($request);
        }

        // MASTER sem tenant_id. Dois sub-casos:
        // (a) Master IMPERSONANDO — session ativa com impersonator = este user.
        //     ResolveTenant já resolveu app('tenant_id') a partir da session.
        //     Permitimos passar para /admin/* — é a área operacional do tenant
        //     que ele está assumindo.
        $imp = $request->session()->get('impersonation');
        if (is_array($imp)
            && isset($imp['impersonator_user_id'])
            && (int) $imp['impersonator_user_id'] === (int) $user->id) {
            return $next($request);
        }

        // (b) Master PURO — nenhuma impersonação ativa. Sua área é /master.
        if (\Illuminate\Support\Facades\Route::has('master.dashboard')) {
            return redirect()->route('master.dashboard');
        }

        // Fallback improvável: rota master.dashboard ausente
        abort(503, 'Área master indisponível.');
    }
}
