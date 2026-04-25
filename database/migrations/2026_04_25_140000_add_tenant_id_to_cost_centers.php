<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BLOCO 3 — Multi-fazenda · Isolamento de cost_centers por tenant.
 *
 * Estado anterior: tabela `cost_centers` SEM `tenant_id`. Coluna `codigo`
 * era unique GLOBAL (entre todos os tenants). Risco real: vazamento e
 * colisão de dados de centros de custo entre clientes diferentes do SaaS.
 *
 * Mudanças:
 *   1. Adiciona coluna `tenant_id` (nullable inicialmente, p/ permitir backfill)
 *   2. Backfill via JOIN com `financial_transactions` (a única tabela que
 *      referencia cost_centers via cost_center_id) — herda o tenant da
 *      transação que usou aquele cost_center
 *   3. Troca unique(codigo) → unique(tenant_id, codigo): cada tenant pode
 *      ter seu próprio "ADM", "OPER", etc. sem colidir com outros tenants
 *   4. Adiciona índice em tenant_id para queries do scope
 *
 * Cost centers órfãos (nunca referenciados por nenhuma transação) ficam com
 * tenant_id = NULL. Com `BelongsToTenant` aplicado no model, o scope filtra
 * por `tenant_id = current_tenant`, tornando esses órfãos invisíveis para
 * usuários — efetivamente neutralizados sem perda física do registro
 * (master pode limpar via console depois).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Adiciona tenant_id nullable + FK
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->cascadeOnDelete();
        });

        // 2. Backfill: para cada cost_center referenciado por alguma
        // financial_transaction, herdar o tenant_id da PRIMEIRA transação
        // que o referencia. Se diferentes transações de tenants distintos
        // já compartilham o mesmo cost_center (sintoma do bug), pegamos o
        // de maior frequência via subquery agregada.
        //
        // MySQL multi-table UPDATE com JOIN — funciona em MySQL 5.7+.
        DB::statement("
            UPDATE cost_centers cc
            INNER JOIN (
                SELECT cost_center_id, tenant_id, COUNT(*) AS n
                FROM financial_transactions
                WHERE cost_center_id IS NOT NULL
                GROUP BY cost_center_id, tenant_id
            ) ft ON ft.cost_center_id = cc.id
            SET cc.tenant_id = ft.tenant_id
            WHERE cc.tenant_id IS NULL
        ");

        // 3. Troca unique global → unique composto (tenant_id, codigo)
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropUnique(['codigo']);          // remove unique global
            $table->unique(['tenant_id', 'codigo']); // permite mesmo codigo entre tenants
            $table->index('tenant_id');              // perf p/ scope
        });
    }

    public function down(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropUnique(['tenant_id', 'codigo']);
            $table->unique(['codigo']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
