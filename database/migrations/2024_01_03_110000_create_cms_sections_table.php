<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seções da página (hero, sobre, galeria, depoimentos, newsletter, rodapé).
 * Cada seção referencia um "type" conhecido (hero, about, gallery, etc.) e possui
 * uma versão publicada e uma versão rascunho em JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->string('type', 50)->index(); // hero, about, gallery, testimonials, newsletter, footer, header, cta, contact, features
            $table->string('nome');
            $table->json('published_data')->nullable();
            $table->json('draft_data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('has_draft')->default(false);
            $table->unsignedInteger('order_column')->default(0)->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_sections');
    }
};
