<?php

namespace App\Domain\Tenancy\Services;

use App\Domain\Billing\Models\Tenant;
use App\Models\Farm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cria os dados-seed operacionais MÍNIMOS pra um tenant novo conseguir usar
 * o sistema imediatamente sem quebrar em wizards que dependem de pré-requisitos.
 *
 * Antes deste seed, tenants novos travavam:
 *   • Despesa wizard exigia Categoria de despesa cadastrada
 *   • Receita exigia Categoria de receita
 *   • Estoque exigia Armazém + Categoria de estoque
 *   • Plantio exigia Talhão + Cultura
 *   • Manutenção exigia Veículo
 *
 * Agora tenant novo recebe um kit de "exemplo" pronto pra ser editado/ampliado.
 *
 * Idempotente: pode rodar várias vezes no mesmo tenant sem duplicar.
 */
class TenantOperationalSeed
{
    public function seed(Tenant $tenant, Farm $farm): void
    {
        $this->seedCategoriasFinanceiras($tenant);
        $this->seedContaFinanceira($tenant);
        $this->seedArmazem($tenant, $farm);
        $this->seedVeiculo($tenant, $farm);
        $this->seedTalhao($tenant, $farm);
        $this->seedCultura($tenant);
    }

    /**
     * Categorias mais comuns: 6 de despesa + 4 de receita.
     * Usuário pode editar/desativar/criar mais depois.
     */
    private function seedCategoriasFinanceiras(Tenant $tenant): void
    {
        $catsDespesa = [
            ['nome' => 'Insumos agrícolas',  'cor' => '#16a34a'],
            ['nome' => 'Insumos pecuários',  'cor' => '#0ea5e9'],
            ['nome' => 'Combustível',         'cor' => '#dc2626'],
            ['nome' => 'Manutenção',          'cor' => '#f59e0b'],
            ['nome' => 'Salários',            'cor' => '#7c3aed'],
            ['nome' => 'Outras despesas',     'cor' => '#64748b'],
        ];
        $catsReceita = [
            ['nome' => 'Venda de animais',    'cor' => '#16a34a'],
            ['nome' => 'Venda de safra',      'cor' => '#22c55e'],
            ['nome' => 'Venda de leite',      'cor' => '#fbbf24'],
            ['nome' => 'Outras receitas',     'cor' => '#64748b'],
        ];

        foreach ([['despesa', $catsDespesa], ['receita', $catsReceita]] as [$tipo, $cats]) {
            foreach ($cats as $i => $cat) {
                $existe = DB::table('categories')
                    ->where('tenant_id', $tenant->id)
                    ->where('tipo', $tipo)
                    ->where('nome', $cat['nome'])
                    ->exists();
                if ($existe) continue;
                DB::table('categories')->insert([
                    'tenant_id' => $tenant->id,
                    'parent_id' => null,
                    'nome' => $cat['nome'],
                    'slug' => Str::slug($cat['nome']),
                    'tipo' => $tipo,
                    'cor' => $cat['cor'],
                    'order_column' => $i,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function seedContaFinanceira(Tenant $tenant): void
    {
        $existe = DB::table('financial_accounts')->where('tenant_id', $tenant->id)->exists();
        if ($existe) return;
        DB::table('financial_accounts')->insert([
            'tenant_id' => $tenant->id,
            'nome' => 'Caixa principal',
            'tipo' => 'caixa',
            'saldo_inicial' => 0,
            'saldo_atual' => 0,
            'is_active' => true,
            'observacoes' => 'Conta padrão criada automaticamente. Edite ou crie outras em Financeiro → Contas.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedArmazem(Tenant $tenant, Farm $farm): void
    {
        $existe = DB::table('warehouses')->where('tenant_id', $tenant->id)->exists();
        if ($existe) return;
        DB::table('warehouses')->insert([
            'tenant_id' => $tenant->id,
            'farm_id' => $farm->id,
            'nome' => 'Depósito principal',
            'localizacao' => 'Sede',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedVeiculo(Tenant $tenant, Farm $farm): void
    {
        $existe = DB::table('vehicles')->where('tenant_id', $tenant->id)->exists();
        if ($existe) return;
        DB::table('vehicles')->insert([
            'tenant_id' => $tenant->id,
            'farm_id' => $farm->id,
            'tipo' => 'trator',
            'nome' => 'Trator (exemplo)',
            'marca' => 'Editar',
            'modelo' => 'Editar',
            'ano_fabricacao' => 2020,
            'combustivel' => 'diesel',
            'medidor' => 'horimetro',
            'medidor_atual' => 0,
            'is_active' => true,
            'observacoes' => 'Veículo de exemplo. Edite com os dados reais ou desative se não tiver.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedTalhao(Tenant $tenant, Farm $farm): void
    {
        $existe = DB::table('fields')->where('tenant_id', $tenant->id)->exists();
        if ($existe) return;
        DB::table('fields')->insert([
            'tenant_id' => $tenant->id,
            'farm_id' => $farm->id,
            'codigo' => 'T-01',
            'nome' => 'Talhão 1',
            'area_ha' => 10.0,
            'tipo_solo' => 'argiloso',
            'descricao' => 'Talhão de exemplo. Edite com os dados reais ou crie outros.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedCultura(Tenant $tenant): void
    {
        $existe = DB::table('crops')->where('tenant_id', $tenant->id)->exists();
        if ($existe) return;
        DB::table('crops')->insert([
            'tenant_id' => $tenant->id,
            'nome' => 'Soja',
            'slug' => 'soja',
            'variedade' => null,
            'ciclo_dias' => 120,
            'unidade_producao' => 'sacas',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
