<?php

namespace App\Http\Controllers\Master\Clientes\Cms;

use App\Domain\Billing\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Models\Cms\Page;
use App\Models\Cms\Section;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * CmsController do CMS por cliente.
 *
 * Espelha Master\Cms\CmsController (legado que edita o CMS global),
 * mas com TODOS os métodos contextualizados em um {cliente}. Cada action:
 *   1. Binda `app('tenant_id') = $cliente->id` durante o scope da operação
 *      (permite ao trait BelongsToClient preencher tenant_id em creates
 *      subsequentes — e ao Setting::getValue resolver o contexto).
 *   2. Filtra queries de leitura por `tenant_id = $cliente->id` explicitamente.
 *   3. Valida que Page/Section pertence ao cliente (proteção cross-client).
 *   4. Passa a prop `cliente` para a view (breadcrumb / título).
 *
 * Guardado apenas por `auth` + `enforce.master` (herdado do grupo /master).
 * Não altera o CMS legado em /master/cms.
 */
class CmsController extends Controller
{
    /** Helper: roda closure com `app('tenant_id')` bindado ao cliente. */
    private function withClientContext(Tenant $cliente, Closure $fn): mixed
    {
        app()->instance('tenant_id', $cliente->id);
        try {
            return $fn();
        } finally {
            app()->forgetInstance('tenant_id');
        }
    }

    private function presentClient(Tenant $cliente): array
    {
        return [
            'id' => $cliente->id,
            'nome' => $cliente->nome,
        ];
    }

    /** Verifica que a page pertence ao cliente; 404 caso contrário. */
    private function ensurePageBelongs(Tenant $cliente, Page $page): void
    {
        if ((int) $page->tenant_id !== (int) $cliente->id) {
            abort(404);
        }
    }

    /** Verifica que a section pertence (via page) ao cliente. */
    private function ensureSectionBelongs(Tenant $cliente, Section $section): void
    {
        $page = Page::find($section->page_id);
        if (! $page || (int) $page->tenant_id !== (int) $cliente->id) {
            abort(404);
        }
    }

    public function index(Tenant $cliente)
    {
        $pages = Page::where('tenant_id', $cliente->id)
            ->withCount('sections')
            ->orderBy('titulo')
            ->get();

        return Inertia::render('Master/Clientes/Cms/Index', [
            'cliente' => $this->presentClient($cliente),
            'pages' => $pages,
        ]);
    }

    public function edit(Tenant $cliente, Page $page)
    {
        $this->ensurePageBelongs($cliente, $page);

        $page->load(['sections' => fn ($q) => $q->orderBy('order_column')]);

        return Inertia::render('Master/Clientes/Cms/Editor', [
            'cliente' => $this->presentClient($cliente),
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

    public function updatePage(Request $request, Tenant $cliente, Page $page)
    {
        $this->ensurePageBelongs($cliente, $page);

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
        ]);

        $this->withClientContext($cliente, fn () => $page->update(
            $data + ['updated_by' => $request->user()?->id]
        ));

        return back()->with('success', 'Metadados atualizados.');
    }

    public function saveSectionDraft(Request $request, Tenant $cliente, Section $section)
    {
        $this->ensureSectionBelongs($cliente, $section);

        $request->validate(['draft_data' => ['required', 'array']]);

        $this->withClientContext($cliente, fn () => $section->update([
            'draft_data' => $request->draft_data,
            'has_draft' => true,
            'is_active' => $request->boolean('is_active', $section->is_active),
            'nome' => $request->string('nome')->value() ?: $section->nome,
            'updated_by' => $request->user()?->id,
        ]));

        return back()->with('success', 'Rascunho salvo.');
    }

    public function publishSection(Request $request, Tenant $cliente, Section $section)
    {
        $this->ensureSectionBelongs($cliente, $section);

        $this->withClientContext($cliente, fn () => $section->publish($request->user()?->id));

        return back()->with('success', 'Seção publicada no site.');
    }

    public function publishAll(Tenant $cliente, Page $page, Request $request)
    {
        $this->ensurePageBelongs($cliente, $page);

        $this->withClientContext($cliente, function () use ($page, $request) {
            $page->sections()->where('has_draft', true)->each(
                fn ($s) => $s->publish($request->user()?->id)
            );
        });

        return back()->with('success', 'Todas as alterações foram publicadas.');
    }

    public function reorderSections(Tenant $cliente, Page $page, Request $request)
    {
        $this->ensurePageBelongs($cliente, $page);

        $request->validate(['order' => ['required', 'array']]);

        foreach ($request->order as $i => $sectionId) {
            Section::where('id', $sectionId)
                ->where('page_id', $page->id)
                ->update(['order_column' => $i]);
        }

        return back()->with('success', 'Ordem atualizada.');
    }

    public function toggleActive(Tenant $cliente, Section $section)
    {
        $this->ensureSectionBelongs($cliente, $section);

        $this->withClientContext($cliente, fn () => $section->update([
            'is_active' => ! $section->is_active,
        ]));

        return back()->with('success', $section->is_active ? 'Seção ativada.' : 'Seção desativada.');
    }

    public function uploadImage(Request $request, Tenant $cliente)
    {
        // Contexto de cliente é implícito: armazenamos em pasta por tenant
        // para organização. Não altera comportamento de autorização.
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
        $filename = \Illuminate\Support\Str::slug(
            pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
        ) . '-' . now()->format('YmdHis') . '.' . $ext;

        // Organização por cliente evita colisão entre clientes diferentes
        $path = $file->storeAs('cms/cliente-' . $cliente->id . '/' . date('Y/m'), $filename, 'public');

        return response()->json([
            'ok' => true,
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
