<?php

namespace App\Domain\Integration\Services;

use App\Models\Agricultural\FieldApplication;
use App\Models\Stock\StockItem;
use App\Models\Stock\StockMovement;
use App\Models\Stock\Warehouse;
use Illuminate\Support\Facades\Log;

/**
 * Integração · Aplicação agrícola (fertilizante/corretivo) → Saída de estoque
 *
 * TERCEIRA integração automática cross-módulo. Quando uma FieldApplication
 * é criada com tipo `adubacao` (fertilizante) ou `calagem` (corretivo),
 * este service dá baixa no estoque do insumo correspondente.
 *
 * Segue EXATAMENTE o padrão de F2.1 (venda→receita) e F2.2 (entrada→despesa):
 *   - Service isolado, chamado explicitamente pelo controller
 *   - Idempotência via `numero_documento = FIELD_APP:<id>`
 *   - Sem migration, sem UI, sem schema change
 *   - Retrocompat: aplicações antigas intocadas
 *
 * ─ DIFERENÇAS EM RELAÇÃO A F2.1/F2.2 ─────────────────────────────
 *
 * RESOLUÇÃO DE ITEM POR NOME (não por FK)
 *   O schema de field_applications não tem coluna stock_item_id.
 *   A resolução usa o mesmo match do D7 (agrícola): busca em
 *   stock_items.nome case-insensitive entre itens ativos. Isto
 *   funciona porque o D7 já IMPEDE adubacao/calagem de serem
 *   criadas com produto que não esteja no estoque — quando o
 *   service é chamado, o item existe.
 *
 * WAREHOUSE PADRÃO
 *   FieldApplication não referencia warehouse. Usa o PRIMEIRO
 *   warehouse ativo do tenant (ordem por id). Se o tenant não
 *   tem warehouse ativo, integração é SKIPPED (sem quebrar a
 *   aplicação — análogo a FinancialAccount ausente em F2.1/F2.2).
 *
 * SALDO NEGATIVO ACEITO (decisão documentada)
 *   O StockMovementController atual NÃO valida saldo prévio antes
 *   de registrar saídas — o sistema admite saldo negativo para
 *   permitir correção posterior (inventário). Este service mantém
 *   o mesmo padrão: se a aplicação consumir mais do que existe em
 *   estoque, a saída é registrada (saldo fica negativo) e um
 *   warning é logado para auditoria. Master ajusta depois se
 *   necessário. Alternativa (bloquear) seria inconsistente com o
 *   comportamento do movimento manual.
 *
 * CUSTO MÉDIO
 *   Saída NÃO recalcula custo médio (padrão do sistema: custo médio
 *   ponderado só é recalculado em ENTRADAS). A saída apenas remove
 *   quantidade; a avaliação financeira fica preservada.
 *
 * VALOR
 *   valor_unitario / valor_total da saída ficam NULL. Motivo: o
 *   valor_total da FieldApplication refere-se ao preço PAGO pelo
 *   produto (pode diferir do custo médio). Misturar os dois é
 *   confuso — a saída é apenas movimentação física; o valor
 *   financeiro já foi contabilizado quando o item entrou.
 */
class FieldApplicationToStockMovementService
{
    /** Prefixo fixo do marcador de idempotência. */
    private const DOC_MARKER_PREFIX = 'FIELD_APP:';

    /**
     * Tipos de aplicação que consomem insumo de estoque.
     * Defensivos (herbicida/fungicida/inseticida) e irrigação NÃO
     * disparam — D7 só exige estoque para adubacao/calagem.
     */
    private const TIPOS_COM_BAIXA_ESTOQUE = ['adubacao', 'calagem'];

    /**
     * Gera (ou recupera) o StockMovement de saída associado a uma
     * FieldApplication. Retorna null quando a integração não se aplica.
     *
     * Caller DEVE envolver em DB::transaction para atomicidade com a
     * criação da aplicação.
     */
    public function generateForApplication(FieldApplication $app): ?StockMovement
    {
        // ── Gate 1: só fertilizante/corretivo ──────────────────────────
        if (! in_array($app->tipo, self::TIPOS_COM_BAIXA_ESTOQUE, true)) {
            return null;
        }

        // ── Gate 2: idempotência ───────────────────────────────────────
        $marker = self::DOC_MARKER_PREFIX . $app->id;
        $existing = StockMovement::where('numero_documento', $marker)->first();
        if ($existing !== null) {
            return $existing;
        }

        // ── Gate 3: produto deve corresponder a item ativo no estoque ──
        // Mesma lógica do D7 (validateApplicationCoherence) — que, na
        // prática, já garante que chegamos aqui com um item válido.
        $produto = trim((string) ($app->produto ?? ''));
        if ($produto === '') {
            return null;
        }

        $tenantId = $app->tenant_id;
        $item = StockItem::query()
            ->when($tenantId, fn ($q) => $q->where(function ($w) use ($tenantId) {
                $w->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            }))
            ->where('is_active', true)
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower($produto)])
            ->first();

        if (! $item) {
            // Defesa em profundidade: D7 já deveria ter bloqueado.
            // Se chegou aqui sem item, loga e pula (não quebra aplicação).
            Log::warning('FieldApplicationToStockMovementService: produto não encontrado no estoque — D7 deveria ter bloqueado.', [
                'field_application_id' => $app->id,
                'tipo' => $app->tipo,
                'produto' => $produto,
                'tenant_id' => $tenantId,
            ]);

            return null;
        }

        // ── Gate 4: warehouse ativo obrigatório (FK NOT NULL) ──────────
        $warehouse = Warehouse::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $warehouse) {
            Log::warning('FieldApplicationToStockMovementService: pulado — nenhum Warehouse ativo para dar baixa.', [
                'field_application_id' => $app->id,
                'tenant_id' => $tenantId,
                'item_id' => $item->id,
                'quantidade' => $app->quantidade,
            ]);

            return null;
        }

        // ── Gate 5: saldo insuficiente — aceita negativo (com warning) ──
        $saldoAtual = (float) StockMovement::where('item_id', $item->id)
            ->selectRaw("SUM(CASE WHEN tipo IN ('entrada','ajuste') THEN quantidade WHEN tipo='saida' THEN -quantidade END) as s")
            ->value('s') ?? 0;

        $quantidade = (float) $app->quantidade;

        if ($saldoAtual < $quantidade) {
            Log::warning('FieldApplicationToStockMovementService: saldo insuficiente — saída será registrada mesmo assim (padrão do sistema).', [
                'field_application_id' => $app->id,
                'item_id' => $item->id,
                'item_nome' => $item->nome,
                'saldo_atual' => $saldoAtual,
                'quantidade_saida' => $quantidade,
                'saldo_resultante' => $saldoAtual - $quantidade,
            ]);
        }

        $tipoHuman = [
            'adubacao' => 'Adubação',
            'calagem' => 'Calagem',
        ][$app->tipo] ?? $app->tipo;

        $descricao = "Uso em aplicação agrícola ({$tipoHuman})";

        // ── Cria a saída ──────────────────────────────────────────────
        return StockMovement::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'partner_id' => null,
            'tipo' => 'saida',
            'motivo' => 'uso',
            'data' => $app->data_aplicacao?->format('Y-m-d') ?? now()->toDateString(),
            'quantidade' => $quantidade,
            // valor_unitario/valor_total intencionalmente NULL — ver docblock
            'numero_documento' => $marker,
            'observacoes' => "Gerado automaticamente pelo registro de aplicação agrícola #{$app->id}. "
                . "Tipo: {$tipoHuman}. Produto: {$item->nome}.",
            'created_by' => $app->created_by ?? null,
            'tenant_id' => $tenantId,
            'farm_id' => $item->farm_id,
        ]);
    }
}
