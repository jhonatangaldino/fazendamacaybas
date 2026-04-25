<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FarmSelectionController — R2.6
 *
 * Entrega a tela de seleção de fazenda e processa a troca.
 *
 * As rotas deste controller ESTÃO na whitelist do middleware EnforceFarm —
 * acessíveis mesmo quando o usuário ainda não tem `current_farm_id` válido.
 *
 * Segurança no switch:
 *   NÃO usamos Farm::find($id) — fazendas são localizadas via query do Eloquent,
 *   que passa pelo BelongsToTenantScope (R2.4). Farm de outro tenant nunca é
 *   encontrada, retornando null e caindo no fluxo de erro controlado.
 */
class FarmSelectionController extends Controller
{
    /**
     * Tela de seleção minimalista — grid de cards.
     */
    public function select(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Master global não usa seleção — manda para dashboard
        if ($user->tenant_id === null) {
            return redirect()->route('admin.inicio');
        }

        // Leitura já isolada por tenant (R2.4). Usa cache de request se disponível.
        $farms = app()->bound('tenant_farms')
            ? app('tenant_farms')
            : Farm::query()
                ->select(['id', 'nome', 'cidade', 'estado', 'is_active'])
                ->where('is_active', true)
                ->orderBy('nome')
                ->get();

        // Se só houver 1, não faz sentido abrir seletor — manda ao dashboard,
        // o EnforceFarm fará auto-seleção no próximo ciclo.
        if ($farms->count() <= 1) {
            return redirect()->route('admin.inicio');
        }

        return Inertia::render('Admin/Farm/Select', [
            'farms' => $farms->map(fn ($f) => [
                'id' => (int) $f->id,
                'nome' => $f->nome,
                'cidade' => $f->cidade,
                'estado' => $f->estado,
                'is_current' => (int) $user->current_farm_id === (int) $f->id,
            ])->values(),
        ]);
    }

    /**
     * Processa a troca. Valida via query (passa pelo scope tenant), nunca find().
     */
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'farm_id' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $requestedId = (int) $validated['farm_id'];

        // BLOCO 3 — multi-fazenda em impersonação:
        // Master tem tenant_id NULL e nunca deve persistir current_farm_id no
        // próprio user. Quando impersonando, salvamos a escolha em
        // session('impersonation.farm_id') — EnforceFarm respeita.
        if ($user->tenant_id === null) {
            $imp = $request->session()->get('impersonation');
            if (! is_array($imp) || empty($imp['tenant_id'])) {
                // Master fora de impersonação: não tem farm context — manda dashboard.
                return redirect()->route('admin.inicio');
            }

            // Validação explícita: farm pertence ao tenant impersonado e está ativa.
            // BelongsToTenantScope já filtra por app('tenant_id') (bindado em impersonação),
            // mas o where explícito é defesa em profundidade.
            $farm = Farm::query()
                ->where('id', $requestedId)
                ->where('tenant_id', (int) $imp['tenant_id'])
                ->where('is_active', true)
                ->first(['id', 'nome']);

            if ($farm === null) {
                return back()->with('error', 'Fazenda não encontrada ou inativa.');
            }

            $imp['farm_id'] = (int) $farm->id;
            $request->session()->put('impersonation', $imp);

            // Limpa cache de request — próxima nav reconsulta com a farm nova.
            if (app()->bound('tenant_farms')) {
                app()->forgetInstance('tenant_farms');
            }

            return redirect()->route('admin.inicio')->with(
                'success',
                'Agora operando em '.$farm->nome.' (impersonando).'
            );
        }

        // Tenant user: fluxo regular — persiste em users.current_farm_id.
        // Query que passa pelo BelongsToTenantScope → se a farm for de outro tenant,
        // retorna null. Nunca usamos find(), exatamente para garantir essa barreira.
        $farm = Farm::query()
            ->where('id', $requestedId)
            ->where('is_active', true)
            ->first(['id', 'nome']);

        if ($farm === null) {
            return back()->with('error', 'Fazenda não encontrada ou inativa.');
        }

        // Persiste escolha no usuário (saveQuietly — evita disparar eventos em User)
        $user->forceFill(['current_farm_id' => (int) $farm->id])->saveQuietly();

        // Invalida cache de request para próxima navegação recarregar (defensivo)
        if (app()->bound('tenant_farms')) {
            app()->forgetInstance('tenant_farms');
        }

        return redirect()->route('admin.inicio')->with(
            'success',
            'Agora operando em '.$farm->nome.'.'
        );
    }
}
