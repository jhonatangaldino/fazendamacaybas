<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona suporte a 3 novos tipos de evento na tabela animal_events:
 *
 *   - controle_leiteiro · produção de leite por ordenha (1ª, 2ª, 3ª…)
 *   - secagem           · cessação da lactação antes do parto
 *   - exame_toque       · palpação retal para diagnóstico de gestação
 *
 * Também adiciona à tabela animal_breeds o tamanho de referência (grande/
 * media/pequena) e categoria (leite/corte/misto) para a tabela DRovet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_events', function (Blueprint $table) {
            // Controle leiteiro: ordenhas em JSON (flexível pra 1, 2, 3+ ordenhas/dia)
            // Estrutura: [{"label":"1ª","litros":12.5}, {"label":"2ª","litros":10.0}]
            $table->json('ordenhas')->nullable()->after('peso');
            $table->decimal('producao_litros', 7, 2)->nullable()->after('ordenhas');

            // Exame de toque
            $table->string('gestacao_status', 20)->nullable()->after('producao_litros'); // prenhe|vazia|duvida
            $table->integer('gestacao_dias')->nullable()->after('gestacao_status');
            $table->date('data_prevista_parto')->nullable()->after('gestacao_dias');
        });

        Schema::table('animal_breeds', function (Blueprint $table) {
            // Tamanho de referência para a tabela DRovet de crescimento
            $table->string('tamanho_referencia', 10)->nullable()->after('nome'); // grande|media|pequena
            $table->string('categoria', 10)->nullable()->after('tamanho_referencia'); // leite|corte|misto
        });

        // Tabela de referência DRovet — pesos esperados por idade × tamanho
        // Não é por tenant (é referência veterinária universal)
        Schema::create('growth_references', function (Blueprint $table) {
            $table->id();
            $table->string('tamanho', 10); // grande|media|pequena
            $table->integer('idade_meses');
            $table->decimal('peso_esperado_kg', 7, 2);
            $table->decimal('peso_min_kg', 7, 2)->nullable();
            $table->decimal('peso_max_kg', 7, 2)->nullable();
            $table->string('observacao', 50)->nullable(); // ex: "fase crítica"
            $table->timestamps();
            $table->unique(['tamanho', 'idade_meses']);
        });
    }

    public function down(): void
    {
        Schema::table('animal_events', function (Blueprint $table) {
            $table->dropColumn(['ordenhas', 'producao_litros', 'gestacao_status', 'gestacao_dias', 'data_prevista_parto']);
        });
        Schema::table('animal_breeds', function (Blueprint $table) {
            $table->dropColumn(['tamanho_referencia', 'categoria']);
        });
        Schema::dropIfExists('growth_references');
    }
};
