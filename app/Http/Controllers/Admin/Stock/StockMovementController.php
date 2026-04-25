<?php

namespace App\Http\Controllers\Admin\Stock;

use App\Domain\Integration\Services\StockPurchaseToExpenseService;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Stock\StockItem;
use App\Models\Stock\StockMovement;
use App\Models\Stock\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $q = StockMovement::with(['item:id,nome,unidade', 'warehouse:id,nome', 'partner:id,nome'])
            ->when($request->tipo, fn ($qq) => $qq->where('tipo', $request->tipo))
            ->when($request->item_id, fn ($qq) => $qq->where('item_id', $request->item_id))
            ->when($request->warehouse_id, fn ($qq) => $qq->where('warehouse_id', $request->warehouse_id))
            ->when($request->from, fn ($qq) => $qq->where('data', '>=', $request->from))
            ->when($request->to, fn ($qq) => $qq->where('data', '<=', $request->to))
            ->orderByDesc('data')
            ->orderByDesc('id');

        return Inertia::render('Admin/Stock/Movements/Index', [
            'movements' => $q->paginate(25)->withQueryString(),
            'filters' => $request->only(['tipo', 'item_id', 'warehouse_id', 'from', 'to']),
            'items' => StockItem::where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'unidade']),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'partners' => Partner::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    /**
     * FASE 2 · F2.2 — Integração cross-módulo:
     *   Quando o movimento é tipo=entrada com valor_total>0, gera
     *   automaticamente uma FinancialTransaction (tipo=despesa) via
     *   StockPurchaseToExpenseService. Tudo dentro da DB::transaction
     *   existente — atomicidade garantida com o recálculo de custo médio.
     */
    public function store(Request $request, StockPurchaseToExpenseService $purchase)
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:stock_items,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'tipo' => ['required', 'in:entrada,saida,ajuste,transferencia'],
            'motivo' => ['nullable', 'string', 'max:50'],
            'data' => ['required', 'date'],
            'quantidade' => ['required', 'numeric', 'gt:0'],
            'valor_unitario' => ['nullable', 'numeric', 'min:0'],
            'numero_documento' => ['nullable', 'string', 'max:50'],
            'observacoes' => ['nullable', 'string'],
        ]);

        // Força valor_unitario não-null (coluna é NOT NULL no schema).
        // Validator aceita nullable para permitir o usuário omitir em ajustes/saídas,
        // mas o DB exige valor definido — default 0 é seguro e não polui custo médio.
        $data['valor_unitario'] = $data['valor_unitario'] ?? 0;
        $data['valor_total'] = $data['valor_unitario'] * $data['quantidade'];
        $data['created_by'] = $request->user()->id;

        DB::transaction(function () use ($data, $purchase) {
            $m = StockMovement::create($data);

            // Atualiza custo médio ponderado se for entrada com valor
            if ($data['tipo'] === 'entrada' && ! empty($data['valor_unitario'])) {
                $item = StockItem::find($data['item_id']);
                // BUG FIX B4.4: usar StockMovement model com scopes (não DB::table)
                $saldoAnterior = StockMovement::query()
                    ->where('item_id', $item->id)
                    ->where('id', '<>', $m->id)
                    ->selectRaw("SUM(CASE WHEN tipo IN ('entrada','ajuste') THEN quantidade WHEN tipo='saida' THEN -quantidade END) as s")
                    ->value('s') ?? 0;

                $custoMedioAnterior = (float) $item->custo_medio;
                $novoSaldo = $saldoAnterior + $data['quantidade'];

                if ($novoSaldo > 0) {
                    $custoMedioNovo = (
                        ($saldoAnterior * $custoMedioAnterior) +
                        ($data['quantidade'] * $data['valor_unitario'])
                    ) / $novoSaldo;
                    $item->update(['custo_medio' => round($custoMedioNovo, 4)]);
                }
            }

            // ── F2.2 · Integração Compra → Despesa Financeira ─────────
            // Service decide se gera (tipo=entrada + valor_total>0 + conta
            // ativa) e é idempotente (numero_documento=STOCK_MOVEMENT:<id>).
            // Retorna null silenciosamente quando não se aplica.
            $m->loadMissing('item');
            $purchase->generateForMovement($m);
        });

        return back()->with('success', 'Movimentação registrada.');
    }

    public function destroy(StockMovement $movement)
    {
        $movement->delete();

        return back()->with('success', 'Movimentação excluída.');
    }
}
