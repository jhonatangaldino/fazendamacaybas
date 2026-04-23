<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master — cria cliente com dados comerciais completos (UX V1 do master).
 *
 * Adiciona `cidade` e `estado` em `tenants`. email e telefone já existiam
 * desde R1.1 e só não eram expostos no form — a form passa a cobri-los.
 *
 * Observações:
 *   - Ambos NULLABLE: cadastros antigos (o seed do tenant 1 entre eles)
 *     continuam válidos sem edição.
 *   - `estado` com length 2 reforça UF. A UI valida client-side e o backend
 *     re-valida — sem tabela de UFs por simplicidade (o master digita).
 *   - Sem FK para uma tabela de municípios: o master vende o sistema, não
 *     operamos uma base de geo. Se for necessário depois, migração aditiva.
 *
 * Idempotente via `Schema::hasColumn`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            if (! Schema::hasColumn('tenants', 'cidade')) {
                $t->string('cidade', 100)->nullable()->after('telefone');
            }
            if (! Schema::hasColumn('tenants', 'estado')) {
                $t->string('estado', 2)->nullable()->after('cidade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            if (Schema::hasColumn('tenants', 'estado')) {
                $t->dropColumn('estado');
            }
            if (Schema::hasColumn('tenants', 'cidade')) {
                $t->dropColumn('cidade');
            }
        });
    }
};
