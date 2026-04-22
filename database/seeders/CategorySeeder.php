<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CostCenter;
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

        // Centros de custo iniciais
        $cc = [
            ['codigo' => 'GERAL', 'nome' => 'Geral', 'descricao' => 'Custos gerais não atribuídos'],
            ['codigo' => 'REBANHO', 'nome' => 'Rebanho', 'descricao' => 'Custos relacionados ao rebanho'],
            ['codigo' => 'AGRICOLA', 'nome' => 'Produção agrícola', 'descricao' => 'Custos de lavoura e talhões'],
            ['codigo' => 'MAQUINAS', 'nome' => 'Máquinas e veículos', 'descricao' => 'Manutenção e operação de frota'],
            ['codigo' => 'ADMIN', 'nome' => 'Administrativo', 'descricao' => 'Custos administrativos e escritório'],
        ];
        foreach ($cc as $c) {
            CostCenter::updateOrCreate(['codigo' => $c['codigo']], $c + ['is_active' => true]);
        }
    }
}
