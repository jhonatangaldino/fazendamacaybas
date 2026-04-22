<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda o array completo de tentativas em JSON — substitui os campos
 * específicos de Open Food Facts / UPCItemDB que eram rígidos.
 * Assim podemos ter N fontes sem precisar de migration por fonte nova.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barcode_lookups', function (Blueprint $table) {
            $table->json('attempts_json')->nullable()->after('marca_sugerida');
        });
    }

    public function down(): void
    {
        Schema::table('barcode_lookups', function (Blueprint $table) {
            $table->dropColumn('attempts_json');
        });
    }
};
