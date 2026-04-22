<?php

namespace App\Http\Controllers\Admin\Stock;

use App\Http\Controllers\Controller;
use App\Models\BarcodeLookup;
use App\Models\Category;
use App\Models\Stock\StockItem;
use App\Models\Stock\Warehouse;
use App\Services\BarcodeLookup\ProductLookupService;
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

    /**
     * Endpoint único de lookup de produto por código de barras.
     * Delega toda a orquestração pro ProductLookupService (cadeia de 11 fontes).
     * Resposta unificada pro front, com log estruturado de observabilidade no banco.
     */
    public function lookupByBarcode(Request $request, ProductLookupService $lookup): \Illuminate\Http\JsonResponse
    {
        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'Código vazio.'], 422);
        }

        $result = $lookup->getProductByBarcode($code);

        // Caso especial: fonte local já devolveu o produto → resposta enriquecida
        // com dados pra abrir o modal "produto já cadastrado".
        if ($result->found() && $result->sourceUsed() === 'Cadastro local') {
            $atrib = $result->product->atributos;
            $itemId = $atrib['stock_item_id'] ?? null;
            $this->logBarcodeLookup($request, $code, $result);

            return response()->json([
                'ok' => true,
                'source' => $result->sourceUsed(),
                'found' => true,
                'item' => $itemId ? [
                    'id' => $itemId,
                    'codigo' => $atrib['codigo_interno'] ?? null,
                    'nome' => $result->product->nome,
                    'marca' => $result->product->marca,
                    'unidade' => $atrib['unidade'] ?? null,
                    'tipo' => $atrib['tipo'] ?? null,
                    'category' => $result->product->categoria ? ['nome' => $result->product->categoria] : null,
                    'saldo_atual' => $atrib['saldo_atual'] ?? 0,
                    'edit_url' => route('admin.estoque.itens.edit', $itemId),
                    'movement_url' => route('admin.estoque.movimentos.index', ['item_id' => $itemId]),
                ] : null,
            ]);
        }

        // Demais fontes (externas) — sugestão pra auto-preencher o form
        $this->logBarcodeLookup($request, $code, $result);

        return response()->json([
            'ok' => true,
            'source' => $result->sourceUsed() ?: 'none',
            'found' => false,
            'suggestion' => $result->product?->toArray(),
            'message' => $result->found()
                ? "Produto identificado em {$result->sourceUsed()}."
                : 'Código não encontrado em bases públicas. Preencha o nome manualmente — das próximas vezes será reconhecido.',
        ]);
    }

    /** Persiste a tentativa em barcode_lookups — observabilidade sem expor ao usuário. */
    protected function logBarcodeLookup(Request $request, string $code, \App\Services\BarcodeLookup\DTO\LookupResult $result): void
    {
        $attemptsArr = array_map(fn ($a) => $a->toArray(), $result->attempts);
        BarcodeLookup::create([
            'code' => $code,
            'user_id' => $request->user()?->id,
            'found_local' => $result->sourceUsed() === 'Cadastro local',
            'source' => $result->sourceUsed() ?: 'none',
            'http_status_off' => collect($attemptsArr)->firstWhere('name', 'openfoodfacts')['status'] ?? null,
            'http_status_upc' => collect($attemptsArr)->firstWhere('name', 'upcitemdb')['status'] ?? null,
            'nome_sugerido' => $result->product?->nome,
            'marca_sugerida' => $result->product?->marca,
            'nota_diagnostica' => sprintf(
                '%d fontes consultadas em %.1fms — %s',
                count($attemptsArr),
                $result->elapsedTotalMs,
                $result->found() ? "hit em {$result->sourceUsed()}" : 'nenhuma fonte identificou'
            ),
            'attempts_json' => $attemptsArr,
        ]);
    }

    // Toda a lógica de consulta externa migrada para:
    //   app/Services/BarcodeLookup/ProductLookupService.php
    // com 11 fontes na cadeia de fallback (ver config/barcode_lookup.php).

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
