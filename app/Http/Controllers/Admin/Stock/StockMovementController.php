<?php

namespace App\Http\Controllers\Admin\Stock;

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

    public function store(Request $request)
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

        $data['valor_total'] = ($data['valor_unitario'] ?? 0) * $data['quantidade'];
        $data['created_by'] = $request->user()->id;

        DB::transaction(function () use ($data) {
            $m = StockMovement::create($data);

            // Atualiza custo médio ponderado se for entrada com valor
            if ($data['tipo'] === 'entrada' && ! empty($data['valor_unitario'])) {
                $item = StockItem::find($data['item_id']);
                $saldoAnterior = DB::table('stock_movements')
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
        });

        return back()->with('success', 'Movimentação registrada.');
    }

    public function destroy(StockMovement $movement)
    {
        $movement->delete();

        return back()->with('success', 'Movimentação excluída.');
    }
}
