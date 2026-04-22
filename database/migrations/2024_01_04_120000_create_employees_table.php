<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->string('nome');
            $table->string('cpf', 14)->nullable()->unique();
            $table->string('rg', 20)->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('setor')->nullable();
            $table->string('funcao')->nullable();
            $table->decimal('salario', 12, 2)->nullable();
            $table->date('data_admissao')->nullable();
            $table->date('data_demissao')->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('endereco')->nullable();
            $table->string('numero', 20)->nullable();
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
        Schema::dropIfExists('employees');
    }
};
