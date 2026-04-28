<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Financial\FinancialTransaction;
use App\Models\Livestock\Animal;
use App\Models\Stock\StockItem;
use App\Models\Task\Task;
use App\Services\Livestock\LivestockMetricsService;
use App\Services\Metrics\FinancialMetrics;
use App\Services\Metrics\TarefasMetrics;
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
     * REFATORADO 2026-04-28 (auditoria METRICS-AUDIT): KPIs consolidados de
     * Metrics services (fonte única de verdade). Antes este controller tinha
     * fórmulas inline e divergia de Hub/Lista/Relatório (22 divergências
     * detectadas). Agora qualquer mudança de fórmula propaga automaticamente.
     *
     * Services consumidos:
     *   - LivestockMetricsService    (já existia, cobre Rebanho)
     *   - FinancialMetrics            (NOVO — KPIs Financeiro)
     *   - TarefasMetrics              (NOVO — KPIs Tarefas)
     *
     * As listas de drill-down (top 15 receitas/despesas, contas a pagar/receber,
     * tarefas pendentes, estoque baixo) continuam aqui — são listas, não KPIs.
     *
     * OTIMIZAÇÃO (Hostinger 500 conn/h):
     *   Eram 13 queries por hit. Agora agrupamos tudo em cache de 90s
     *   por (tenant_id, farm_id, user_id). Navegar entre páginas e voltar
     *   pro dashboard durante 90s reutiliza a resposta → zero queries.
     *   90s é balance entre "dado recente" e "economia de conexão".
     *   Os Metrics services também cacheiam internamente (TTL_FAST=300s),
     *   então hits subsequentes em outros controladores reaproveitam.
     *
     *   Força refresh: `?refresh=1` ignora o cache (útil após criar
     *   transação/animal/tarefa).
     */
    public function index(
        Request $request,
        FinancialMetrics $financeiro,
        TarefasMetrics $tarefas,
    ): Response {
        $tenant = app()->bound('tenant_id') ? app('tenant_id') : 'null';
        $farm   = app()->bound('farm_id') ? app('farm_id') : 'null';
        $user   = $request->user()?->id ?? 'guest';
        // Cache key versionado: bump v3 invalida caches anteriores (refator pra Metrics services).
        $cacheKey = "dashboard:v3:{$tenant}:{$farm}:{$user}";

        if ($request->query('refresh') === '1') {
            Cache::forget($cacheKey);
        }

        $payload = Cache::remember(
            $cacheKey,
            now()->addSeconds(90),
            fn () => $this->buildPayload(app(LivestockMetricsService::class), $financeiro, $tarefas),
        );

        return Inertia::render('Admin/Dashboard', $payload);
    }

    protected function buildPayload(
        LivestockMetricsService $metrics,
        FinancialMetrics $financeiro,
        TarefasMetrics $tarefas,
    ): array {
        $hoje = Carbon::today();
        $inicioMes = Carbon::now()->startOfMonth();
        $fimMes = Carbon::now()->endOfMonth();

        // ═══════════ FINANCEIRO — via FinancialMetrics (fonte única) ═══════════
        $receitasMesTotal = $financeiro->receitasNoPeriodo('mes_atual');
        $despesasMesTotal = $financeiro->despesasNoPeriodo('mes_atual');
        // Atrasadas agora = SÓ despesas (decisão de produto; antes contava receita+despesa).
        $atrasadas = $financeiro->atrasadas();

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

        // ═══════════ REBANHO — via LivestockMetricsService (fonte única) ═══════════
        // Antes este bloco tinha 3 queries inline com sutis variações em relação
        // ao Hub e ao Relatório. Bovino aparecia 345 num lugar e 344 noutro pq
        // cada controller calculava diferente. Service uniformiza.
        $totalAnimais = $metrics->totalCabecasTodasEspecies();
        $animaisPorEspecie = $metrics->cabecasPorEspecie()
            ->filter(fn ($s) => $s['animals_count'] > 0)
            ->map(fn ($s) => (object) [
                'especie' => $s['nome'],
                'total'   => $s['animals_count'],
            ])->sortByDesc('total')->values();

        // ═══════════ ESTOQUE — query inline (EstoqueMetrics fica para Fase 4) ═══════════
        // BUG FIX B4.4: usar StockItem model (não DB::table) pra preservar scopes.
        // BUG FIX METRICS-AUDIT alta-6: KPI antes era count() da lista limitada.
        // Se houver 50 itens abaixo, mostrava 10. Agora count REAL via subquery.
        $itensBaixoEstoqueQuery = StockItem::query()
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
            ->havingRaw('COALESCE(saldo, 0) < stock_items.estoque_minimo');

        $itensBaixoEstoqueList = (clone $itensBaixoEstoqueQuery)->limit(10)->get();
        $itensBaixoEstoqueCount = (clone $itensBaixoEstoqueQuery)->get()->count();

        // ═══════════ TAREFAS — via TarefasMetrics (fonte única) ═══════════
        // Antes Painel incluía em_andamento; AlertsService só pendente.
        // Padronizamos no resumo() do service (decisão de produto: incluir
        // em_andamento — tarefa em andamento atrasada ainda é tarefa atrasada).
        $tarefasResumo = $tarefas->resumo();

        $tarefasPendentes = Task::whereIn('status', ['pendente', 'em_andamento'])
            ->orderBy('data_vencimento')
            ->limit(10)
            ->get(['id', 'titulo', 'prioridade', 'status', 'data_vencimento', 'modulo']);

        return [
            'widgets' => [
                'financeiro' => [
                    'receitas_mes' => $receitasMesTotal,
                    'despesas_mes' => $despesasMesTotal,
                    'saldo_mes' => $receitasMesTotal - $despesasMesTotal,
                    'contas_atrasadas' => $atrasadas['count'],
                ],
                'rebanho' => [
                    'total' => $totalAnimais,
                    'por_especie' => $animaisPorEspecie,
                ],
                'estoque' => [
                    'itens_baixo_estoque' => $itensBaixoEstoqueCount,
                ],
                'tarefas' => [
                    'pendentes' => $tarefasResumo['pendentes'],
                    'atrasadas' => $tarefasResumo['atrasadas'],
                ],
            ],
            'contas_a_pagar' => $contasAPagar,
            'contas_a_receber' => $contasAReceber,
            'itens_baixo_estoque' => $itensBaixoEstoqueList,
            'tarefas_pendentes' => $tarefasPendentes,
            // Listas de drill-down dos KPIs (para drawers)
            'drillReceitasMes' => $receitasMesLista,
            'drillDespesasMes' => $despesasMesLista,
        ];
    }
}
