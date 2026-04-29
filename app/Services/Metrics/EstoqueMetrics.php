<?php

namespace App\Services\Metrics;

use App\Models\Stock\StockItem;
use App\Models\Stock\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * EstoqueMetrics — fonte única de verdade para KPIs do módulo estoque.
 *
 * Substitui as fórmulas hoje em:
 *   - Admin\Stock\StockItemController::index (Lista) — query agregada inline
 *   - Admin\DashboardController::buildPayload (Painel)
 *   - Admin\HubController (futuro: Hub Estoque com KPIs)
 *
 * Decisões de produto registradas:
 *   - "Total itens ativos" = COUNT(StockItem WHERE is_active=true).
 *     Items soft-deleted não contam.
 *   - "Saldo valorizado" = SUM(saldo_atual * custo_medio) para items ativos.
 *     Saldo é derivado de stock_movements (entradas - saídas).
 *     Items sem movimentação (saldo=0) entram como 0.
 *   - "Itens com estoque baixo" = items ativos com saldo < estoque_minimo.
 *     Itens sem mínimo definido (estoque_minimo=0) NÃO contam (caso contrário
 *     todo item zerado seria flagged).
 *   - "Itens sem estoque" = items ativos com saldo <= 0.
 *   - "Movimentações no mês" = COUNT(StockMovement WHERE data BETWEEN [...]).
 *
 * Fórmula canônica do saldo:
 *   SUM(CASE
 *     WHEN tipo IN ('entrada','ajuste') THEN +quantidade
 *     WHEN tipo = 'saida' THEN -quantidade
 *     ELSE 0 END) GROUP BY item_id
 *
 * Esta fórmula bate com `StockItem::saldoAtual()` (canônico).
 */
class EstoqueMetrics extends BaseMetrics
{
    public const MODULE = 'estoque';

    /** Total de items ATIVOS (is_active=true, não soft-deleted). */
    public function totalItensAtivos(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "total_itens." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => $this->scope(StockItem::query(), $tenantId, $farmId)
                ->where('is_active', true)
                ->count()
        );
    }

    /**
     * Saldo VALORIZADO total (R$).
     *
     * Fórmula:
     *   SUM(saldo_atual * custo_medio) para items ativos com saldo > 0.
     *
     * Saldo é derivado de stock_movements via subquery agregada.
     * Items sem movimentação entram como saldo=0 (LEFT JOIN). custo_medio
     * NULL é tratado como 0 (item sem custo registrado não inflaciona valor).
     *
     * Cache: TTL_STANDARD (15min — métrica analítica, não realtime crítico).
     */
    public function saldoValorizado(?int $tenantId = null, ?int $farmId = null): float
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "saldo_valorizado." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_STANDARD,
            function () use ($tenantId, $farmId) {
                $row = $this->scope(StockItem::query(), $tenantId, $farmId)
                    ->leftJoin('stock_movements', 'stock_items.id', '=', 'stock_movements.item_id')
                    ->where('stock_items.is_active', true)
                    ->select(DB::raw("
                        SUM(
                            COALESCE(stock_items.custo_medio, 0) *
                            CASE
                                WHEN stock_movements.tipo IN ('entrada','ajuste') THEN stock_movements.quantidade
                                WHEN stock_movements.tipo = 'saida' THEN -stock_movements.quantidade
                                ELSE 0
                            END
                        ) as valor_total
                    "))
                    ->first();

                return (float) max(0, $row->valor_total ?? 0);
            }
        );
    }

    /**
     * Itens com estoque BAIXO — saldo > 0 mas saldo < estoque_minimo.
     *
     * Bug corrigido: StockItemController::index conta items com saldo<minimo,
     * INCLUINDO itens com mínimo=0 (que sempre seriam "baixos" se zerados).
     * Decisão de produto: itens sem mínimo configurado NÃO entram no count
     * de "baixo" (vão para "sem estoque" se aplicável).
     */
    public function itensComEstoqueBaixo(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "abaixo_minimo." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            function () use ($tenantId, $farmId) {
                $rows = $this->scope(StockItem::query(), $tenantId, $farmId)
                    ->leftJoin('stock_movements', 'stock_items.id', '=', 'stock_movements.item_id')
                    ->where('stock_items.is_active', true)
                    ->where('stock_items.estoque_minimo', '>', 0)
                    ->select(
                        'stock_items.id',
                        'stock_items.estoque_minimo',
                        DB::raw("SUM(CASE WHEN stock_movements.tipo IN ('entrada','ajuste') THEN stock_movements.quantidade WHEN stock_movements.tipo = 'saida' THEN -stock_movements.quantidade ELSE 0 END) as saldo")
                    )
                    ->groupBy('stock_items.id', 'stock_items.estoque_minimo')
                    ->get();

                return $rows->filter(fn ($r) => (float) ($r->saldo ?? 0) < (float) $r->estoque_minimo
                                              && (float) ($r->saldo ?? 0) > 0)
                            ->count();
            }
        );
    }

    /**
     * Itens SEM ESTOQUE — saldo <= 0 entre items ativos.
     *
     * Items que existem ativos mas nunca foram movimentados também caem aqui.
     */
    public function itensSemEstoque(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "sem_estoque." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            function () use ($tenantId, $farmId) {
                $rows = $this->scope(StockItem::query(), $tenantId, $farmId)
                    ->leftJoin('stock_movements', 'stock_items.id', '=', 'stock_movements.item_id')
                    ->where('stock_items.is_active', true)
                    ->select(
                        'stock_items.id',
                        DB::raw("SUM(CASE WHEN stock_movements.tipo IN ('entrada','ajuste') THEN stock_movements.quantidade WHEN stock_movements.tipo = 'saida' THEN -stock_movements.quantidade ELSE 0 END) as saldo")
                    )
                    ->groupBy('stock_items.id')
                    ->get();

                return $rows->filter(fn ($r) => (float) ($r->saldo ?? 0) <= 0)->count();
            }
        );
    }

    /** Movimentações no período (StockMovement.data). */
    public function movimentacoesNoMes(?int $tenantId = null, ?int $farmId = null): int
    {
        return $this->movimentacoesNoPeriodo('mes_atual', $tenantId, $farmId);
    }

    public function movimentacoesNoPeriodo(string|array $period = 'mes_atual', ?int $tenantId = null, ?int $farmId = null): int
    {
        [$from, $to] = $this->period($period);
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "movimentacoes_periodo." . MetricsCache::suffix($tenantId, $farmId, $from->toDateString() . '_' . $to->toDateString()), MetricsCache::TTL_FAST,
            fn () => $this->scope(StockMovement::query(), $tenantId, $farmId)
                ->whereBetween('data', [$from->toDateString(), $to->toDateString()])
                ->count()
        );
    }

    /**
     * Items por categoria — Collection<['categoria','total']>.
     * "Sem categoria" agrupa items sem category_id.
     */
    public function itensPorCategoria(?int $tenantId = null, ?int $farmId = null): \Illuminate\Support\Collection
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "por_categoria." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_STANDARD,
            fn () => $this->scope(StockItem::query(), $tenantId, $farmId)
                ->leftJoin('categories', 'stock_items.category_id', '=', 'categories.id')
                ->where('stock_items.is_active', true)
                ->select(DB::raw("COALESCE(categories.nome, 'Sem categoria') as categoria"), DB::raw('COUNT(*) as total'))
                ->groupBy('categoria')
                ->orderByDesc('total')
                ->get()
        );
    }

    /**
     * Resumo agregado para Hub/Lista — bate com `total_itens`, `abaixo_minimo`,
     * `sem_estoque` da StockItemController::index.
     */
    public function resumo(?int $tenantId = null, ?int $farmId = null): array
    {
        return [
            'total_itens'   => $this->totalItensAtivos($tenantId, $farmId),
            'abaixo_minimo' => $this->itensComEstoqueBaixo($tenantId, $farmId),
            'sem_estoque'   => $this->itensSemEstoque($tenantId, $farmId),
            'saldo_valorizado' => $this->saldoValorizado($tenantId, $farmId),
            'movimentacoes_mes' => $this->movimentacoesNoMes($tenantId, $farmId),
        ];
    }
}
