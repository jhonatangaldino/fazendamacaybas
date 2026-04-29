<?php

use App\Domain\Billing\Middleware\EnforceSubscriptionStatus;
use App\Domain\Platform\Middleware\EnforceMaster;
use App\Domain\Tenancy\Middleware\EnforceFarm;
use App\Domain\Tenancy\Middleware\EnsureTenantUser;
use App\Domain\Tenancy\Middleware\ResolveTenant;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\QueryCounter;
use App\Http\Middleware\RouteByHost;
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
        $middleware->web(prepend: [
            // Reestruturação 2026-04-27: detecta o Host HTTP e popula
            // request.attributes.{request_context, expected_tenant_id}.
            // Roda ANTES de tudo: outras camadas (auth, login isolation,
            // route groups) consomem esses atributos para tomar decisão.
            RouteByHost::class,
        ]);

        $middleware->web(append: [
            // Query probe — ativo apenas com APP_DEBUG=true OU header X-Query-Probe:1
            QueryCounter::class,
            SetLocaleTimezone::class,
            // R2.1: resolve app('tenant_id') a partir do user autenticado.
            // Posicionado ANTES do HandleInertiaRequests para que o share()
            // possa enxergar o tenant já resolvido (uso futuro em R2+).
            // Em rotas públicas ou sem user, o middleware passa sem efeito.
            ResolveTenant::class,
            // F5-S02: força user com must_change_password=true a trocar
            // senha antes de acessar qualquer rota /admin/* ou /master/*.
            // Antes o redirect só ocorria no momento do login; depois o
            // user navegava livremente sem trocar.
            \App\Http\Middleware\EnforcePasswordChange::class,
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
            // Reestruturação 2026-04-27: aplicado em route groups específicos
            // para forçar contexto. Ex.: rota só pode ser acessada se
            // request_context = 'master_landing'. Útil para impedir que
            // /admin/* seja acessível pela raiz após a reestruturação.
            'route.host' => \App\Http\Middleware\RouteHostGate::class,
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
        // BUG-C-04 fix · 403 em POST sem permissão não mostrava toast.
        // Spatie Permission lança UnauthorizedException → Laravel renderizava
        // página 403 default. Em POST via Inertia/AJAX, isso virava redirect
        // silencioso (usuário não sabia por que a ação não rolou).
        //
        // Agora: capturamos UnauthorizedException, redirecionamos com flash
        // de erro descritivo. Inertia entrega o flash, FlashMessages converte
        // em toast vermelho.
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, \Illuminate\Http\Request $request) {
            $perm = method_exists($e, 'requiredPermissions') && $e->requiredPermissions
                ? implode(', ', (array) $e->requiredPermissions)
                : null;
            $msg = $perm
                ? "Você não tem permissão para esta ação ({$perm}). Fale com o administrador da sua fazenda."
                : 'Você não tem permissão para esta ação. Fale com o administrador da sua fazenda.';

            // Inertia/AJAX → redirect com flash (FlashMessages.vue mostra toast)
            if ($request->header('X-Inertia') || $request->expectsJson()) {
                return back()->with('error', $msg);
            }

            // Navegação tradicional → mantém página 403 mas com mensagem clara
            abort(403, $msg);
        });

        // F10 fix · 404/405/500 default do Symfony aparecia em INGLÊS
        // quando user acessava direto rotas que só existem como POST/PUT
        // (ex.: /admin/agricola/talhoes/novo após F5). "Oops! An Error
        // Occurred. Something is broken..." é cru e em inglês.
        // Detectado em produção 2026-04-29.
        //
        // Agora: 404/405 em rota /admin/* → redirect amigável pra hub do
        // módulo correspondente com flash de aviso. Outros 4xx/5xx caem
        // numa view Blade pt-BR ao invés do default Symfony.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            $status = $e->getStatusCode();

            // 404/405 em /admin/*/novo → tenta voltar pra /admin/*/index
            if (in_array($status, [404, 405], true) && str_starts_with($request->path(), 'admin/')) {
                $path = $request->path();
                // Se URL termina em /novo, tira o /novo e redirect
                if (str_ends_with($path, '/novo')) {
                    $base = '/' . preg_replace('#/novo$#', '', $path);
                    return redirect($base)->with('warning', 'Esta tela usa um modal de cadastro — clique em "+ Novo" para abrir.');
                }
                // admin/X/Y (2 segmentos depois de admin) → redirect pro hub /admin/X
                // Cobre 404 (rota inexistente, ex.: /admin/financeiro/categorias só
                // tem POST inline) e 405 (rota só POST/PUT). Antes só pegava 405,
                // deixando 404 mostrar página default em inglês.
                if (preg_match('#^admin/[^/]+/[^/]+$#', $path)) {
                    $hubModule = '/' . preg_replace('#/[^/]+$#', '', $path);
                    $msg = $status === 405
                        ? 'Esta tela está disponível apenas como modal — abra pelo hub do módulo.'
                        : 'Esta tela não existe ou foi movida — abra pelo hub do módulo.';
                    return redirect($hubModule)->with('warning', $msg);
                }
            }

            // Inertia/AJAX → flash error genérico em pt-BR
            if ($request->header('X-Inertia') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Não foi possível processar a requisição. Tente novamente ou volte pra tela anterior.',
                ], $status);
            }

            // Navegação tradicional → renderiza view custom em pt-BR
            // (Laravel já tem resources/views/errors/{status}.blade.php se houver)
            // Fallback pro default permite que páginas customizadas sobrevivam.
        });
    })->create();
