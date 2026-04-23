<?php

namespace App\Domain\Tenancy\Middleware;

use App\Models\Farm;
use Closure;
use Illuminate\Http\Request;

/**
 * EnforceFarm — R2.6
 *
 * Resolve a fazenda ativa para o usuário autenticado e registra no container
 * como `app('farm_id')` para uso downstream explícito em controllers/queries.
 *
 * Responsabilidade desta fase (R2.6 — APENAS):
 *   - Disponibilizar `app('farm_id')` em requests admin autenticadas
 *   - Forçar seleção quando o tenant tiver múltiplas fazendas
 *   - Auto-selecionar quando o tenant tiver exatamente 1 fazenda
 *   - Guiar o usuário a criar a primeira fazenda quando o tenant tiver 0
 *
 * O QUE NÃO FAZ (propositalmente):
 *   - Não aplica global scope por farm_id em nenhum model
 *   - Não modifica queries existentes
 *   - Não faz enforcement no creating/updating
 *   - Não altera controllers ou scopes
 *   - Não bloqueia rotas públicas (não é aplicado fora do grupo admin)
 *
 * FLUXO:
 *   auth    → se não houver user, retornado em middleware anterior
 *   master  → user com tenant_id = NULL opera platform-wide; pula sem bindar
 *   bypass  → rotas whitelistadas (selector/switch/profile/logout/first-farm)
 *   0 farms → redirect para criar a primeira (wizard futuro; por ora /admin)
 *   1 farm  → auto-seleciona silenciosa; UPDATE só se diferir do persistido
 *   N farms → valida escolha; se inválida/nula, limpa e redireciona ao seletor
 *
 * PERFORMANCE:
 *   Cache por request no container: `app('tenant_farms')` guarda a collection
 *   das fazendas do tenant (id + nome + cidade + estado). Evita N queries
 *   quando middleware + controller + Inertia share consultam o mesmo dado.
 *
 * SEGURANÇA:
 *   A leitura de Farm passa pelo BelongsToTenantScope (R2.4), então a
 *   listagem retorna apenas fazendas do tenant corrente por construção —
 *   impossível enxergar farm de outro tenant mesmo se alguém passar id
 *   manipulado downstream.
 */
class EnforceFarm
{
    /**
     * Rotas onde o middleware é intencionalmente inerte — permite ao usuário
     * escolher/trocar a fazenda, editar perfil, mudar senha, sair do sistema
     * sem precisar ter uma fazenda ativa.
     */
    private const BYPASS_ROUTES = [
        'admin.fazenda.select',
        'admin.fazenda.switch',
        'password.change',
        'me.avatar.upload',
        'me.avatar.remove',
        'logout',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        // Não autenticado: outro middleware já redirecionou. Defesa em profundidade.
        if ($user === null) {
            return $next($request);
        }

        // M5.1 — Master impersonando: binda app('farm_id') com a primeira
        // fazenda ativa do tenant impersonado, SEM persistir em user.current_farm_id
        // (master nunca deve ter esse campo preenchido).
        //
        // Condições estritas — TODAS precisam ser verdadeiras:
        //   1. user.tenant_id NULL (é master)
        //   2. session('impersonation') existe
        //   3. impersonator_user_id da session bate com este user (anti-hijack)
        //   4. app('tenant_id') já bindado (ResolveTenant confirmou impersonação)
        //
        // Se qualquer check falhar, o fluxo continua para a salvaguarda original
        // (master puro bypassa; tenant user segue para auto-select).
        $imp = $request->session()->get('impersonation');
        $isImpersonating = $user->tenant_id === null
            && is_array($imp)
            && isset($imp['impersonator_user_id'])
            && (int) $imp['impersonator_user_id'] === (int) $user->id
            && app()->bound('tenant_id');

        if ($isImpersonating) {
            // Query explícita conforme brief — redundante com BelongsToTenantScope
            // mas deixa o intento claro e é imune a mudanças futuras no scope.
            $farm = Farm::query()
                ->where('tenant_id', app('tenant_id'))
                ->where('is_active', true)
                ->orderBy('id')
                ->first(['id']);

            if ($farm) {
                app()->instance('farm_id', (int) $farm->id);
            }
            // Se tenant não tem farm ativa: app('farm_id') fica não-bindado,
            // consumidores downstream decidem fallback. Sem crash, sem persist.

            return $next($request);
        }

        // Master global puro (não impersonando) opera platform-wide; não exige
        // contexto de fazenda. Mantemos `app('farm_id')` NÃO bindado.
        if ($user->tenant_id === null) {
            return $next($request);
        }

        // Rota whitelistada — seletor, troca, perfil, logout.
        $routeName = $request->route()?->getName();
        if ($routeName !== null && in_array($routeName, self::BYPASS_ROUTES, true)) {
            return $next($request);
        }

        // Cache leve por request: evita reconsultar farms em Inertia::share, controllers, etc.
        $farms = $this->tenantFarms();

        // Zero fazendas: tenant recém-criado, dono_fazenda em onboarding.
        // Redirect defensivo para o dashboard com flash — quando existir o
        // wizard de primeira fazenda, redirecionamos para ele aqui.
        if ($farms->isEmpty()) {
            if ($routeName === 'admin.dashboard') {
                return $next($request); // evita loop; dashboard acolhe usuário sem fazenda
            }

            return redirect()->route('admin.dashboard')->with(
                'info',
                'Cadastre a primeira fazenda para começar a operar.'
            );
        }

        // 1 fazenda: auto-selecionar silenciosamente (regra 6 — zero fricção).
        if ($farms->count() === 1) {
            $soleFarmId = (int) $farms->first()->id;

            if ((int) ($user->current_farm_id ?? 0) !== $soleFarmId) {
                // saveQuietly para não disparar observers/eventos em User
                $user->forceFill(['current_farm_id' => $soleFarmId])->saveQuietly();
            }

            app()->instance('farm_id', $soleFarmId);

            return $next($request);
        }

        // Múltiplas fazendas: exige escolha válida.
        $currentId = (int) ($user->current_farm_id ?? 0);
        $isValid = $currentId > 0 && $farms->contains('id', $currentId);

        if (! $isValid) {
            // Limpa escolha stale para não deixar FK apontando para fora do tenant.
            if ($user->current_farm_id !== null) {
                $user->forceFill(['current_farm_id' => null])->saveQuietly();
            }

            return redirect()->route('admin.fazenda.select');
        }

        app()->instance('farm_id', $currentId);

        return $next($request);
    }

    /**
     * Fazendas do tenant corrente, cacheadas no container da request.
     *
     * Usa `Farm::query()` (passa pelo BelongsToTenantScope R2.4) — leitura
     * já isolada por tenant_id. Retorna apenas colunas baratas; o nome da
     * fazenda é usado no seletor, topbar e no Inertia share.
     */
    private function tenantFarms()
    {
        if (app()->bound('tenant_farms')) {
            return app('tenant_farms');
        }

        $farms = Farm::query()
            ->select(['id', 'nome', 'cidade', 'estado', 'is_active'])
            ->where('is_active', true)
            ->orderBy('nome')
            ->get();

        app()->instance('tenant_farms', $farms);

        return $farms;
    }
}
