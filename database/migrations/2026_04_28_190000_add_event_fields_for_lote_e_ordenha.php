<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona campos específicos do contexto de manejo:
 *
 * Eventos agregados em lote (Ave/Peixe):
 *   - quantidade_ovos          → postura diária
 *   - peso_medio_amostra       → biometria amostral
 *   - quantidade_amostra       → biometria amostral (N animais pesados)
 *   - kg_racao                 → alimentação
 *   - ph                       → qualidade da água
 *   - temperatura_agua         → qualidade da água
 *   - oxigenio_dissolvido      → qualidade da água
 *
 * Ordenha leiteira:
 *   - litros_manha             → produção da manhã
 *   - litros_tarde             → produção da tarde
 *   (producao_litros continua existindo como soma agregada)
 *
 * Bug detectado pelo usuário: modal de "Registrar postura" não pedia
 * quantidade de ovos (sem coluna pra gravar) e ordenha tinha só 1 input
 * agregado mesmo o padrão DROVET separar manhã/tarde.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('animal_events', function (Blueprint $table) {
            // Eventos lote — Ave/Peixe
            $table->unsignedInteger('quantidade_ovos')->nullable()->after('producao_litros');
            $table->decimal('peso_medio_amostra', 8, 3)->nullable()->after('quantidade_ovos');
            $table->unsignedInteger('quantidade_amostra')->nullable()->after('peso_medio_amostra');
            $table->decimal('kg_racao', 10, 2)->nullable()->after('quantidade_amostra');
            $table->decimal('ph', 4, 2)->nullable()->after('kg_racao');
            $table->decimal('temperatura_agua', 5, 1)->nullable()->after('ph');
            $table->decimal('oxigenio_dissolvido', 5, 2)->nullable()->after('temperatura_agua');

            // Ordenha — manhã + tarde
            $table->decimal('litros_manha', 8, 1)->nullable()->after('oxigenio_dissolvido');
            $table->decimal('litros_tarde', 8, 1)->nullable()->after('litros_manha');
        });
    }

    public function down(): void
    {
        Schema::table('animal_events', function (Blueprint $table) {
            $table->dropColumn([
                'quantidade_ovos',
                'peso_medio_amostra',
                'quantidade_amostra',
                'kg_racao',
                'ph',
                'temperatura_agua',
                'oxigenio_dissolvido',
                'litros_manha',
                'litros_tarde',
            ]);
        });
    }
};
