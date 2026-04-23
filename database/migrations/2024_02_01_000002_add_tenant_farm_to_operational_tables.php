<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R1.2 — Adiciona `tenant_id` (e `farm_id` onde faltar) em ~34 tabelas operacionais.
 *
 * Estratégia:
 *   - Todas as colunas novas são NULLABLE + DEFAULT 1 (conforme decisão D/C da R1).
 *     Isso garante que código legado que insere sem `tenant_id` continua funcionando —
 *     novos registros caem automaticamente no tenant Macaybas (id=1, semeado na R1.3).
 *   - `users.tenant_id` é NULLABLE sem default: master global é identificado por NULL.
 *   - Idempotência: usa `Schema::hasColumn()` antes de cada ADD — seguro rodar 2x.
 *   - MySQL 8: ADD COLUMN NULLABLE DEFAULT usa ALGORITHM=INSTANT (sem lock real).
 *     FK constraints usam INPLACE (leituras não bloqueiam).
 *   - Cada `Schema::table()` é um ALTER isolado (transação curta por tabela).
 *   - Nenhum índice composto nesta migration — ficam para R2 quando o enforcement
 *     entrar. Aqui só precisamos da coluna + FK.
 *   - Nenhum comportamento existente é afetado. Sistema continua operando como
 *     single-tenant até a R2 ativar os scopes globais.
 *
 * Tabelas tocadas: 34
 *   - users, farms                                         (2)
 *   - cadastros: partners, categories, animal_species,
 *     animal_breeds, crops, seasons                        (6)
 *   - rebanho: animal_lots, animals, animal_events          (3)
 *   - agrícola: fields, plantings, harvests,
 *     field_applications                                   (4)
 *   - estoque: warehouses, stock_items, stock_movements    (3)
 *   - máquinas: vehicles, maintenance_orders,
 *     vehicle_events                                       (3)
 *   - financeiro: financial_accounts, financial_transactions,
 *     financial_recurrences                                (3)
 *   - pessoas: employees, tasks, task_assignments,
 *     checklists, checklist_items                          (5)
 *   - documentos: document_categories, documents            (2)
 *   - infra: activity_log, barcode_lookups, menu_usage     (3)
 *
 * Rollback: down() remove apenas as colunas adicionadas pela R1.2, preservando
 *   `farm_id` das tabelas que JÁ tinham farm_id na schema original.
 */
return new class extends Migration
{
    /**
     * Lista de tabelas que receberam `farm_id` novo pela R1.2.
     * Usada no down() para não dropar o farm_id original das outras.
     */
    private array $farmIdAddedByR12 = [
        'animal_events',
        'plantings',
        'harvests',
        'field_applications',
        'stock_items',
        'stock_movements',
        'maintenance_orders',
        'vehicle_events',
        'checklists',
        'documents',
        'financial_accounts',
        'financial_transactions',
        'financial_recurrences',
        'barcode_lookups',
    ];

    public function up(): void
    {
        /* ─── Core ──────────────────────────────────────────────────────── */
        // users: tenant_id nullable SEM default → master global é NULL
        $this->addTenant('users', default: null);

        // farms: tenant_id default 1 (toda farm pertence a um tenant)
        $this->addTenant('farms', default: 1);

        /* ─── Cadastros mestres (só tenant_id) ──────────────────────────── */
        foreach (['partners', 'categories', 'animal_species', 'animal_breeds', 'crops', 'seasons'] as $table) {
            $this->addTenant($table);
        }

        /* ─── Rebanho ───────────────────────────────────────────────────── */
        $this->addTenant('animal_lots');                            // farm_id já existe
        $this->addTenant('animals');                                // farm_id já existe
        $this->addTenant('animal_events');
        $this->addFarm('animal_events');                            // novo

        /* ─── Agrícola ──────────────────────────────────────────────────── */
        $this->addTenant('fields');                                 // farm_id já existe
        $this->addTenant('plantings');
        $this->addFarm('plantings');                                // novo
        $this->addTenant('harvests');
        $this->addFarm('harvests');                                 // novo
        $this->addTenant('field_applications');
        $this->addFarm('field_applications');                       // novo

        /* ─── Estoque ───────────────────────────────────────────────────── */
        $this->addTenant('warehouses');                             // farm_id já existe
        $this->addTenant('stock_items');
        $this->addFarm('stock_items');                              // novo (nullable: item pode ser do tenant sem farm específica)
        $this->addTenant('stock_movements');
        $this->addFarm('stock_movements');                          // novo

        /* ─── Máquinas ──────────────────────────────────────────────────── */
        $this->addTenant('vehicles');                               // farm_id já existe
        $this->addTenant('maintenance_orders');
        $this->addFarm('maintenance_orders');                       // novo
        if (Schema::hasTable('vehicle_events')) {
            $this->addTenant('vehicle_events');
            $this->addFarm('vehicle_events');                       // novo
        }

        /* ─── Financeiro ────────────────────────────────────────────────── */
        $this->addTenant('financial_accounts');
        $this->addFarm('financial_accounts');                       // novo (nullable)
        $this->addTenant('financial_transactions');
        $this->addFarm('financial_transactions');                   // novo
        $this->addTenant('financial_recurrences');
        $this->addFarm('financial_recurrences');                    // novo

        /* ─── Pessoas ───────────────────────────────────────────────────── */
        $this->addTenant('employees');                              // farm_id já existe
        $this->addTenant('tasks');                                  // farm_id já existe
        if (Schema::hasTable('task_assignments')) {
            $this->addTenant('task_assignments');
        }
        $this->addTenant('checklists');
        $this->addFarm('checklists');                               // novo
        if (Schema::hasTable('checklist_items')) {
            $this->addTenant('checklist_items');
        }

        /* ─── Documentos ────────────────────────────────────────────────── */
        $this->addTenant('document_categories');
        $this->addTenant('documents');
        $this->addFarm('documents');                                // novo

        /* ─── Infra / auditoria ─────────────────────────────────────────── */
        if (Schema::hasTable('activity_log')) {
            $this->addTenant('activity_log', default: null);        // legacy pode ter null
        }
        if (Schema::hasTable('barcode_lookups')) {
            $this->addTenant('barcode_lookups');
            $this->addFarm('barcode_lookups');                      // novo
        }
        if (Schema::hasTable('menu_usage')) {
            $this->addTenant('menu_usage');                         // não tem farm_id (é por usuário)
        }
    }

    public function down(): void
    {
        // ORDEM: dropar farm_id primeiro (só das tabelas que a R1.2 adicionou),
        // depois dropar tenant_id de tudo.

        foreach ($this->farmIdAddedByR12 as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'farm_id')) {
                // Só dropa se essa tabela teve farm_id adicionado pela R1.2
                $this->dropConstrainedIfExists($table, 'farm_id');
            }
        }

        // Lista completa de tabelas com tenant_id
        $tenantTables = [
            'users', 'farms',
            'partners', 'categories', 'animal_species', 'animal_breeds', 'crops', 'seasons',
            'animal_lots', 'animals', 'animal_events',
            'fields', 'plantings', 'harvests', 'field_applications',
            'warehouses', 'stock_items', 'stock_movements',
            'vehicles', 'maintenance_orders', 'vehicle_events',
            'financial_accounts', 'financial_transactions', 'financial_recurrences',
            'employees', 'tasks', 'task_assignments', 'checklists', 'checklist_items',
            'document_categories', 'documents',
            'activity_log', 'barcode_lookups', 'menu_usage',
        ];

        foreach ($tenantTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                $this->dropConstrainedIfExists($table, 'tenant_id');
            }
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
     * Helpers privados
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Adiciona coluna tenant_id (nullable) com FK para tenants.
     * Idempotente: se já existe, não faz nada.
     */
    private function addTenant(string $table, int|null $default = 1, string $after = 'id'): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($default, $after) {
            $col = $t->foreignId('tenant_id')->nullable()->after($after);
            if ($default !== null) {
                $col->default($default);
            }
            $col->constrained('tenants')->cascadeOnDelete();
        });
    }

    /**
     * Adiciona coluna farm_id (nullable) com FK para farms.
     * Idempotente: se já existe, não faz nada.
     */
    private function addFarm(string $table, string $after = 'tenant_id'): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'farm_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($after) {
            $t->foreignId('farm_id')
                ->nullable()
                ->after($after)
                ->constrained('farms')
                ->nullOnDelete();
        });
    }

    /**
     * Dropa FK + coluna, com segurança se não existir.
     */
    private function dropConstrainedIfExists(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($column) {
            $t->dropConstrainedForeignId($column);
        });
    }
};
