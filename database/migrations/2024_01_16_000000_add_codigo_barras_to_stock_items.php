<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campo de código de barras (EAN-13, UPC, Code128) lido via câmera no cadastro do item.
 * Permite lookup em bases públicas (Open Food Facts) e entrada rápida via leitor físico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->string('codigo_barras', 32)->nullable()->after('codigo')->index();
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropColumn('codigo_barras');
        });
    }
};
