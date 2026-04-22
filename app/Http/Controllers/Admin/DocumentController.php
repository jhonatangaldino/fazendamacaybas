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
        ]);

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
}
