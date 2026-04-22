<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_menus', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nome');
            $table->string('local', 30)->index(); // header, footer, sidebar
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cms_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('cms_menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cms_menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->string('url');
            $table->string('target', 20)->default('_self');
            $table->string('icon')->nullable();
            $table->unsignedInteger('order_column')->default(0)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_menu_items');
        Schema::dropIfExists('cms_menus');
    }
};
