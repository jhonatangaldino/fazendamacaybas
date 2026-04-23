<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PendingPaymentController — tela "Acesso bloqueado por inadimplência".
 *
 * Renderiza lista de invoices em aberto (overdue + pending) do tenant
 * do usuário logado, com payload PIX copia-e-cola para regularização.
 *
 * Acessível mesmo com subscription overdue — a rota
 * `admin.pagamento-pendente` está na whitelist do `enforce.subscription`.
 *
 * Esta é a ÚNICA forma do tenant user ver seu PIX (a tela rica em
 * /master/cobrancas/{id}/pix é guardada por enforce.master e não é
 * acessível a tenant users).
 */
class PendingPaymentController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        // Defesa: tenant_id deve existir (tenant.user.only já filtrou),
        // mas como a rota está fora do fluxo bloqueante do enforce.subscription,
        // vamos checar de novo aqui.
        $subscription = $tenantId
            ? Subscription::where('tenant_id', $tenantId)->first()
            : null;

        $invoices = $tenantId
            ? Invoice::where('tenant_id', $tenantId)
                ->whereIn('status', ['overdue', 'pending'])
                ->orderBy('data_vencimento')
                ->orderBy('id')
                ->get()
            : collect();

        return Inertia::render('Admin/PagamentoPendente', [
            'subscription' => $subscription ? [
                'status' => $subscription->status,
            ] : null,
            'invoices' => $invoices->map(fn ($i) => [
                'id' => $i->id,
                'numero' => $i->numero,
                'valor' => (float) $i->valor,
                'status' => $i->status,
                'data_emissao' => $i->data_emissao?->format('d/m/Y'),
                'data_vencimento' => $i->data_vencimento?->format('d/m/Y'),
                'pix_txid' => $i->pix_txid,
                'pix_payload' => $i->pix_payload,
            ])->values(),
        ]);
    }
}
