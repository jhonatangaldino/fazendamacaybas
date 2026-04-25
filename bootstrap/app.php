<?php

use App\Domain\Billing\Middleware\EnforceSubscriptionStatus;
use App\Domain\Platform\Middleware\EnforceMaster;
use App\Domain\Tenancy\Middleware\EnforceFarm;
use App\Domain\Tenancy\Middleware\EnsureTenantUser;
use App\Domain\Tenancy\Middleware\ResolveTenant;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\QueryCounter;
use App\Http\Middleware\SetLocaleTimezone;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            // Query probe — ativo apenas com APP_DEBUG=true OU header X-Query-Probe:1
            QueryCounter::class,
            SetLocaleTimezone::class,
            // R2.1: resolve app('tenant_id') a partir do user autenticado.
            // Posicionado ANTES do HandleInertiaRequests para que o share()
            // possa enxergar o tenant já resolvido (uso futuro em R2+).
            // Em rotas públicas ou sem user, o middleware passa sem efeito.
            ResolveTenant::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            // R2.6: resolve app('farm_id') e guia o usuário quando há múltiplas
            // fazendas no tenant. Aplicado SOMENTE no grupo admin — nunca em
            // rotas públicas, login ou recuperação de senha.
            'enforce.farm' => EnforceFarm::class,
            // M1: guarda suave do grupo /admin/* — redireciona master puro
            // para /master/dashboard antes que o enforce.farm seja consultado.
            'tenant.user.only' => EnsureTenantUser::class,
            // M1: guarda estrita do grupo /master/* — exige user.tenant_id
            // NULL + role admin_master. Qualquer outro caso = 403.
            'enforce.master' => EnforceMaster::class,
            // Billing: bloqueia tenant com subscription overdue em /admin/*
            // exceto a rota de pagamento (admin.pagamento-pendente). Master
            // nunca é bloqueado, mesmo impersonando.
            'enforce.subscription' => EnforceSubscriptionStatus::class,
            // Bloqueia rotas /admin/* cujo módulo não está no plano do tenant.
            // Master puro ignora; impersonando respeita o plano do tenant alvo.
            'enforce.feature' => \App\Http\Middleware\EnforceFeature::class,
        ]);

        // M0 — Usuário já logado tentando acessar rota guest (ex.: /login):
        // decide destino baseado no tipo de user via User::homeUrl().
        //   MASTER (tenant_id NULL) → master.dashboard (fallback admin.dashboard até M1)
        //   TENANT USER             → admin.dashboard
        // Coerente com AuthenticatedSessionController::store(). Fallback para
        // /admin/dashboard se, por algum motivo defensivo, não houver user no request.
        $middleware->redirectUsersTo(function (Request $request) {
            return $request->user()?->homeUrl() ?? '/admin/dashboard';
        });
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
