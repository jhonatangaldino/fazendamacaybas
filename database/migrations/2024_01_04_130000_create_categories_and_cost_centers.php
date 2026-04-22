<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('tipo', 30)->index(); // financeiro_receita, financeiro_despesa, estoque, documento, etc.
            $table->string('nome');
            $table->string('slug');
            $table->string('cor', 10)->nullable();
            $table->string('icon', 50)->nullable();
            $table->unsignedInteger('order_column')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tipo', 'slug']);
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('categories');
    }
};
