<?php

namespace App\Http\Controllers\Master;

use App\Domain\Billing\Models\Tenant;
use App\Domain\Platform\Models\ImpersonationAudit;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * ImpersonationController — M5
 *
 * Entrega/encerra sessões de impersonação master → tenant.
 *
 * Ambas as actions rodam no grupo /master (auth + enforce.master), então:
 *   - user é sempre master (tenant_id NULL + role admin_master)
 *   - exit é acessível mesmo quando o master está operando em /admin
 *     (porque EnforceMaster só olha user.tenant_id e role, não route)
 *
 * REGRAS DE NEGÓCIO:
 *   - Não permite stacking: se já existir session('impersonation'), recusa
 *     nova start. Master precisa sair antes de trocar de tenant.
 *   - Tenant inválido não é aceito: se o tenant não existe, Laravel 404 via
 *     route-model binding. Não impersona tenant fantasma.
 *
 * AUDITORIA:
 *   - start cria ImpersonationAudit com ended_at=NULL, grava audit_id na session
 *   - exit atualiza ended_at=now() no registro de audit_id da session
 *   - Se master esquecer o exit e a session expirar, ended_at fica NULL
 *     (orphaned) — reportável em tela futura de auditoria
 */
class ImpersonationController extends Controller
{
    /**
     * Inicia impersonação no tenant informado.
     */
    public function start(Request $request, Tenant $tenant): RedirectResponse
    {
        // Stacking proibido — 1 impersonação por vez
        if ($request->session()->has('impersonation')) {
            return back()->with(
                'error',
                'Saia da impersonação atual antes de iniciar outra.'
            );
        }

        $master = $request->user();

        $audit = ImpersonationAudit::create([
            'impersonator_user_id' => $master->id,
            'tenant_id' => $tenant->id,
            'started_at' => now(),
            'ended_at' => null,
            'ip_address' => $this->safeIp($request),
            'user_agent' => $this->safeUserAgent($request),
        ]);

        $request->session()->put('impersonation', [
            'impersonator_user_id' => (int) $master->id,
            'tenant_id' => (int) $tenant->id,
            'started_at' => now()->toIso8601String(),
            'audit_id' => (int) $audit->id,
        ]);

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Agora operando como "'.$tenant->nome.'". Saia pelo banner no topo quando terminar.');
    }

    /**
     * Encerra impersonação. Idempotente — chamada sem session ativa
     * apenas redireciona sem efeito colateral.
     */
    public function exit(Request $request): RedirectResponse
    {
        // pull = get + forget atômico
        $imp = $request->session()->pull('impersonation');

        if (is_array($imp) && ! empty($imp['audit_id'])) {
            // whereNull defensivo: não sobrescreve ended_at se já fechado
            // (ex.: cronjob de cleanup futuro)
            ImpersonationAudit::where('id', $imp['audit_id'])
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);
        }

        return redirect()
            ->route('master.dashboard')
            ->with('success', 'Impersonação encerrada.');
    }

    private function safeIp(Request $request): ?string
    {
        $ip = $request->ip();
        return $ip === null ? null : substr($ip, 0, 45);
    }

    private function safeUserAgent(Request $request): ?string
    {
        $ua = $request->userAgent();
        return $ua === null ? null : substr($ua, 0, 512);
    }
}
