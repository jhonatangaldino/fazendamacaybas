<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FaturasController — visualização de faturas no lado do TENANT.
 *
 * Diferente do InvoiceController do master:
 *   - Lista APENAS as faturas do próprio tenant logado.
 *   - Aplica filtro `aparece_em <= today` — fatura futura não aparece antes da hora.
 *   - Sem ações de master (gerar, marcar paga). Tenant só visualiza.
 *   - Mostra estado de vigência da subscription para que o cliente entenda
 *     "estou em dia até quando".
 */
class FaturasController extends Controller
{
    public function index(): Response
    {
        // Usa o tenant resolvido pelo middleware ResolveTenant (respeita
        // impersonation: master operando como tenant tem auth()->user()->tenant_id
        // = NULL, mas app('tenant_id') = ID do tenant impersonado).
        // Bug reportado pelo PO em 2026-04-28: fatura avulsa não aparecia
        // pro tenant quando master impersonava.
        $tenantId = app()->bound('tenant_id') ? (int) app('tenant_id') : (int) (auth()->user()->tenant_id ?? 0);
        $hoje = today()->toDateString();

        // Faturas visíveis: aparece_em é null (legado, sempre visível) OU <= hoje
        $invoices = Invoice::where('tenant_id', $tenantId)
            ->where(function ($q) use ($hoje) {
                $q->whereNull('aparece_em')->orWhereDate('aparece_em', '<=', $hoje);
            })
            ->orderByDesc('data_vencimento')
            ->orderByDesc('id')
            ->get([
                'id', 'numero', 'tipo',
                'referencia_mes', 'referencia_ano',
                'periodo_inicio', 'periodo_fim',
                'valor', 'status',
                'data_emissao', 'data_vencimento', 'data_pagamento',
                'pix_payload', 'pix_txid',
            ]);

        $subscription = Subscription::where('tenant_id', $tenantId)->with('plan:id,nome,preco_mensal')->first();

        return Inertia::render('Admin/Faturas/Index', [
            'invoices' => $invoices->map(fn ($i) => [
                'id' => $i->id,
                'numero' => $i->numero,
                'referencia_curta' => $i->referencia_curta,
                'tipo' => $i->tipo,
                'referencia_label' => $i->referencia_label,
                'periodo_inicio' => $i->periodo_inicio?->format('d/m/Y'),
                'periodo_fim' => $i->periodo_fim?->format('d/m/Y'),
                'valor' => (float) $i->valor,
                'status' => $i->status,
                'data_emissao' => $i->data_emissao?->format('d/m/Y'),
                'data_vencimento' => $i->data_vencimento?->format('d/m/Y'),
                'data_vencimento_iso' => $i->data_vencimento?->toDateString(),
                'data_pagamento' => $i->data_pagamento?->format('d/m/Y'),
                'pix_payload' => $i->pix_payload,
                'pix_txid' => $i->pix_txid,
            ])->values(),
            'subscription' => $subscription ? [
                'status' => $subscription->status,
                'plano_nome' => $subscription->plan?->nome,
                'plano_preco' => (float) ($subscription->plan?->preco_mensal ?? 0),
                'current_period_start' => $subscription->current_period_start?->format('d/m/Y'),
                'current_period_end' => $subscription->current_period_end?->format('d/m/Y'),
                'grace_until' => $subscription->grace_until?->format('d/m/Y'),
            ] : null,
            'totals' => [
                'pendente' => $invoices->whereIn('status', ['pending', 'overdue'])->sum('valor'),
                'pago_total' => $invoices->where('status', 'paid')->sum('valor'),
                'count_pendente' => $invoices->whereIn('status', ['pending', 'overdue'])->count(),
            ],
        ]);
    }
}
