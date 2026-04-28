<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona `location_id` em animal_lots — local FÍSICO atual do lote
 * (tanque, pasto, baia, curral). Pra lote agregado (Ave/Peixe), movimentação
 * atualiza esta coluna; pra lote convencional é informação descritiva
 * (animais individuais já têm sua própria location_id).
 *
 * Bug detectado: movimentação de lote de peixe não tinha onde gravar
 * "tanque atual" — toda movimentação ficava só em animal_events sem
 * efeito visível na lista de lotes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('animal_lots', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->nullable()
                ->after('farm_id')
                ->constrained('animal_locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('animal_lots', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
