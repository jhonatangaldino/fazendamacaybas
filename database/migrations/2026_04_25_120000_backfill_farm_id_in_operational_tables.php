<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill — atribui `farm_id` a registros operacionais existentes que
 * estão com `farm_id NULL`.
 *
 * Por quê: a aplicação do trait BelongsToFarm + BelongsToFarmScope faz com
 * que toda query operacional filtre por `farm_id = app('farm_id')`. Se os
 * registros antigos tiverem `farm_id NULL`, eles SOMEM da listagem.
 *
 * Estratégia: para cada registro órfão, associa à PRIMEIRA fazenda ativa do
 * tenant correspondente. Quando o tenant tem só 1 fazenda (caso comum), isso
 * é exatamente o que o usuário esperava (não ficou órfão por descuido — só
 * porque farm_id era nullable e ninguém preencheu).
 *
 * Reversibilidade: trivial — basta voltar `farm_id = NULL` em down().
 * Mas como é backfill defensivo, NÃO desfazemos no down (preserva consistência).
 */
return new class extends Migration
{
    /** Tabelas operacionais com farm_id nullable que precisam backfill. */
    private const TABLES = [
        'animals', 'animal_lots', 'animal_locations', 'animal_events',
        'fields', 'plantings', 'harvests', 'field_applications',
        'stock_items', 'warehouses', 'stock_movements',
        'vehicles', 'vehicle_events', 'maintenance_orders',
        'employees', 'tasks',
        'documents',
        'financial_accounts', 'financial_transactions', 'financial_recurrences',
        'barcode_lookups',
    ];

    public function up(): void
    {
        // Mapa tenant_id → primeira farm ativa (id)
        $primaryFarms = DB::table('farms')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'tenant_id'])
            ->groupBy('tenant_id')
            ->map(fn ($g) => $g->first()->id)
            ->all();

        $totalUpdated = 0;
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) continue;
            if (! Schema::hasColumn($table, 'farm_id')) continue;
            if (! Schema::hasColumn($table, 'tenant_id')) continue;

            // UPDATE em massa por tenant — uma query por tenant que tem órfãos.
            // Mais eficiente que iterar registro a registro (especialmente em
            // tabelas como animals com 137 órfãos).
            $orphanTenants = DB::table($table)
                ->whereNull('farm_id')
                ->distinct()
                ->pluck('tenant_id');

            foreach ($orphanTenants as $tenantId) {
                $farmId = $primaryFarms[$tenantId] ?? null;
                if ($farmId === null) {
                    // Tenant sem farm cadastrada — não há para onde apontar.
                    // Deixa órfão (caso raro, master decide depois manualmente).
                    continue;
                }

                $count = DB::table($table)
                    ->where('tenant_id', $tenantId)
                    ->whereNull('farm_id')
                    ->update(['farm_id' => $farmId]);

                $totalUpdated += $count;
            }
        }

        // Log para auditoria — útil em rollback de deploy.
        if ($totalUpdated > 0) {
            \Illuminate\Support\Facades\Log::info(
                "Backfill farm_id: $totalUpdated registros atualizados em ".count(self::TABLES)." tabelas."
            );
        }
    }

    public function down(): void
    {
        // Não desfaz — backfill defensivo é estado correto.
        // Se for necessário "limpar", seria intervenção manual em registro
        // específico via Master após análise caso a caso.
    }
};
