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
    /**
     * ═════ D3 — Consolidação de Domínio · ESTOQUE ═════
     *
     * Regras condicionais ao `tipo` do item. Espelha o padrão aplicado em D2
     * (Rebanho): matriz de regras em constants + método validateDomainCoherence
     * chamado em store/update, com retrocompat para registros legados.
     *
     * RETROCOMPATIBILIDADE:
     *   - Tipos NÃO listados nas matrizes (ferramenta, peca, material, combustivel)
     *     passam sem validação extra — fallback permissivo.
     *   - Em update, regras só aplicam a CAMPOS QUE MUDARAM (items cadastrados
     *     antes destas regras continuam editáveis sem exigir retrabalho).
     */

    /** Tipos que exigem registro no Ministério da Saúde. */
    private const TIPOS_EXIGEM_REGISTRO_MS = ['medicamento'];

    /**
     * Tipos que exigem `descricao` com contexto real de uso.
     * Evita cadastros genéricos ("Ração", "Insumo") sem indicar público-alvo,
     * composição ou finalidade — o que torna o estoque inútil na operação.
     */
    private const TIPOS_EXIGEM_DESCRICAO = ['racao', 'insumo'];

    /** Mínimo de caracteres da descrição contextual. */
    private const DESCRICAO_MIN_CHARS = 10;

    /** Orientação específica por tipo (texto de ajuda no bloqueio). */
    private const DICAS_DESCRICAO = [
        'racao'  => "Ex.: 'Ração 20% proteína para bezerros em desmame' ou 'Ração peletizada para postura fase 2'.",
        'insumo' => "Ex.: 'NPK 08-28-16 para plantio de milho' ou 'Calcário dolomítico para correção de solo'.",
    ];

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

        // Resumo de valor — ajuda o dono a decidir o que comprar HOJE,
        // sem precisar varrer a tabela procurando vermelhos. A subquery
        // é agregada, então impacto no MySQL é baixo.
        $resumo = DB::table('stock_items')
            ->leftJoin('stock_movements', 'stock_items.id', '=', 'stock_movements.item_id')
            ->whereNull('stock_items.deleted_at')
            ->where('stock_items.is_active', true)
            ->select(
                'stock_items.id',
                'stock_items.estoque_minimo',
                DB::raw("SUM(CASE WHEN stock_movements.tipo IN ('entrada','ajuste') THEN stock_movements.quantidade WHEN stock_movements.tipo = 'saida' THEN -stock_movements.quantidade ELSE 0 END) as saldo")
            )
            ->groupBy('stock_items.id', 'stock_items.estoque_minimo')
            ->get();

        $abaixoMinimo = $resumo->filter(fn ($r) => (float) ($r->saldo ?? 0) < (float) $r->estoque_minimo)->count();
        $semEstoque  = $resumo->filter(fn ($r) => (float) ($r->saldo ?? 0) <= 0)->count();
        $totalItens  = $resumo->count();

        return Inertia::render('Admin/Stock/Items/Index', [
            'items' => $items,
            'filters' => $request->only(['search', 'tipo', 'category_id', 'status']),
            'categories' => Category::where('tipo', 'estoque')->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'resumo' => [
                'total_itens' => $totalItens,
                'abaixo_minimo' => $abaixoMinimo,
                'sem_estoque' => $semEstoque,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Stock/Items/Form', [
            'item' => null,
            'categories' => Category::where('tipo', 'estoque')->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    /**
     * Endpoint inline (JSON) — cria item de estoque sem redirect, para uso
     * em modais dentro de wizards (Estoque Ajustar, Receber Mercadoria,
     * e também Vacinação quando usuário quer registrar o medicamento).
     */
    public function storeInline(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:200'],
            'tipo' => ['required', 'in:insumo,medicamento,racao,ferramenta,peca,combustivel,material'],
            'unidade' => ['required', 'string', 'max:20'],
            'estoque_minimo' => ['nullable', 'numeric', 'min:0'],
        ]);

        // `codigo` é NOT NULL — gera automático quando não informado.
        $base = \Illuminate\Support\Str::slug($data['nome']);
        $codigo = strtoupper(substr($base, 0, 8)) . '-' . substr(time(), -4);

        $item = \App\Models\Stock\StockItem::create([
            'nome' => $data['nome'],
            'codigo' => $codigo,
            'tipo' => $data['tipo'],
            'unidade' => $data['unidade'],
            'estoque_minimo' => $data['estoque_minimo'] ?? 0,
            'is_active' => true,
        ]);

        return response()->json([
            'id' => $item->id,
            'nome' => $item->nome,
            'tipo' => $item->tipo,
            'unidade' => $item->unidade,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateItem($request);

        // D3 · Coerência por tipo
        if ($err = $this->validateDomainCoherence($data, null)) {
            return back()->withInput()->with('error', $err);
        }

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

        // D3 · Coerência também em update, respeitando legado:
        // regras só aplicam a campos que MUDARAM.
        if ($err = $this->validateDomainCoherence($data, $item)) {
            return back()->withInput()->with('error', $err);
        }

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

    /**
     * D3 · Valida coerência entre os dados do item e o tipo selecionado.
     *
     * Retorna string com mensagem amigável ou null se tudo coerente.
     *
     * Regras por tipo:
     *   1. medicamento → registro_ms obrigatório (regulação sanitária)
     *   2. racao       → descrição contextual obrigatória (evitar item genérico)
     *   3. insumo      → descrição contextual obrigatória (composição/cultura)
     *
     * Em UPDATE ($existing != null), cada regra só dispara para o campo que
     * MUDOU OU quando o TIPO mudou (virou medicamento/racao/insumo agora).
     * Items legados não são penalizados retroativamente.
     *
     * NOTA sobre "controle de validade" para medicamentos: o schema atual
     * não tem campo `data_validade` em stock_items (validade é por lote,
     * deveria ficar no stock_movements de entrada). A validação de validade
     * fica para D3.1 quando adicionarmos o campo; aqui nos limitamos ao
     * registro_ms, que é inequívoco e tem coluna.
     */
    protected function validateDomainCoherence(array $data, ?StockItem $existing): ?string
    {
        $tipo = $data['tipo'] ?? null;
        if (! $tipo) {
            return null; // já validado pelo validator base (required)
        }

        $tipoMudou = $existing === null || $tipo !== $existing->tipo;

        // ── R1. MEDICAMENTO exige registro_ms ──────────────────────────────
        if (in_array($tipo, self::TIPOS_EXIGEM_REGISTRO_MS, true)) {
            $regNovo = trim((string) ($data['registro_ms'] ?? ''));
            $regAntigo = (string) ($existing->registro_ms ?? '');
            $regMudou = $existing === null || $regNovo !== $regAntigo;

            // Só bloqueia se:
            //   - é cadastro novo (sempre valida) OU
            //   - é update que mudou o tipo para medicamento OU
            //   - é update que mexeu no próprio registro_ms
            $deveValidar = $existing === null || $tipoMudou || $regMudou;

            if ($deveValidar && $regNovo === '') {
                return 'Medicamentos exigem registro no Ministério da Saúde. '
                    . "Preencha o campo 'Registro MS' (ex.: MS 1.2345.6789) antes de salvar. "
                    . 'Este número garante rastreabilidade sanitária obrigatória por lei.';
            }
        }

        // ── R2/R3. RACAO e INSUMO exigem descrição contextual ──────────────
        if (in_array($tipo, self::TIPOS_EXIGEM_DESCRICAO, true)) {
            $descNova = trim((string) ($data['descricao'] ?? ''));
            $descAntiga = trim((string) ($existing->descricao ?? ''));
            $descMudou = $existing === null || $descNova !== $descAntiga;

            $deveValidar = $existing === null || $tipoMudou || $descMudou;

            if ($deveValidar && mb_strlen($descNova) < self::DESCRICAO_MIN_CHARS) {
                $rotulo = $tipo === 'racao' ? 'Ração' : 'Insumo agrícola';
                $motivo = $tipo === 'racao'
                    ? 'indique para qual animal/fase ou finalidade se destina'
                    : 'indique composição, finalidade ou cultura aplicável';
                $dica = self::DICAS_DESCRICAO[$tipo] ?? '';

                return "{$rotulo} exige descrição com contexto real ("
                    . self::DESCRICAO_MIN_CHARS . ' caracteres no mínimo). Use o campo "Descrição" para '
                    . $motivo . '. ' . $dica;
            }
        }

        return null;
    }
}
