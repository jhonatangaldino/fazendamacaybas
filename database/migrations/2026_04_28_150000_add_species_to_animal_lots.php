<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * species_id em animal_lots — permite cadastrar lote de ave/peixe sem
 * precisar criar Animal individual (cadastro em lote real).
 *
 * Antes desta migration, a espécie do lote era inferida via Animal->lot_id
 * (1 animal por lote). Pra espécies com gestao='lote' (ave, peixe), isso
 * forçava cadastro de 1 Animal "representativo" — fluxo errado.
 *
 * Agora: lote pode existir SEM animais individuais, com species_id +
 * quantidade_inicial/atual + peso_medio_kg refletindo o lote como um todo.
 *
 * Nullable inicialmente pra retrocompat (lots antigos sem species_id
 * continuam válidos — espécie ainda inferida via animals.species_id).
 * Em cadastros novos via Lots/Form, é obrigatório.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_lots', function (Blueprint $t) {
            $t->foreignId('species_id')->nullable()->after('farm_id')
              ->constrained('animal_species')->nullOnDelete();
            $t->index('species_id');
        });
    }

    public function down(): void
    {
        Schema::table('animal_lots', function (Blueprint $t) {
            $t->dropForeign(['species_id']);
            $t->dropColumn('species_id');
        });
    }
};
