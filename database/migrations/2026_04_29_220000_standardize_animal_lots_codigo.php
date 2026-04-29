<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Padroniza `animal_lots.codigo` em formato auto-incremento `LT-####`.
 *
 * Antes: usuário precisava digitar um código a cada lote criado (atrito + risco
 * de typos + duplicidade). E o formato era livre — alguns lotes tinham nomes
 * tipo "ENG-2026-Q1", outros "L-001", outros "LEITE". Sem padrão = caos no
 * relatório master.
 *
 * Agora: código é gerado automaticamente no model boot (`creating` hook) e
 * o usuário NÃO informa mais. Formato `LT-` + 4 dígitos zero-padded, contador
 * por tenant (cada cliente começa em LT-0001).
 *
 * Mudanças:
 *   1. Drop unique global antigo `animal_lots_codigo_unique`
 *   2. Backfill: todos os lotes existentes ganham LT-#### na ordem de `id`
 *      (preserva ordem cronológica de criação)
 *   3. Cria unique composto `(tenant_id, codigo)` — tenants têm contadores
 *      independentes, cada cliente começa em LT-0001
 *
 * Ordem importa: drop ANTES do backfill, pra permitir LT-0001 repetido entre
 * tenants. Add unique composto DEPOIS do backfill, com dados já consistentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop unique global ANTES do backfill — senão não conseguimos
        //    atribuir LT-0001 pra dois tenants diferentes
        Schema::table('animal_lots', function (Blueprint $table) {
            try {
                $table->dropUnique('animal_lots_codigo_unique');
            } catch (\Throwable $e) {
                // já removido — segue
            }
        });

        // 2. Backfill por tenant em ordem de id (cronológica de criação)
        $tenants = DB::table('animal_lots')->select('tenant_id')->distinct()->get();
        foreach ($tenants as $t) {
            $lots = DB::table('animal_lots')
                ->where('tenant_id', $t->tenant_id)
                ->orderBy('id')
                ->get(['id']);

            $i = 1;
            foreach ($lots as $lot) {
                $codigo = 'LT-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
                DB::table('animal_lots')
                    ->where('id', $lot->id)
                    ->update(['codigo' => $codigo]);
                $i++;
            }
        }

        // 3. Adiciona unique composto (tenant_id, codigo) — multi-tenant safe
        Schema::table('animal_lots', function (Blueprint $table) {
            $table->unique(['tenant_id', 'codigo'], 'animal_lots_tenant_codigo_unique');
        });
    }

    public function down(): void
    {
        // Reverter unique scoped → global. NÃO desfaz o backfill (irreversível
        // sem perda de informação — não temos backup dos códigos antigos).
        Schema::table('animal_lots', function (Blueprint $table) {
            try {
                $table->dropUnique('animal_lots_tenant_codigo_unique');
            } catch (\Throwable $e) {
                // já removido
            }
        });

        Schema::table('animal_lots', function (Blueprint $table) {
            $table->unique('codigo', 'animal_lots_codigo_unique');
        });
    }
};
