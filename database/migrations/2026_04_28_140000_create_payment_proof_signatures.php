<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payment_proof_signatures — "memória" de comprovantes aprovados.
 *
 * A cada aprovação manual do master, o JS no browser extrai do OCR:
 *   - e2e_id          (ID único da transação PIX no BACEN)
 *   - banco_detectado (Itaú, Nubank, PicPay etc — match em palavras-chave)
 *   - valor_aprovado  (snapshot do valor no momento)
 *   - hint_pattern    (1ª linha do texto OCR — "Comprovante de Pix recebido"
 *                      etc — assinatura do template do banco)
 *
 * Esses dados alimentam 3 verificações futuras (Fase 3):
 *
 *   1. DUPLICATA — se outro comprovante tem mesmo e2e_id, é tentativa de
 *      reuso. Tela de validação alerta vermelho e impede aprovação.
 *
 *   2. CONFIANÇA POR BANCO — se "Itaú" já apareceu em 5+ aprovações sem
 *      rejeição, o sistema sobe a confiança ao processar novo comprovante
 *      do Itaú.
 *
 *   3. AUTO-APROVAÇÃO EM LOTE — quando master clica "Processar lote",
 *      JS no browser roda OCR em todos pendentes; os que passam em todos
 *      os checks (valor bate + e2e válido + não duplicata + banco confiável)
 *      são auto-aprovados sem clique individual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_proof_signatures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // E2E PIX é único globalmente no BACEN — duplicata = fraude/erro
            $t->string('e2e_id', 50)->nullable();
            $t->string('banco_detectado', 50)->nullable();
            $t->decimal('valor_aprovado', 10, 2);
            // Primeiros 60 chars do texto OCR — assinatura do template
            $t->string('hint_pattern', 80)->nullable();
            $t->timestamps();

            // Busca rápida de duplicata
            $t->unique('e2e_id', 'pps_e2e_unique');
            $t->index('banco_detectado');
            $t->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proof_signatures');
    }
};
