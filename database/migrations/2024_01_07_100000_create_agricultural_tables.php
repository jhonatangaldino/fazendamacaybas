<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->string('codigo', 30)->unique();
            $table->string('nome');
            $table->decimal('area_ha', 12, 4);
            $table->string('tipo_solo')->nullable();
            $table->text('descricao')->nullable();
            $table->string('localizacao')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('crops', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->string('slug')->unique();
            $table->string('variedade')->nullable();
            $table->integer('ciclo_dias')->nullable();
            $table->string('unidade_producao', 10)->default('kg'); // kg, ton, sc, un
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique(); // Safra 2025/2026
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('plantings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('fields')->restrictOnDelete();
            $table->foreignId('crop_id')->constrained('crops')->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained('seasons')->nullOnDelete();
            $table->date('data_plantio');
            $table->date('previsao_colheita')->nullable();
            $table->decimal('area_plantada_ha', 12, 4);
            $table->decimal('custo_previsto', 14, 2)->nullable();
            $table->decimal('custo_real', 14, 2)->nullable();
            $table->string('status', 20)->default('em_andamento'); // em_andamento, colhido, perdido, cancelado
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('harvests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planting_id')->constrained('plantings')->cascadeOnDelete();
            $table->date('data_colheita');
            $table->decimal('quantidade_colhida', 14, 4);
            $table->string('unidade', 10); // kg, ton, sc
            $table->decimal('produtividade_por_ha', 14, 4)->nullable();
            $table->decimal('valor_total', 14, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        Schema::create('field_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('fields')->cascadeOnDelete();
            $table->foreignId('planting_id')->nullable()->constrained('plantings')->nullOnDelete();
            $table->date('data_aplicacao');
            $table->string('tipo', 30); // adubacao, herbicida, fungicida, inseticida, calagem, irrigacao
            $table->string('produto');
            $table->decimal('quantidade', 12, 4);
            $table->string('unidade', 10);
            $table->decimal('valor_total', 14, 2)->nullable();
            $table->string('responsavel')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_applications');
        Schema::dropIfExists('harvests');
        Schema::dropIfExists('plantings');
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('crops');
        Schema::dropIfExists('fields');
    }
};
