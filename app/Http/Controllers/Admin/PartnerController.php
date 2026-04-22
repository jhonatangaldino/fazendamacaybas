<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PartnerController extends Controller
{
    public function index(Request $request)
    {
        $q = Partner::query()
            ->when($request->search, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('nome', 'like', "%{$request->search}%")
                ->orWhere('documento', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")))
            ->when($request->tipo, fn ($qq) => $qq->where('tipo', $request->tipo))
            ->orderBy('nome');

        return Inertia::render('Admin/Partners/Index', [
            'partners' => $q->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'tipo']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Partners/Form', ['partner' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePartner($request);
        Partner::create($data);

        return redirect()->route('admin.parceiros.index')->with('success', 'Parceiro cadastrado.');
    }

    public function edit(Partner $parceiro)
    {
        return Inertia::render('Admin/Partners/Form', ['partner' => $parceiro]);
    }

    public function update(Request $request, Partner $parceiro)
    {
        $data = $this->validatePartner($request, $parceiro->id);
        $parceiro->update($data);

        return redirect()->route('admin.parceiros.index')->with('success', 'Parceiro atualizado.');
    }

    public function destroy(Partner $parceiro)
    {
        $parceiro->delete();

        return back()->with('success', 'Parceiro excluído.');
    }

    protected function validatePartner(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'tipo' => ['required', 'in:fornecedor,cliente,ambos'],
            'pessoa' => ['required', 'in:pf,pj'],
            'nome' => ['required', 'string', 'max:255'],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:18', Rule::unique('partners', 'documento')->ignore($id)->whereNull('deleted_at')],
            'inscricao_estadual' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'celular' => ['nullable', 'string', 'max:20'],
            'cep' => ['nullable', 'string', 'max:9'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'size:2'],
            'observacoes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
