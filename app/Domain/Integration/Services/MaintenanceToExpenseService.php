<?php

namespace App\Domain\Integration\Services;

use App\Models\Category;
use App\Models\Financial\FinancialAccount;
use App\Models\Financial\FinancialTransaction;
use App\Models\Vehicle\MaintenanceOrder;
use App\Models\Vehicle\Vehicle;
use Illuminate\Support\Facades\Log;

/**
 * Integração · Manutenção concluída → Despesa financeira
 *
 * QUARTA integração automática cross-módulo. Quando uma
 * `MaintenanceOrder` atinge status `concluida` com `valor_total > 0`,
 * este service gera automaticamente uma `FinancialTransaction` de
 * despesa, SE ainda não houver uma associada.
 *
 * ─ COEXISTÊNCIA COM FLUXO MANUAL EXISTENTE ───────────────────────
 *
 * O `VehicleController::maintenanceStore` já tem um fluxo manual
 * OPCIONAL de geração de lançamento financeiro (checkbox
 * `gerar_lancamento_financeiro` + `account_id` informado). Quando o
 * fluxo manual é usado, ele popula `maintenance_order.transaction_id`
 * diretamente — sem `numero_documento` de marcador.
 *
 * Este service é `EXPLÍCITO AUTOMÁTICO` (dispara sempre que
 * status=concluida + valor>0), mas RESPEITA o fluxo manual:
 *
 *   - Gate 1: se `order->transaction_id` já está preenchido
 *     (sinal de que alguém — fluxo manual OU chamada anterior —
 *     já gerou a FT) → retorna a FT existente sem criar nova
 *
 *   - Gate 2: se já existe FT com `numero_documento=MAINTENANCE:<id>`
 *     (chamada anterior deste service) → retorna a existente
 *
 *   - Caso contrário: cria a FT + popula `order.transaction_id`
 *
 * Assim:
 *   - User que prefere o fluxo manual continua usando-o como antes
 *   - Demais manutenções concluídas recebem FT automática
 *   - Re-chamar o service em qualquer cenário é no-op (idempotente)
 *
 * ─ OUTROS PRINCÍPIOS ─────────────────────────────────────────────
 *
 * Segue o padrão F2.1/F2.2/F2.3:
 *   - Sem migration, sem UI, sem schema change
 *   - Retrocompat com manutenções antigas
 *   - Categoria pelo tipo do veículo (implemento vs demais)
 *   - Conta ativa obrigatória (skip + log se ausente)
 *   - Caller envolve em DB::transaction (não abre própria)
 */
class MaintenanceToExpenseService
{
    /** Prefixo fixo do marcador de idempotência. */
    private const DOC_MARKER_PREFIX = 'MAINTENANCE:';

    /**
     * Mapa: vehicle.tipo → slug da categoria de despesa.
     *
     * Seedado em CategorySeeder como financeiro_despesa:
     *   - manutencao-de-veiculos (veículos rodoviários + máquinas)
     *   - manutencao-de-implementos (equipamentos sem motor próprio)
     */
    private const CATEGORIA_IMPLEMENTO = 'manutencao-de-implementos';
    private const CATEGORIA_VEICULO = 'manutencao-de-veiculos';

    /**
     * Gera (ou recupera) a FinancialTransaction de despesa associada
     * a uma MaintenanceOrder. Retorna null quando não se aplica.
     *
     * Caller deve envolver em DB::transaction para atomicidade com a
     * criação/update da order.
     */
    public function generateForOrder(MaintenanceOrder $order): ?FinancialTransaction
    {
        // ── Gate 1: só manutenção concluída ────────────────────────────
        if ($order->status !== 'concluida') {
            return null;
        }

        // ── Gate 2: valor > 0 ──────────────────────────────────────────
        $valor = (float) ($order->valor_total ?? 0);
        if ($valor <= 0) {
            return null;
        }

        // ── Gate 3: respeita fluxo manual/prévio via transaction_id ────
        if (! empty($order->transaction_id)) {
            return FinancialTransaction::find($order->transaction_id);
        }

        // ── Gate 4: idempotência por marcador ──────────────────────────
        $marker = self::DOC_MARKER_PREFIX . $order->id;
        $existing = FinancialTransaction::where('numero_documento', $marker)->first();
        if ($existing !== null) {
            // Repara FK desnormalizada caso alguém tenha limpado transaction_id
            if ($order->transaction_id !== $existing->id) {
                $order->update(['transaction_id' => $existing->id]);
            }

            return $existing;
        }

        // ── Gate 5: conta ativa obrigatória ────────────────────────────
        $vehicle = $order->vehicle ?? Vehicle::find($order->vehicle_id);
        $tenantId = $vehicle?->tenant_id;

        $account = FinancialAccount::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $account) {
            Log::warning('MaintenanceToExpenseService: pulado — nenhuma FinancialAccount ativa para gerar despesa.', [
                'maintenance_order_id' => $order->id,
                'tenant_id' => $tenantId,
                'vehicle_id' => $order->vehicle_id,
                'valor_total' => $valor,
            ]);

            return null;
        }

        // ── Gate 6: categoria por tipo do veículo ──────────────────────
        $catSlug = $vehicle?->tipo === 'implemento'
            ? self::CATEGORIA_IMPLEMENTO
            : self::CATEGORIA_VEICULO;

        $category = Category::query()
            ->when($tenantId, fn ($q) => $q->where(function ($w) use ($tenantId) {
                $w->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            }))
            ->where('tipo', 'financeiro_despesa')
            ->where('slug', $catSlug)
            ->where('is_active', true)
            ->first();

        // ── Descrição auditável ──────────────────────────────────────
        $nomeVeiculo = $vehicle?->nome ?? "#{$order->vehicle_id}";
        $placa = $vehicle?->placa ? " (placa {$vehicle->placa})" : '';
        $descricao = "Manutenção de máquina {$nomeVeiculo}{$placa}";

        $dataOrder = $order->data_realizada
            ?? $order->data_prevista
            ?? now()->toDateString();
        $dataOrder = $dataOrder instanceof \DateTimeInterface
            ? $dataOrder->format('Y-m-d')
            : (string) $dataOrder;

        // ── Cria a transação + atualiza FK desnormalizada ─────────────
        $tx = FinancialTransaction::create([
            'account_id' => $account->id,
            'category_id' => $category?->id,
            'partner_id' => $order->partner_id,
            'tipo' => 'despesa',
            'descricao' => $descricao,
            'observacoes' => "Gerado automaticamente pelo registro de manutenção #{$order->id}. "
                . ($order->descricao ? "Descrição: {$order->descricao}. " : '')
                . ($order->tipo ? "Tipo: {$order->tipo}. " : '')
                . 'Para refletir um pagamento efetivo, marque esta transação como paga.',
            'valor' => $valor,
            'data_vencimento' => $dataOrder,
            'status' => 'pendente',
            'numero_documento' => $marker,
            'created_by' => $order->created_by,
            'tenant_id' => $tenantId,
            'farm_id' => $vehicle?->farm_id,
        ]);

        // Popula FK desnormalizada — útil para queries e para o fluxo
        // manual legado que consulta $order->transaction_id diretamente.
        $order->update(['transaction_id' => $tx->id]);

        return $tx;
    }
}
