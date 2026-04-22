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

    /**
     * Lookup por código de barras com:
     *  1. Busca local (StockItem)
     *  2. Fallback Open Food Facts + UPCItemDB
     *  3. Log da tentativa em barcode_lookups para diagnóstico
     *  4. Retorna `attempts` com status HTTP de cada fonte pro usuário entender
     */
    public function lookupByBarcode(Request $request): \Illuminate\Http\JsonResponse
    {
        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'Código vazio.'], 422);
        }

        $logData = [
            'code' => $code,
            'user_id' => $request->user()?->id,
            'found_local' => false,
            'source' => 'none',
            'http_status_off' => null,
            'http_status_upc' => null,
            'nome_sugerido' => null,
            'marca_sugerida' => null,
            'nota_diagnostica' => null,
        ];
        $attempts = [];

        // 1. LOCAL
        $item = StockItem::with('category:id,nome')
            ->where('codigo_barras', $code)
            ->orWhere('codigo', $code)
            ->first();

        if ($item) {
            $logData['found_local'] = true;
            $logData['source'] = 'local';
            \App\Models\BarcodeLookup::create($logData);

            return response()->json([
                'ok' => true,
                'source' => 'local',
                'found' => true,
                'item' => [
                    'id' => $item->id,
                    'codigo' => $item->codigo,
                    'codigo_barras' => $item->codigo_barras,
                    'nome' => $item->nome,
                    'marca' => $item->marca,
                    'unidade' => $item->unidade,
                    'tipo' => $item->tipo,
                    'category' => $item->category,
                    'saldo_atual' => $item->saldoAtual(),
                    'edit_url' => route('admin.estoque.itens.edit', $item->id),
                    'movement_url' => route('admin.estoque.movimentos.index', ['item_id' => $item->id]),
                ],
            ]);
        }

        // 2. PÚBLICAS
        [$suggestion, $attempts, $diag] = $this->searchPublicBarcode($code);
        $logData['http_status_off'] = $attempts['openfoodfacts']['status'] ?? null;
        $logData['http_status_upc'] = $attempts['upcitemdb']['status'] ?? null;
        $logData['nota_diagnostica'] = $diag;
        if ($suggestion) {
            $logData['source'] = $suggestion['source'];
            $logData['nome_sugerido'] = $suggestion['nome'];
            $logData['marca_sugerida'] = $suggestion['marca'];
        }
        \App\Models\BarcodeLookup::create($logData);

        return response()->json([
            'ok' => true,
            'source' => $suggestion ? $suggestion['source'] : 'none',
            'found' => false,
            'suggestion' => $suggestion,
            'attempts' => $attempts,
            'diagnostico' => $diag,
            'message' => $suggestion
                ? "Produto identificado em {$suggestion['source']}."
                : 'Código não encontrado em bases públicas. Preencha o nome manualmente — das próximas vezes será reconhecido.',
        ]);
    }

    /**
     * Consulta bases públicas. Retorna [suggestion, attempts, diagnostico].
     * `attempts` expõe status HTTP e motivo de cada fonte, pro diagnóstico do usuário.
     */
    protected function searchPublicBarcode(string $code): array
    {
        $attempts = [
            'openfoodfacts' => ['status' => null, 'encontrado' => false, 'nota' => null],
            'upcitemdb' => ['status' => null, 'encontrado' => false, 'nota' => null],
        ];

        // Open Food Facts — alimentos
        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(3)
                ->acceptJson()
                ->get("https://world.openfoodfacts.org/api/v2/product/{$code}.json");
            $attempts['openfoodfacts']['status'] = $resp->status();
            if ($resp->successful()) {
                $data = $resp->json();
                if (($data['status'] ?? 0) === 1 && ! empty($data['product'])) {
                    $p = $data['product'];
                    $nome = $p['product_name_pt'] ?? $p['product_name'] ?? null;
                    if ($nome) {
                        $attempts['openfoodfacts']['encontrado'] = true;
                        return [[
                            'source' => 'Open Food Facts',
                            'nome' => $nome,
                            'marca' => $this->firstItem($p['brands'] ?? ''),
                            'categoria_hint' => $p['categories'] ?? null,
                            'imagem_url' => $p['image_front_small_url'] ?? null,
                            'quantidade_embalagem' => $p['quantity'] ?? null,
                        ], $attempts, 'Encontrado em Open Food Facts.'];
                    }
                    $attempts['openfoodfacts']['nota'] = 'produto existe mas sem nome';
                } else {
                    $attempts['openfoodfacts']['nota'] = 'não encontrado (status=0)';
                }
            } else {
                $attempts['openfoodfacts']['nota'] = 'HTTP '.$resp->status();
            }
        } catch (\Throwable $e) {
            $attempts['openfoodfacts']['nota'] = 'erro: '.substr($e->getMessage(), 0, 120);
        }

        // UPCItemDB — produtos gerais
        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(3)
                ->acceptJson()
                ->get('https://api.upcitemdb.com/prod/trial/lookup', ['upc' => $code]);
            $attempts['upcitemdb']['status'] = $resp->status();
            if ($resp->successful()) {
                $data = $resp->json();
                $items = $data['items'] ?? [];
                if (! empty($items)) {
                    $i = $items[0];
                    $nome = $i['title'] ?? null;
                    if ($nome) {
                        $attempts['upcitemdb']['encontrado'] = true;
                        return [[
                            'source' => 'UPCItemDB',
                            'nome' => $nome,
                            'marca' => $i['brand'] ?? null,
                            'categoria_hint' => $i['category'] ?? null,
                            'imagem_url' => ! empty($i['images']) ? $i['images'][0] : null,
                            'quantidade_embalagem' => null,
                        ], $attempts, 'Encontrado em UPCItemDB.'];
                    }
                    $attempts['upcitemdb']['nota'] = 'item sem title';
                } else {
                    $attempts['upcitemdb']['nota'] = 'sem itens';
                }
            } elseif ($resp->status() === 429) {
                $attempts['upcitemdb']['nota'] = 'limite da API gratuita atingido (100/dia)';
            } else {
                $attempts['upcitemdb']['nota'] = 'HTTP '.$resp->status();
            }
        } catch (\Throwable $e) {
            $attempts['upcitemdb']['nota'] = 'erro: '.substr($e->getMessage(), 0, 120);
        }

        $diag = 'Tentativas: Open Food Facts — '.$attempts['openfoodfacts']['nota']
            .'; UPCItemDB — '.$attempts['upcitemdb']['nota'];

        return [null, $attempts, $diag];
    }

    protected function firstItem(?string $s): ?string
    {
        if (! $s) return null;
        $first = explode(',', $s)[0] ?? null;
        return $first ? trim($first) : null;
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
