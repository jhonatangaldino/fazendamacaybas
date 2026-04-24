<?php

namespace App\Http\Controllers\Admin\Livestock;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Livestock\AnimalLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de localizações físicas (pasto, piquete, curral, baia, tanque, galpão).
 *
 * Introduzido após o diagnóstico de domínio que separou LOCALIZAÇÃO (posição
 * física) de LOTE (grupo lógico de animais). Antes disso, os dois conceitos
 * estavam misturados em `animal_lots`.
 */
class AnimalLocationController extends Controller
{
    public function index(Request $request): Response
    {
        $q = AnimalLocation::query()
            ->withCount('animals as animais_count')
            ->orderBy('tipo')
            ->orderBy('nome');

        if ($busca = $request->query('q')) {
            $q->where(function ($qq) use ($busca) {
                $qq->where('nome', 'like', "%{$busca}%")
                    ->orWhere('codigo', 'like', "%{$busca}%");
            });
        }

        return Inertia::render('Admin/Livestock/Locations/Index', [
            'locations' => $q->paginate(30)->withQueryString(),
            'filters' => $request->only(['q']),
            'tipos' => AnimalLocation::TIPOS,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Livestock/Locations/Form', [
            'location' => null,
            'farms' => Farm::orderBy('nome')->get(['id', 'nome']),
            'tipos' => AnimalLocation::TIPOS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        AnimalLocation::create($data);

        return redirect()->route('admin.rebanho.locais.index')
            ->with('success', 'Local criado.');
    }

    public function edit(AnimalLocation $local): Response
    {
        return Inertia::render('Admin/Livestock/Locations/Form', [
            'location' => $local,
            'farms' => Farm::orderBy('nome')->get(['id', 'nome']),
            'tipos' => AnimalLocation::TIPOS,
        ]);
    }

    public function update(Request $request, AnimalLocation $local): RedirectResponse
    {
        $data = $this->validated($request, $local->id);
        $local->update($data);

        return redirect()->route('admin.rebanho.locais.index')
            ->with('success', 'Local atualizado.');
    }

    public function destroy(AnimalLocation $local): RedirectResponse
    {
        if ($local->animals()->exists()) {
            return back()->with('error', 'Este local tem animais. Mova os animais antes de excluir.');
        }
        $local->delete();

        return back()->with('success', 'Local removido.');
    }

    public function toggle(AnimalLocation $local): RedirectResponse
    {
        $local->update(['is_active' => ! $local->is_active]);

        return back()->with('success', $local->is_active ? 'Local reativado.' : 'Local desativado.');
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'farm_id' => ['nullable', 'exists:farms,id'],
            'codigo' => [
                'nullable', 'string', 'max:30',
                Rule::unique('animal_locations', 'codigo')
                    ->where('tenant_id', $tenantId)
                    ->ignore($ignoreId),
            ],
            'nome' => ['required', 'string', 'max:150'],
            'tipo' => ['required', Rule::in(array_keys(AnimalLocation::TIPOS))],
            'area_ha' => ['nullable', 'numeric', 'min:0'],
            'capacidade_ua' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
