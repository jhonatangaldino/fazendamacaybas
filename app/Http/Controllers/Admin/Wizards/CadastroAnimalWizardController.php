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

        // Auditoria 2026-04-27 — modo agregado (lote em massa) para aves/peixes.
        // Detectado pelo flag 'agregado' no payload (vindo do front quando species.gestao=lote).
        if ($request->boolean('agregado')) {
            return $this->storeAggregated($request, $modo);
        }

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

    /**
     * Cadastro agregado — lote em massa (aves, peixes, abelhas).
     *
     * NÃO cria 1 row por animal. Cria/atualiza um AnimalLot com:
     *   gestao_modo = 'agregada'
     *   quantidade_inicial / quantidade_atual = N
     *   peso_medio_kg = peso médio (opcional)
     *
     * Cenários:
     *   - cadastro: lote interno, sem fornecedor (ex.: pintinhos cedidos)
     *   - compra:   lote vindo de fornecedor com NF + valor de aquisição
     *
     * Sempre cria UM lote novo (não soma em existente, para preservar histórico
     * de rastreabilidade). Master pode mesclar manualmente depois se quiser.
     */
    private function storeAggregated(Request $request, string $modo): RedirectResponse
    {
        $rules = [
            'species_id' => ['required', 'exists:animal_species,id'],
            'breed_id' => ['nullable', 'exists:animal_breeds,id'],
            'lot_nome' => ['required', 'string', 'max:120'],
            'quantidade' => ['required', 'numeric', 'min:1'],
            'peso_medio_kg' => ['nullable', 'numeric', 'min:0'],
            'location_id' => ['nullable', 'exists:animal_locations,id'],
            'data_inicio' => ['nullable', 'date'],
            'finalidade' => ['nullable', 'in:engorda,postura,reproducao,recria,corte,leite,outros'],
            'observacoes' => ['nullable', 'string'],
        ];

        if ($modo === 'compra') {
            $rules['partner_id'] = ['required', 'exists:partners,id'];
            $rules['data_aquisicao'] = ['required', 'date'];
            $rules['valor_aquisicao'] = ['required', 'numeric', 'min:0'];
        }

        $data = $request->validate($rules);

        $codigo = 'L' . substr((string) (microtime(true) * 1000), -6);
        $custoUnit = null;
        if ($modo === 'compra' && ! empty($data['valor_aquisicao']) && ! empty($data['quantidade']) && $data['quantidade'] > 0) {
            $custoUnit = $data['valor_aquisicao'] / $data['quantidade'];
        }

        $lote = AnimalLot::create([
            'farm_id' => app('farm_id'),
            'codigo' => $codigo,
            'nome' => $data['lot_nome'],
            'descricao' => $data['observacoes'] ?? null,
            'finalidade' => $data['finalidade'] ?? null,
            'is_active' => true,
            'gestao_modo' => 'agregada',
            'quantidade_inicial' => $data['quantidade'],
            'quantidade_atual' => $data['quantidade'],
            'peso_medio_kg' => $data['peso_medio_kg'] ?? null,
            'data_inicio' => $data['data_inicio'] ?? now()->toDateString(),
            'partner_id_aquisicao' => $modo === 'compra' ? $data['partner_id'] : null,
            'valor_aquisicao' => $modo === 'compra' ? $data['valor_aquisicao'] : null,
            'custo_unitario' => $custoUnit,
        ]);

        return back()->with('success', match ($modo) {
            'compra' => "Lote \"{$lote->nome}\" comprado com {$data['quantidade']} unidades.",
            default => "Lote \"{$lote->nome}\" cadastrado com {$data['quantidade']} unidades.",
        });
    }
}
