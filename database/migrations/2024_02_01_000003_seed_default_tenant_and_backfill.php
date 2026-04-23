<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R1.3 — Seeder do tenant default (Fazenda Macaybas) + backfill de segurança.
 *
 * REVISÃO DE BLINDAGEM (pré-R1.4):
 *   - Todos os INSERT trocados por `updateOrInsert` com chave natural
 *     (garante re-execução segura após falha parcial)
 *   - TODA a migration (seed + backfill + verificações) dentro de
 *     `DB::transaction()` único — rollback automático em qualquer erro
 *   - `$now` congelado em variável única para consistência temporal
 *
 * Objetivos (inalterados):
 *   1) Plano "Essencial" (id=1) — grátis, vitalício para o tenant zero
 *   2) Tenant id=1 "Fazenda Macaybas" vinculado ao plano
 *   3) Subscription ativa (sem expiração)
 *   4) Backfill de `tenant_id = 1` em tabelas sem DEFAULT (users, activity_log)
 *   5) Backfill defensivo em 32 tabelas (DEFAULT 1 da R1.2 já cobriu — garantia)
 *   6) Validação fail-fast da integridade referencial
 *
 * NÃO altera:
 *   - Estrutura de tabelas (zero Schema::table)
 *   - Foreign keys (nenhuma constraint tocada)
 *   - Models, controllers, queries existentes
 *
 * Rollback: down() desfaz o seed preservando os backfills de DEFAULT 1
 * (responsabilidade da R1.2 — não dessa migration).
 */
return new class extends Migration
{
    /** Tabelas que receberam DEFAULT 1 via R1.2 — backfill defensivo. */
    private array $defensiveBackfillTables = [
        'farms',
        'partners', 'categories', 'animal_species', 'animal_breeds', 'crops', 'seasons',
        'animal_lots', 'animals', 'animal_events',
        'fields', 'plantings', 'harvests', 'field_applications',
        'warehouses', 'stock_items', 'stock_movements',
        'vehicles', 'maintenance_orders', 'vehicle_events',
        'financial_accounts', 'financial_transactions', 'financial_recurrences',
        'employees', 'tasks', 'task_assignments', 'checklists', 'checklist_items',
        'document_categories', 'documents',
        'barcode_lookups', 'menu_usage',
    ];

    public function up(): void
    {
        $now = now(); // congelado — uma referência temporal única para toda a migration

        DB::transaction(function () use ($now) {

            /* ─── 1) Plano Essencial (id=1) — grátis vitalício ────────────── */
            DB::table('plans')->updateOrInsert(
                ['id' => 1],
                [
                    'slug' => 'essencial',
                    'nome' => 'Essencial',
                    'preco_mensal' => 0.00,
                    'max_farms' => 1,
                    'max_users' => 10,
                    'features' => json_encode([
                        'rebanho', 'estoque', 'financeiro', 'agricola', 'maquinas',
                        'funcionarios', 'documentos', 'relatorios', 'scanner_barcode',
                    ]),
                    'is_active' => true,
                    'sort_order' => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            /* ─── 2) Tenant id=1 — Fazenda Macaybas ───────────────────────── */
            DB::table('tenants')->updateOrInsert(
                ['id' => 1],
                [
                    'nome' => 'Fazenda Macaybas',
                    'slug' => 'macaybas',
                    'documento' => null,
                    'email' => null,
                    'telefone' => null,
                    'plan_id' => 1,
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            /* ─── 3) Subscription ativa (vitalícia — sem expiração) ───────── */
            DB::table('subscriptions')->updateOrInsert(
                ['tenant_id' => 1], // UNIQUE constraint — 1 sub por tenant
                [
                    'plan_id' => 1,
                    'status' => 'active',
                    'started_at' => $now,
                    'trial_ends_at' => null,
                    'current_period_start' => $now,
                    'current_period_end' => null, // null = sem expiração
                    'canceled_at' => null,
                    'meta' => json_encode([
                        'note' => 'Tenant seed inicial — assinatura vitalícia sem cobrança',
                        'seeded_by' => 'migration:2024_02_01_000003',
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            /* ─── 4) Backfill explícito (tabelas sem DEFAULT) ─────────────── */
            if (Schema::hasTable('users')) {
                DB::table('users')->whereNull('tenant_id')->update(['tenant_id' => 1]);
            }
            if (Schema::hasTable('activity_log')) {
                DB::table('activity_log')->whereNull('tenant_id')->update(['tenant_id' => 1]);
            }

            /* ─── 5) Backfill defensivo (DEFAULT 1 já aplicou — garantia) ─── */
            foreach ($this->defensiveBackfillTables as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                    DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => 1]);
                }
            }

            /* ─── 6) Verificações fail-fast (dentro da transaction) ────────── */
            // Se qualquer throw aqui dispara, a transaction inteira é revertida
            if (! DB::table('tenants')->where('id', 1)->exists()) {
                throw new \RuntimeException('R1.3: tenant id=1 não foi criado — estado inconsistente');
            }

            if (! DB::table('subscriptions')->where('tenant_id', 1)->where('status', 'active')->exists()) {
                throw new \RuntimeException('R1.3: subscription ativa do tenant 1 não encontrada');
            }

            $orfaos = DB::table('animals')
                ->whereNotNull('tenant_id')
                ->whereNotIn('tenant_id', function ($q) {
                    $q->select('id')->from('tenants');
                })
                ->count();

            if ($orfaos > 0) {
                throw new \RuntimeException("R1.3: {$orfaos} animais com tenant_id apontando para tenant inexistente");
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            /* ─── Reverte backfill explícito ───────────────────────────── */
            // (tabelas com DEFAULT 1 mantêm tenant_id=1 — responsabilidade da R1.2)
            if (Schema::hasTable('users')) {
                DB::table('users')->where('tenant_id', 1)->update(['tenant_id' => null]);
            }
            if (Schema::hasTable('activity_log')) {
                DB::table('activity_log')->where('tenant_id', 1)->update(['tenant_id' => null]);
            }

            /* ─── Remove subscription + tenant + plano ─────────────────── */
            // Ordem respeita FK: subscription → tenant → plan
            DB::table('subscriptions')->where('tenant_id', 1)->delete();
            DB::table('tenants')->where('id', 1)->delete();
            DB::table('plans')->where('id', 1)->delete();
        });
    }
};
