<?php

namespace App\Http\Controllers\Master;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Billing\PixPayloadGenerator;
use App\Support\BillingCache;
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
        // Filtro de busca para conciliação: aceita "MAC-0008", "0008", "INV-202604-0008",
        // nome do tenant ou parte dele. Usado quando cliente paga e master precisa achar
        // a cobrança correspondente rapidamente.
        $q = trim((string) $request->query('q', ''));

        $query = Invoice::with('tenant:id,nome')
            ->orderByDesc('data_emissao')
            ->orderByDesc('id');

        if (in_array($status, ['pending', 'paid', 'overdue'], true)) {
            $query->where('status', $status);
        }

        if ($q !== '') {
            // Casa busca de 6 jeitos:
            //  1. MAC-000037 → tira "MAC-" e zeros → id = 37 (match exato global único)
            //  2. 37         → numérico puro → id = 37
            //  3. INV-202604-0001 → substring no `numero`
            //  4. nome cliente → substring em tenant.nome
            //  5. VALOR exato (ex.: "199,00", "1.00", "1") → match em valor decimal
            //  6. external_payment_id (E2E PIX colado do comprovante) — substring
            $idMatch = null;
            if (preg_match('/^MAC-?(\d+)$/i', trim($q), $m)) {
                $idMatch = (int) ltrim($m[1], '0') ?: 0;
            } elseif (ctype_digit(str_replace([' ', '-'], '', $q))) {
                $idMatch = (int) ltrim(str_replace([' ', '-'], '', $q), '0') ?: 0;
            }
            // Tenta interpretar como valor monetário BR ou US (199,00 ou 199.00 ou 199)
            $valorMatch = null;
            $candidato = str_replace(['R$', ' '], '', $q);
            $candidato = str_replace(',', '.', $candidato);
            if (is_numeric($candidato) && (float) $candidato > 0) {
                $valorMatch = (float) $candidato;
            }
            $query->where(function ($w) use ($q, $idMatch, $valorMatch) {
                if ($idMatch && $idMatch > 0) {
                    $w->orWhere('id', $idMatch);
                }
                if ($valorMatch !== null) {
                    $w->orWhere('valor', $valorMatch);
                }
                $w->orWhere('numero', 'like', "%{$q}%")
                  ->orWhere('external_payment_id', 'like', "%{$q}%")
                  ->orWhereHas('tenant', fn ($t) => $t->where('nome', 'like', "%{$q}%"));
            });
        }

        $invoices = $query->get([
            'id', 'tenant_id', 'subscription_id', 'numero', 'tipo',
            'valor', 'status', 'data_emissao', 'data_vencimento', 'data_pagamento',
            'external_payment_id', 'pix_txid',
        ]);

        return Inertia::render('Master/Cobrancas/Index', [
            'invoices' => $invoices->map(fn ($i) => [
                'id' => $i->id,
                'numero' => $i->numero,
                'referencia_curta' => $i->referencia_curta,
                'tipo' => $i->tipo,
                'tenant_id' => $i->tenant_id,
                'tenant_nome' => $i->tenant?->nome,
                'valor' => (float) $i->valor,
                'status' => $i->status,
                'data_emissao' => $i->data_emissao?->format('d/m/Y'),
                'data_vencimento' => $i->data_vencimento?->format('d/m/Y'),
                'data_pagamento' => $i->data_pagamento?->format('d/m/Y'),
                'external_payment_id' => $i->external_payment_id,
                'pix_txid' => $i->pix_txid,
            ])->values(),
            'filter_status' => $status,
            'filter_q' => $q,
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
            'valor' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'data_emissao' => ['required', 'date'],
            'data_vencimento' => ['required', 'date', 'after_or_equal:data_emissao'],
        ], [
            'valor.required' => 'Informe o valor da cobrança.',
            'valor.numeric' => 'Valor deve ser numérico.',
            'valor.min' => 'Valor mínimo é R$ 0,01.',
            'data_emissao.required' => 'Informe a data de emissão.',
            'data_vencimento.required' => 'Informe a data de vencimento.',
            'data_vencimento.after_or_equal' => 'Vencimento não pode ser anterior à emissão.',
        ]);

        $subscription = $tenant->subscription()->first();

        // Sem subscription → não é possível emitir invoice (subscription_id é NOT NULL).
        // Retorna validation amigável em vez de 500. O master atribui plano antes.
        if ($subscription === null) {
            return back()->withErrors([
                'valor' => 'Atribua um plano ao cliente antes de gerar a cobrança. Use "Atribuir plano" no card de Assinatura acima.',
            ])->withInput();
        }

        // Numeração no formato INV-AAAAMM-NNNN (sequencial por tenant + ano-mês de emissão).
        // Mesma lógica de InvoiceGenerationService — usa string ao invés do `(int) max()` antigo,
        // que colidia com invoices novas no formato INV-... (cast para 0).
        $numero = $this->nextNumero($tenant->id);

        // Carrega config PIX salva pelo master em /master/cobrancas/configuracoes.
        // Sem chave configurada: invoice é criada do mesmo jeito (campos PIX ficam null);
        // master vê aviso amigável após salvar, em vez de 500.
        $cfg = $this->loadPixConfig();
        // TXID legível: SIGLA + NÚMERO da fatura. Aparece no extrato bancário
        // do master quando o pagamento cai — facilita conciliação manual sem
        // depender de ferramenta externa.
        $txid = $pix->generateTxid($tenant->nome, $numero);
        $payload = null;
        if (! empty($cfg['chave'])) {
            $payload = $pix->build(
                amount: (float) $validated['valor'],
                txid: $txid,
                merchantName: $cfg['nome'] ?? null,
                city: $cfg['cidade'] ?? null,
                pixKey: $cfg['chave'],
            );
        }

        try {
            $invoice = Invoice::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'numero' => $numero,
                'tipo' => 'unica',
                'periodo_inicio' => $validated['data_emissao'],
                'periodo_fim' => $validated['data_vencimento'],
                'aparece_em' => today()->toDateString(),
                'valor' => $validated['valor'],
                'status' => 'pending',
                'data_emissao' => $validated['data_emissao'],
                'data_vencimento' => $validated['data_vencimento'],
                'pix_txid' => $payload ? $txid : null,
                'pix_payload' => $payload,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Race condition (improvável mas defensivo): tenta uma vez mais
            // recalculando a sequência e abortando elegantemente se persistir.
            $invoice = Invoice::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'numero' => $this->nextNumero($tenant->id),
                'tipo' => 'unica',
                'periodo_inicio' => $validated['data_emissao'],
                'periodo_fim' => $validated['data_vencimento'],
                'aparece_em' => today()->toDateString(),
                'valor' => $validated['valor'],
                'status' => 'pending',
                'data_emissao' => $validated['data_emissao'],
                'data_vencimento' => $validated['data_vencimento'],
                'pix_txid' => $payload ? $txid : null,
                'pix_payload' => $payload,
            ]);
        }

        // Cache invalidation: alerts/badges do tenant impactado + master KPIs
        BillingCache::forgetForTenant($tenant->id);

        // Mensagem condicional: avisa se PIX ainda não está configurado
        $msg = empty($cfg['chave'])
            ? 'Cobrança '.$invoice->numero.' gerada. ⚠ Chave PIX ainda não configurada — configure em Cobranças → Configurar PIX para que o cliente possa pagar.'
            : 'Cobrança '.$invoice->numero.' gerada. Envie o PIX ao cliente.';

        return redirect()
            ->route('master.cobrancas.pix', $invoice->id)
            ->with(empty($cfg['chave']) ? 'warning' : 'success', $msg);
    }

    /**
     * Próximo número INV-AAAAMM-NNNN para o tenant, sequencial dentro do ano-mês.
     * Mesma lógica usada por InvoiceGenerationService — mantém invariante:
     *   "todas as invoices do tenant têm numero único e ordenável".
     */
    private function nextNumero(int $tenantId): string
    {
        $prefix = 'INV-'.today()->format('Ym').'-';
        $last = Invoice::where('tenant_id', $tenantId)
            ->where('numero', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('numero');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }
        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** Lê config PIX salva em settings (group=billing_pix). */
    private function loadPixConfig(): array
    {
        $rows = Setting::whereIn('key', [
            'billing_pix.tipo_chave',
            'billing_pix.chave',
            'billing_pix.nome_recebedor',
            'billing_pix.cidade_recebedor',
        ])->pluck('value', 'key');

        return [
            'tipo_chave' => $rows['billing_pix.tipo_chave'] ?? null,
            'chave' => $rows['billing_pix.chave'] ?? null,
            'nome' => $rows['billing_pix.nome_recebedor'] ?? null,
            'cidade' => $rows['billing_pix.cidade_recebedor'] ?? null,
        ];
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
                'referencia_curta' => $invoice->referencia_curta,
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
        // BLOCO 4.3 — processo seguro: aceita data_pagamento, método e observação.
        // Método e observação ficam em `meta.payment` (Invoice já tem coluna meta JSON).
        // external_payment_id (E2E PIX, ID TED etc.) vai pra coluna própria — fica
        // pesquisável e visível na lista (PO 2026-04-28: trilha de conciliação).
        $validated = $request->validate([
            'data_pagamento' => ['nullable', 'date', 'before_or_equal:today'],
            'metodo_pagamento' => ['nullable', 'in:pix,transferencia,dinheiro,boleto,cartao,outro'],
            'observacao_pagamento' => ['nullable', 'string', 'max:500'],
            'external_payment_id' => ['nullable', 'string', 'max:50'],
        ]);

        $meta = $invoice->meta ?? [];
        if (! empty($validated['metodo_pagamento']) || ! empty($validated['observacao_pagamento'])) {
            $meta['payment'] = array_filter([
                'metodo' => $validated['metodo_pagamento'] ?? null,
                'observacao' => $validated['observacao_pagamento'] ?? null,
                'registrado_em' => now()->toIso8601String(),
                'registrado_por' => auth()->id(),
            ]);
        }

        $invoice->update([
            'status' => 'paid',
            'data_pagamento' => $validated['data_pagamento'] ?? now()->toDateString(),
            'external_payment_id' => $validated['external_payment_id'] ?? null,
            'meta' => $meta,
        ]);

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

        // Cache invalidation: alerts/badges/dashboard do tenant + master KPIs
        // — qualquer mudança financeira reflete na tela em até 5min OU no
        // próximo redirect (ações via UI já refazem fetch do Inertia share).
        BillingCache::forgetForTenant($tenantId);
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
