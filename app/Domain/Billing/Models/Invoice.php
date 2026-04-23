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

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'numero',
        'valor',
        'status',
        'data_emissao',
        'data_vencimento',
        'data_pagamento',
        'pix_txid',
        'pix_payload',
        'pix_qrcode_base64',
        'meta',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_emissao' => 'date',
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
        'meta' => 'array',
    ];

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
