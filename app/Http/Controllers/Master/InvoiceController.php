<?php

namespace App\Http\Controllers\Master;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Tenant;
use App\Http\Controllers\Controller;
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
     * Cria nova invoice para um tenant. Usa a subscription ativa do tenant
     * e o preço do plano como default do valor (editável no form).
     */
    public function store(Request $request, Tenant $tenant): RedirectResponse
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

        // Próximo número sequencial por tenant
        $nextNumero = (int) (Invoice::where('tenant_id', $tenant->id)->max('numero') ?? 0) + 1;

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription?->id,
            'numero' => $nextNumero,
            'valor' => $validated['valor'],
            'status' => 'pending',
            'data_emissao' => $validated['data_emissao'],
            'data_vencimento' => $validated['data_vencimento'],
        ]);

        return redirect()
            ->route('master.tenants.subscription.show', $tenant)
            ->with('success', 'Cobrança #'.$invoice->numero.' gerada para '.$tenant->nome.'.');
    }

    /**
     * Marca invoice como paga, registra data_pagamento = hoje (ou fornecida).
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

        return back()->with('success', 'Cobrança #'.$invoice->numero.' marcada como paga.');
    }

    /**
     * Reverte status para pending (caso marcação por engano).
     * data_pagamento volta para NULL.
     */
    public function markPending(Invoice $invoice): RedirectResponse
    {
        $invoice->update([
            'status' => 'pending',
            'data_pagamento' => null,
        ]);

        return back()->with('success', 'Cobrança #'.$invoice->numero.' voltou a pendente.');
    }
}
