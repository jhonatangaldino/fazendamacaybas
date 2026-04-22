<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_species', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique(); // Bovino, Equino, Suíno, Caprino, Ovino, Ave
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('animal_breeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('species_id')->constrained('animal_species')->cascadeOnDelete();
            $table->string('nome');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['species_id', 'nome']);
        });

        Schema::create('animal_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->string('codigo', 30)->unique();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('finalidade', 30)->nullable(); // corte, leite, reprodução, recria
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('animals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->foreignId('species_id')->constrained('animal_species')->restrictOnDelete();
            $table->foreignId('breed_id')->nullable()->constrained('animal_breeds')->nullOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('animal_lots')->nullOnDelete();
            $table->foreignId('mae_id')->nullable()->constrained('animals')->nullOnDelete();
            $table->foreignId('pai_id')->nullable()->constrained('animals')->nullOnDelete();
            $table->string('identificacao', 30)->unique(); // brinco ou similar
            $table->string('nome')->nullable();
            $table->string('sexo', 1); // M, F
            $table->date('data_nascimento')->nullable();
            $table->decimal('peso_nascimento', 8, 2)->nullable();
            $table->decimal('peso_atual', 10, 2)->nullable();
            $table->string('origem', 30)->default('nascido')->index(); // nascido, compra
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete(); // se comprado
            $table->date('data_aquisicao')->nullable();
            $table->decimal('valor_aquisicao', 12, 2)->nullable();
            $table->string('status', 20)->default('ativo')->index(); // ativo, vendido, morto, abatido, transferido
            $table->date('data_saida')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('animal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->nullable()->constrained('animals')->cascadeOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('animal_lots')->cascadeOnDelete();
            $table->string('tipo', 30)->index(); // pesagem, vacinacao, medicacao, reproducao, nascimento, morte, compra, venda, movimentacao
            $table->date('data');
            $table->decimal('peso', 10, 2)->nullable();
            $table->string('vacina')->nullable();
            $table->string('medicamento')->nullable();
            $table->decimal('dose', 10, 3)->nullable();
            $table->string('via_aplicacao', 30)->nullable();
            $table->string('responsavel')->nullable();
            $table->decimal('valor', 12, 2)->nullable();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignId('lot_origem_id')->nullable()->constrained('animal_lots')->nullOnDelete();
            $table->foreignId('lot_destino_id')->nullable()->constrained('animal_lots')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_events');
        Schema::dropIfExists('animals');
        Schema::dropIfExists('animal_lots');
        Schema::dropIfExists('animal_breeds');
        Schema::dropIfExists('animal_species');
    }
};
