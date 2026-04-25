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

    public function saida(): Response
    {
        // Saldos atuais por item — ajuda o usuário a escolher só o que tem.
        $saldos = StockMovement::selectRaw("
            item_id,
            SUM(CASE WHEN tipo IN ('entrada','ajuste') THEN quantidade WHEN tipo='saida' THEN -quantidade ELSE 0 END) as saldo
        ")->groupBy('item_id')->pluck('saldo', 'item_id')->toArray();

        return Inertia::render('Admin/Wizards/EstoqueSaida', [
            'itens' => StockItem::where('is_active', true)->orderBy('nome')
                ->get(['id', 'nome', 'unidade'])
                ->map(fn ($i) => [
                    'id' => $i->id,
                    'nome' => $i->nome,
                    'unidade' => $i->unidade,
                    'saldo' => (float) ($saldos[$i->id] ?? 0),
                ])->values(),
            'armazens' => Warehouse::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function storeSaida(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:stock_items,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'quantidade' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['required', 'in:uso,perda,doacao,transferencia,outro'],
            'data' => ['required', 'date'],
            'observacoes' => ['nullable', 'string'],
        ]);

        // Saldo atual antes da saída — pra calcular saldo final no contexto
        $saldoAntes = (float) StockMovement::where('item_id', $data['item_id'])
            ->where('warehouse_id', $data['warehouse_id'])
            ->selectRaw("
                SUM(CASE
                    WHEN tipo IN ('entrada','ajuste') THEN quantidade
                    WHEN tipo='saida' THEN -quantidade
                    ELSE 0
                END) as saldo
            ")->value('saldo') ?? 0;

        // BLOCO 4.3 RN1 — bloqueia saldo negativo. Valida ANTES do create.
        if ((float) $data['quantidade'] > $saldoAntes) {
            $item = \App\Models\Stock\StockItem::find($data['item_id']);
            $unidade = $item?->unidade ?? 'un';
            $nomeItem = $item?->nome ?? 'Item';
            return back()
                ->withInput()
                ->withErrors([
                    'quantidade' => sprintf(
                        'Saldo insuficiente. %s tem apenas %s %s disponível neste armazém. Você tentou retirar %s %s.',
                        $nomeItem,
                        number_format($saldoAntes, 2, ',', '.'),
                        $unidade,
                        number_format((float) $data['quantidade'], 2, ',', '.'),
                        $unidade
                    ),
                ]);
        }

        StockMovement::create([
            ...$data,
            'tipo' => 'saida',
            'valor_unitario' => 0,
            'valor_total' => 0,
            'created_by' => $request->user()->id,
        ]);

        session()->flash('ajuste_contexto', [
            'item_id' => (int) $data['item_id'],
            'warehouse_id' => (int) $data['warehouse_id'],
            'ajuste' => -(float) $data['quantidade'],
            'saldo_anterior' => $saldoAntes,
            'saldo_atual' => $saldoAntes - (float) $data['quantidade'],
        ]);

        return back()->with('success', 'Saída de estoque registrada.');
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

        // Contexto pós-ajuste — saldo atual do item no armazém, que o
        // wizard exibe no passo 4 para o usuário VER que o ajuste teve
        // o efeito esperado. Dado crítico pra confiança no sistema.
        $saldoAtual = StockMovement::where('item_id', $data['item_id'])
            ->where('warehouse_id', $data['warehouse_id'])
            ->selectRaw("
                SUM(CASE
                    WHEN tipo IN ('entrada', 'ajuste') THEN quantidade
                    WHEN tipo = 'saida' THEN -quantidade
                    ELSE 0
                END) as saldo
            ")->value('saldo') ?? 0;

        session()->flash('ajuste_contexto', [
            'item_id' => (int) $data['item_id'],
            'warehouse_id' => (int) $data['warehouse_id'],
            'ajuste' => (float) $data['quantidade'],
            'saldo_atual' => (float) $saldoAtual,
            'saldo_anterior' => (float) $saldoAtual - (float) $data['quantidade'],
        ]);

        return back()->with('success', 'Ajuste realizado.');
    }
}
