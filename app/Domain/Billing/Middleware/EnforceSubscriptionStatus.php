<?php

namespace App\Domain\Billing\Middleware;

use App\Domain\Billing\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnforceSubscriptionStatus — bloqueio de inadimplência.
 *
 * Bloqueia o acesso à área operacional do tenant (/admin/*) quando a
 * subscription do tenant está com status `overdue`. O redirect leva
 * para /admin/pagamento-pendente — uma tela standalone com as invoices
 * em aberto e o PIX copia-e-cola para o cliente regularizar.
 *
 * REGRAS DE PASSAGEM (NÃO bloqueia):
 *   - user não autenticado (auth anterior já cuidou)
 *   - user master (tenant_id NULL) — mesmo impersonando; master nunca
 *     é cliente do próprio SaaS
 *   - tenant sem subscription cadastrada (tenant ainda não foi ativado
 *     comercialmente — evita bloquear quem nunca teve plano)
 *   - subscription.status NÃO é `overdue` (active, trial, canceled etc)
 *   - rota whitelistada (ex.: admin.pagamento-pendente)
 *
 * ROTAS FORA DO GRUPO /admin NÃO PASSAM POR ESTE MIDDLEWARE:
 *   - logout, password.change, me.avatar.* estão em Route::middleware('auth')
 *     diretamente (não dentro de /admin). Portanto tenant bloqueado CONTINUA
 *     podendo deslogar e trocar senha — comportamento desejado.
 *
 * MASTER /master/cobrancas/{id}/pix continua guardado apenas por
 * `enforce.master`. O tenant vê seu próprio PIX em
 * /admin/pagamento-pendente (tela dedicada).
 *
 * IMPERSONAÇÃO:
 *   Master impersonando tem user->tenant_id === NULL no DB (identidade
 *   real). Portanto passa direto pelo early-return do branch master.
 *   Jhonatan consegue impersonar tenant mesmo overdue — útil para
 *   suporte técnico.
 */
class EnforceSubscriptionStatus
{
    /**
     * Rotas (names) dentro do grupo /admin que NÃO são bloqueadas
     * mesmo com subscription overdue. A tela de pagamento óbvia, e
     * rotas críticas de segurança defensiva.
     */
    private const BYPASS_ROUTES = [
        'admin.pagamento-pendente',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Defesa em profundidade (auth já cuidou)
        if ($user === null) {
            return $next($request);
        }

        // Master (puro ou impersonando): user->tenant_id é NULL no DB
        if ($user->tenant_id === null) {
            return $next($request);
        }

        // Tenant sem subscription: ainda não foi ativado comercialmente
        // — não bloquear por algo que nunca existiu
        $subscription = Subscription::where('tenant_id', $user->tenant_id)->first();
        if ($subscription === null) {
            return $next($request);
        }

        // Status não-overdue: passa
        if ($subscription->status !== 'overdue') {
            return $next($request);
        }

        // Status overdue — aplica whitelist
        $routeName = $request->route()?->getName();
        if ($routeName !== null && in_array($routeName, self::BYPASS_ROUTES, true)) {
            return $next($request);
        }

        // Bloqueia: redirect para tela de pagamento do próprio tenant
        if (\Illuminate\Support\Facades\Route::has('admin.pagamento-pendente')) {
            return redirect()->route('admin.pagamento-pendente');
        }

        // Fallback improvável (rota ausente): mensagem clara em vez de 500
        abort(503, 'Acesso bloqueado por inadimplência. Contate o suporte.');
    }
}
