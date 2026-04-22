<?php

namespace App\Http\Controllers\Admin\Stock;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Stock\StockItem;
use App\Models\Stock\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StockItemController extends Controller
{
    public function index(Request $request)
    {
        $q = StockItem::with('category:id,nome')
            ->when($request->search, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('nome', 'like', "%{$request->search}%")
                ->orWhere('codigo', 'like', "%{$request->search}%")))
            ->when($request->tipo, fn ($qq) => $qq->where('tipo', $request->tipo))
            ->when($request->category_id, fn ($qq) => $qq->where('category_id', $request->category_id))
            ->when($request->status === 'inativos', fn ($qq) => $qq->where('is_active', false))
            ->when($request->status === 'ativos' || ! $request->status, fn ($qq) => $qq->where('is_active', true))
            ->orderBy('nome');

        // saldo atual por item (soma de entradas - saidas)
        $items = $q->paginate(25)->withQueryString()->through(function (StockItem $i) {
            $saldo = DB::table('stock_movements')
                ->where('item_id', $i->id)
                ->selectRaw("SUM(CASE WHEN tipo IN ('entrada','ajuste') THEN quantidade WHEN tipo = 'saida' THEN -quantidade ELSE 0 END) as saldo")
                ->value('saldo') ?? 0;

            return [
                'id' => $i->id,
                'codigo' => $i->codigo,
                'nome' => $i->nome,
                'tipo' => $i->tipo,
                'unidade' => $i->unidade,
                'marca' => $i->marca,
                'estoque_minimo' => (float) $i->estoque_minimo,
                'custo_medio' => (float) $i->custo_medio,
                'saldo' => (float) $saldo,
                'abaixo_minimo' => (float) $saldo < (float) $i->estoque_minimo,
                'is_active' => $i->is_active,
                'category' => $i->category ? ['id' => $i->category->id, 'nome' => $i->category->nome] : null,
            ];
        });

        return Inertia::render('Admin/Stock/Items/Index', [
            'items' => $items,
            'filters' => $request->only(['search', 'tipo', 'category_id', 'status']),
            'categories' => Category::where('tipo', 'estoque')->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Stock/Items/Form', [
            'item' => null,
            'categories' => Category::where('tipo', 'estoque')->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateItem($request);
        StockItem::create($data);

        return redirect()->route('admin.estoque.itens.index')->with('success', 'Item cadastrado.');
    }

    public function edit(StockItem $item)
    {
        return Inertia::render('Admin/Stock/Items/Form', [
            'item' => $item,
            'categories' => Category::where('tipo', 'estoque')->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function update(Request $request, StockItem $item)
    {
        $data = $this->validateItem($request, $item->id);
        $item->update($data);

        return redirect()->route('admin.estoque.itens.index')->with('success', 'Item atualizado.');
    }

    public function destroy(StockItem $item)
    {
        if ($item->movements()->exists()) {
            $item->update(['is_active' => false]);

            return back()->with('warning', 'Item tem movimentações — foi desativado em vez de excluído.');
        }

        $item->delete();

        return back()->with('success', 'Item excluído.');
    }

    public function toggle(StockItem $item)
    {
        $item->update(['is_active' => ! $item->is_active]);

        return back()->with('success', $item->is_active ? 'Item ativado.' : 'Item desativado.');
    }

    protected function validateItem(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'codigo' => ['required', 'string', 'max:50', Rule::unique('stock_items', 'codigo')->ignore($id)->whereNull('deleted_at')],
            'codigo_barras' => ['nullable', 'string', 'max:32'],
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'unidade' => ['required', 'string', 'max:10'],
            'marca' => ['nullable', 'string', 'max:100'],
            'estoque_minimo' => ['nullable', 'numeric', 'min:0'],
            'estoque_maximo' => ['nullable', 'numeric', 'min:0'],
            'custo_medio' => ['nullable', 'numeric', 'min:0'],
            'tipo' => ['required', 'in:insumo,medicamento,racao,ferramenta,peca,combustivel,material'],
            'registro_ms' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);
    }
}
