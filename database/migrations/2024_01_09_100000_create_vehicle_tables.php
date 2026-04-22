<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->string('tipo', 30); // trator, caminhao, pick_up, motocicleta, implemento, colheitadeira, outros
            $table->string('nome');
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->integer('ano_fabricacao')->nullable();
            $table->integer('ano_modelo')->nullable();
            $table->string('placa', 10)->nullable()->unique();
            $table->string('renavam', 20)->nullable();
            $table->string('chassi', 50)->nullable();
            $table->string('cor')->nullable();
            $table->string('combustivel', 20)->nullable();
            $table->string('medidor', 20)->default('km'); // km, h
            $table->decimal('medidor_atual', 12, 2)->default(0);
            $table->decimal('valor_aquisicao', 14, 2)->nullable();
            $table->date('data_aquisicao')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('maintenance_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('tipo', 20); // preventiva, corretiva, revisao
            $table->string('descricao');
            $table->date('data_prevista')->nullable();
            $table->date('data_realizada')->nullable();
            $table->decimal('medidor', 12, 2)->nullable();
            $table->decimal('valor_pecas', 14, 2)->default(0);
            $table->decimal('valor_servico', 14, 2)->default(0);
            $table->decimal('valor_total', 14, 2)->default(0);
            $table->string('status', 20)->default('agendada')->index(); // agendada, em_andamento, concluida, cancelada
            $table->foreignId('transaction_id')->nullable()->constrained('financial_transactions')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_orders');
        Schema::dropIfExists('vehicles');
    }
};
