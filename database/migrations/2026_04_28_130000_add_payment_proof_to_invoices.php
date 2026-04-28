<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprovante de pagamento + double-check (Fase 1).
 *
 * Fluxo:
 *   1. Cliente clica "Já paguei" na fatura → faz upload do comprovante
 *      (PDF, JPG ou PNG até 5MB).
 *   2. Sistema move status pra `paid_pending_review` e grava:
 *        - payment_proof_path  (caminho relativo em storage/app/public/...)
 *        - payment_proof_mime  (image/jpeg, image/png, application/pdf)
 *        - payment_proof_size  (bytes — mostrar pro master)
 *        - payment_submitted_at (timestamp do envio)
 *   3. Master recebe alerta "X pagamentos aguardando validação".
 *   4. Master abre tela de validação (vê comprovante grande + dados),
 *      aprova ou rejeita com motivo.
 *   5. Aprovação → status='paid', payment_review_reason=null.
 *      Rejeição → status volta pra 'pending', payment_review_reason=motivo,
 *      arquivo é removido. Cliente vê o motivo na próxima visita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->string('payment_proof_path', 255)->nullable()->after('external_payment_id');
            $t->string('payment_proof_mime', 60)->nullable()->after('payment_proof_path');
            $t->unsignedInteger('payment_proof_size')->nullable()->after('payment_proof_mime');
            $t->timestamp('payment_submitted_at')->nullable()->after('payment_proof_size');
            $t->string('payment_review_reason', 500)->nullable()->after('payment_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropColumn([
                'payment_proof_path',
                'payment_proof_mime',
                'payment_proof_size',
                'payment_submitted_at',
                'payment_review_reason',
            ]);
        });
    }
};
