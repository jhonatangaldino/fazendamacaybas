<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->string('prioridade', 10)->default('media'); // baixa, media, alta, urgente
            $table->string('status', 20)->default('pendente')->index(); // pendente, em_andamento, concluida, cancelada, atrasada
            $table->date('data_inicio')->nullable();
            $table->date('data_vencimento')->nullable();
            $table->timestamp('concluida_em')->nullable();
            $table->string('modulo', 30)->nullable()->index(); // rebanho, agricola, estoque, maquinas, geral
            $table->nullableMorphs('related');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            $table->unique(['task_id', 'employee_id']);
        });

        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('checklists')->cascadeOnDelete();
            $table->string('descricao');
            $table->boolean('is_done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('order_column')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklists');
        Schema::dropIfExists('task_assignments');
        Schema::dropIfExists('tasks');
    }
};
