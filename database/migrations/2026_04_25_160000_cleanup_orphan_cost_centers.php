<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BLOCO 3.1 — Limpeza de cost_centers órfãos.
 *
 * Contexto: a migration 2026_04_25_140000 adicionou `tenant_id` em
 * `cost_centers` e tentou fazer backfill via JOIN com `financial_transactions`.
 * Resultado: 5 cost_centers (GERAL, REBANHO, AGRICOLA, MAQUINAS, ADMIN) ficaram
 * com `tenant_id NULL` — nunca foram referenciados por nenhuma transação.
 *
 * Origem: `database/seeders/CategorySeeder.php` os criava como "defaults
 * globais" antes do sistema ser multi-tenant. Hoje cada tenant gerencia seus
 * próprios centros de custo, então os órfãos não servem para ninguém.
 *
 * Por que limpar agora:
 *   • Já são INVISÍVEIS pelo `BelongsToTenantScope` (zero risco de uso futuro)
 *   • Mas poluem listagens administrativas com `withoutGlobalScopes()`
 *   • Mantê-los induz confusão sobre "tem registro mas não aparece"
 *
 * Forward-only: down() é no-op. Se precisar recriar, rode CategorySeeder com
 * tenant_id apropriado (que o seeder já não faz mais — vide commit do BLOCO 3.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        $deleted = DB::table('cost_centers')
            ->whereNull('tenant_id')
            ->delete();

        if ($deleted > 0) {
            echo "  Removidos {$deleted} cost_centers órfãos (tenant_id NULL).\n";
        }
    }

    public function down(): void
    {
        // Forward-only: dados removidos não são restaurados. Se precisar,
        // recriar manualmente com tenant_id apropriado (cada tenant tem o seu).
    }
};
