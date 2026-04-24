<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Stock\StockItem;
use App\Models\Stock\StockMovement;
use App\Models\Stock\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assistente guiado — Operações de estoque.
 *
 *   receber  → entrada (com fornecedor + nota opcional)
 *   ajustar  → ajuste (corrige saldo pra bater com a realidade)
 *
 * Submit próprio retorna back() para que o wizard avance ao passo final.
 */
class EstoqueWizardController extends Controller
{
    public function receber(): Response
    {
        return Inertia::render('Admin/Wizards/EstoqueReceber', [
            'itens' => StockItem::where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'unidade']),
            'armazens' => Warehouse::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'fornecedores' => Partner::whereIn('tipo', ['fornecedor', 'ambos'])->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function ajustar(): Response
    {
        return Inertia::render('Admin/Wizards/EstoqueAjustar', [
            'itens' => StockItem::where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'unidade']),
            'armazens' => Warehouse::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function storeReceber(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:stock_items,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'quantidade' => ['required', 'numeric', 'gt:0'],
            'valor_unitario' => ['nullable', 'numeric', 'min:0'],
            'numero_documento' => ['nullable', 'string', 'max:50'],
            'data' => ['required', 'date'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $data['tipo'] = 'entrada';
        $data['motivo'] = 'compra';
        $data['valor_unitario'] = $data['valor_unitario'] ?? 0;
        $data['valor_total'] = $data['valor_unitario'] * $data['quantidade'];
        $data['created_by'] = $request->user()->id;

        StockMovement::create($data);

        return back()->with('success', 'Mercadoria recebida.');
    }

    public function storeAjustar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:stock_items,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'quantidade' => ['required', 'numeric'],     // pode ser negativa
            'motivo' => ['required', 'string', 'max:50'],
            'data' => ['required', 'date'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $data['tipo'] = 'ajuste';
        $data['valor_unitario'] = 0;
        $data['valor_total'] = 0;
        $data['created_by'] = $request->user()->id;

        StockMovement::create($data);

        return back()->with('success', 'Ajuste realizado.');
    }
}
