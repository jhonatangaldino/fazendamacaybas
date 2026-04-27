<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tabela DRovet — Crescimento de Fêmeas leiteiras para parição aos 27 meses.
 *
 * Fonte: cartilha veterinária DRovet+ (Medicina Veterinária & Agropecuária Planejada).
 * Usada como referência para o sistema comparar peso real vs peso esperado.
 *
 * Tamanhos:
 *   GRANDE   → Holandês, Pardo Suíço
 *   MÉDIA    → Girolando, Jersolando
 *   PEQUENA  → Jersey
 *
 * OBS crítica: entre 8 e 12 meses (puberdade), fêmeas NÃO devem estar acima do
 * peso da tabela — risco de acumular gordura no aparelho reprodutivo/mamário.
 */
class GrowthReferenceSeeder extends Seeder
{
    public function run(): void
    {
        // [idade_meses, grande, media, pequena, observacao?]
        $data = [
            [0,  40,  35,  30,  null],
            [1,  55,  48,  42,  null],
            [2,  73,  64,  55,  null],
            [3,  91,  80,  69,  null],
            [4,  109, 95,  82,  null],
            [5,  127, 111, 96,  null],
            [6,  145, 127, 109, null],
            [7,  163, 143, 123, null],
            [8,  181, 158, 136, 'fase crítica'],
            [9,  199, 174, 150, 'fase crítica'],
            [10, 217, 190, 163, 'fase crítica'],
            [11, 235, 205, 176, 'fase crítica'],
            [12, 253, 221, 190, 'fase crítica'],
            [13, 263, 233, 204, null],
            [14, 275, 246, 217, null],
            [15, 289, 259, 230, null],
            [16, 310, 277, 244, null],
            [17, 331, 294, 257, null],
            [18, 352, 310, 268, 'cobrição'],
            [19, 376, 330, 284, null],
            [20, 400, 350, 300, null],
            [21, 424, 369, 315, null],
            [22, 448, 389, 330, null],
            [23, 472, 413, 354, null],
            [24, 496, 437, 378, null],
            [25, 524, 463, 402, null],
            [26, 552, 489, 426, null],
            [27, 580, 510, 450, 'parição'],
        ];

        DB::table('growth_references')->truncate();

        $rows = [];
        $now = now();
        foreach ($data as [$idade, $g, $m, $p, $obs]) {
            // Faixas (margem ±5% para 'esperado')
            foreach ([
                ['grande',  $g],
                ['media',   $m],
                ['pequena', $p],
            ] as [$tam, $peso]) {
                $rows[] = [
                    'tamanho' => $tam,
                    'idade_meses' => $idade,
                    'peso_esperado_kg' => $peso,
                    'peso_min_kg' => round($peso * 0.95, 2),
                    'peso_max_kg' => round($peso * 1.05, 2),
                    'observacao' => $obs,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('growth_references')->insert($rows);

        $this->command->info('GrowthReferenceSeeder: ' . count($rows) . ' linhas inseridas (28 idades × 3 tamanhos).');
    }
}
