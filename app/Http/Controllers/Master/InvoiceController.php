<?php

namespace App\Http\Controllers\Master;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Services\Billing\PixPayloadGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * InvoiceController — M6
 *
 * Gera e gerencia cobranças (invoices) emitidas pelo master para tenants.
 * Escopo M6:
 *   index()       — listagem global de todas invoices (com filtro por status)
 *   store()       — master gera invoice para um tenant específico
 *   markPaid()    — marca como paga, registra data_pagamento
 *   markPending() — reverte status para pending (caso marcado por engano)
 *
 * PIX deliberadamente não implementado (conforme brief). Campos pix_*
 * permanecem NULL até uma fase futura integrar o gateway.
 *
 * `numero` é gerado sequencialmente por tenant (UNIQUE composto no DB).
 */
class InvoiceController extends Controller
{
    /**
     * Listagem global de cobranças, ordenada por mais recentes.
     * Filtro opcional por status via query string (?status=pending|paid|overdue).
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $query = Invoice::with('tenant:id,nome')
            ->orderByDesc('data_emissao')
            ->orderByDesc('id');

        if (in_array($status, ['pending', 'paid', 'overdue'], true)) {
            $query->where('status', $status);
        }

        $invoices = $query->get([
            'id', 'tenant_id', 'subscription_id', 'numero',
            'valor', 'status', 'data_emissao', 'data_vencimento', 'data_pagamento',
        ]);

        return Inertia::render('Master/Cobrancas/Index', [
            'invoices' => $invoices->map(fn ($i) => [
                'id' => $i->id,
                'numero' => $i->numero,
                'tenant_id' => $i->tenant_id,
                'tenant_nome' => $i->tenant?->nome,
                'valor' => (float) $i->valor,
                'status' => $i->status,
                'data_emissao' => $i->data_emissao?->format('d/m/Y'),
                'data_vencimento' => $i->data_vencimento?->format('d/m/Y'),
                'data_pagamento' => $i->data_pagamento?->format('d/m/Y'),
            ])->values(),
            'filter_status' => $status,
            'totals' => [
                'total' => $invoices->count(),
                'pending' => $invoices->where('status', 'pending')->count(),
                'paid' => $invoices->where('status', 'paid')->count(),
                'overdue' => $invoices->where('status', 'overdue')->count(),
                'valor_pendente' => (float) $invoices->whereIn('status', ['pending', 'overdue'])->sum('valor'),
                'valor_pago' => (float) $invoices->where('status', 'paid')->sum('valor'),
            ],
        ]);
    }

    /**
     * Cria nova invoice para um tenant. Gera automaticamente o payload PIX
     * (copia-e-cola simulado — padrão EMV BR Code válido, mas aponta para
     * chave fictícia). Quando a integração com gateway real for implementada,
     * basta trocar a chave PIX em config/billing.php.
     *
     * Redireciona direto para a tela de PIX após criar — facilita o fluxo
     * "gerou cobrança → envia pro cliente".
     */
    public function store(Request $request, Tenant $tenant, PixPayloadGenerator $pix): RedirectResponse
    {
        $validated = $request->validate([
            'valor' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'data_emissao' => ['required', 'date'],
            'data_vencimento' => ['required', 'date', 'after_or_equal:data_emissao'],
        ], [
            'valor.required' => 'Informe o valor da cobrança.',
            'valor.numeric' => 'Valor deve ser numérico.',
            'data_emissao.required' => 'Informe a data de emissão.',
            'data_vencimento.required' => 'Informe a data de vencimento.',
            'data_vencimento.after_or_equal' => 'Vencimento não pode ser anterior à emissão.',
        ]);

        $subscription = $tenant->subscription()->first();
        $nextNumero = (int) (Invoice::where('tenant_id', $tenant->id)->max('numero') ?? 0) + 1;

        // Gera identificador PIX e payload EMV (simulado — válido por formato,
        // mas sem gateway real atrás)
        $txid = $pix->generateTxid();
        $payload = $pix->build(
            amount: (float) $validated['valor'],
            txid: $txid,
            merchantName: config('app.name', 'Fazenda Macaybas'),
        );

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription?->id,
            'numero' => $nextNumero,
            'valor' => $validated['valor'],
            'status' => 'pending',
            'data_emissao' => $validated['data_emissao'],
            'data_vencimento' => $validated['data_vencimento'],
            'pix_txid' => $txid,
            'pix_payload' => $payload,
        ]);

        return redirect()
            ->route('master.cobrancas.pix', $invoice->id)
            ->with('success', 'Cobrança #'.$invoice->numero.' gerada. Envie o PIX ao cliente.');
    }

    /**
     * Tela de pagamento PIX — exibe payload copia-e-cola, valor, vencimento
     * e permite marcar como paga manualmente (fluxo sem gateway).
     */
    public function showPix(Invoice $invoice): Response
    {
        $invoice->load('tenant:id,nome');

        return Inertia::render('Master/Cobrancas/Pix', [
            'invoice' => [
                'id' => $invoice->id,
                'numero' => $invoice->numero,
                'tenant_id' => $invoice->tenant_id,
                'tenant_nome' => $invoice->tenant?->nome,
                'valor' => (float) $invoice->valor,
                'status' => $invoice->status,
                'data_emissao' => $invoice->data_emissao?->format('d/m/Y'),
                'data_vencimento' => $invoice->data_vencimento?->format('d/m/Y'),
                'data_vencimento_iso' => $invoice->data_vencimento?->toDateString(),
                'data_pagamento' => $invoice->data_pagamento?->format('d/m/Y'),
                'pix_txid' => $invoice->pix_txid,
                'pix_payload' => $invoice->pix_payload,
            ],
        ]);
    }

    /**
     * Marca invoice como paga, registra data_pagamento = hoje (ou fornecida).
     *
     * LÓGICA DE NEGÓCIO (fluxo real de billing):
     *   Se a subscription do tenant estava `overdue` e NÃO há mais invoices
     *   vencidas, a subscription volta para `active`. Isso automatiza o
     *   "regularizou o pagamento → sistema destrava" manualmente.
     *
     * Não estende current_period_end nem cria próxima cobrança — essas
     * regras ficam para uma próxima iteração (billing recurrente automático).
     */
    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'data_pagamento' => ['nullable', 'date'],
        ]);

        $invoice->update([
            'status' => 'paid',
            'data_pagamento' => $validated['data_pagamento'] ?? now()->toDateString(),
        ]);

        // Regulariza subscription: se estava em atraso E não há mais overdue,
        // volta para active.
        $this->reconcileSubscriptionFromInvoices($invoice->tenant_id);

        return back()->with('success', 'Cobrança #'.$invoice->numero.' marcada como paga.');
    }

    /**
     * Re-avalia o status da subscription do tenant com base nas invoices.
     *
     * Regras:
     *   - Se há qualquer invoice `overdue` → subscription = `overdue`
     *   - Se não há overdue E subscription estava `overdue` → volta para `active`
     *
     * Chamado automaticamente em markPaid/markPending e no command
     * billing:mark-overdue. Idempotente.
     */
    private function reconcileSubscriptionFromInvoices(int $tenantId): void
    {
        $sub = Subscription::where('tenant_id', $tenantId)->first();
        if ($sub === null) {
            return;
        }

        $hasOverdue = Invoice::where('tenant_id', $tenantId)
            ->where('status', 'overdue')
            ->exists();

        if ($hasOverdue && $sub->status === 'active') {
            $sub->update(['status' => 'overdue']);
        } elseif (! $hasOverdue && $sub->status === 'overdue') {
            $sub->update(['status' => 'active']);
        }
    }

    /**
     * Reverte status para pending (caso marcação por engano).
     * data_pagamento volta para NULL.
     *
     * Se a invoice já venceu e voltar para pending, o command
     * billing:mark-overdue irá re-marcá-la como overdue no próximo ciclo.
     * Por simplicidade, reavaliamos já aqui: se data_vencimento < hoje,
     * status vira diretamente `overdue` (evita janela intermediária).
     */
    public function markPending(Invoice $invoice): RedirectResponse
    {
        $newStatus = 'pending';
        if ($invoice->data_vencimento && $invoice->data_vencimento->isPast()) {
            $newStatus = 'overdue';
        }

        $invoice->update([
            'status' => $newStatus,
            'data_pagamento' => null,
        ]);

        $this->reconcileSubscriptionFromInvoices($invoice->tenant_id);

        $msg = $newStatus === 'overdue'
            ? 'Cobrança #'.$invoice->numero.' voltou a pendente (e já está vencida).'
            : 'Cobrança #'.$invoice->numero.' voltou a pendente.';

        return back()->with('success', $msg);
    }
}
