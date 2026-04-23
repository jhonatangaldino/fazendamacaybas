<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R2.3.5 — Blindagem estrutural de tenant_id.
 *
 * Converte `tenant_id` de NULLABLE DEFAULT 1 para NOT NULL DEFAULT 1 em todas
 * as tabelas operacionais. A partir daqui, MySQL rejeita qualquer INSERT que
 * tente `tenant_id = NULL` — elimina a possibilidade de linhas órfãs que
 * sumiriam no global scope da R2.4.
 *
 * PRESERVADAS como NULLABLE por design:
 *   - users          (master global é identificado por tenant_id = NULL)
 *   - activity_log   (logs antigos pré-R1.3 tinham NULL; manter compatibilidade)
 *
 * PRÉ-REQUISITO (auditado antes deste deploy):
 *   - Zero registros com tenant_id NULL em qualquer das 32 tabelas afetadas
 *   - Tenant id=1 (Fazenda Macaybas) existe e está ativo
 *
 * COMPORTAMENTO PRESERVADO:
 *   - DEFAULT 1 mantido → código legado que insere sem `tenant_id` continua
 *     funcionando (DB preenche automático)
 *   - Trait BelongsToTenant (R2.3) continua sobrescrevendo com app('tenant_id')
 *     quando disponível, tendo precedência sobre o DEFAULT
 *   - FK tenant_id → tenants.id preservada (não é tocada pelo MODIFY)
 *
 * FAIL-FAST:
 *   O up() re-valida ausência de NULL em cada tabela antes de alterar.
 *   Se alguma tabela tiver NULL entre a auditoria e o deploy, a migration
 *   aborta com mensagem explícita, sem deixar estado parcial.
 *
 * ROLLBACK:
 *   down() reverte para NULLABLE DEFAULT 1 (estado anterior à R2.3.5).
 */
return new class extends Migration
{
    /**
     * 32 tabelas que recebem tenant_id NOT NULL.
     * Lista fechada — espelha exatamente a R1.2, menos users e activity_log.
     */
    private array $tables = [
        // Core
        'farms',
        // Cadastros mestres
        'partners',
        'categories',
        'animal_species',
        'animal_breeds',
        'crops',
        'seasons',
        // Rebanho
        'animal_lots',
        'animals',
        'animal_events',
        // Agrícola
        'fields',
        'plantings',
        'harvests',
        'field_applications',
        // Estoque
        'warehouses',
        'stock_items',
        'stock_movements',
        // Máquinas
        'vehicles',
        'maintenance_orders',
        'vehicle_events',
        // Financeiro
        'financial_accounts',
        'financial_transactions',
        'financial_recurrences',
        // Pessoas
        'employees',
        'tasks',
        'task_assignments',
        'checklists',
        'checklist_items',
        // Documentos
        'document_categories',
        'documents',
        // Infra
        'barcode_lookups',
        'menu_usage',
    ];

    public function up(): void
    {
        /* ─── Fail-fast: re-auditar NULL antes de alterar ────────────── */
        $violations = [];
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $nulls = DB::table($table)->whereNull('tenant_id')->count();
            if ($nulls > 0) {
                $violations[$table] = $nulls;
            }
        }

        if (! empty($violations)) {
            $msg = "R2.3.5: abortada — tabelas com tenant_id NULL:\n";
            foreach ($violations as $t => $n) {
                $msg .= "  - {$t}: {$n} registros\n";
            }
            $msg .= "Execute backfill antes:\n";
            foreach ($violations as $t => $n) {
                $msg .= "  UPDATE {$t} SET tenant_id=1 WHERE tenant_id IS NULL;\n";
            }
            throw new \RuntimeException($msg);
        }

        /* ─── Conversão NULLABLE → NOT NULL ──────────────────────────── */
        // DDL em MySQL é auto-commit (não envolver em DB::transaction).
        // MODIFY preserva FK existente em `tenant_id` (Laravel/Schema R1.2).
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            DB::statement(
                "ALTER TABLE `{$table}` MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1"
            );
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            DB::statement(
                "ALTER TABLE `{$table}` MODIFY `tenant_id` BIGINT UNSIGNED NULL DEFAULT 1"
            );
        }
    }
};
