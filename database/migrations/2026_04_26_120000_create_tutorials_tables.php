<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistema de tutorial in-app contextual.
 *
 *  - tutorials: catálogo definido em código (seed) com chave, título, passos e
 *    permissões necessárias. Permite o conteúdo ser versionado no git.
 *  - user_tutorial_states: rastreia o status por usuário (pendente/completado/dispensado)
 *    + when para exibir novamente (snooze 15d se sair sem dispensar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutorials', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();             // ex: 'hub.boas-vindas', 'wizard.despesa.intro'
            $table->string('titulo', 150);
            $table->string('rota', 120);                      // ex: '/admin/inicio'
            $table->json('passos');                           // [{titulo, descricao, target_selector?}]
            $table->json('permissions_required')->nullable(); // ['operational.financeiro.view', ...]
            $table->boolean('is_active')->default(true);
            $table->integer('order_column')->default(0);
            $table->timestamps();
        });

        Schema::create('user_tutorial_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tutorial_key', 80);
            $table->enum('status', ['pendente', 'completado', 'dispensado'])->default('pendente');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();   // se sair sem dispensar, +15d
            $table->timestamps();

            $table->unique(['user_id', 'tutorial_key']);
            $table->index(['user_id', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tutorial_states');
        Schema::dropIfExists('tutorials');
    }
};
