<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Memória de comprovantes aprovados — alimenta auto-aprovação adaptativa
 * (sem IA paga). Ver migration pra detalhes do contrato e uso.
 */
class PaymentProofSignature extends Model
{
    protected $table = 'payment_proof_signatures';

    protected $fillable = [
        'invoice_id',
        'tenant_id',
        'e2e_id',
        'banco_detectado',
        'valor_aprovado',
        'hint_pattern',
    ];

    protected $casts = [
        'valor_aprovado' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
