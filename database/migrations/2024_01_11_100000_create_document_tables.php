<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->string('slug')->unique();
            $table->string('cor', 10)->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('document_categories')->nullOnDelete();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->string('path');
            $table->string('nome_arquivo');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->date('data_documento')->nullable();
            $table->date('data_vencimento')->nullable();
            $table->nullableMorphs('related'); // pode ser ligado a animal, veículo, funcionário, transação, etc.
            $table->boolean('is_confidential')->default(false);
            $table->json('tags')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_categories');
    }
};
