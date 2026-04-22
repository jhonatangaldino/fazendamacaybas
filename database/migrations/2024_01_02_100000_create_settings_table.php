<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações globais em formato chave/valor.
 * Ex.: nome da fazenda, cnpj, endereço, contatos, redes sociais, textos padrão do CMS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->default('geral')->index();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('type', 20)->default('string'); // string, text, json, image, bool, int
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('order_column')->default(0);
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
