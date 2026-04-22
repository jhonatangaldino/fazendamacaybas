<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partners = fornecedores E clientes (compradores de gado, leite, produção).
 * Tipo diferencia comportamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20)->index(); // fornecedor, cliente, ambos
            $table->string('pessoa', 2); // pf, pj
            $table->string('nome');
            $table->string('nome_fantasia')->nullable();
            $table->string('documento', 18)->nullable()->index(); // CPF ou CNPJ
            $table->string('inscricao_estadual', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('endereco')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
