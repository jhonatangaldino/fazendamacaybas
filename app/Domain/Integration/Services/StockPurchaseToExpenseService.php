<?php

namespace App\Domain\Integration\Services;

use App\Models\Category;
use App\Models\Financial\FinancialAccount;
use App\Models\Financial\FinancialTransaction;
use App\Models\Stock\StockMovement;
use Illuminate\Support\Facades\Log;

/**
 * Integração · Entrada de estoque (compra) → Despesa financeira
 *
 * SEGUNDA integração automática cross-módulo. Gera uma
 * `FinancialTransaction` do tipo `despesa` quando um `StockMovement`
 * é criado com `tipo=entrada` e `valor_total > 0`.
 *
 * Segue EXATAMENTE o padrão estabelecido em F2.1 (venda de animal):
 *   - Service isolado chamado explicitamente pelo controller
 *   - Idempotência via `numero_documento = STOCK_MOVEMENT:<id>`
 *   - Sem migration, sem UI change, sem schema change
 *   - Retrocompat: movimentos antigos intocados
 *
 * ─ DIFERENÇAS EM RELAÇÃO À F2.1 ──────────────────────────────────
 *
 * CATEGORIA INTELIGENTE
 *   O tipo do stock_item (insumo, medicamento, racao, combustivel,
 *   etc.) mapeia para a categoria de despesa correspondente já
 *   seedada em CategorySeeder:
 *
 *     medicamento  → 'Medicamentos'
 *     racao        → 'Alimentação animal'
 *     combustivel  → 'Combustível'
 *     outros tipos → sem categoria (null, aceito pelo D6)
 *
 * DESCRIÇÃO COM QUANTIDADE
 *   Como StockMovement traz quantidade e unidade, a descrição inclui
 *   essa informação para contexto:
 *     "Compra de item de estoque Ração 20% (500 kg)"
 *
 * ─ OUTROS PRINCÍPIOS ─────────────────────────────────────────────
 *
 * Se o tenant não tem nenhuma FinancialAccount ativa, integração
 * é SKIPPED. O movimento ainda é criado normalmente — despesa NÃO
 * aparecerá em Financeiro, mas o master pode cadastrar depois.
 *
 * NÃO abre transação própria — o caller já opera dentro de
 * `DB::transaction` (ver StockMovementController::store).
 *
 * Só dispara para tipo=entrada. Saídas (tipo=saida), ajustes e
 * transferências passam sem integração — saída é consumo interno,
 * não gera despesa contábil (insumo já foi contabilizado na entrada).
 */
class StockPurchaseToExpenseService
{
    /** Prefixo fixo do marcador de idempotência. */
    private const DOC_MARKER_PREFIX = 'STOCK_MOVEMENT:';

    /**
     * Mapeia tipo de item de estoque (enum) → slug da categoria de
     * despesa correspondente no CategorySeeder. Tipos fora do mapa
     * ficam sem categoria (null) — D6 aceita despesa sem categoria.
     */
    private const CATEGORIA_POR_TIPO_ITEM = [
        'medicamento' => 'medicamentos',
        'racao'       => 'alimentacao-animal',
        'combustivel' => 'combustivel',
        // insumo, ferramenta, peca, material → null (sem categoria padrão)
    ];

    /**
     * Gera (ou recupera) a FinancialTransaction associada a um
     * StockMovement de entrada. Retorna null se a integração não
     * se aplica.
     *
     * Caller DEVE envolver em DB::transaction para garantir atomicidade
     * com a criação do movimento (StockMovementController::store já faz).
     */
    public function generateForMovement(StockMovement $mov): ?FinancialTransaction
    {
        // ── Gate 1: só entrada (saídas não geram despesa) ──────────────
        if ($mov->tipo !== 'entrada') {
            return null;
        }

        // ── Gate 2: valor > 0 ──────────────────────────────────────────
        $valor = (float) ($mov->valor_total ?? 0);
        if ($valor <= 0) {
            return null;
        }

        // ── Gate 3: idempotência ───────────────────────────────────────
        $marker = self::DOC_MARKER_PREFIX . $mov->id;
        $existing = FinancialTransaction::where('numero_documento', $marker)->first();
        if ($existing !== null) {
            return $existing;
        }

        // ── Gate 4: conta ativa ────────────────────────────────────────
        $item = $mov->item;
        $tenantId = $mov->tenant_id ?? $item?->tenant_id;

        $account = FinancialAccount::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $account) {
            Log::warning('StockPurchaseToExpenseService: pulado — nenhuma FinancialAccount ativa para gerar despesa.', [
                'stock_movement_id' => $mov->id,
                'tenant_id' => $tenantId,
                'item_id' => $mov->item_id,
                'valor_total' => $valor,
            ]);

            return null;
        }

        // ── Gate 5: categoria conforme tipo do item ────────────────────
        $catSlug = self::CATEGORIA_POR_TIPO_ITEM[$item?->tipo] ?? null;
        $category = $catSlug
            ? Category::query()
                ->when($tenantId, fn ($q) => $q->where(function ($w) use ($tenantId) {
                    $w->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                }))
                ->where('tipo', 'financeiro_despesa')
                ->where('slug', $catSlug)
                ->where('is_active', true)
                ->first()
            : null;

        // ── Descrição auditável com quantidade + unidade ──────────────
        $nomeItem = $item?->nome ?? "#{$mov->item_id}";
        $descricao = "Compra de item de estoque {$nomeItem}";
        if ($mov->quantidade > 0 && $item?->unidade) {
            $qtdFmt = rtrim(rtrim(number_format((float) $mov->quantidade, 4, ',', '.'), '0'), ',');
            $descricao .= " ({$qtdFmt} {$item->unidade})";
        }

        $dataMov = $mov->data?->format('Y-m-d') ?? now()->toDateString();

        // ── Cria a transação ──────────────────────────────────────────
        return FinancialTransaction::create([
            'account_id' => $account->id,
            'category_id' => $category?->id,
            'partner_id' => $mov->partner_id,
            'tipo' => 'despesa',
            'descricao' => $descricao,
            'observacoes' => "Gerado automaticamente pelo registro de entrada de estoque #{$mov->id}. "
                . "Item: {$nomeItem}. "
                . 'Para refletir um pagamento efetivo, marque esta transação como paga.',
            'valor' => $valor,
            'data_vencimento' => $dataMov,
            'status' => 'pendente',
            'numero_documento' => $marker,
            'created_by' => $mov->created_by,
            'tenant_id' => $tenantId,
            'farm_id' => $item?->farm_id,
        ]);
    }
}
