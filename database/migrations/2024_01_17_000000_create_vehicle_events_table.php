<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log incremental de eventos operacionais por veículo/máquina.
 * Princípio incremental-first: medidor_atual e km_rodado vêm do último evento,
 * nunca editáveis no cadastro do veículo.
 *
 * Tipos de evento:
 *   - abastecimento: litros, preço/L, combustível, hodômetro no abastecimento
 *   - leitura: só snapshot do hodômetro/horímetro
 *   - ocorrencia: falha, acidente, observação operacional
 *   - manutencao: já existe em maintenance_orders; não duplicar
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 30)->index(); // abastecimento, leitura, ocorrencia
            $table->date('data');
            $table->time('hora')->nullable();

            // Snapshot do medidor (km ou horas) no momento do evento
            $table->decimal('medidor', 10, 1)->nullable();

            // Abastecimento
            $table->decimal('litros', 10, 3)->nullable();
            $table->decimal('preco_litro', 10, 4)->nullable();
            $table->decimal('valor_total', 12, 2)->nullable();
            $table->string('combustivel', 30)->nullable(); // diesel, gasolina, etanol, gnv
            $table->string('posto', 120)->nullable();

            // Ocorrencia
            $table->string('severidade', 20)->nullable(); // baixa, media, alta, critica
            $table->string('titulo')->nullable();

            // Comuns
            $table->string('responsavel')->nullable();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_events');
    }
};
