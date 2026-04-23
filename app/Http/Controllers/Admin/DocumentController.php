<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document\Document;
use App\Models\Document\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentController extends Controller
{
    /**
     * ═════ D8 — Consolidação de Domínio · DOCUMENTOS ═════
     *
     * O schema `documents` tem `related_type` + `related_id` (morph
     * polimórfico), mas NÃO tem coluna `tipo`. Uso `document_categories`
     * como proxy de tipo via palavra-chave no nome/slug. Esta fase aplica:
     *
     *   1. Whitelist de related_type — só modelos reais do sistema são aceitos.
     *   2. Coerência tipo × vínculo — contrato→Partner, laudo→Animal/Planting etc.
     *
     * RETROCOMPAT CRÍTICA:
     *   O validator atual NÃO recebe `related_type`/`related_id` do form
     *   (a UI atual não expõe esses campos). Aplicar "bloquear documento
     *   sem vínculo" ESTRITAMENTE quebraria 100% dos uploads novos. Por
     *   isso a regra de whitelist/coerência só dispara quando o vínculo É
     *   informado no payload. Documento sem vínculo permanece aceito —
     *   consistente com o comportamento histórico e com a UI atual.
     *
     *   A versão estrita ("todo documento DEVE ter vínculo") fica para
     *   D8.1 em conjunto com a UI expor os campos de vínculo. Registrado
     *   como pendência.
     */

    /**
     * Modelos aceitos como vínculo de documento. FQCN idêntico ao que o
     * Eloquent persiste em `related_type` quando se usa $doc->related()->associate().
     */
    private const RELATED_TYPES_WHITELIST = [
        'App\\Models\\Partner',
        'App\\Models\\Livestock\\Animal',
        'App\\Models\\Vehicle\\Vehicle',
        'App\\Models\\Financial\\FinancialTransaction',
        'App\\Models\\Agricultural\\Planting',
        'App\\Models\\Agricultural\\Field',
    ];

    /**
     * Mapeia palavra-chave presente em `document_categories.nome/slug`
     * para tipo conceitual do brief. Match case-insensitive por contém.
     * Categoria sem palavra-chave conhecida → sem regra de coerência.
     */
    private const TIPO_POR_PALAVRA_CHAVE = [
        'contrato'    => 'contrato',
        'nota'        => 'nota_fiscal',
        'fiscal'      => 'nota_fiscal',
        'laudo'       => 'laudo',
        'comprovante' => 'comprovante',
    ];

    /**
     * Tipos conceituais → related_types aceitos. Array vazio = sem regra.
     *   contrato    → precisa vincular a Partner
     *   nota_fiscal → FinancialTransaction ou Partner
     *   laudo       → Animal, Planting ou Field (diagnóstico técnico)
     *   comprovante → sem regra (aceita qualquer entity ou nenhuma)
     *   outros/NULL → sem regra
     */
    private const RELATED_POR_TIPO = [
        'contrato' => [
            'App\\Models\\Partner',
        ],
        'nota_fiscal' => [
            'App\\Models\\Financial\\FinancialTransaction',
            'App\\Models\\Partner',
        ],
        'laudo' => [
            'App\\Models\\Livestock\\Animal',
            'App\\Models\\Agricultural\\Planting',
            'App\\Models\\Agricultural\\Field',
        ],
        'comprovante' => [], // sem regra
    ];

    public function index(Request $request)
    {
        $q = Document::with('category:id,nome,cor,icon')
            ->when($request->search, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('titulo', 'like', "%{$request->search}%")
                ->orWhere('descricao', 'like', "%{$request->search}%")))
            ->when($request->category_id, fn ($qq) => $qq->where('category_id', $request->category_id))
            ->when($request->venc === 'proximos', fn ($qq) => $qq
                ->whereNotNull('data_vencimento')
                ->whereBetween('data_vencimento', [now(), now()->addDays(30)]))
            ->when($request->venc === 'vencidos', fn ($qq) => $qq
                ->whereNotNull('data_vencimento')
                ->where('data_vencimento', '<', now()))
            ->orderByDesc('data_documento');

        return Inertia::render('Admin/Documents/Index', [
            'documents' => $q->paginate(25)->withQueryString()->through(fn ($d) => [
                'id' => $d->id,
                'titulo' => $d->titulo,
                'descricao' => $d->descricao,
                'nome_arquivo' => $d->nome_arquivo,
                'mime_type' => $d->mime_type,
                'size' => $d->size,
                'data_documento' => $d->data_documento,
                'data_vencimento' => $d->data_vencimento,
                'is_confidential' => $d->is_confidential,
                'tags' => $d->tags,
                'url' => $d->url(),
                'category' => $d->category ? [
                    'id' => $d->category->id,
                    'nome' => $d->category->nome,
                    'cor' => $d->category->cor,
                    'icon' => $d->category->icon,
                ] : null,
            ]),
            'filters' => $request->only(['search', 'category_id', 'venc']),
            'categories' => DocumentCategory::where('is_active', true)->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'exists:document_categories,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'arquivo' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt'],
            'data_documento' => ['nullable', 'date'],
            'data_vencimento' => ['nullable', 'date'],
            'is_confidential' => ['boolean'],
            'tags' => ['nullable', 'array'],
            // D8: aceitos como nullable — UI atual não envia; quando
            // enviar (ex.: anexar doc a um animal específico), os valores
            // são persistidos e validados por validateDomainCoherence.
            'related_type' => ['nullable', 'string', 'max:191'],
            'related_id' => ['nullable', 'integer'],
        ]);

        // D8 · Coerência do vínculo (se informado)
        if ($err = $this->validateDomainCoherence($data)) {
            return back()->withInput()->with('error', $err);
        }

        $file = $request->file('arquivo');
        $path = $file->store('documents/'.date('Y/m'), 'public');

        Document::create([
            'category_id' => $data['category_id'] ?? null,
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'] ?? null,
            'path' => $path,
            'nome_arquivo' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'data_documento' => $data['data_documento'] ?? null,
            'data_vencimento' => $data['data_vencimento'] ?? null,
            'is_confidential' => $data['is_confidential'] ?? false,
            'tags' => $data['tags'] ?? null,
            // D8: persiste vínculo quando informado pelo caller; caso
            // contrário, continua NULL como antes (retrocompat com UI).
            'related_type' => ! empty($data['related_type']) ? $data['related_type'] : null,
            'related_id' => ! empty($data['related_id']) ? $data['related_id'] : null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Documento enviado.');
    }

    public function destroy(Document $document)
    {
        if ($document->path && Storage::disk('public')->exists($document->path)) {
            Storage::disk('public')->delete($document->path);
        }
        $document->delete();

        return back()->with('success', 'Documento removido.');
    }

    // ========== Categorias ==========

    public function categoryStore(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:100', 'unique:document_categories,nome'],
            'cor' => ['nullable', 'string', 'max:10'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);
        $data['slug'] = \Illuminate\Support\Str::slug($data['nome']);
        $data['is_active'] = true;
        DocumentCategory::create($data);

        return back()->with('success', 'Categoria criada.');
    }

    public function categoryDestroy(DocumentCategory $category)
    {
        $category->delete();

        return back()->with('success', 'Categoria excluída.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // D8 · Coerência de vínculo e tipo
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Valida coerência do documento:
     *   - Se vínculo (related_type + related_id) for informado:
     *       · related_type DEVE estar no whitelist
     *       · se a category implica tipo conhecido, related_type DEVE
     *         estar na lista permitida para aquele tipo
     *   - Se vínculo NÃO for informado: aceita (retrocompat com UI atual)
     *
     * Retorna string de erro amigável ou null se tudo coerente.
     */
    protected function validateDomainCoherence(array $data): ?string
    {
        $relatedType = trim((string) ($data['related_type'] ?? ''));
        $relatedId = $data['related_id'] ?? null;

        // Nenhum dos dois informados → aceita (retrocompat)
        if ($relatedType === '' && empty($relatedId)) {
            return null;
        }

        // XOR — um sem o outro é incoerente
        if ($relatedType === '' || empty($relatedId)) {
            return 'Para vincular um documento a uma entidade, informe AMBOS: tipo do vínculo (ex.: Partner, Animal) e ID. '
                . 'Se não quiser vincular, deixe ambos em branco.';
        }

        // Whitelist — related_type deve ser um dos modelos do sistema
        if (! in_array($relatedType, self::RELATED_TYPES_WHITELIST, true)) {
            return "Tipo de vínculo '{$relatedType}' não é aceito. "
                . 'Documentos podem ser vinculados a: Parceiro, Animal, Veículo, '
                . 'Lançamento Financeiro, Plantio ou Talhão. '
                . 'Verifique o tipo de entidade ao qual você quer anexar este documento.';
        }

        // Coerência tipo × vínculo (inferindo tipo via category)
        $tipo = $this->inferirTipoDocumento($data['category_id'] ?? null);
        if (! $tipo || ! array_key_exists($tipo, self::RELATED_POR_TIPO)) {
            return null; // sem regra para esse tipo ou sem categoria mapeada
        }

        $permitidos = self::RELATED_POR_TIPO[$tipo];
        if (empty($permitidos)) {
            return null; // tipo sem restrição (ex.: comprovante)
        }

        if (in_array($relatedType, $permitidos, true)) {
            return null; // coerente
        }

        // Incoerência — mensagem prescritiva com nomes amigáveis dos permitidos
        $tipoHuman = [
            'contrato' => 'Contratos',
            'nota_fiscal' => 'Notas fiscais',
            'laudo' => 'Laudos',
        ][$tipo] ?? ucfirst($tipo);

        $rel = $this->humanRelated($relatedType);
        $permHuman = implode(' ou ', array_map(fn ($c) => $this->humanRelated($c), $permitidos));

        return "{$tipoHuman} devem estar vinculados a {$permHuman}. "
            . "O vínculo informado ('{$rel}') não é adequado para este tipo de documento. "
            . ($tipo === 'contrato' ? 'Contratos são acordos com terceiros — vincule ao parceiro contratado. ' : '')
            . ($tipo === 'nota_fiscal' ? 'Notas fiscais documentam operações comerciais — vincule ao lançamento financeiro ou ao parceiro. ' : '')
            . ($tipo === 'laudo' ? 'Laudos são diagnósticos técnicos — vincule ao animal examinado, plantio ou talhão. ' : '')
            . 'Se a categoria do documento está errada, troque a categoria em vez de forçar o vínculo.';
    }

    /**
     * Infere o tipo conceitual do documento a partir da category.
     * Busca palavra-chave (contém) em `nome` + `slug` da category.
     */
    private function inferirTipoDocumento(?int $categoryId): ?string
    {
        if (! $categoryId) {
            return null;
        }
        $cat = DocumentCategory::find($categoryId);
        if (! $cat) {
            return null;
        }

        $haystack = mb_strtolower(($cat->nome ?? '') . ' ' . ($cat->slug ?? ''));
        foreach (self::TIPO_POR_PALAVRA_CHAVE as $palavra => $tipo) {
            if (str_contains($haystack, $palavra)) {
                return $tipo;
            }
        }

        return null;
    }

    /** Converte FQCN de related_type em rótulo humano. */
    private function humanRelated(string $fqcn): string
    {
        $map = [
            'App\\Models\\Partner' => 'parceiro',
            'App\\Models\\Livestock\\Animal' => 'animal',
            'App\\Models\\Vehicle\\Vehicle' => 'veículo',
            'App\\Models\\Financial\\FinancialTransaction' => 'lançamento financeiro',
            'App\\Models\\Agricultural\\Planting' => 'plantio',
            'App\\Models\\Agricultural\\Field' => 'talhão',
        ];

        return $map[$fqcn] ?? $fqcn;
    }
}
