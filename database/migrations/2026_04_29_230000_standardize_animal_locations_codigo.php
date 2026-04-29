<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Padroniza `animal_locations.codigo` em formato auto-incremento `MP-#####`.
 *
 * Mesma motivação dos lotes (2026_04_29_220000_standardize_animal_lots_codigo):
 * usuário não digita mais código. Sistema gera sequencialmente.
 *
 * Diferenças do lote:
 *   • prefixo MP- ("Manejo Pasto" — pasto/piquete/curral/baia/tanque/galpão)
 *   • 5 dígitos (00001) em vez de 4 — fazendas grandes têm muito mais piquetes
 *     que lotes; antecipamos crescimento
 *   • Codigo já era NULLABLE e unique já era (tenant_id, codigo) — não mexemos
 *     em índice; só backfill
 *
 * Backfill por tenant em ordem de id (cronológica). Inclui locations que
 * tinham codigo NULL — todas recebem MP-##### sequencial.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tenants = DB::table('animal_locations')->select('tenant_id')->distinct()->get();

        foreach ($tenants as $t) {
            $locs = DB::table('animal_locations')
                ->where('tenant_id', $t->tenant_id)
                ->orderBy('id')
                ->get(['id']);

            $i = 1;
            foreach ($locs as $loc) {
                $codigo = 'MP-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT);
                DB::table('animal_locations')
                    ->where('id', $loc->id)
                    ->update(['codigo' => $codigo]);
                $i++;
            }
        }
    }

    public function down(): void
    {
        // Down é no-op: não temos backup dos códigos antigos pra restaurar.
        // Reverter requer restore do dump de produção.
    }
};
