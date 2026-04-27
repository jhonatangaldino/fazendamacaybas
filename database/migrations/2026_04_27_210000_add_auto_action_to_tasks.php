<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarefas que disparam um wizard ao serem concluídas.
 *
 * Caso de uso: o ExameToqueController cria a tarefa "Preparar maternidade"
 * com auto_action='parto'. Quando o usuário clica em "Concluir" essa tarefa,
 * em vez de só fechar, o sistema redireciona para o wizard de Registrar Parto
 * (que cadastra os filhotes, vincula à mãe, cria lote da leitegada se múltiplo,
 * agenda tarefa de desmame).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('auto_action', 30)->nullable()->after('modulo');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('auto_action');
        });
    }
};
