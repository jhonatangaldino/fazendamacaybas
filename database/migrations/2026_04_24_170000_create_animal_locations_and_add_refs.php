<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correção de domínio — separação de LOTE (grupo lógico) e LOCALIZAÇÃO (pasto/piquete).
 *
 * Contexto:
 *   Até esta migration, a tabela `animal_lots` era usada indistintamente como
 *   "grupo de animais" (finalidade=corte/leite/reprodução) e como "local físico"
 *   (nomes tipo "Pasto QA", "Curral de manejo"). Isso causava:
 *     - impossibilidade de fazer rotação de pasto sem perder identidade do lote
 *     - 3 conceitos colidindo com o mesmo nome
 *     - ausência de resposta para "quantos animais tem no Pasto 3?"
 *
 * Decisão:
 *   - Mantemos `animal_lots` como está (passa a representar grupo lógico).
 *   - Criamos `animal_locations` para posição física (pasto, piquete, curral, baia, tanque).
 *   - Animal ganha `location_id` além do `lot_id` existente.
 *   - AnimalEvent ganha `location_origem_id` + `location_destino_id` para movimentação física
 *     (distinta de `lot_origem_id`/`lot_destino_id` que continua sendo movimentação de grupo).
 *
 * Estratégia:
 *   - TUDO nullable: nenhum dado existente quebra.
 *   - Nenhuma coluna antiga é removida ou renomeada.
 *   - Backfill é decisão separada (via comando artisan no futuro) — esta migration
 *     não assume conversão automática pra não destruir semânticas mistas do legado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();

            $table->string('codigo', 30)->nullable();          // ex: PASTO-1, PIQ-NORTE (pode repetir entre tenants)
            $table->string('nome');                             // ex: "Pasto das palmeiras"
            $table->string('tipo', 20)->default('pasto');       // pasto, piquete, curral, baia, tanque, galpao, outro

            $table->decimal('area_ha', 10, 2)->nullable();      // área em hectares (faz sentido em pasto/piquete)
            $table->decimal('capacidade_ua', 10, 2)->nullable(); // unidade-animal máxima (opcional)

            $table->text('observacoes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Unicidade por tenant, não global — dois tenants podem ter "Pasto 1"
            $table->unique(['tenant_id', 'codigo']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'tipo']);
        });

        Schema::table('animals', function (Blueprint $table) {
            // Nova coluna — posição física do animal HOJE (presente).
            // Histórico vem dos eventos (location_origem/destino em animal_events).
            $table->foreignId('location_id')
                ->nullable()
                ->after('lot_id')
                ->constrained('animal_locations')
                ->nullOnDelete();

            $table->index(['tenant_id', 'location_id']);
        });

        Schema::table('animal_events', function (Blueprint $table) {
            // Movimentação FÍSICA (pasto → pasto). Distinta de mov. de grupo.
            $table->foreignId('location_origem_id')
                ->nullable()
                ->after('lot_destino_id')
                ->constrained('animal_locations')
                ->nullOnDelete();

            $table->foreignId('location_destino_id')
                ->nullable()
                ->after('location_origem_id')
                ->constrained('animal_locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('animal_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_destino_id');
            $table->dropConstrainedForeignId('location_origem_id');
        });

        Schema::table('animals', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'location_id']);
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::dropIfExists('animal_locations');
    }
};
