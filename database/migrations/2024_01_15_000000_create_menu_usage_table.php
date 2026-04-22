<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registra a frequência com que cada usuário acessa cada item do menu de Operação.
 * Permite reordenar dinamicamente a sidebar colocando no topo os módulos mais usados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('menu_key', 80);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'menu_key']);
            $table->index(['user_id', 'hits']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_usage');
    }
};
