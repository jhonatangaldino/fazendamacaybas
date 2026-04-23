<?php

use App\Domain\Tenancy\Middleware\ResolveTenant;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocaleTimezone;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
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
        ]);

        // Usuário já logado tentando acessar /login: manda pro dashboard
        $middleware->redirectUsersTo('/admin/dashboard');
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
