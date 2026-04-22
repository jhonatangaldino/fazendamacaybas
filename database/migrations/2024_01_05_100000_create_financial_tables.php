<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('tipo', 20); // caixa, banco, cartao, aplicacao
            $table->string('banco')->nullable();
            $table->string('agencia', 20)->nullable();
            $table->string('conta', 30)->nullable();
            $table->decimal('saldo_inicial', 14, 2)->default(0);
            $table->decimal('saldo_atual', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('tipo', 10); // receita, despesa, transferencia
            $table->string('descricao');
            $table->text('observacoes')->nullable();
            $table->decimal('valor', 14, 2);
            $table->date('data_vencimento');
            $table->date('data_pagamento')->nullable();
            $table->string('status', 20)->default('pendente')->index(); // pendente, pago, atrasado, cancelado
            $table->string('forma_pagamento', 30)->nullable(); // dinheiro, pix, cartao, boleto, transferencia
            $table->string('numero_documento')->nullable();
            $table->foreignId('recurrence_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tipo', 'status', 'data_vencimento']);
        });

        Schema::create('financial_transaction_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('financial_transactions')->cascadeOnDelete();
            $table->string('nome');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        Schema::create('financial_recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('tipo', 10); // receita, despesa
            $table->string('descricao');
            $table->decimal('valor', 14, 2);
            $table->string('periodicidade', 20); // mensal, bimestral, trimestral, semestral, anual
            $table->integer('dia_vencimento');
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->integer('parcelas_geradas')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->foreign('recurrence_id')->references('id')->on('financial_recurrences')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropForeign(['recurrence_id']);
        });
        Schema::dropIfExists('financial_recurrences');
        Schema::dropIfExists('financial_transaction_attachments');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('financial_accounts');
    }
};
