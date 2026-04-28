<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * external_payment_id — ID da transação bancária (E2E do PIX, ID do TED, etc.)
 * que o master cola do comprovante ao marcar a fatura como paga. Serve como
 * trilha de auditoria pra dispute/reconciliação futura.
 *
 * Tamanho: até 50 chars cobre o E2E do PIX (32 chars), IDs de TED/DOC e
 * referências de operadoras de cartão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->string('external_payment_id', 50)->nullable()->after('data_pagamento');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropColumn('external_payment_id');
        });
    }
};
