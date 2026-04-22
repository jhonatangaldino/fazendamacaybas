<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('cnpj', 18)->nullable()->unique();
            $table->string('inscricao_estadual', 30)->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('endereco')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            $table->decimal('area_total_ha', 12, 4)->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};
