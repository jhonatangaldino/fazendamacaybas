<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria 2026-04-27 — A1 Despesa parcelada.
 *
 * Permite registrar UMA despesa em N parcelas (ex.: maquinário R$24.000 em
 * 12 vezes de R$2.000). Cada parcela vira uma row em financial_transactions
 * com `parent_transaction_id` apontando para a parcela 1 (que é a "matriz"
 * do grupo). `parcela_atual` / `total_parcelas` permitem mostrar "3/12" no UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_transaction_id')->nullable()->after('recurrence_id');
            $table->unsignedSmallInteger('parcela_atual')->nullable()->after('parent_transaction_id');
            $table->unsignedSmallInteger('total_parcelas')->nullable()->after('parcela_atual');

            $table->foreign('parent_transaction_id')
                ->references('id')->on('financial_transactions')
                ->nullOnDelete();
            $table->index('parent_transaction_id', 'fin_trans_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropForeign(['parent_transaction_id']);
            $table->dropIndex('fin_trans_parent_idx');
            $table->dropColumn(['parent_transaction_id', 'parcela_atual', 'total_parcelas']);
        });
    }
};
