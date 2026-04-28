<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                'payment_proof_path', 'payment_submitted_at', 'payment_review_reason',
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
                'payment_proof_url' => $i->payment_proof_path ? asset('storage/'.$i->payment_proof_path) : null,
                'payment_submitted_at' => $i->payment_submitted_at?->format('d/m/Y H:i'),
                'payment_review_reason' => $i->payment_review_reason,
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

    /**
     * Cliente envia comprovante de pagamento — fica em status
     * `paid_pending_review` aguardando o master validar.
     *
     * Uploads aceitos: PDF, JPG, PNG, WEBP até 5MB. Salvo em
     * storage/app/public/payment-proofs/{tenant_id}/{timestamp}_{invoice_id}.{ext}
     * (acessível via /storage/payment-proofs/...).
     *
     * Idempotente em re-envios: substitui comprovante anterior se houver.
     */
    public function submitPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        // Garante que a fatura é deste tenant (via app('tenant_id') que respeita
        // impersonação — mesmo bug do index() corrigido em commit d54e1dd).
        $tenantId = app()->bound('tenant_id') ? (int) app('tenant_id') : (int) (auth()->user()->tenant_id ?? 0);
        if ($invoice->tenant_id !== $tenantId) {
            abort(404);
        }
        if (! in_array($invoice->status, ['pending', 'overdue', 'paid_pending_review'], true)) {
            return back()->with('error', 'Esta fatura não aceita envio de comprovante neste estado.');
        }

        $validated = $request->validate([
            'comprovante' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'external_payment_id' => ['nullable', 'string', 'max:50'],
        ], [
            'comprovante.required' => 'Anexe o comprovante de pagamento.',
            'comprovante.mimes' => 'Aceito: PDF, JPG, PNG ou WEBP.',
            'comprovante.max' => 'Tamanho máximo: 5 MB.',
        ]);

        // Remove comprovante anterior se for re-envio (cliente errou e mandou outro)
        if ($invoice->payment_proof_path && Storage::disk('public')->exists($invoice->payment_proof_path)) {
            Storage::disk('public')->delete($invoice->payment_proof_path);
        }

        $file = $request->file('comprovante');
        $ext = $file->getClientOriginalExtension();
        $filename = sprintf('%d_%d_%s.%s', $invoice->id, time(), bin2hex(random_bytes(4)), $ext);
        $path = $file->storeAs("payment-proofs/{$tenantId}", $filename, 'public');

        $invoice->update([
            'status' => 'paid_pending_review',
            'payment_proof_path' => $path,
            'payment_proof_mime' => $file->getMimeType(),
            'payment_proof_size' => $file->getSize(),
            'payment_submitted_at' => now(),
            'external_payment_id' => $validated['external_payment_id'] ?? $invoice->external_payment_id,
            'payment_review_reason' => null, // limpa motivo anterior se era rejeição
        ]);

        // Invalida cache de alertas do master pra ver na próxima request
        \App\Support\BillingCache::forgetForTenant($invoice->tenant_id);

        return back()->with('success', 'Comprovante enviado! Aguardando confirmação do administrador.');
    }
}
