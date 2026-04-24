<?php

namespace App\Http\Controllers\Admin\Livestock;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Livestock\AnimalLot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de lotes (agrupamentos lógicos de animais).
 *
 * Antes existia a tabela `animal_lots` mas NÃO havia CRUD pela UI —
 * só era possível criar via seed/tinker. Isso é corrigido aqui.
 *
 * Lote ≠ Localização:
 *   - LOTE = grupo lógico (ex: "Engorda Q1 2026", "Vacas Leite")
 *   - LOCALIZAÇÃO = posição física (ex: "Pasto 3") → AnimalLocationController
 */
class AnimalLotController extends Controller
{
    private const FINALIDADES = [
        'corte' => 'Corte (engorda)',
        'leite' => 'Leite',
        'reproducao' => 'Reprodução',
        'recria' => 'Recria',
        'engorda' => 'Engorda',
        'descarte' => 'Descarte',
        'outro' => 'Outro',
    ];

    public function index(Request $request): Response
    {
        $q = AnimalLot::query()
            ->withCount('animals as animais_count')
            ->orderBy('nome');

        if ($busca = $request->query('q')) {
            $q->where(function ($qq) use ($busca) {
                $qq->where('nome', 'like', "%{$busca}%")
                    ->orWhere('codigo', 'like', "%{$busca}%");
            });
        }

        return Inertia::render('Admin/Livestock/Lots/Index', [
            'lots' => $q->paginate(30)->withQueryString(),
            'filters' => $request->only(['q']),
            'finalidades' => self::FINALIDADES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Livestock/Lots/Form', [
            'lot' => null,
            'farms' => Farm::orderBy('nome')->get(['id', 'nome']),
            'finalidades' => self::FINALIDADES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        AnimalLot::create($data);

        return redirect()->route('admin.rebanho.lotes.index')
            ->with('success', 'Lote criado.');
    }

    public function edit(AnimalLot $lote): Response
    {
        return Inertia::render('Admin/Livestock/Lots/Form', [
            'lot' => $lote,
            'farms' => Farm::orderBy('nome')->get(['id', 'nome']),
            'finalidades' => self::FINALIDADES,
        ]);
    }

    public function update(Request $request, AnimalLot $lote): RedirectResponse
    {
        $data = $this->validated($request, $lote->id);
        $lote->update($data);

        return redirect()->route('admin.rebanho.lotes.index')
            ->with('success', 'Lote atualizado.');
    }

    public function destroy(AnimalLot $lote): RedirectResponse
    {
        if ($lote->animals()->exists()) {
            return back()->with('error', 'Este lote tem animais. Mova os animais antes de excluir.');
        }
        $lote->delete();

        return back()->with('success', 'Lote removido.');
    }

    public function toggle(AnimalLot $lote): RedirectResponse
    {
        $lote->update(['is_active' => ! $lote->is_active]);

        return back()->with('success', $lote->is_active ? 'Lote reativado.' : 'Lote desativado.');
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'farm_id' => ['nullable', 'exists:farms,id'],
            'codigo' => [
                'required', 'string', 'max:30',
                Rule::unique('animal_lots', 'codigo')->ignore($ignoreId),
            ],
            'nome' => ['required', 'string', 'max:150'],
            'finalidade' => ['nullable', Rule::in(array_keys(self::FINALIDADES))],
            'descricao' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
