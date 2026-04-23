<?php

namespace App\Http\Controllers\Master\Cms;

use App\Http\Controllers\Controller;
use App\Models\Cms\Page;
use App\Models\Cms\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * CmsController — M7
 *
 * CMS da landing page institucional. MIGRADO de App\Http\Controllers\Admin\Cms
 * para a área master porque o site público é platform-level (promove o SaaS
 * como produto, não é de nenhum tenant).
 *
 * Lógica 1:1 com a versão antiga — apenas namespace, nomes de rota
 * (Inertia::render) e registro de rotas mudaram.
 *
 * NÃO consulta tenant_id nem farm_id — o CMS é global por design.
 */
class CmsController extends Controller
{
    public function index()
    {
        $pages = Page::withCount('sections')->orderBy('titulo')->get();

        return Inertia::render('Master/Cms/Index', [
            'pages' => $pages,
        ]);
    }

    public function edit(Page $page)
    {
        $page->load(['sections' => fn ($q) => $q->orderBy('order_column')]);

        return Inertia::render('Master/Cms/Editor', [
            'page' => [
                'id' => $page->id,
                'slug' => $page->slug,
                'titulo' => $page->titulo,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'meta_keywords' => $page->meta_keywords,
                'is_published' => $page->is_published,
            ],
            'sections' => $page->sections->map(fn ($s) => [
                'id' => $s->id,
                'type' => $s->type,
                'nome' => $s->nome,
                'is_active' => $s->is_active,
                'has_draft' => $s->has_draft,
                'order_column' => $s->order_column,
                'draft_data' => $s->draft_data,
                'published_data' => $s->published_data,
                'published_at' => $s->published_at,
            ]),
        ]);
    }

    public function updatePage(Request $request, Page $page)
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
        ]);

        $page->update($data + ['updated_by' => $request->user()?->id]);

        return back()->with('success', 'Metadados atualizados.');
    }

    public function saveSectionDraft(Request $request, Section $section)
    {
        $request->validate(['draft_data' => ['required', 'array']]);

        $section->update([
            'draft_data' => $request->draft_data,
            'has_draft' => true,
            'is_active' => $request->boolean('is_active', $section->is_active),
            'nome' => $request->string('nome')->value() ?: $section->nome,
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Rascunho salvo.');
    }

    public function publishSection(Request $request, Section $section)
    {
        $section->publish($request->user()?->id);

        return back()->with('success', 'Seção publicada no site.');
    }

    public function publishAll(Page $page, Request $request)
    {
        $page->sections()->where('has_draft', true)->each(fn ($s) => $s->publish($request->user()?->id));

        return back()->with('success', 'Todas as alterações foram publicadas.');
    }

    public function reorderSections(Page $page, Request $request)
    {
        $request->validate(['order' => ['required', 'array']]);

        foreach ($request->order as $i => $sectionId) {
            Section::where('id', $sectionId)->where('page_id', $page->id)->update(['order_column' => $i]);
        }

        return back()->with('success', 'Ordem atualizada.');
    }

    public function toggleActive(Section $section)
    {
        $section->update(['is_active' => ! $section->is_active]);

        return back()->with('success', $section->is_active ? 'Seção ativada.' : 'Seção desativada.');
    }

    public function uploadImage(Request $request)
    {
        $validator = validator($request->all(), [
            'file' => [
                'required',
                'file',
                'max:5120',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => $validator->errors()->first('file') ?: 'Arquivo inválido.',
            ], 422);
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension()) ?: $file->guessExtension();
        $filename = \Illuminate\Support\Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            .'-'.now()->format('YmdHis')
            .'.'.$ext;

        $path = $file->storeAs('cms/'.date('Y/m'), $filename, 'public');

        return response()->json([
            'ok' => true,
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
