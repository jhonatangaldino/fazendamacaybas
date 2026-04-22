<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->string('nome');
            $table->string('localizacao')->nullable();
            $table->string('responsavel')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('codigo', 50)->unique();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('unidade', 10); // kg, l, un, sc, cx, m
            $table->string('marca')->nullable();
            $table->decimal('estoque_minimo', 14, 4)->default(0);
            $table->decimal('estoque_maximo', 14, 4)->nullable();
            $table->decimal('custo_medio', 14, 4)->default(0);
            $table->string('tipo', 30)->index(); // insumo, medicamento, racao, ferramenta, peca, combustivel, material
            $table->string('registro_ms')->nullable(); // para medicamentos veterinários
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('tipo', 20)->index(); // entrada, saida, ajuste, transferencia
            $table->string('motivo')->nullable(); // compra, uso, perda, vencimento, inventario
            $table->date('data');
            $table->decimal('quantidade', 14, 4);
            $table->decimal('valor_unitario', 14, 4)->default(0);
            $table->decimal('valor_total', 14, 2)->default(0);
            $table->string('numero_documento')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('financial_transactions')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('warehouses');
    }
};
