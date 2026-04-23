<?php

namespace App\Domain\Integration\Services;

use App\Models\Agricultural\Harvest;
use App\Models\Agricultural\Planting;
use App\Models\Category;
use App\Models\Financial\FinancialAccount;
use App\Models\Financial\FinancialTransaction;
use Illuminate\Support\Facades\Log;

/**
 * Integração · Colheita agrícola → Receita financeira
 *
 * QUINTA integração automática cross-módulo. Quando uma `Harvest`
 * é registrada com `quantidade_colhida > 0` e `valor_total > 0`,
 * este service gera automaticamente uma `FinancialTransaction` de
 * receita.
 *
 * Segue EXATAMENTE o padrão estabelecido em F2.1-F2.4:
 *   - Service isolado chamado explicitamente pelo controller
 *   - Idempotência via `numero_documento = HARVEST:<id>`
 *   - Sem migration, sem UI, sem schema change
 *   - Retrocompat com colheitas antigas
 *
 * ─ ESPECIFICIDADES ───────────────────────────────────────────────
 *
 * RESOLUÇÃO DE CONTEXTO VIA PLANTING
 *   Harvest traz FK para Planting, que por sua vez tem Field e Crop.
 *   tenant_id, farm_id e nome da cultura são obtidos via eager load
 *   dessa cadeia (planting.field, planting.crop).
 *
 * CATEGORIA FIXA
 *   Diferente das integrações que variam por tipo (F2.2 medicamento/
 *   ração/combustível, F2.4 veículo/implemento), toda colheita usa a
 *   mesma categoria de receita: "Venda de produção agrícola"
 *   (slug: venda-de-producao-agricola), já seedada em CategorySeeder.
 *   Se não encontrada, category_id fica NULL (D6 aceita).
 *
 * SEM PARTNER NO SCHEMA
 *   O schema de harvests não tem coluna partner_id — a venda da
 *   produção pode ser consolidada em uma transação financeira ampla
 *   ou dividida em múltiplas vendas separadas (caso mais complexo).
 *   Por ora, a FT gerada não tem partner vinculado; o master ajusta
 *   manualmente se precisar.
 *
 * ─ OUTROS PRINCÍPIOS ─────────────────────────────────────────────
 *
 * Caller envolve em DB::transaction para atomicidade com a criação
 * da colheita e o update de status do planting para "colhido".
 *
 * Se o tenant não tem FinancialAccount ativa, integração é SKIPPED
 * com log warning — colheita é registrada normalmente, receita fica
 * para cadastro manual depois.
 */
class HarvestToRevenueService
{
    /** Prefixo fixo do marcador de idempotência. */
    private const DOC_MARKER_PREFIX = 'HARVEST:';

    /** Slug da categoria de receita preferida (seedada em CategorySeeder). */
    private const CATEGORY_SLUG = 'venda-de-producao-agricola';

    /**
     * Gera (ou recupera) a FinancialTransaction de receita associada
     * à Harvest. Retorna null quando a integração não se aplica.
     *
     * Caller DEVE envolver em DB::transaction.
     */
    public function generateForHarvest(Harvest $harvest): ?FinancialTransaction
    {
        // ── Gate 1: quantidade > 0 (já forçada pelo validator base, defensivo)
        if ((float) ($harvest->quantidade_colhida ?? 0) <= 0) {
            return null;
        }

        // ── Gate 2: valor_total > 0 (nullable no schema) ───────────────
        $valor = (float) ($harvest->valor_total ?? 0);
        if ($valor <= 0) {
            return null;
        }

        // ── Gate 3: idempotência ───────────────────────────────────────
        $marker = self::DOC_MARKER_PREFIX . $harvest->id;
        $existing = FinancialTransaction::where('numero_documento', $marker)->first();
        if ($existing !== null) {
            return $existing;
        }

        // ── Contexto via planting ──────────────────────────────────────
        $planting = $harvest->planting
            ?? Planting::with(['field', 'crop'])->find($harvest->planting_id);
        if (! $planting) {
            Log::warning('HarvestToRevenueService: planting não encontrado — pulado.', [
                'harvest_id' => $harvest->id,
                'planting_id' => $harvest->planting_id,
            ]);

            return null;
        }

        $tenantId = $harvest->tenant_id
            ?? $planting->tenant_id
            ?? $planting->field?->tenant_id;

        $farmId = $harvest->farm_id
            ?? $planting->farm_id
            ?? $planting->field?->farm_id;

        // ── Gate 4: conta ativa obrigatória ────────────────────────────
        $account = FinancialAccount::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $account) {
            Log::warning('HarvestToRevenueService: pulado — nenhuma FinancialAccount ativa para gerar receita.', [
                'harvest_id' => $harvest->id,
                'tenant_id' => $tenantId,
                'valor_total' => $valor,
            ]);

            return null;
        }

        // ── Gate 5: categoria preferida (fallback null) ────────────────
        $category = Category::query()
            ->when($tenantId, fn ($q) => $q->where(function ($w) use ($tenantId) {
                $w->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            }))
            ->where('tipo', 'financeiro_receita')
            ->where('slug', self::CATEGORY_SLUG)
            ->where('is_active', true)
            ->first();

        // ── Descrição auditável com cultura + quantidade ──────────────
        $crop = $planting->crop;
        $nomeCultura = $crop?->nome ?? 'cultura #' . $planting->crop_id;
        $descricao = "Receita de colheita ({$nomeCultura})";

        $qtdFmt = rtrim(rtrim(number_format((float) $harvest->quantidade_colhida, 4, ',', '.'), '0'), ',');
        $unidade = $harvest->unidade ?? '';
        $qtdHuman = "{$qtdFmt} {$unidade}";

        $dataColheita = $harvest->data_colheita?->format('Y-m-d') ?? now()->toDateString();

        // ── Cria a transação ──────────────────────────────────────────
        return FinancialTransaction::create([
            'account_id' => $account->id,
            'category_id' => $category?->id,
            // Harvest não tem partner_id no schema — receita fica sem
            // vínculo direto com comprador. Master pode ajustar depois.
            'partner_id' => null,
            'tipo' => 'receita',
            'descricao' => $descricao,
            'observacoes' => "Gerado automaticamente pelo registro de colheita #{$harvest->id}. "
                . "Cultura: {$nomeCultura}. Quantidade colhida: {$qtdHuman}. "
                . 'Para refletir um recebimento efetivo, marque esta transação como paga.',
            'valor' => $valor,
            'data_vencimento' => $dataColheita,
            'status' => 'pendente',
            'numero_documento' => $marker,
            'created_by' => null, // Harvest não rastreia criador no schema atual
            'tenant_id' => $tenantId,
            'farm_id' => $farmId,
        ]);
    }
}
