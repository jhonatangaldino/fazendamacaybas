<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona short_name em roles para exibição compacta em listagens/badges.
 * `name`        = identificador técnico (admin_master, dono_fazenda, ...)
 * `short_name`  = nome curto para UI (ex: "Admin", "Dono")
 * `description` = descrição longa, aparece em tooltip/detalhe
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('short_name', 40)->nullable()->after('name');
        });

        // Backfill: usa nome técnico como fallback nos registros existentes
        $defaults = [
            'admin_master' => 'Admin',
            'dono_fazenda' => 'Dono',
            'gerente' => 'Gerente',
            'financeiro' => 'Financeiro',
            'veterinario' => 'Veterinário',
            'agronomo' => 'Agrônomo',
            'administrativo' => 'Administrativo',
            'funcionario' => 'Funcionário',
            'auditor' => 'Auditor',
            'visitante' => 'Visitante',
        ];

        foreach ($defaults as $name => $short) {
            DB::table('roles')->where('name', $name)->update(['short_name' => $short]);
        }

        // Qualquer role customizado fica com short = nome técnico humanizado
        DB::table('roles')->whereNull('short_name')->update([
            'short_name' => DB::raw("UPPER(SUBSTRING(name, 1, 1))"),
        ]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('short_name');
        });
    }
};
