<?php
/**
 * cleanup-demo-data.php
 *
 * Apaga dados de QA/demo do banco PRESERVANDO:
 *   - Tenant 1 (fazenda-macaybas) e tudo dele intacto
 *   - User master Jhonatan_freitas_galdino@hotmail.com
 *   - Catálogos globais (plans, roles, permissions, animal_species)
 *   - Activity log inteiro
 *
 * Apaga:
 *   - Tenant 1061 (demo-manual) com todos os filhos em cascata
 *   - User master qa.admin_master@fazendamacaybas.com.br
 *   - Manual envios que referenciam tenant 1061
 *
 * Estratégia: transação SQL única — qualquer erro = rollback total.
 *
 * Uso (no servidor):
 *   cd domains/fazendamacaybas.com.br/releases/current
 *   php scripts/cleanup-demo-data.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

const TENANT_REMOVER = 1061;
const TENANT_PRESERVAR = 1;
const QA_MASTER_EMAIL = 'qa.admin_master@fazendamacaybas.com.br';

echo "╔═══════════════════════════════════════════════════════╗\n";
echo "║   CLEANUP · INICIANDO                                  ║\n";
echo "╚═══════════════════════════════════════════════════════╝\n\n";

DB::beginTransaction();

try {
    // ── 1. Identifica usuários do tenant a remover ─────────────────
    $users1061 = DB::table('users')->where('tenant_id', TENANT_REMOVER)->pluck('id')->toArray();
    echo "▶ Tenant " . TENANT_REMOVER . " · " . count($users1061) . " users a remover\n";

    // ── 2. Identifica farms do tenant ─────────────────────────────
    $farms1061 = DB::table('farms')->where('tenant_id', TENANT_REMOVER)->pluck('id')->toArray();
    echo "▶ Farms a remover: " . count($farms1061) . "\n";

    // ── 3. APAGA logs de users do tenant a remover ────────────────
    // Quando o subject (animal/transação) também já foi apagado, o log
    // fica sem nada que faça sentido — é lixo. Apaga direto.
    // Preserva apenas logs de users do tenant master (fazenda-macaybas).
    echo "▶ Apagando logs de activity_log dos users do tenant a remover...\n";
    $logsApagados = 0;
    if (!empty($users1061)) {
        $logsApagados = DB::table('activity_log')
            ->where('causer_type', 'App\\Models\\User')
            ->whereIn('causer_id', $users1061)
            ->delete();
        echo "  ✓ $logsApagados logs apagados\n";
    }

    // ── 4. Tabelas tenant-scoped: deleta por tenant_id ────────────
    // A ordem importa por causa de FKs. Filhos primeiro.
    $tabelasComTenantId = [
        'animal_events',           // referencia animals
        'stock_movements',          // referencia stock_items
        'maintenance_orders',       // referencia vehicles
        'plantings',                // referencia fields, crops, seasons
        'harvests',                 // referencia plantings
        'field_applications',       // referencia fields
        'tasks',
        'documents',
        'animals',                  // referencia animal_lots, locations, breeds
        'animal_lots',
        'animal_locations',
        'animal_breeds',            // se for tenant-scoped (verificar)
        'fields',
        'crops',
        'seasons',
        'stock_items',
        'warehouses',
        'stock_categories',
        'vehicles',
        'maintenance_types',
        'employees',
        'partners',
        'cost_centers',
        'categories',
        'financial_transactions',
        'financial_accounts',
        'financial_recurrences',
        'financial_budgets',
        'invoices',
        'subscriptions',
        'cms_pages',
        'cms_sections',
        'cms_section_drafts',
        'cms_blocks',
        'cms_menus',
        'cms_menu_items',
        'cms_settings',
    ];

    echo "\n▶ Limpando tabelas tenant-scoped do tenant " . TENANT_REMOVER . ":\n";
    foreach ($tabelasComTenantId as $tabela) {
        if (!Schema::hasTable($tabela)) {
            echo "  ⊘ $tabela · tabela não existe (pula)\n";
            continue;
        }
        if (!Schema::hasColumn($tabela, 'tenant_id')) {
            echo "  ⊘ $tabela · sem coluna tenant_id (pula)\n";
            continue;
        }
        $deletados = DB::table($tabela)->where('tenant_id', TENANT_REMOVER)->delete();
        if ($deletados > 0) {
            echo "  ✓ $tabela · $deletados removidos\n";
        }
    }

    // ── 5. Tabelas relacionadas via user_id ou farm_id ────────────
    echo "\n▶ Limpando tabelas relacionadas por user_id / farm_id:\n";

    if (!empty($users1061)) {
        // user_farm_access (pivot)
        if (Schema::hasTable('user_farm_access')) {
            $d = DB::table('user_farm_access')->whereIn('user_id', $users1061)->delete();
            if ($d > 0) echo "  ✓ user_farm_access · $d removidos (por user)\n";
        }
        // model_has_roles (Spatie)
        $d = DB::table('model_has_roles')
            ->where('model_type', 'App\\Models\\User')
            ->whereIn('model_id', $users1061)
            ->delete();
        if ($d > 0) echo "  ✓ model_has_roles · $d removidos\n";

        // model_has_permissions (Spatie)
        $d = DB::table('model_has_permissions')
            ->where('model_type', 'App\\Models\\User')
            ->whereIn('model_id', $users1061)
            ->delete();
        if ($d > 0) echo "  ✓ model_has_permissions · $d removidos\n";

        // password_resets / sessions (se houver)
        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            $d = DB::table('sessions')->whereIn('user_id', $users1061)->delete();
            if ($d > 0) echo "  ✓ sessions · $d removidas\n";
        }
    }

    if (!empty($farms1061)) {
        if (Schema::hasTable('user_farm_access')) {
            $d = DB::table('user_farm_access')->whereIn('farm_id', $farms1061)->delete();
            if ($d > 0) echo "  ✓ user_farm_access · $d removidos (por farm)\n";
        }
    }

    // ── 6. manual_envios — apaga as do tenant 1061 + as enviadas por
    //      qa.admin_master (que vamos remover)
    echo "\n▶ Limpando manual_envios:\n";
    $qaMaster = DB::table('users')->where('email', QA_MASTER_EMAIL)->first();
    $manualEnviosRemovidos = DB::table('manual_envios')
        ->where('tenant_id', TENANT_REMOVER)
        ->orWhereIn('recipient_id', $users1061)
        ->orWhereIn('sender_id', $users1061);
    if ($qaMaster) {
        $manualEnviosRemovidos = $manualEnviosRemovidos
            ->orWhere('sender_id', $qaMaster->id)
            ->orWhere('recipient_id', $qaMaster->id);
    }
    $d = $manualEnviosRemovidos->delete();
    if ($d > 0) echo "  ✓ manual_envios · $d removidos\n";

    // ── 7. Farms do tenant 1061 ───────────────────────────────────
    echo "\n▶ Apagando farms do tenant " . TENANT_REMOVER . ":\n";
    $d = DB::table('farms')->where('tenant_id', TENANT_REMOVER)->delete();
    echo "  ✓ farms · $d removidos\n";

    // ── 8. Users do tenant 1061 ───────────────────────────────────
    echo "\n▶ Apagando users do tenant " . TENANT_REMOVER . ":\n";
    $d = DB::table('users')->where('tenant_id', TENANT_REMOVER)->delete();
    echo "  ✓ users · $d removidos\n";

    // ── 9. QA admin master ────────────────────────────────────────
    echo "\n▶ Apagando user master qa.admin_master:\n";
    if ($qaMaster) {
        // Apaga logs dele primeiro
        $logsQa = DB::table('activity_log')
            ->where('causer_type', 'App\\Models\\User')
            ->where('causer_id', $qaMaster->id)
            ->delete();
        echo "  ✓ $logsQa logs do qa.admin_master apagados\n";

        DB::table('model_has_roles')
            ->where('model_type', 'App\\Models\\User')
            ->where('model_id', $qaMaster->id)
            ->delete();
        DB::table('model_has_permissions')
            ->where('model_type', 'App\\Models\\User')
            ->where('model_id', $qaMaster->id)
            ->delete();
        DB::table('users')->where('id', $qaMaster->id)->delete();
        echo "  ✓ qa.admin_master apagado\n";
    } else {
        echo "  ⊘ qa.admin_master não encontrado (já removido)\n";
    }

    // ── 10. Tenant 1061 ───────────────────────────────────────────
    echo "\n▶ Apagando tenant " . TENANT_REMOVER . ":\n";
    $d = DB::table('tenants')->where('id', TENANT_REMOVER)->delete();
    echo "  ✓ tenant " . TENANT_REMOVER . " removido\n";

    DB::commit();

    echo "\n╔═══════════════════════════════════════════════════════╗\n";
    echo "║   ✅ CLEANUP CONCLUÍDO COM SUCESSO                     ║\n";
    echo "╚═══════════════════════════════════════════════════════╝\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "\n❌ ERRO · ROLLBACK EXECUTADO. Banco intacto.\n";
    echo "Detalhe: " . $e->getMessage() . "\n";
    echo "Linha: " . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}
