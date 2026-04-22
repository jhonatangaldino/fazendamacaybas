<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->string('categoria', 20)->nullable()->after('status')->index();
            // valores: 'leite', 'corte', 'reproducao', 'misto', 'pet', 'servico'
            $table->string('numero_registro', 50)->nullable()->after('nome');
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->dropColumn(['categoria', 'numero_registro']);
        });
    }
};
