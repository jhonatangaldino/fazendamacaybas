<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria 2026-04-27 — geo do tenant para zona rural.
 *
 * Endereço textual em zona rural geralmente não tem rua/número padrão. O que
 * funciona pra mostrar a fazenda no mapa é coordenada decimal (latitude/
 * longitude). Adicionamos os 3 campos no `tenants` direto:
 *  - endereco: texto livre (ex.: "Rod. MG-262, km 12 — Bairro Boa Vista")
 *  - latitude / longitude: decimal com 7 casas (~1cm de precisão)
 *
 * Esses campos espelham (e complementam) os settings `landing.map.*` que já
 * existiam — o TenantController vai sincronizar para manter ambos coerentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('endereco')->nullable()->after('estado');
            $table->decimal('latitude', 10, 7)->nullable()->after('endereco');
            $table->decimal('longitude', 11, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['endereco', 'latitude', 'longitude']);
        });
    }
};
