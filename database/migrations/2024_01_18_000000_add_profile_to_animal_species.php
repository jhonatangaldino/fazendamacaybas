<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil de manejo por espécie — dita que tipo de eventos/ações fazem sentido.
 * Princípio context-aware forms: peixe não vacina, implemento não abastece.
 *
 *  - gestao: 'individual' | 'lote' (peixes/aves em lote; bovinos individuais)
 *  - profile: chave ampla (bovino_corte, bovino_leite, aves_postura, aquicultura...)
 *  - allowed_events: JSON array com eventos permitidos
 *      (pesagem, vacinacao, medicacao, vermifugacao, reproducao, movimentacao,
 *       ordenha, tosquia, ferrageamento, castracao, postura_diaria,
 *       biometria_amostral, qualidade_agua, alimentacao, mortalidade, observacao)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_species', function (Blueprint $table) {
            $table->string('gestao', 15)->default('individual')->after('nome'); // individual | lote
            $table->string('profile', 40)->nullable()->after('gestao');
            $table->json('allowed_events')->nullable()->after('profile');
        });
    }

    public function down(): void
    {
        Schema::table('animal_species', function (Blueprint $table) {
            $table->dropColumn(['gestao', 'profile', 'allowed_events']);
        });
    }
};
