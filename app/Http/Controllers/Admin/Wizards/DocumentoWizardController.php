<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Document\Document;
use App\Models\Document\DocumentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assistente guiado — Anexar documento.
 *
 * Fluxo:
 *   1 · Que tipo?   (GTA / Licença / Receita / Contrato / Outro)
 *   2 · Anexar     (upload com validação por tipo)
 *   3 · Detalhes   (datas adaptadas: GTA tem validade curta, contrato anos)
 *   4 · Pronto     (sucesso + link pra ver)
 *
 * Cada tipo carrega configuração diferente (extensões aceitas, dicas).
 */
class DocumentoWizardController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Wizards/Documento', [
            'tipos' => $this->tiposCatalogo(),
            'categorias' => DocumentCategory::where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'slug']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tipo' => ['required', 'string'],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'arquivo' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt'],
            'data_documento' => ['nullable', 'date'],
            'data_vencimento' => ['nullable', 'date'],
        ]);

        $tipoConfig = collect($this->tiposCatalogo())->firstWhere('id', $data['tipo']);
        $categoria = null;
        if ($tipoConfig && ! empty($tipoConfig['categoria_slug'])) {
            $categoria = DocumentCategory::firstOrCreate(
                ['slug' => $tipoConfig['categoria_slug']],
                ['nome' => $tipoConfig['rotulo'], 'is_active' => true],
            );
        }

        $file = $request->file('arquivo');
        $path = $file->store('documents/'.date('Y/m'), 'public');

        $doc = Document::create([
            'category_id' => $categoria?->id,
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'] ?? null,
            'path' => $path,
            'nome_arquivo' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'data_documento' => $data['data_documento'] ?? null,
            'data_vencimento' => $data['data_vencimento'] ?? null,
            'is_confidential' => false,
            'tags' => [$data['tipo']],
            'created_by' => $request->user()->id,
        ]);

        session()->flash('documento_contexto', [
            'id' => $doc->id,
            'titulo' => $doc->titulo,
            'tipo' => $tipoConfig['rotulo'] ?? $data['tipo'],
            'vencimento' => $doc->data_vencimento?->format('Y-m-d'),
        ]);

        return back()->with('success', 'Documento anexado.');
    }

    private function tiposCatalogo(): array
    {
        return [
            [
                'id' => 'gta',
                'rotulo' => 'GTA — Guia de Trânsito Animal',
                'icone' => '🚚',
                'desc' => 'Validade curta (geralmente 5-7 dias). Vincule à movimentação de animais.',
                'categoria_slug' => 'gta',
                'pede_validade' => true,
                'sugestao_validade_dias' => 7,
                'mimes' => 'pdf,jpg,jpeg,png',
            ],
            [
                'id' => 'licenca_ambiental',
                'rotulo' => 'Licença ambiental',
                'icone' => '🌳',
                'desc' => 'Validade longa (1-3 anos). Inclui outorga de água, licença de operação.',
                'categoria_slug' => 'licenca-ambiental',
                'pede_validade' => true,
                'sugestao_validade_dias' => 365,
                'mimes' => 'pdf',
            ],
            [
                'id' => 'receita_veterinaria',
                'rotulo' => 'Receita veterinária',
                'icone' => '💊',
                'desc' => 'Para compra de medicamento controlado. Validade curta.',
                'categoria_slug' => 'receita-veterinaria',
                'pede_validade' => true,
                'sugestao_validade_dias' => 30,
                'mimes' => 'pdf,jpg,jpeg,png',
            ],
            [
                'id' => 'contrato',
                'rotulo' => 'Contrato',
                'icone' => '📜',
                'desc' => 'Arrendamento, comodato, prestação de serviço. Validade longa.',
                'categoria_slug' => 'contrato',
                'pede_validade' => true,
                'sugestao_validade_dias' => 730,
                'mimes' => 'pdf,doc,docx',
            ],
            [
                'id' => 'nota_fiscal',
                'rotulo' => 'Nota fiscal',
                'icone' => '🧾',
                'desc' => 'NF-e de compra ou venda. Sem validade — apenas data de emissão.',
                'categoria_slug' => 'nota-fiscal',
                'pede_validade' => false,
                'mimes' => 'pdf,xml',
            ],
            [
                'id' => 'certificado',
                'rotulo' => 'Certificado / vacina',
                'icone' => '📋',
                'desc' => 'Comprovante sanitário, brucelose, tuberculose, vacina.',
                'categoria_slug' => 'certificado',
                'pede_validade' => true,
                'sugestao_validade_dias' => 365,
                'mimes' => 'pdf,jpg,jpeg,png',
            ],
            [
                'id' => 'outro',
                'rotulo' => 'Outro documento',
                'icone' => '📄',
                'desc' => 'Qualquer outro documento. Categoria genérica.',
                'categoria_slug' => null,
                'pede_validade' => false,
                'mimes' => 'pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt',
            ],
        ];
    }
}
