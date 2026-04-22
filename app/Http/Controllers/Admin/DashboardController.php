<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Financial\FinancialTransaction;
use App\Models\Livestock\Animal;
use App\Models\Stock\StockItem;
use App\Models\Task\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $hoje = Carbon::today();
        $inicioMes = Carbon::now()->startOfMonth();
        $fimMes = Carbon::now()->endOfMonth();

        // Financeiro do mês — totais + listas de drill-down
        $receitasMesTotal = FinancialTransaction::receitas()
            ->pagas()
            ->whereBetween('data_pagamento', [$inicioMes, $fimMes])
            ->sum('valor');

        $despesasMesTotal = FinancialTransaction::despesas()
            ->pagas()
            ->whereBetween('data_pagamento', [$inicioMes, $fimMes])
            ->sum('valor');

        // Top 15 de cada tipo para alimentar o drawer de drill-down
        $receitasMesLista = FinancialTransaction::receitas()
            ->pagas()
            ->whereBetween('data_pagamento', [$inicioMes, $fimMes])
            ->orderByDesc('valor')
            ->limit(15)
            ->get(['id', 'descricao', 'valor', 'data_pagamento']);

        $despesasMesLista = FinancialTransaction::despesas()
            ->pagas()
            ->whereBetween('data_pagamento', [$inicioMes, $fimMes])
            ->orderByDesc('valor')
            ->limit(15)
            ->get(['id', 'descricao', 'valor', 'data_pagamento']);

        $contasAPagar = FinancialTransaction::despesas()
            ->pendentes()
            ->whereBetween('data_vencimento', [$hoje, $hoje->copy()->addDays(30)])
            ->orderBy('data_vencimento')
            ->limit(10)
            ->get(['id', 'descricao', 'valor', 'data_vencimento', 'status']);

        $contasAReceber = FinancialTransaction::receitas()
            ->pendentes()
            ->whereBetween('data_vencimento', [$hoje, $hoje->copy()->addDays(30)])
            ->orderBy('data_vencimento')
            ->limit(10)
            ->get(['id', 'descricao', 'valor', 'data_vencimento', 'status']);

        $contasAtrasadas = FinancialTransaction::pendentes()
            ->where('data_vencimento', '<', $hoje)
            ->count();

        // Rebanho
        $totalAnimais = Animal::ativos()->count();
        $animaisPorEspecie = DB::table('animals')
            ->leftJoin('animal_species', 'animals.species_id', '=', 'animal_species.id')
            ->where('animals.status', 'ativo')
            ->whereNull('animals.deleted_at')
            ->select('animal_species.nome as especie', DB::raw('COUNT(*) as total'))
            ->groupBy('animal_species.nome')
            ->get();

        // Estoque: itens com estoque baixo (soma de entradas - saídas < estoque_minimo)
        $itensBaixoEstoque = DB::table('stock_items')
            ->leftJoin('stock_movements', 'stock_items.id', '=', 'stock_movements.item_id')
            ->whereNull('stock_items.deleted_at')
            ->where('stock_items.is_active', true)
            ->select(
                'stock_items.id',
                'stock_items.nome',
                'stock_items.unidade',
                'stock_items.estoque_minimo',
                DB::raw("SUM(CASE WHEN stock_movements.tipo IN ('entrada','ajuste') THEN stock_movements.quantidade WHEN stock_movements.tipo = 'saida' THEN -stock_movements.quantidade ELSE 0 END) as saldo")
            )
            ->groupBy('stock_items.id', 'stock_items.nome', 'stock_items.unidade', 'stock_items.estoque_minimo')
            ->havingRaw('COALESCE(saldo, 0) < stock_items.estoque_minimo')
            ->limit(10)
            ->get();

        // Tarefas pendentes
        $tarefasPendentes = Task::whereIn('status', ['pendente', 'em_andamento'])
            ->orderBy('data_vencimento')
            ->limit(10)
            ->get(['id', 'titulo', 'prioridade', 'status', 'data_vencimento', 'modulo']);

        return Inertia::render('Admin/Dashboard', [
            'widgets' => [
                'financeiro' => [
                    'receitas_mes' => (float) $receitasMesTotal,
                    'despesas_mes' => (float) $despesasMesTotal,
                    'saldo_mes' => (float) $receitasMesTotal - (float) $despesasMesTotal,
                    'contas_atrasadas' => $contasAtrasadas,
                ],
                'rebanho' => [
                    'total' => $totalAnimais,
                    'por_especie' => $animaisPorEspecie,
                ],
                'estoque' => [
                    'itens_baixo_estoque' => $itensBaixoEstoque->count(),
                ],
                'tarefas' => [
                    'pendentes' => Task::whereIn('status', ['pendente', 'em_andamento'])->count(),
                    'atrasadas' => Task::whereIn('status', ['pendente', 'em_andamento'])->where('data_vencimento', '<', $hoje)->count(),
                ],
            ],
            'contas_a_pagar' => $contasAPagar,
            'contas_a_receber' => $contasAReceber,
            'itens_baixo_estoque' => $itensBaixoEstoque,
            'tarefas_pendentes' => $tarefasPendentes,
            // Listas de drill-down dos KPIs (para drawers)
            'drillReceitasMes' => $receitasMesLista,
            'drillDespesasMes' => $despesasMesLista,
        ]);
    }
}
