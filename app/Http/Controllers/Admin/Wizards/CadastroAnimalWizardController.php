<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Domain\Tenancy\Scopes\BelongsToTenantScope;
use App\Http\Controllers\Controller;
use App\Models\Livestock\Animal;
use App\Models\Livestock\AnimalLocation;
use App\Models\Livestock\AnimalLot;
use App\Models\Livestock\AnimalSpecies;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Wizard guiado de cadastro de animal — 3 modos:
 *
 *   modo=cadastro     → registro genérico (doação, importação, pedigree)
 *   modo=compra       → compra com fornecedor, NF, valor de aquisição
 *   modo=nascimento   → cria nasce no rebanho, vincula à mãe, peso ao nascer
 *
 * Cada modo adapta os passos do wizard:
 *   - cadastro: 4 passos (espécie → identificação → lote/local → confirmar)
 *   - compra:   5 passos (espécie → identificação → lote/local → fornecedor+valor → confirmar)
 *   - nascimento: 5 passos (espécie → identificação → lote/local → mãe+peso ao nascer → confirmar)
 *
 * Reusa a lógica de criação do AnimalController (mesmo modelo Animal::create).
 */
class CadastroAnimalWizardController extends Controller
{
    public function create(Request $request, string $modo = 'cadastro'): Response
    {
        $modo = in_array($modo, ['cadastro', 'compra', 'nascimento'], true) ? $modo : 'cadastro';

        // Reference data (espécies/raças) tenant_id=1 — bypassa scope.
        $species = AnimalSpecies::withoutGlobalScope(BelongsToTenantScope::class)
            ->with(['breeds:id,species_id,nome'])
            ->where('is_active', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'profile', 'gestao', 'allowed_events']);

        $animaisFemeas = null;
        if ($modo === 'nascimento') {
            // Para nascimento, lista matrizes (fêmeas adultas) — mais comum como mãe
            $animaisFemeas = Animal::where('status', 'ativo')
                ->where('sexo', 'F')
                ->orderBy('identificacao')
                ->get(['id', 'identificacao', 'nome', 'species_id'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'identificacao' => $a->identificacao,
                    'nome' => $a->nome,
                    'species_id' => $a->species_id,
                ]);
        }

        $partners = null;
        if ($modo === 'compra') {
            $partners = Partner::where('is_active', true)
                ->whereIn('tipo', ['fornecedor', 'ambos', 'cliente'])
                ->orderBy('nome')
                ->get(['id', 'nome', 'pessoa', 'documento']);
        }

        return Inertia::render('Admin/Wizards/CadastrarAnimal', [
            'modo' => $modo,
            'species' => $species,
            'lots' => AnimalLot::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'locations' => AnimalLocation::where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'tipo']),
            'partners' => $partners,
            'maes' => $animaisFemeas,
        ]);
    }

    public function store(Request $request, string $modo = 'cadastro'): RedirectResponse
    {
        $modo = in_array($modo, ['cadastro', 'compra', 'nascimento'], true) ? $modo : 'cadastro';

        $rules = [
            'species_id' => ['required', 'exists:animal_species,id'],
            'breed_id' => ['nullable', 'exists:animal_breeds,id'],
            'identificacao' => ['required', 'string', 'max:30', 'unique:animals,identificacao'],
            'nome' => ['nullable', 'string', 'max:100'],
            'sexo' => ['required', 'in:M,F'],
            'data_nascimento' => ['nullable', 'date'],
            'lot_id' => ['nullable', 'exists:animal_lots,id'],
            'location_id' => ['nullable', 'exists:animal_locations,id'],
            'categoria' => ['nullable', 'in:leite,corte,reproducao,misto,pet,servico,trabalho,esporte,postura,companhia'],
            'observacoes' => ['nullable', 'string'],
        ];

        if ($modo === 'compra') {
            $rules['partner_id'] = ['required', 'exists:partners,id'];
            $rules['data_aquisicao'] = ['required', 'date'];
            $rules['valor_aquisicao'] = ['required', 'numeric', 'min:0'];
        } elseif ($modo === 'nascimento') {
            $rules['mae_id'] = ['nullable', 'exists:animals,id'];
            $rules['peso_nascimento'] = ['nullable', 'numeric', 'min:0', 'max:999'];
            $rules['data_nascimento'] = ['required', 'date', 'before_or_equal:today'];
        }

        $data = $request->validate($rules);

        // Define origem conforme modo
        $origem = $modo === 'compra' ? 'compra' : 'nascido';

        $payload = [
            'species_id' => $data['species_id'],
            'breed_id' => $data['breed_id'] ?? null,
            'identificacao' => $data['identificacao'],
            'nome' => $data['nome'] ?? null,
            'sexo' => $data['sexo'],
            'data_nascimento' => $data['data_nascimento'] ?? null,
            'lot_id' => $data['lot_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'categoria' => $data['categoria'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'origem' => $origem,
            'status' => 'ativo',
        ];

        if ($modo === 'compra') {
            $payload['partner_id'] = $data['partner_id'];
            $payload['data_aquisicao'] = $data['data_aquisicao'];
            $payload['valor_aquisicao'] = $data['valor_aquisicao'];
        } elseif ($modo === 'nascimento') {
            $payload['peso_nascimento'] = $data['peso_nascimento'] ?? null;
            // mãe → guarda nas observações enquanto não há FK dedicada
            if (! empty($data['mae_id'])) {
                $mae = Animal::find($data['mae_id']);
                $payload['observacoes'] = trim(
                    ($payload['observacoes'] ?? '')
                    . "\n[Nascido de] " . ($mae?->identificacao ?? '#'.$data['mae_id'])
                );
            }
        }

        Animal::create($payload);

        return back()->with('success', match ($modo) {
            'compra' => 'Animal comprado e adicionado ao rebanho.',
            'nascimento' => 'Nascimento registrado — cria entrou no rebanho.',
            default => 'Animal cadastrado.',
        });
    }
}
