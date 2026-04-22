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

        // 2. PÚBLICAS (cadeia de fontes — Open Food Facts, UPCItemDB, Cosmos, Go-UPC, UPCDatabase, Barcode Lookup)
        [$suggestion, $attempts, $diag] = $this->searchPublicBarcode($code);
        $logData['http_status_off'] = $attempts['openfoodfacts']['status'] ?? null;
        $logData['http_status_upc'] = $attempts['upcitemdb']['status'] ?? null;
        $logData['nota_diagnostica'] = $diag;
        $logData['attempts_json'] = $attempts;
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
     * Consulta cadeia de bases públicas de código de barras.
     * Fontes na ordem (falha em uma vai pra próxima):
     *   1. Open Food Facts — alimentos (forte em Brasil/Europa)
     *   2. UPCItemDB — geral, trial sem key (limite 100/dia)
     *   3. Cosmos BlueSoft — oficial brasileira (quando BARCODE_COSMOS_TOKEN configurado)
     *   4. Go-UPC — cobertura ampla (quando BARCODE_GOUPC_KEY configurado)
     *   5. UPCDatabase.org — fallback comunitário (quando BARCODE_UPCDB_KEY configurado)
     *   6. Barcode Lookup — robusto (quando BARCODE_LOOKUP_KEY configurado)
     *
     * Chaves são opcionais: sem elas, a fonte é pulada com nota "sem API key".
     */
    protected function searchPublicBarcode(string $code): array
    {
        $attempts = [];
        $sources = [
            ['openfoodfacts', 'Open Food Facts', fn () => $this->tryOpenFoodFacts($code)],
            ['upcitemdb', 'UPCItemDB', fn () => $this->tryUpcItemDb($code)],
            ['cosmos', 'Cosmos (GS1 Brasil)', fn () => $this->tryCosmos($code)],
            ['goupc', 'Go-UPC', fn () => $this->tryGoUpc($code)],
            ['upcdatabase', 'UPCDatabase.org', fn () => $this->tryUpcDatabase($code)],
            ['barcodelookup', 'Barcode Lookup', fn () => $this->tryBarcodeLookup($code)],
        ];

        foreach ($sources as [$key, $label, $fn]) {
            try {
                [$suggestion, $status, $nota] = $fn();
                $attempts[$key] = [
                    'label' => $label,
                    'status' => $status,
                    'encontrado' => (bool) $suggestion,
                    'nota' => $nota,
                ];
                if ($suggestion) {
                    $diag = "Encontrado em {$label}.";
                    return [$suggestion, $attempts, $diag];
                }
            } catch (\Throwable $e) {
                $attempts[$key] = [
                    'label' => $label,
                    'status' => null,
                    'encontrado' => false,
                    'nota' => 'erro: '.substr($e->getMessage(), 0, 120),
                ];
            }
        }

        $diag = collect($attempts)
            ->map(fn ($a, $k) => $a['label'].' — '.($a['nota'] ?? 'sem informação'))
            ->implode('; ');

        return [null, $attempts, $diag];
    }

    protected function tryOpenFoodFacts(string $code): array
    {
        $resp = \Illuminate\Support\Facades\Http::timeout(3)
            ->acceptJson()
            ->get("https://world.openfoodfacts.org/api/v2/product/{$code}.json");
        $status = $resp->status();
        if (! $resp->successful()) return [null, $status, 'HTTP '.$status];

        $data = $resp->json();
        if (($data['status'] ?? 0) !== 1 || empty($data['product'])) {
            return [null, $status, 'não encontrado'];
        }
        $p = $data['product'];
        $nome = $p['product_name_pt'] ?? $p['product_name'] ?? null;
        if (! $nome) return [null, $status, 'sem nome'];

        return [[
            'source' => 'Open Food Facts',
            'nome' => $nome,
            'marca' => $this->firstItem($p['brands'] ?? ''),
            'categoria_hint' => $p['categories'] ?? null,
            'imagem_url' => $p['image_front_small_url'] ?? null,
            'quantidade_embalagem' => $p['quantity'] ?? null,
        ], $status, 'OK'];
    }

    protected function tryUpcItemDb(string $code): array
    {
        $resp = \Illuminate\Support\Facades\Http::timeout(3)
            ->acceptJson()
            ->get('https://api.upcitemdb.com/prod/trial/lookup', ['upc' => $code]);
        $status = $resp->status();
        if ($status === 429) return [null, $status, 'limite diário atingido (100/dia) — adicione BARCODE_UPCITEMDB_KEY'];
        if (! $resp->successful()) return [null, $status, 'HTTP '.$status];

        $items = $resp->json('items') ?? [];
        if (empty($items)) return [null, $status, 'sem itens'];
        $i = $items[0];
        if (empty($i['title'])) return [null, $status, 'item sem título'];

        return [[
            'source' => 'UPCItemDB',
            'nome' => $i['title'],
            'marca' => $i['brand'] ?? null,
            'categoria_hint' => $i['category'] ?? null,
            'imagem_url' => ! empty($i['images']) ? $i['images'][0] : null,
            'quantidade_embalagem' => null,
        ], $status, 'OK'];
    }

    protected function tryCosmos(string $code): array
    {
        $token = env('BARCODE_COSMOS_TOKEN');
        if (! $token) return [null, null, 'sem API key (BARCODE_COSMOS_TOKEN) — cadastre em cosmos.bluesoft.com.br'];

        $resp = \Illuminate\Support\Facades\Http::timeout(4)
            ->acceptJson()
            ->withHeaders(['X-Cosmos-Token' => $token, 'User-Agent' => 'Cosmos-API-Request'])
            ->get("https://api.cosmos.bluesoft.com.br/gtins/{$code}.json");
        $status = $resp->status();
        if ($status === 404) return [null, $status, 'não encontrado'];
        if (! $resp->successful()) return [null, $status, 'HTTP '.$status];

        $d = $resp->json();
        $nome = $d['description'] ?? $d['title'] ?? null;
        if (! $nome) return [null, $status, 'sem descrição'];

        return [[
            'source' => 'Cosmos (GS1 Brasil)',
            'nome' => $nome,
            'marca' => $d['brand']['name'] ?? null,
            'categoria_hint' => $d['ncm']['description'] ?? null,
            'imagem_url' => $d['thumbnail'] ?? null,
            'quantidade_embalagem' => $d['gross_weight'] ?? null,
        ], $status, 'OK'];
    }

    protected function tryGoUpc(string $code): array
    {
        $key = env('BARCODE_GOUPC_KEY');
        if (! $key) return [null, null, 'sem API key (BARCODE_GOUPC_KEY) — cadastre em go-upc.com'];

        $resp = \Illuminate\Support\Facades\Http::timeout(4)
            ->acceptJson()
            ->withHeaders(['Authorization' => 'Bearer '.$key])
            ->get("https://go-upc.com/api/v1/code/{$code}");
        $status = $resp->status();
        if (! $resp->successful()) return [null, $status, 'HTTP '.$status];

        $p = $resp->json('product');
        if (empty($p['name'])) return [null, $status, 'sem nome'];

        return [[
            'source' => 'Go-UPC',
            'nome' => $p['name'],
            'marca' => $p['brand'] ?? null,
            'categoria_hint' => $p['category'] ?? null,
            'imagem_url' => $p['imageUrl'] ?? null,
            'quantidade_embalagem' => null,
        ], $status, 'OK'];
    }

    protected function tryUpcDatabase(string $code): array
    {
        $key = env('BARCODE_UPCDB_KEY');
        if (! $key) return [null, null, 'sem API key (BARCODE_UPCDB_KEY) — cadastre em upcdatabase.org'];

        $resp = \Illuminate\Support\Facades\Http::timeout(4)
            ->acceptJson()
            ->withHeaders(['Authorization' => 'Bearer '.$key])
            ->get("https://api.upcdatabase.org/product/{$code}");
        $status = $resp->status();
        if (! $resp->successful()) return [null, $status, 'HTTP '.$status];

        $d = $resp->json();
        $nome = $d['title'] ?? $d['description'] ?? null;
        if (! $nome) return [null, $status, 'sem título'];

        return [[
            'source' => 'UPCDatabase.org',
            'nome' => $nome,
            'marca' => $d['brand'] ?? null,
            'categoria_hint' => $d['category'] ?? null,
            'imagem_url' => null,
            'quantidade_embalagem' => $d['size'] ?? null,
        ], $status, 'OK'];
    }

    protected function tryBarcodeLookup(string $code): array
    {
        $key = env('BARCODE_LOOKUP_KEY');
        if (! $key) return [null, null, 'sem API key (BARCODE_LOOKUP_KEY) — cadastre em barcodelookup.com'];

        $resp = \Illuminate\Support\Facades\Http::timeout(4)
            ->acceptJson()
            ->get('https://api.barcodelookup.com/v3/products', [
                'barcode' => $code,
                'key' => $key,
            ]);
        $status = $resp->status();
        if (! $resp->successful()) return [null, $status, 'HTTP '.$status];

        $products = $resp->json('products') ?? [];
        if (empty($products)) return [null, $status, 'sem produtos'];
        $p = $products[0];
        if (empty($p['product_name']) && empty($p['title'])) return [null, $status, 'sem nome'];

        return [[
            'source' => 'Barcode Lookup',
            'nome' => $p['product_name'] ?? $p['title'],
            'marca' => $p['brand'] ?? $p['manufacturer'] ?? null,
            'categoria_hint' => $p['category'] ?? null,
            'imagem_url' => ! empty($p['images']) ? $p['images'][0] : null,
            'quantidade_embalagem' => $p['size'] ?? null,
        ], $status, 'OK'];
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
