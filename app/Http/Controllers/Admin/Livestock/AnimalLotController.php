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
        'postura' => 'Postura (ovos)',     // adicionado pós-QA E1 — ave_postura existia como profile mas faltava finalidade
        'reproducao' => 'Reprodução',
        'recria' => 'Recria',
        'engorda' => 'Engorda',
        'descarte' => 'Descarte',
        'outro' => 'Outro',
    ];

    public function index(Request $request): Response
    {
        $q = AnimalLot::query()
            // withoutGlobalScopes na relação Species — o catálogo é global (tenant_id=1)
            // e BelongsToTenantScope retornaria null pra outros tenants. Sem isso o
            // template não detectava gestao=lote e exibia "0 animais" pra Ave/Peixe.
            ->with(['species' => fn ($s) => $s->withoutGlobalScopes()->select('id', 'nome', 'slug', 'gestao')])
            ->withCount('animals as animais_count')
            ->select(['id', 'nome', 'codigo', 'finalidade', 'is_active', 'species_id',
                'quantidade_atual', 'quantidade_inicial', 'gestao_modo'])
            ->orderBy('nome');

        if ($busca = $request->query('q')) {
            $q->where(function ($qq) use ($busca) {
                $qq->where('nome', 'like', "%{$busca}%")
                    ->orWhere('codigo', 'like', "%{$busca}%");
            });
        }

        // Filtro por espécie — preserva contexto após cadastro/edição
        // (vindo de dashboard de espécie lote ou form com species_id).
        if ($speciesId = $request->integer('species_id')) {
            $q->where('species_id', $speciesId);
        }

        return Inertia::render('Admin/Livestock/Lots/Index', [
            'lots' => $q->paginate(30)->withQueryString(),
            'filters' => $request->only(['q', 'species_id']),
            'finalidades' => self::FINALIDADES,
            'species' => \App\Models\Livestock\AnimalSpecies::withoutGlobalScopes()
                ->where('is_active', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'slug', 'gestao']),
        ]);
    }

    public function create(\Illuminate\Http\Request $request): Response
    {
        // Pré-seleção de espécie via ?species_id=X (vem do dashboard de espécie
        // gestão lote: "+ Novo lote de Ave/Peixe").
        $prefillSpeciesId = $request->integer('species_id') ?: null;

        return Inertia::render('Admin/Livestock/Lots/Form', [
            'lot' => null,
            'farms' => Farm::orderBy('nome')->get(['id', 'nome']),
            'finalidades' => self::FINALIDADES,
            'species' => \App\Models\Livestock\AnimalSpecies::withoutGlobalScopes()
                ->where('is_active', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'gestao', 'profile']),
            'prefillSpeciesId' => $prefillSpeciesId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        // gestao_modo=agregada quando species é gestao=lote — sinaliza que
        // o lote NÃO tem 1 Animal por cabeça (cadastro consolidado).
        if (! empty($data['species_id'])) {
            $species = \App\Models\Livestock\AnimalSpecies::withoutGlobalScopes()->find($data['species_id']);
            if ($species && $species->gestao === 'lote') {
                $data['gestao_modo'] = 'agregada';
            }
            // quantidade_atual = quantidade_inicial ao criar (sem mortalidade ainda)
            if (! empty($data['quantidade_inicial'])) {
                $data['quantidade_atual'] = $data['quantidade_inicial'];
            }
        }
        $lote = AnimalLot::create($data);

        // Preserva contexto da espécie no redirect (consistente com Animals).
        $params = [];
        if (! empty($lote->species_id)) {
            $params['species_id'] = $lote->species_id;
        }

        return redirect()->route('admin.rebanho.lotes.index', $params)
            ->with('success', 'Lote criado.');
    }

    /**
     * Endpoint inline (AJAX/JSON) — cria um lote e retorna {id, nome}
     * SEM REDIRECIONAR. Usado por modais em wizards/forms que precisam
     * criar um lote sem tirar o usuário do fluxo principal.
     *
     * Princípio: qualquer ação em curso deve conseguir concluir mesmo
     * sem dados prévios; a dependência é criada no próprio lugar.
     */
    public function storeInline(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'codigo' => ['nullable', 'string', 'max:30'],
            'finalidade' => ['nullable', 'string', 'max:30'],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ]);

        // Bloco 3 — multi-fazenda: SEMPRE usar farm ativa do contexto
        // (resolved by EnforceFarm middleware). Sem fallback silencioso —
        // se o binding faltar, é bug de boot, não pode escolher farm sozinho.
        abort_unless(app()->bound('farm_id'), 500, 'Contexto de fazenda não resolvido (EnforceFarm).');
        $farmId = app('farm_id');

        // `codigo` é NOT NULL na tabela — se o usuário não informou, geramos
        // um automático baseado no nome (slug + timestamp curto).
        $codigo = $data['codigo'] ?? null;
        if (! $codigo) {
            $base = \Illuminate\Support\Str::slug($data['nome']);
            $codigo = strtoupper(substr($base, 0, 8)) . '-' . substr(time(), -4);
        }

        $lote = AnimalLot::create([
            'farm_id' => $farmId,
            'nome' => $data['nome'],
            'codigo' => $codigo,
            'finalidade' => $data['finalidade'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'id' => $lote->id,
            'nome' => $lote->nome,
            'codigo' => $lote->codigo,
        ]);
    }

    public function edit(AnimalLot $lote): Response
    {
        return Inertia::render('Admin/Livestock/Lots/Form', [
            'lot' => $lote,
            'farms' => Farm::orderBy('nome')->get(['id', 'nome']),
            'finalidades' => self::FINALIDADES,
            // Sem species[], o Form não detecta isLoteAgregado e esconde
            // os campos quantidade/peso médio (Ave/Peixe). Bug pego no QA.
            'species' => \App\Models\Livestock\AnimalSpecies::withoutGlobalScopes()
                ->where('is_active', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'slug', 'gestao', 'profile']),
        ]);
    }

    public function update(Request $request, AnimalLot $lote): RedirectResponse
    {
        $data = $this->validated($request, $lote->id);
        $lote->update($data);

        $params = [];
        if (! empty($lote->species_id)) {
            $params['species_id'] = $lote->species_id;
        }

        return redirect()->route('admin.rebanho.lotes.index', $params)
            ->with('success', 'Lote atualizado.');
    }

    public function destroy(AnimalLot $lote): RedirectResponse
    {
        if ($lote->animals()->exists()) {
            return back()->with('error', 'Este lote tem animais. Mova os animais antes de excluir.');
        }

        // Defensivo: ANTES retornávamos sucesso mesmo se o delete silenciosamente
        // falhasse. Bug detectado pelo QA E2E (BUG-B-002): toast confirmava
        // remoção mas o lote persistia em produção. Agora checamos retorno
        // do delete() e capturamos exceções FK explicitamente, surfaçando
        // a causa real ao usuário em vez de mentir com sucesso.
        try {
            $ok = $lote->delete();
            if (! $ok) {
                \Log::warning('AnimalLot::delete retornou false', ['lot_id' => $lote->id]);
                return back()->with('error', 'Não foi possível remover o lote. Tente novamente ou contate o suporte.');
            }
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('AnimalLot delete falhou', ['lot_id' => $lote->id, 'error' => $e->getMessage()]);
            if (str_contains($e->getMessage(), 'foreign key') || str_contains($e->getMessage(), 'a foreign key constraint fails')) {
                return back()->with('error', 'Este lote tem registros vinculados (eventos, movimentações ou vendas). Desative o lote ao invés de excluir.');
            }
            return back()->with('error', 'Erro ao remover lote: ' . $e->getMessage());
        }

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
            'species_id' => ['nullable', 'exists:animal_species,id'],
            'codigo' => [
                'required', 'string', 'max:30',
                Rule::unique('animal_lots', 'codigo')->ignore($ignoreId),
            ],
            'nome' => ['required', 'string', 'max:150'],
            'finalidade' => ['nullable', Rule::in(array_keys(self::FINALIDADES))],
            'descricao' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            // Campos de lote agregado (ave/peixe) — quando preenchidos
            // sinalizam cadastro em massa (gestao_modo=agregada no store).
            // max:100000 = 100mil cabeças num lote único; mais que isso indica erro de digitação.
            'quantidade_inicial' => ['nullable', 'numeric', 'min:1', 'max:100000'],
            // Peso médio por cabeça com max plausível: ave/peixe pequenos, max 5kg por cabeça.
            // Bovino agregado é raro, mas se for: 1500kg max por cabeça.
            'peso_medio_kg' => ['nullable', 'numeric', 'min:0', 'max:1500'],
            'data_inicio' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
    }
}
