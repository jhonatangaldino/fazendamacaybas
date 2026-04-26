<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\Tenant;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Farm;
use App\Models\Financial\FinancialAccount;
use App\Models\Financial\FinancialTransaction;
use App\Models\Livestock\Animal;
use App\Models\Livestock\AnimalLot;
use App\Models\Livestock\AnimalSpecies;
use App\Models\Partner;
use App\Models\Stock\StockItem;
use App\Models\Task\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * macaybas:seed-qa — popula o tenant QA com dados realistas para validar
 * forms, ações contextuais, filtros aplicando, paginação, detalhes (Show)
 * em pilots E2E sem precisar criar registros manualmente um a um.
 *
 * Idempotente: pula registros que já existem por código/identificação.
 */
class SeedQaData extends Command
{
    protected $signature = 'macaybas:seed-qa {tenant_slug=b3mf924907-tenant} {--force : recriar tudo}';
    protected $description = 'Popula tenant QA com dados realistas para pilot E2E';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant_slug'))->first();
        if (! $tenant) {
            $this->error("Tenant {$this->argument('tenant_slug')} não encontrado.");
            return 1;
        }

        $this->info("Tenant: {$tenant->id} - {$tenant->nome}");

        $farms = Farm::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('id')->get();
        $this->info("Farms ativas: {$farms->count()}");

        if ($farms->isEmpty()) {
            $this->error('Sem farms ativas — abortando.');
            return 1;
        }

        // Bind manual de tenant/farm para BelongsToTenantScope/BelongsToFarmScope funcionarem.
        app()->instance('tenant_id', $tenant->id);

        DB::transaction(function () use ($tenant, $farms) {
            // 1. PARCEIROS (3) — fornecedores e clientes
            $parceiros = $this->seedPartners($tenant->id);
            $this->info("✓ Parceiros: {$parceiros->count()}");

            // 2. FUNCIONÁRIOS (3) — distribuídos nas farms
            $funcionarios = $this->seedEmployees($tenant->id, $farms);
            $this->info("✓ Funcionários: {$funcionarios->count()}");

            // 3. CATEGORIAS financeiras (5) — tenant-scoped
            $cats = $this->seedFinancialCategories($tenant->id);
            $this->info("✓ Categorias: {$cats->count()}");

            // Para cada farm, criar dataset completo
            foreach ($farms as $farm) {
                app()->instance('farm_id', $farm->id);
                $this->line("  → Farm {$farm->id} ({$farm->nome})");

                // 4. CONTAS FINANCEIRAS (2 por farm)
                $contas = $this->seedAccounts($tenant->id, $farm->id);
                $this->line("    ✓ Contas: {$contas->count()}");

                // 5. TRANSAÇÕES (10 por farm) — mix receita/despesa, status mix
                $trx = $this->seedTransactions($tenant->id, $farm->id, $contas, $cats, $parceiros);
                $this->line("    ✓ Transações: {$trx}");

                // 6. ITENS DE ESTOQUE (5 por farm)
                $items = $this->seedStockItems($tenant->id, $farm->id);
                $this->line("    ✓ Itens de estoque: {$items->count()}");

                // 7. LOTES e ANIMAIS (2 lotes + 5 animais por farm)
                $animais = $this->seedAnimals($tenant->id, $farm->id);
                $this->line("    ✓ Animais: {$animais}");

                // 8. TAREFAS (4 por farm)
                $tasks = $this->seedTasks($tenant->id, $farm->id, $funcionarios);
                $this->line("    ✓ Tarefas: {$tasks}");

                app()->forgetInstance('farm_id');
            }
        });

        $this->info("\n✓ Seed QA concluído.");
        return 0;
    }

    private function seedPartners(int $tenantId)
    {
        $base = [
            ['tipo' => 'fornecedor', 'pessoa' => 'PJ', 'nome' => 'Agropecuária São José LTDA', 'documento' => '12.345.678/0001-90', 'cidade' => 'Goiânia', 'estado' => 'GO'],
            ['tipo' => 'cliente', 'pessoa' => 'PJ', 'nome' => 'Frigorífico Regional SA', 'documento' => '98.765.432/0001-10', 'cidade' => 'Anápolis', 'estado' => 'GO'],
            ['tipo' => 'ambos', 'pessoa' => 'PF', 'nome' => 'João da Silva', 'documento' => '111.222.333-44', 'cidade' => 'Trindade', 'estado' => 'GO'],
        ];
        foreach ($base as $b) {
            Partner::firstOrCreate(
                ['tenant_id' => $tenantId, 'documento' => $b['documento']],
                array_merge($b, ['tenant_id' => $tenantId, 'is_active' => true])
            );
        }
        return Partner::where('tenant_id', $tenantId)->get();
    }

    private function seedEmployees(int $tenantId, $farms)
    {
        $base = [
            ['nome' => 'Carlos Operador', 'cpf' => '111.111.111-11', 'setor' => 'campo', 'funcao' => 'Operador de máquinas', 'data_admissao' => '2024-01-15', 'salario' => 2800.00],
            ['nome' => 'Maria Veterinária', 'cpf' => '222.222.222-22', 'setor' => 'tecnico', 'funcao' => 'Veterinária', 'data_admissao' => '2023-06-01', 'salario' => 6500.00],
            ['nome' => 'Pedro Capataz', 'cpf' => '333.333.333-33', 'setor' => 'campo', 'funcao' => 'Capataz', 'data_admissao' => '2022-03-10', 'salario' => 4200.00],
        ];
        foreach ($base as $i => $b) {
            $farm = $farms[$i % $farms->count()];
            Employee::firstOrCreate(
                ['tenant_id' => $tenantId, 'cpf' => $b['cpf']],
                array_merge($b, ['tenant_id' => $tenantId, 'farm_id' => $farm->id, 'is_active' => true])
            );
        }
        return Employee::where('tenant_id', $tenantId)->get();
    }

    private function seedFinancialCategories(int $tenantId)
    {
        $base = [
            ['tipo' => 'despesa', 'nome' => 'Insumos agrícolas', 'slug' => 'insumos-agricolas'],
            ['tipo' => 'despesa', 'nome' => 'Manutenção de máquinas', 'slug' => 'manutencao-maquinas'],
            ['tipo' => 'despesa', 'nome' => 'Salários', 'slug' => 'salarios'],
            ['tipo' => 'receita', 'nome' => 'Venda de animais', 'slug' => 'venda-animais'],
            ['tipo' => 'receita', 'nome' => 'Venda de grãos', 'slug' => 'venda-graos'],
        ];
        foreach ($base as $b) {
            Category::firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $b['slug']],
                array_merge($b, ['tenant_id' => $tenantId, 'is_active' => true])
            );
        }
        return Category::where('tenant_id', $tenantId)->get();
    }

    private function seedAccounts(int $tenantId, int $farmId)
    {
        $base = [
            ['nome' => 'Banco do Brasil — Conta corrente', 'tipo' => 'conta_corrente', 'banco' => 'Banco do Brasil', 'agencia' => '0001', 'conta' => '12345-6', 'saldo_inicial' => 50000.00, 'saldo_atual' => 50000.00],
            ['nome' => 'Caixa interno', 'tipo' => 'caixa', 'saldo_inicial' => 5000.00, 'saldo_atual' => 5000.00],
        ];
        foreach ($base as $b) {
            FinancialAccount::firstOrCreate(
                ['tenant_id' => $tenantId, 'farm_id' => $farmId, 'nome' => $b['nome']],
                array_merge($b, ['tenant_id' => $tenantId, 'farm_id' => $farmId, 'is_active' => true])
            );
        }
        return FinancialAccount::where('tenant_id', $tenantId)->where('farm_id', $farmId)->get();
    }

    private function seedTransactions(int $tenantId, int $farmId, $contas, $cats, $parceiros): int
    {
        if (FinancialTransaction::where('tenant_id', $tenantId)->where('farm_id', $farmId)->count() > 0) {
            return 0;
        }
        $contaCorrente = $contas->firstWhere('tipo', 'conta_corrente');
        $catsDesp = $cats->where('tipo', 'despesa');
        $catsRec = $cats->where('tipo', 'receita');
        $fornecedor = $parceiros->firstWhere('tipo', 'fornecedor');
        $cliente = $parceiros->firstWhere('tipo', 'cliente');

        $base = [
            ['tipo' => 'despesa', 'descricao' => 'Compra de adubo NPK 20-05-20 (50 sacas)', 'valor' => 12500.00, 'status' => 'pago', 'venc' => -10, 'pag' => -10, 'cat' => 'insumos-agricolas', 'partner' => $fornecedor->id ?? null],
            ['tipo' => 'despesa', 'descricao' => 'Troca de óleo Trator John Deere', 'valor' => 850.00, 'status' => 'pago', 'venc' => -5, 'pag' => -5, 'cat' => 'manutencao-maquinas'],
            ['tipo' => 'despesa', 'descricao' => 'Folha mensal — Carlos Operador', 'valor' => 2800.00, 'status' => 'pendente', 'venc' => 5, 'pag' => null, 'cat' => 'salarios'],
            ['tipo' => 'despesa', 'descricao' => 'Vacina aftosa — 50 doses', 'valor' => 950.00, 'status' => 'pendente', 'venc' => 12, 'pag' => null, 'cat' => 'insumos-agricolas', 'partner' => $fornecedor->id ?? null],
            ['tipo' => 'despesa', 'descricao' => 'Energia elétrica — Setembro', 'valor' => 1450.00, 'status' => 'pendente', 'venc' => -2, 'pag' => null, 'cat' => null], // ATRASADA
            ['tipo' => 'receita', 'descricao' => 'Venda 5 bezerros desmamados', 'valor' => 8500.00, 'status' => 'pago', 'venc' => -15, 'pag' => -14, 'cat' => 'venda-animais', 'partner' => $cliente->id ?? null],
            ['tipo' => 'receita', 'descricao' => 'Venda 200 sacas de milho', 'valor' => 22000.00, 'status' => 'pendente', 'venc' => 20, 'pag' => null, 'cat' => 'venda-graos'],
            ['tipo' => 'receita', 'descricao' => 'Frete agropecuário', 'valor' => 1200.00, 'status' => 'pago', 'venc' => -3, 'pag' => -2, 'cat' => null],
            ['tipo' => 'despesa', 'descricao' => 'Combustível — diesel S10 (200L)', 'valor' => 1180.00, 'status' => 'pago', 'venc' => -1, 'pag' => -1, 'cat' => null],
            ['tipo' => 'receita', 'descricao' => 'Venda 1 vaca descarte', 'valor' => 4500.00, 'status' => 'pendente', 'venc' => 8, 'pag' => null, 'cat' => 'venda-animais', 'partner' => $cliente->id ?? null],
        ];

        foreach ($base as $b) {
            $cat = null;
            if (! empty($b['cat'])) {
                $cat = ($b['tipo'] === 'despesa' ? $catsDesp : $catsRec)->firstWhere('slug', $b['cat']);
            }
            FinancialTransaction::create([
                'tenant_id' => $tenantId,
                'farm_id' => $farmId,
                'account_id' => $contaCorrente->id,
                'category_id' => $cat?->id,
                'partner_id' => $b['partner'] ?? null,
                'tipo' => $b['tipo'],
                'descricao' => $b['descricao'],
                'valor' => $b['valor'],
                'status' => $b['status'],
                'data_vencimento' => now()->addDays($b['venc'])->format('Y-m-d'),
                'data_pagamento' => $b['pag'] !== null ? now()->addDays($b['pag'])->format('Y-m-d') : null,
                'forma_pagamento' => $b['status'] === 'pago' ? 'pix' : null,
            ]);
        }
        return count($base);
    }

    private function seedStockItems(int $tenantId, int $farmId)
    {
        // Codigo é único global — prefixar com farmId para evitar colisão entre farms.
        $prefix = 'F' . $farmId . '-';
        $base = [
            ['codigo' => $prefix . 'INS-001', 'nome' => 'Adubo NPK 20-05-20', 'unidade' => 'kg', 'tipo' => 'insumo', 'estoque_minimo' => 100, 'custo_medio' => 4.20],
            ['codigo' => $prefix . 'MED-001', 'nome' => 'Vacina aftosa', 'unidade' => 'dose', 'tipo' => 'medicamento', 'estoque_minimo' => 20, 'custo_medio' => 18.50, 'registro_ms' => '12345/2024'],
            ['codigo' => $prefix . 'RAC-001', 'nome' => 'Ração bovina engorda', 'unidade' => 'kg', 'tipo' => 'racao', 'estoque_minimo' => 500, 'custo_medio' => 1.85],
            ['codigo' => $prefix . 'COM-001', 'nome' => 'Diesel S10', 'unidade' => 'L', 'tipo' => 'combustivel', 'estoque_minimo' => 200, 'custo_medio' => 5.90],
            ['codigo' => $prefix . 'FER-001', 'nome' => 'Pá de bico cabo madeira', 'unidade' => 'un', 'tipo' => 'ferramenta', 'estoque_minimo' => 2, 'custo_medio' => 65.00],
        ];
        foreach ($base as $b) {
            StockItem::firstOrCreate(
                ['tenant_id' => $tenantId, 'farm_id' => $farmId, 'codigo' => $b['codigo']],
                array_merge($b, ['tenant_id' => $tenantId, 'farm_id' => $farmId, 'is_active' => true])
            );
        }
        return StockItem::where('tenant_id', $tenantId)->where('farm_id', $farmId)->get();
    }

    private function seedAnimals(int $tenantId, int $farmId): int
    {
        if (Animal::where('tenant_id', $tenantId)->where('farm_id', $farmId)->count() > 0) {
            return 0;
        }
        $bovino = AnimalSpecies::where('slug', 'bovino')->first();
        if (! $bovino) {
            return 0;
        }

        // codigo de lote e identificacao de animal são únicos GLOBAIS — prefixar com farm.
        $prefix = 'F' . $farmId . '-';
        // 2 lotes
        $loteEngorda = AnimalLot::firstOrCreate(
            ['tenant_id' => $tenantId, 'farm_id' => $farmId, 'codigo' => $prefix . 'ENG-2026'],
            ['nome' => 'Engorda 2026', 'finalidade' => 'corte', 'gestao_modo' => 'individual', 'is_active' => true, 'tenant_id' => $tenantId, 'farm_id' => $farmId]
        );
        $loteCria = AnimalLot::firstOrCreate(
            ['tenant_id' => $tenantId, 'farm_id' => $farmId, 'codigo' => $prefix . 'CRIA-2026'],
            ['nome' => 'Cria 2026', 'finalidade' => 'reproducao', 'gestao_modo' => 'individual', 'is_active' => true, 'tenant_id' => $tenantId, 'farm_id' => $farmId]
        );

        $base = [
            ['identificacao' => $prefix . 'BR-001', 'nome' => 'Mimosa', 'sexo' => 'F', 'peso_atual' => 480, 'lot_id' => $loteCria->id, 'categoria' => 'reproducao'],
            ['identificacao' => $prefix . 'BR-002', 'nome' => 'Estrela', 'sexo' => 'F', 'peso_atual' => 510, 'lot_id' => $loteCria->id, 'categoria' => 'leite'],
            ['identificacao' => $prefix . 'BR-003', 'nome' => 'Touro Rei', 'sexo' => 'M', 'peso_atual' => 920, 'lot_id' => $loteCria->id, 'categoria' => 'reproducao'],
            ['identificacao' => $prefix . 'BR-004', 'nome' => null, 'sexo' => 'M', 'peso_atual' => 380, 'lot_id' => $loteEngorda->id, 'categoria' => 'corte'],
            ['identificacao' => $prefix . 'BR-005', 'nome' => null, 'sexo' => 'M', 'peso_atual' => 405, 'lot_id' => $loteEngorda->id, 'categoria' => 'corte'],
        ];
        foreach ($base as $b) {
            Animal::create(array_merge($b, [
                'tenant_id' => $tenantId,
                'farm_id' => $farmId,
                'species_id' => $bovino->id,
                'status' => 'ativo',
                'data_nascimento' => now()->subMonths(rand(8, 36))->format('Y-m-d'),
                'origem' => 'nascimento',
            ]));
        }
        return count($base);
    }

    private function seedTasks(int $tenantId, int $farmId, $funcionarios): int
    {
        if (Task::where('tenant_id', $tenantId)->where('farm_id', $farmId)->count() > 0) {
            return 0;
        }
        $base = [
            ['titulo' => 'Pesar lote ENG-2026', 'modulo' => 'rebanho', 'prioridade' => 'alta', 'venc' => 2, 'status' => 'pendente'],
            ['titulo' => 'Aplicar vacina aftosa lote CRIA', 'modulo' => 'rebanho', 'prioridade' => 'urgente', 'venc' => -1, 'status' => 'pendente'], // ATRASADA
            ['titulo' => 'Trocar óleo trator John Deere', 'modulo' => 'maquinas', 'prioridade' => 'media', 'venc' => 7, 'status' => 'pendente'],
            ['titulo' => 'Plantio milho safrinha — talhão 3', 'modulo' => 'agricola', 'prioridade' => 'alta', 'venc' => 10, 'status' => 'pendente'],
        ];
        foreach ($base as $b) {
            Task::create([
                'tenant_id' => $tenantId,
                'farm_id' => $farmId,
                'titulo' => $b['titulo'],
                'modulo' => $b['modulo'],
                'prioridade' => $b['prioridade'],
                'status' => $b['status'],
                'data_vencimento' => now()->addDays($b['venc'])->format('Y-m-d'),
            ]);
        }
        return count($base);
    }
}
