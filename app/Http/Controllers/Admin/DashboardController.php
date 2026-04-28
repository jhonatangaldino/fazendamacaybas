<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Financial\FinancialTransaction;
use App\Models\Livestock\Animal;
use App\Models\Stock\StockItem;
use App\Models\Task\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Dashboard — painel de KPIs + drill-down.
     *
     * OTIMIZAÇÃO (Hostinger 500 conn/h):
     *   Eram 13 queries por hit. Agora agrupamos tudo em cache de 90s
     *   por (tenant_id, farm_id, user_id). Navegar entre páginas e voltar
     *   pro dashboard durante 90s reutiliza a resposta → zero queries.
     *   90s é balance entre "dado recente" e "economia de conexão".
     *
     *   Força refresh: `?refresh=1` ignora o cache (útil após criar
     *   transação/animal/tarefa).
     */
    public function index(Request $request): Response
    {
        $tenant = app()->bound('tenant_id') ? app('tenant_id') : 'null';
        $farm   = app()->bound('farm_id') ? app('farm_id') : 'null';
        $user   = $request->user()?->id ?? 'guest';
        // Cache key versionado: bump v2 invalida automaticamente caches velhos
        // que tinham contagens incorretas (DB::table sem scopes — bug B4.4 fix).
        $cacheKey = "dashboard:v2:{$tenant}:{$farm}:{$user}";

        if ($request->query('refresh') === '1') {
            Cache::forget($cacheKey);
        }

        $payload = Cache::remember($cacheKey, now()->addSeconds(90), fn () => $this->buildPayload());

        return Inertia::render('Admin/Dashboard', $payload);
    }

    protected function buildPayload(): array
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

        // Rebanho — TUDO via Eloquent (Animal model com BelongsToTenant + BelongsToFarm).
        // BUG FIX B4.4: antes usava DB::table('animals') que BYPASSA scopes globais.
        // Isso causava modal "Rebanho ativo" mostrar animais de outras farms (46+26+5=77)
        // enquanto card Rebanho (Animal::ativos()) mostrava apenas 1 da farm correta.
        //
        // BUG FIX 2026-04-28 (apontado pelo usuário): Animal::ativos()->count() ignora
        // cabeças em lotes agregados (Ave/Peixe gestão=lote). Modal mostrava "Ave: 1"
        // (único animal individual residual) enquanto sidebar mostrava "Ave: 4580"
        // (cabeças em lotes). Solução: somar individuais + agregados, igual à sidebar.
        $totalAnimaisIndividuais = Animal::ativos()->count();
        $cabecasAgregadas = (int) \App\Models\Livestock\AnimalLot::where('is_active', true)
            ->whereHas('species', fn ($q) => $q->withoutGlobalScopes()->where('gestao', 'lote'))
            ->sum('quantidade_atual');
        $totalAnimais = $totalAnimaisIndividuais + $cabecasAgregadas;

        // Por espécie: individuais (count animals) + agregados (sum quantidade_atual)
        $individuaisPorEspecie = Animal::ativos()
            ->leftJoin('animal_species', 'animals.species_id', '=', 'animal_species.id')
            ->select('animal_species.nome as especie', DB::raw('COUNT(*) as total'))
            ->groupBy('animal_species.nome')
            ->pluck('total', 'especie');
        $agregadosPorEspecie = \App\Models\Livestock\AnimalLot::where('animal_lots.is_active', true)
            ->leftJoin('animal_species', 'animal_lots.species_id', '=', 'animal_species.id')
            ->whereHas('species', fn ($q) => $q->withoutGlobalScopes()->where('gestao', 'lote'))
            ->select('animal_species.nome as especie', DB::raw('SUM(animal_lots.quantidade_atual) as total'))
            ->groupBy('animal_species.nome')
            ->pluck('total', 'especie');
        // Mescla: cada espécie com soma individual + agregado
        $todasEspecies = collect($individuaisPorEspecie->keys())->merge($agregadosPorEspecie->keys())->unique();
        $animaisPorEspecie = $todasEspecies->map(fn ($especie) => (object) [
            'especie' => $especie,
            'total' => (int) ($individuaisPorEspecie[$especie] ?? 0) + (int) ($agregadosPorEspecie[$especie] ?? 0),
        ])->sortByDesc('total')->values();

        // Estoque — TUDO via Eloquent (StockItem model com BelongsToTenant + BelongsToFarm).
        // BUG FIX B4.4: idem para stock_items. DB::table() bypassava scopes →
        // alerta mostrava itens baixos de OUTRAS farms enquanto /admin/estoque/itens
        // (que usa StockItem model) renderizava "Nenhum registro encontrado".
        $itensBaixoEstoque = StockItem::query()
            ->leftJoin('stock_movements', 'stock_items.id', '=', 'stock_movements.item_id')
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

        // Tarefas pendentes — consolidado em 1 query com CASE para
        // derivar total pendentes + total atrasadas (antes eram 3 queries).
        $tarefasAgg = Task::whereIn('status', ['pendente', 'em_andamento'])
            ->selectRaw('COUNT(*) as pendentes, SUM(CASE WHEN data_vencimento < ? THEN 1 ELSE 0 END) as atrasadas', [$hoje])
            ->first();

        $tarefasPendentes = Task::whereIn('status', ['pendente', 'em_andamento'])
            ->orderBy('data_vencimento')
            ->limit(10)
            ->get(['id', 'titulo', 'prioridade', 'status', 'data_vencimento', 'modulo']);

        return [
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
                    'pendentes' => (int) ($tarefasAgg->pendentes ?? 0),
                    'atrasadas' => (int) ($tarefasAgg->atrasadas ?? 0),
                ],
            ],
            'contas_a_pagar' => $contasAPagar,
            'contas_a_receber' => $contasAReceber,
            'itens_baixo_estoque' => $itensBaixoEstoque,
            'tarefas_pendentes' => $tarefasPendentes,
            // Listas de drill-down dos KPIs (para drawers)
            'drillReceitasMes' => $receitasMesLista,
            'drillDespesasMes' => $despesasMesLista,
        ];
    }
}
