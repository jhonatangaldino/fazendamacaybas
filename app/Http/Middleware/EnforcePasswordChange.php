<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnforcePasswordChange · força usuário com `must_change_password=true`
 * a trocar a senha ANTES de acessar qualquer rota autenticada.
 *
 * Bug F5-S02 (QA Deep 2026-04-29): redirect pra /alterar-senha acontecia
 * SOMENTE no momento do login (AuthenticatedSessionController::store).
 * Após isso, usuário podia acessar /admin/inicio e demais rotas /admin/*
 * sem trocar a senha temporária. Sem middleware global, o flag virava
 * decoração visual.
 *
 * Agora: este middleware roda em TODAS as rotas autenticadas. Se user
 * tem must_change_password=true, redireciona forçadamente pra
 * /alterar-senha. Apenas /alterar-senha, /password.update e /logout
 * passam (pra permitir a troca + logout de emergência).
 *
 * Aplicado em bootstrap/app.php no grupo `auth`.
 */
class EnforcePasswordChange
{
    /**
     * Rotas que NÃO precisam do enforce — usuário pode acessá-las
     * mesmo com must_change_password=true (pra de fato trocar a senha).
     */
    private const ROTAS_PERMITIDAS = [
        'password.change',  // GET + PUT compartilham o name (PUT é unnamed mas a URL é a mesma)
        'logout',           // sair em emergência
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        // Permite as rotas necessárias pra trocar/sair, por nome ou pela URL
        // (PUT em /alterar-senha não tem name explicito).
        $routeName = $request->route()?->getName();
        $path = $request->path();
        if (in_array($routeName, self::ROTAS_PERMITIDAS, true) || $path === 'alterar-senha') {
            return $next($request);
        }

        // AJAX/Inertia → flash + redirect (Inertia respeita o redirect)
        return redirect()
            ->route('password.change')
            ->with('warning', 'Você precisa trocar sua senha temporária antes de continuar.');
    }
}
