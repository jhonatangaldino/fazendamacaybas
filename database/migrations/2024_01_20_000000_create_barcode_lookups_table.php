<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log de tentativas de busca de código de barras.
 * Serve para diagnóstico (por que um código não foi identificado?) e análise
 * futura (códigos mais escaneados, taxa de sucesso por fonte, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barcode_lookups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('found_local')->default(false);
            $table->string('source')->nullable(); // Open Food Facts, UPCItemDB, local, none
            $table->unsignedSmallInteger('http_status_off')->nullable();
            $table->unsignedSmallInteger('http_status_upc')->nullable();
            $table->string('nome_sugerido')->nullable();
            $table->string('marca_sugerida')->nullable();
            $table->text('nota_diagnostica')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barcode_lookups');
    }
};
