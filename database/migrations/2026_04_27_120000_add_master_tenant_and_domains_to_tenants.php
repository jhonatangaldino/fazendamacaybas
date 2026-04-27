<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reestruturação 2026-04-27 — multi-host + tenant master único.
 *
 * Adiciona:
 *   - is_master_tenant: flag para identificar o "tenant principal" da plataforma.
 *     A landing pública do domínio raiz (fazendamacaybas.com.br) renderiza o
 *     CMS deste tenant. **Apenas 1 tenant pode ter true.**
 *
 *   - domains: lista de hosts próprios aceitos para esse tenant
 *     (ex.: ["fazendadotiao.com.br", "www.fazendadotiao.com.br"]).
 *     Quando um request chega com Host = um destes, o middleware RouteByHost
 *     resolve o tenant correto e renderiza o CMS dele com URL bonita.
 *
 * Regra única-do-master via partial unique index:
 *   CREATE UNIQUE INDEX ... WHERE is_master_tenant = TRUE
 *
 * MySQL 8 não suporta partial index nativo, então usamos generated column
 * que vira NULL quando is_master_tenant = false (NULLs não conflitam em
 * UNIQUE indexes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_master_tenant')->default(false)->after('is_active');
            $table->json('domains')->nullable()->after('is_master_tenant');
        });

        // Partial unique constraint via generated column.
        // master_singleton = 1 quando é master, NULL caso contrário.
        // UNIQUE em master_singleton garante que só 1 tenant tem master_singleton=1.
        DB::statement("
            ALTER TABLE tenants
            ADD COLUMN master_singleton TINYINT GENERATED ALWAYS AS
                (CASE WHEN is_master_tenant = 1 THEN 1 ELSE NULL END) VIRTUAL,
            ADD UNIQUE INDEX tenants_master_singleton_unique (master_singleton)
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tenants DROP INDEX tenants_master_singleton_unique");
        DB::statement("ALTER TABLE tenants DROP COLUMN master_singleton");

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['is_master_tenant', 'domains']);
        });
    }
};
