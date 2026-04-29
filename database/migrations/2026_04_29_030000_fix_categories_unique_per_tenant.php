<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix BUG-CRIT-F6-01 (QA Deep 2026-04-29) · onboarding 100% quebrado.
 *
 * O migration original `2024_01_04_130000_create_categories_and_cost_centers`
 * declarou `unique(['tipo', 'slug'])` GLOBAL. Quando o tenant_id foi adicionado
 * em `2024_02_01_000002`, o unique antigo nunca foi atualizado.
 *
 * Resultado: TenantOperationalSeed insere "Insumos agrícolas" pra tenant
 * novo → MySQL recusa porque já existe pra tenant 1061. Plataforma SaaS
 * NÃO ACEITAVA NENHUM tenant novo. POST /master/tenants → HTTP 500.
 *
 * Fix: drop unique antigo, cria unique composto `(tenant_id, tipo, slug)`.
 * Cada tenant pode ter seu próprio slug "insumos-agricolas".
 *
 * Idempotente: checa se constraint existe antes de tentar dropar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Drop unique antigo se existir (idempotente · usa raw pra
            // evitar erro caso já tenha sido removido)
            try {
                $table->dropUnique('categories_tipo_slug_unique');
            } catch (\Throwable $e) {
                // já não existia — segue
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            // Novo unique scoped por tenant
            $table->unique(['tenant_id', 'tipo', 'slug'], 'categories_tenant_tipo_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            try {
                $table->dropUnique('categories_tenant_tipo_slug_unique');
            } catch (\Throwable $e) {
                // já removido
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['tipo', 'slug'], 'categories_tipo_slug_unique');
        });
    }
};
