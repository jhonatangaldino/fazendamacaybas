<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invoice — fatura mensal do SaaS.
 *
 * Cada tenant tem sua própria sequência de `numero` (unique composto com tenant_id).
 * Pagamento via PIX: qr_code + copia-e-cola gravados no próprio registro.
 * Webhook do banco atualiza status='paga' + data_pagamento.
 */
class Invoice extends Model
{
    protected $table = 'invoices';

    protected static function booted(): void
    {
        $invalidate = function (Invoice $m) {
            // Invalida métricas master + tenant (faturas afetam ambos).
            \App\Services\Metrics\MetricsCache::forgetMaster();
            if ($m->tenant_id) {
                \App\Services\Metrics\MetricsCache::forgetForTenant((int) $m->tenant_id, 'financial');
            }
        };
        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'numero',
        'tipo',
        'referencia_mes',
        'referencia_ano',
        'periodo_inicio',
        'periodo_fim',
        'aparece_em',
        'valor',
        'status',
        'data_emissao',
        'data_vencimento',
        'data_pagamento',
        'external_payment_id',
        'pix_txid',
        'pix_payload',
        'pix_qrcode_base64',
        'meta',
        'payment_proof_path',
        'payment_proof_mime',
        'payment_proof_size',
        'payment_submitted_at',
        'payment_review_reason',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'referencia_mes' => 'integer',
        'referencia_ano' => 'integer',
        'periodo_inicio' => 'date',
        'periodo_fim' => 'date',
        'aparece_em' => 'date',
        'data_emissao' => 'date',
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
        'payment_submitted_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Rótulo legível do mês de referência: "Maio/2026".
     * Retorna null para faturas únicas ou sem referência.
     */
    public function getReferenciaLabelAttribute(): ?string
    {
        if (! $this->referencia_mes || ! $this->referencia_ano) {
            return null;
        }
        $meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        return $meses[$this->referencia_mes].'/'.$this->referencia_ano;
    }

    /**
     * Referência curta para conciliação manual de pagamento.
     *
     * Usa o ID GLOBAL da invoice (PK), pad em 6 dígitos. Garantia:
     *   • Único em todo o sistema (não colide entre tenants)
     *   • Imutável após criação
     *   • Buscável por `?q=MAC-000123` ou `?q=123`
     *
     * Exemplos:
     *   id=37    → MAC-000037
     *   id=4981  → MAC-004981
     *
     * Usado:
     *   • Lista master: coluna "Ref." em Cobrancas/Index.vue
     *   • Tela PIX: instrução "Inclua MAC-000037 no comentário do PIX"
     *   • Tela tenant Faturas: exibe junto da fatura
     *   • Busca master: filtro WHERE id = N quando query for numérica
     *
     * NOTA HISTÓRICA: a versão anterior derivava do `numero` (sequencial por
     * tenant), o que fazia múltiplos tenants terem MAC-0001/0002/etc. Bug
     * crítico de conciliação corrigido aqui.
     */
    public function getReferenciaCurtaAttribute(): string
    {
        return 'MAC-'.str_pad((string) ($this->id ?? 0), 6, '0', STR_PAD_LEFT);
    }

    /* ───── Relacionamentos ───── */

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
