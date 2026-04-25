<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $financeiro_receita = [
            'Venda de gado', 'Venda de leite', 'Venda de produção agrícola',
            'Aluguel de pasto', 'Serviços', 'Outros recebimentos',
        ];

        $financeiro_despesa = [
            'Alimentação animal', 'Medicamentos veterinários', 'Vacinas',
            'Sementes e mudas', 'Adubos e fertilizantes', 'Defensivos agrícolas',
            'Combustível', 'Manutenção de veículos', 'Manutenção de implementos',
            'Energia elétrica', 'Água', 'Salários e encargos',
            'Mão de obra temporária', 'Impostos', 'Taxas e contribuições',
            'Transporte', 'Material de escritório', 'Material de construção',
            'Ferramentas', 'Outros',
        ];

        foreach ($financeiro_receita as $i => $nome) {
            Category::updateOrCreate(
                ['tipo' => 'financeiro_receita', 'slug' => Str::slug($nome)],
                ['nome' => $nome, 'order_column' => $i, 'is_active' => true]
            );
        }

        foreach ($financeiro_despesa as $i => $nome) {
            Category::updateOrCreate(
                ['tipo' => 'financeiro_despesa', 'slug' => Str::slug($nome)],
                ['nome' => $nome, 'order_column' => $i, 'is_active' => true]
            );
        }

        // Categorias de estoque
        $estoque = [
            'Insumos agrícolas', 'Medicamentos veterinários', 'Rações e suplementos',
            'Ferramentas', 'Peças e componentes', 'Combustíveis e lubrificantes',
            'Material de construção', 'Material de limpeza', 'EPIs',
        ];
        foreach ($estoque as $i => $nome) {
            Category::updateOrCreate(
                ['tipo' => 'estoque', 'slug' => Str::slug($nome)],
                ['nome' => $nome, 'order_column' => $i, 'is_active' => true]
            );
        }

        // BLOCO 3.1 — Cost centers ficam por tenant agora.
        // Antes este seeder criava 5 "defaults globais" (GERAL, REBANHO, AGRICOLA,
        // MAQUINAS, ADMIN) sem tenant_id. Com o isolamento por tenant, defaults
        // globais não fazem sentido — cada cliente cadastra os centros que usa
        // no fluxo de financeiro. Os 5 órfãos antigos foram removidos pela
        // migration 2026_04_25_160000_cleanup_orphan_cost_centers.
    }
}
