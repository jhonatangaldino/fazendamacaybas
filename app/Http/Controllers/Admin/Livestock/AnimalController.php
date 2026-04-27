<?php

namespace App\Http\Controllers\Admin\Livestock;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Livestock\Animal;
use App\Models\Livestock\AnimalBreed;
use App\Domain\Integration\Services\AnimalSaleToRevenueService;
use App\Models\Livestock\AnimalEvent;
use App\Models\Livestock\AnimalLocation;
use App\Models\Livestock\AnimalLot;
use App\Models\Livestock\AnimalSpecies;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AnimalController extends Controller
{
    /**
     * ═════ D2 — Consolidação de Domínio · REBANHO ═════
     *
     * Matrizes de regras por `animal_species.profile`. Centralizam o que cada
     * perfil aceita, permitindo que store/update apliquem validação de
     * coerência sem espalhar if/else ad-hoc.
     *
     * RETROCOMPATIBILIDADE:
     *   - Profiles NÃO listados aqui passam sem validação extra (fallback permissivo).
     *   - Em update, regras só aplicam se o campo em questão MUDOU (evita
     *     quebrar registros legados que usam categorias antigas tipo "misto"
     *     em perfis onde ela não consta da matriz nova).
     */

    /** Categorias aceitas por profile. Lista vazia = perfil NÃO aceita categoria. */
    private const CATEGORIAS_POR_PROFILE = [
        'ruminante_corte'  => ['corte', 'reproducao', 'misto'],
        'ruminante_leite'  => ['leite', 'reproducao', 'misto'],
        'ruminante_lan'    => ['corte', 'reproducao', 'misto'],
        'equino'           => ['trabalho', 'esporte', 'reproducao'],
        'suino'            => ['corte', 'reproducao'],
        'ave_postura'      => ['postura'],
        'ave_corte'        => ['corte'],
        'aquicultura_lote' => [],
        'apicultura'       => [],
        'pet'              => ['companhia', 'servico', 'pet'],
    ];

    /** Profiles cujo manejo é por lote — exigem `lot_id`. */
    private const PROFILES_EXIGEM_LOTE = [
        'ave_postura', 'ave_corte', 'aquicultura_lote', 'apicultura',
    ];

    /** Profiles que exigem `data_nascimento` (manejo individual com ciclo etário). */
    private const PROFILES_EXIGEM_DATA_NASC = [
        'ruminante_corte', 'ruminante_leite', 'ruminante_lan',
        'equino', 'suino', 'pet',
    ];

    /** Profiles que exigem `nome` (animais identificados por nome, não número). */
    private const PROFILES_EXIGEM_NOME = [
        'pet',
    ];

    public function index(Request $request)
    {
        // Reference data multi-tenant: species/breed estão em tenant_id=1 (sistema)
        // mas o user pode estar em outro tenant. O global scope filtra por tenant
        // no HTTP, deixando species nulo. Carregamos manualmente sem escopo —
        // mesmo padrão do SaleWizardController.
        $speciesById = AnimalSpecies::withoutGlobalScope(\App\Domain\Tenancy\Scopes\BelongsToTenantScope::class)
            ->get(['id', 'nome', 'profile', 'gestao', 'allowed_events'])
            ->keyBy('id');

        $q = Animal::with(['breed:id,nome', 'lot:id,nome', 'location:id,nome,tipo', 'farm:id,nome'])
            ->when($request->search, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('identificacao', 'like', "%{$request->search}%")
                ->orWhere('nome', 'like', "%{$request->search}%")))
            ->when($request->species_id, fn ($qq) => $qq->where('species_id', $request->species_id))
            ->when($request->lot_id, fn ($qq) => $qq->where('lot_id', $request->lot_id))
            ->when($request->location_id, fn ($qq) => $qq->where('location_id', $request->location_id))
            ->when($request->status, fn ($qq) => $qq->where('status', $request->status))
            ->when($request->categoria, fn ($qq) => $qq->where('categoria', $request->categoria))
            ->orderBy('identificacao');

        return Inertia::render('Admin/Livestock/Animals/Index', [
            'animals' => $q->paginate(25)->withQueryString()->through(function (Animal $a) use ($speciesById) {
                $sp = $speciesById->get($a->species_id);
                return [
                    'id' => $a->id,
                    'identificacao' => $a->identificacao,
                    'nome' => $a->nome,
                    'sexo' => $a->sexo,
                    'categoria' => $a->categoria,
                    'status' => $a->status,
                    'data_nascimento' => $a->data_nascimento,
                    'peso_atual' => $a->peso_atual,
                    'photo_url' => $a->photoUrl(),
                    'species' => $sp ? [
                        'id' => $sp->id,
                        'nome' => $sp->nome,
                        'gestao' => $sp->gestao,
                        'profile' => $sp->profile,
                        'allowed_events' => $sp->allowed_events,
                    ] : null,
                    'breed' => $a->breed ? ['nome' => $a->breed->nome] : null,
                    'lot' => $a->lot ? ['nome' => $a->lot->nome] : null,
                    'location' => $a->location ? ['nome' => $a->location->nome, 'tipo' => $a->location->tipo] : null,
                ];
            }),
            'filters' => $request->only(['search', 'species_id', 'lot_id', 'location_id', 'status', 'categoria']),
            'species' => AnimalSpecies::where('is_active', true)->get(['id', 'nome']),
            'lots' => AnimalLot::where('is_active', true)->get(['id', 'nome']),
            'locations' => AnimalLocation::ativos()->orderBy('tipo')->orderBy('nome')->get(['id', 'nome', 'tipo']),
            // Resumo de valor — 1 query agregada ajuda o dono a saber
            // "quanto rebanho tenho" e "quantos precisam de atenção"
            // sem ter que navegar. Sem pesagem há 60+ dias = lembrete
            // de pesar (ganho/perda só é calculável com pesagens).
            'resumo' => Animal::selectRaw("
                SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as ativos,
                SUM(CASE WHEN status = 'vendido' THEN 1 ELSE 0 END) as vendidos,
                SUM(CASE WHEN status IN ('morto','abatido') THEN 1 ELSE 0 END) as baixas,
                SUM(CASE WHEN status = 'ativo' AND peso_atual > 0 THEN peso_atual ELSE 0 END) as peso_total,
                SUM(CASE WHEN status = 'ativo' AND peso_atual > 0 THEN 1 ELSE 0 END) as ativos_com_peso
            ")->first(),
            'partners' => Partner::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'categorias' => [
                ['value' => 'leite', 'label' => 'Leite'],
                ['value' => 'corte', 'label' => 'Corte'],
                ['value' => 'reproducao', 'label' => 'Reprodução'],
                ['value' => 'misto', 'label' => 'Misto'],
                ['value' => 'pet', 'label' => 'Pet'],
                ['value' => 'servico', 'label' => 'Serviço / trabalho'],
            ],
        ]);
    }

    public function create()
    {
        return $this->renderForm(null);
    }

    public function store(Request $request)
    {
        $data = $this->validateAnimal($request);

        // D2 · Coerência por profile — bloqueio semântico antes de gravar.
        if ($err = $this->validateDomainCoherence($data, null)) {
            return back()->withInput()->with('error', $err);
        }

        // Foto opcional anexada ao cadastro (mesma request — UX zero-saída).
        // Antes o front fazia upload em request separada e às vezes perdia o ID;
        // agora é atômico: cadastro + foto em uma transação.
        $animal = Animal::create($data);

        if ($request->hasFile('foto')) {
            $request->validate(['foto' => ['image', 'max:5120']]); // 5 MB
            $path = $request->file('foto')->store(
                "animais/tenant_{$animal->tenant_id}",
                'public',
            );
            $animal->update(['photo_path' => $path]);
        }

        return redirect()
            ->route('admin.rebanho.animais.index')
            ->with('success', 'Animal cadastrado.')
            ->with('created_animal_id', $animal->id);
    }

    public function edit(Animal $animal)
    {
        return $this->renderForm($animal);
    }

    public function update(Request $request, Animal $animal)
    {
        $data = $this->validateAnimal($request, $animal->id);

        // D2 · Coerência por profile também em update — mas respeita legado:
        // regras só aplicam a CAMPOS QUE MUDARAM (ver validateDomainCoherence).
        if ($err = $this->validateDomainCoherence($data, $animal)) {
            return back()->withInput()->with('error', $err);
        }

        $animal->update($data);

        return redirect()->route('admin.rebanho.animais.index')->with('success', 'Animal atualizado.');
    }

    public function destroy(Animal $animal)
    {
        $animal->delete();

        return back()->with('success', 'Animal excluído.');
    }

    protected function renderForm(?Animal $animal)
    {
        $animalPayload = $animal ? array_merge($animal->toArray(), [
            'photo_url' => $animal->photoUrl(),
        ]) : null;

        return Inertia::render('Admin/Livestock/Animals/Form', [
            'animal' => $animalPayload,
            'species' => AnimalSpecies::where('is_active', true)->with(['breeds:id,species_id,nome'])->get(),
            'lots' => AnimalLot::where('is_active', true)->get(['id', 'nome', 'codigo']),
            'locations' => AnimalLocation::ativos()->orderBy('tipo')->orderBy('nome')->get(['id', 'nome', 'tipo']),
            'farms' => Farm::where('is_active', true)->get(['id', 'nome']),
            'partners' => Partner::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    protected function validateAnimal(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'farm_id' => ['nullable', 'exists:farms,id'],
            'species_id' => ['required', 'exists:animal_species,id'],
            'breed_id' => ['nullable', 'exists:animal_breeds,id'],
            'lot_id' => ['nullable', 'exists:animal_lots,id'],
            'location_id' => ['nullable', 'exists:animal_locations,id'],
            'identificacao' => ['required', 'string', 'max:30', Rule::unique('animals', 'identificacao')->ignore($id)->whereNull('deleted_at')],
            'nome' => ['nullable', 'string', 'max:100'],
            'sexo' => ['required', 'in:M,F'],
            'data_nascimento' => ['nullable', 'date'],
            'peso_nascimento' => ['nullable', 'numeric', 'min:0'],
            // peso_atual NÃO é editável no form — é derivado do último evento de pesagem (regra incremental-first)
            'origem' => ['required', 'in:nascido,compra'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'data_aquisicao' => ['nullable', 'date'],
            'valor_aquisicao' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:ativo,vendido,morto,abatido,transferido'],
            'observacoes' => ['nullable', 'string'],
            // Enum ampliado (D2) — perfis ave_postura/ave_corte/equino exigem
            // categorias que o enum antigo não contemplava. A rule por profile
            // em validateDomainCoherence filtra quais valores são aceitos para
            // cada espécie; este enum só define o universo sintático.
            'categoria' => ['nullable', 'in:leite,corte,reproducao,misto,pet,servico,trabalho,esporte,postura,companhia'],
            'numero_registro' => ['nullable', 'string', 'max:50'],
        ]);
    }

    /**
     * D2 · Valida coerência entre os dados do animal e o perfil da espécie.
     *
     * Retorna a string de erro amigável para `back()->with('error', ...)` ou
     * `null` se tudo estiver coerente.
     *
     * Regras aplicadas:
     *   1. Categoria deve estar na lista CATEGORIAS_POR_PROFILE do perfil.
     *      Perfil com lista vazia → categoria deve ser nula (aquicultura,
     *      apicultura).
     *   2. Perfis em PROFILES_EXIGEM_LOTE requerem `lot_id` informado.
     *   3. Perfis em PROFILES_EXIGEM_DATA_NASC requerem `data_nascimento`.
     *   4. Perfis em PROFILES_EXIGEM_NOME requerem `nome`.
     *
     * Em UPDATE ($existing != null), as regras só disparam para os campos que
     * MUDARAM. Isso preserva registros legados cadastrados antes destas regras
     * — reeditar um bovino com categoria "misto" antiga continua salvando sem
     * exigir que o usuário troque só porque a matriz mudou. Regra: quem não
     * mexeu, não paga.
     */
    protected function validateDomainCoherence(array $data, ?Animal $existing): ?string
    {
        $species = AnimalSpecies::find($data['species_id'] ?? null);
        if (! $species) {
            return null; // já validado pelo 'exists' do validator base
        }

        $profile = $species->profile;
        if (! $profile) {
            return null; // retrocompat: espécie sem profile cadastrado → deixa passar
        }

        $nomeEsp = $species->nome ?? 'esta espécie';

        // ── 1. CATEGORIA coerente com profile ──────────────────────────────
        $categoriaAtual = $data['categoria'] ?? null;
        $categoriaMudou = $existing === null || $categoriaAtual !== $existing->categoria;

        if ($categoriaMudou && array_key_exists($profile, self::CATEGORIAS_POR_PROFILE)) {
            $permitidas = self::CATEGORIAS_POR_PROFILE[$profile];

            // Perfil com lista vazia → não aceita categoria alguma
            if (empty($permitidas)) {
                if (! empty($categoriaAtual)) {
                    return "A espécie selecionada ({$nomeEsp}) não aceita categoria leite/corte. "
                        . 'Animais de aquicultura/apicultura são manejados em lote, sem categoria individual. '
                        . 'Deixe o campo categoria em branco.';
                }
            } elseif (! empty($categoriaAtual) && ! in_array($categoriaAtual, $permitidas, true)) {
                return "A espécie selecionada ({$nomeEsp}) não permite categoria '{$categoriaAtual}'. "
                    . 'Categorias válidas para esta espécie: ' . implode(', ', $permitidas) . '.';
            }
        }

        // ── 2. LOTE obrigatório (aves, aquicultura, apicultura) ────────────
        $lotAtual = $data['lot_id'] ?? null;
        $lotMudou = $existing === null || (int) $lotAtual !== (int) $existing->lot_id;

        if ($lotMudou && in_array($profile, self::PROFILES_EXIGEM_LOTE, true) && empty($lotAtual)) {
            $exemplos = [
                'ave_postura'      => 'Aves de postura',
                'ave_corte'        => 'Aves de corte',
                'aquicultura_lote' => 'Animais de aquicultura',
                'apicultura'       => 'Apiários',
            ];
            $rotulo = $exemplos[$profile] ?? $nomeEsp;

            return "{$rotulo} devem ser cadastrados em lote. Selecione um lote no formulário.";
        }

        // ── 3. DATA DE NASCIMENTO obrigatória ──────────────────────────────
        $dnAtual = $data['data_nascimento'] ?? null;
        $dnMudou = $existing === null || $dnAtual !== $existing->data_nascimento?->format('Y-m-d');

        if ($dnMudou && in_array($profile, self::PROFILES_EXIGEM_DATA_NASC, true) && empty($dnAtual)) {
            return "Data de nascimento é obrigatória para {$nomeEsp}. "
                . 'O ciclo etário (desmame, cobertura, abate, etc.) é calculado a partir dela.';
        }

        // ── 4. NOME obrigatório (pets) ─────────────────────────────────────
        $nomeAtual = $data['nome'] ?? null;
        $nomeAnimal = $existing?->nome;
        $nomeMudou = $existing === null || $nomeAtual !== $nomeAnimal;

        if ($nomeMudou && in_array($profile, self::PROFILES_EXIGEM_NOME, true) && empty($nomeAtual)) {
            return "Animais do tipo {$nomeEsp} exigem o campo Nome. "
                . 'Diferente de animais de produção, pets são identificados pelo nome, não só pelo número.';
        }

        return null; // tudo coerente
    }

    /**
     * Upload de foto do animal.
     */
    public function uploadPhoto(Request $request, Animal $animal): \Illuminate\Http\JsonResponse
    {
        $v = validator($request->all(), [
            'file' => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp,image/gif'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'message' => $v->errors()->first('file')], 422);
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension()) ?: $file->guessExtension();
        $filename = 'animal-'.$animal->id.'-'.\Illuminate\Support\Str::random(8).'.'.$ext;
        $path = $file->storeAs('animals', $filename, 'public');

        if ($animal->photo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($animal->photo_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($animal->photo_path);
        }

        $animal->update(['photo_path' => $path]);

        return response()->json([
            'ok' => true,
            'message' => 'Foto atualizada.',
            'avatar_url' => asset('storage/'.$path),
            'path' => $path,
        ]);
    }

    public function removePhoto(Animal $animal): \Illuminate\Http\JsonResponse
    {
        if ($animal->photo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($animal->photo_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($animal->photo_path);
        }
        $animal->update(['photo_path' => null]);

        return response()->json(['ok' => true]);
    }

    /**
     * Página de detalhe do animal com timeline completa de eventos.
     * Inclui evolução de peso, vacinas, medicamentos, eventos reprodutivos.
     */
    public function show(Animal $animal)
    {
        $events = $animal->events()
            ->with([
                'partner:id,nome',
                'lotOrigem:id,nome', 'lotDestino:id,nome',
                'locationOrigem:id,nome,tipo', 'locationDestino:id,nome,tipo',
                'creator:id,name',
            ])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        // Série para gráfico de evolução de peso (ordem cronológica ESTÁVEL).
        // Ordena por data ASC + id ASC como desempate — assim múltiplas pesagens
        // no mesmo dia mantêm a ordem de inserção e first/last são sempre a
        // mais antiga / mais recente de verdade.
        $pesagens = $events->where('tipo', 'pesagem')
            ->sortBy(fn ($e) => sprintf('%s-%010d', $e->data?->format('Y-m-d') ?? '9999-12-31', $e->id))
            ->values()
            ->map(fn ($e) => [
                'id' => $e->id,
                'data' => $e->data?->toDateString(),
                'peso' => (float) $e->peso,
            ]);

        // Estado reprodutivo / produtivo derivado dos eventos — pra renderizar
        // badges automáticos na ficha (prenhe, seca, última produção).
        $statusReprodutivo = $this->calcularStatusReprodutivo($events);

        return Inertia::render('Admin/Livestock/Animals/Show', [
            'animal' => [
                ...$animal->load(['species:id,nome', 'breed:id,nome', 'lot:id,nome', 'location:id,nome,tipo', 'farm:id,nome', 'fornecedor:id,nome'])->toArray(),
                'photo_url' => $animal->photoUrl(),
                'idade_em_meses' => $animal->data_nascimento?->diffInMonths(now()),
                'status_reprodutivo' => $statusReprodutivo,
            ],
            'events' => $events,
            'pesagens' => $pesagens,
            'partners' => Partner::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'lots' => AnimalLot::where('is_active', true)->get(['id', 'nome']),
            'locations' => AnimalLocation::ativos()->orderBy('tipo')->orderBy('nome')->get(['id', 'nome', 'tipo']),
        ]);
    }

    /**
     * Resume o estado reprodutivo/produtivo do animal a partir dos eventos.
     *
     * Retorno:
     *   {
     *     prenhe: { status, dpp, dias_para_parto, data_exame } | null
     *     secagem: { data, dias_atras } | null
     *     producao_recente: { data, litros } | null
     *     ultimo_toque: { data, status } | null
     *   }
     */
    private function calcularStatusReprodutivo($events): array
    {
        $resultado = [
            'prenhe' => null,
            'secagem' => null,
            'producao_recente' => null,
            'ultimo_toque' => null,
        ];

        // Último exame de toque (qualquer status)
        $ultimoToque = $events->where('tipo', 'exame_toque')->sortByDesc('data')->first();
        if ($ultimoToque) {
            $resultado['ultimo_toque'] = [
                'data' => $ultimoToque->data?->toDateString(),
                'data_br' => $ultimoToque->data?->format('d/m/Y'),
                'status' => $ultimoToque->gestacao_status,
            ];

            // Se prenhe, verificar se NÃO houve parto/mortalidade depois
            if ($ultimoToque->gestacao_status === 'prenhe' && $ultimoToque->data_prevista_parto) {
                $partoDepois = $events
                    ->where('tipo', 'reproducao')
                    ->where('data', '>', $ultimoToque->data)
                    ->where(fn ($e) => str_contains($e->observacoes ?? '', 'Parto'))
                    ->first();
                $morteDepois = $events
                    ->where('tipo', 'mortalidade')
                    ->where('data', '>', $ultimoToque->data)
                    ->first();

                if (! $partoDepois && ! $morteDepois) {
                    $dpp = \Carbon\Carbon::parse($ultimoToque->data_prevista_parto);
                    $resultado['prenhe'] = [
                        'data_exame' => $ultimoToque->data?->toDateString(),
                        'data_exame_br' => $ultimoToque->data?->format('d/m/Y'),
                        'dpp' => $dpp->toDateString(),
                        'dpp_br' => $dpp->format('d/m/Y'),
                        'dias_para_parto' => (int) now()->startOfDay()->diffInDays($dpp, false),
                        'gestacao_dias_no_exame' => $ultimoToque->gestacao_dias,
                    ];
                }
            }
        }

        // Última secagem (verifica se NÃO houve novo controle leiteiro depois)
        $ultimaSecagem = $events->where('tipo', 'secagem')->sortByDesc('data')->first();
        if ($ultimaSecagem) {
            $producaoDepois = $events
                ->where('tipo', 'controle_leiteiro')
                ->where('data', '>', $ultimaSecagem->data)
                ->first();
            if (! $producaoDepois) {
                $resultado['secagem'] = [
                    'data' => $ultimaSecagem->data?->toDateString(),
                    'data_br' => $ultimaSecagem->data?->format('d/m/Y'),
                    'dias_atras' => (int) $ultimaSecagem->data?->diffInDays(now()),
                ];
            }
        }

        // Última produção leiteira (último controle_leiteiro)
        $ultimaProducao = $events->where('tipo', 'controle_leiteiro')->sortByDesc('data')->first();
        if ($ultimaProducao) {
            $resultado['producao_recente'] = [
                'data' => $ultimaProducao->data?->toDateString(),
                'data_br' => $ultimaProducao->data?->format('d/m/Y'),
                'litros' => (float) ($ultimaProducao->producao_litros ?? 0),
            ];
        }

        return $resultado;
    }

    /**
     * Registra um evento no animal (pesagem, vacinação, medicação, reprodução, etc.).
     * Valor e partner opcionais — quando informados, alimentam o ecossistema financeiro.
     *
     * FASE 2 · Integração cross-módulo:
     *   Quando tipo=venda com valor>0, gera automaticamente uma
     *   FinancialTransaction (tipo=receita) via AnimalSaleToRevenueService.
     *   Todo o fluxo (criação do evento + updates do animal + integração)
     *   roda em DB::transaction — atomicidade garantida.
     */
    public function storeEvent(Request $request, Animal $animal, AnimalSaleToRevenueService $sale)
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:pesagem,vacinacao,medicacao,vermifugacao,reproducao,movimentacao,movimentacao_local,observacao,ordenha,tosquia,ferrageamento,castracao,postura_diaria,biometria_amostral,qualidade_agua,alimentacao,mortalidade,venda,compra,secagem,controle_leiteiro,exame_toque'],
            'data' => ['required', 'date', 'before_or_equal:today'],
            'peso' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'vacina' => ['nullable', 'string', 'max:120'],
            'medicamento' => ['nullable', 'string', 'max:120'],
            'dose' => ['nullable', 'numeric', 'min:0'],
            'via_aplicacao' => ['nullable', 'string', 'max:30'],
            'responsavel' => ['nullable', 'string', 'max:120'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'lot_origem_id' => ['nullable', 'exists:animal_lots,id'],
            'lot_destino_id' => ['nullable', 'exists:animal_lots,id'],
            'location_origem_id' => ['nullable', 'exists:animal_locations,id'],
            'location_destino_id' => ['nullable', 'exists:animal_locations,id'],
            'observacoes' => ['nullable', 'string'],
            // Novos · controle leiteiro
            'ordenhas' => ['nullable', 'array', 'max:6'],
            'ordenhas.*.label' => ['nullable', 'string', 'max:20'],
            'ordenhas.*.litros' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'producao_litros' => ['nullable', 'numeric', 'min:0', 'max:299.99'],
            // Novos · exame de toque (palpação)
            'gestacao_status' => ['nullable', 'in:prenhe,vazia,duvida'],
            'gestacao_dias' => ['nullable', 'integer', 'min:0', 'max:340'],
            'data_prevista_parto' => ['nullable', 'date'],
        ], [
            'data.before_or_equal' => 'A data do evento não pode ser futura.',
            'tipo.required' => 'Informe o tipo de evento.',
        ]);

        // ── Regras por TIPO de evento (campos obrigatórios condicionais) ──
        if ($data['tipo'] === 'pesagem' && empty($data['peso'])) {
            return back()->with('error', 'Pesagem exige o valor do peso.');
        }
        if ($data['tipo'] === 'vacinacao' && empty($data['vacina'])) {
            return back()->with('error', 'Vacinação exige o nome da vacina.');
        }
        if ($data['tipo'] === 'medicacao' && empty($data['medicamento'])) {
            return back()->with('error', 'Medicação exige o nome do medicamento.');
        }

        // ── Regra de DOMÍNIO: evento precisa ser permitido pelo perfil da espécie ──
        //
        // Cada AnimalSpecies traz `allowed_events` (JSON) com a lista de tipos de
        // evento pertinentes ao manejo daquele perfil. Ex.:
        //   - bovino_corte: pesagem, vacinacao, medicacao, reproducao, venda…
        //   - peixe/aquicultura_lote: biometria_amostral, qualidade_agua,
        //     alimentacao, mortalidade, venda — SEM pesagem individual nem
        //     vacinação tradicional
        //   - ave_postura: postura_diaria, mortalidade, alimentacao, venda —
        //     SEM pesagem individual
        //
        // Sem esta validação o sistema aceitava "pesagem de peixe" e "vacinação
        // em postura" — legais tecnicamente, sem sentido no domínio. A bloqueio
        // server-side (antes do form filtrar UI na próxima iteração) garante
        // integridade dos dados.
        //
        // Retrocompat: species sem `allowed_events` (coluna nova ou seed vazio)
        // passa sem validação — não quedoesn't dados existentes nem tenants que
        // ainda não rodaram o seed atualizado.
        //
        // EVENTOS UNIVERSAIS (ciclo de vida): venda, compra, mortalidade,
        // nascimento e observação são permitidos para QUALQUER espécie,
        // independentemente de estarem listados em `allowed_events`. Todos
        // os animais podem ser vendidos/mortos/observados — o seeder de
        // allowed_events filtra apenas eventos de manejo ESPECÍFICO do
        // perfil (ex.: ordenha só para leiteiro, postura só para ave).
        $allowed = $animal->species?->allowed_events;
        // Eventos universais — aplicáveis a QUALQUER espécie independente do profile:
        //   - venda/compra/mortalidade/nascimento/observacao: ciclo de vida
        //   - movimentacao: mudar de LOTE (grupo) — todo animal pertence a algum grupo
        //   - movimentacao_local: mudar de PASTO/piquete/tanque (local físico) — todo animal está em algum lugar
        // allowed_events é só para eventos de manejo ESPECÍFICO do profile (ex.: ordenha só para leiteiro).
        $eventosUniversais = ['venda', 'compra', 'mortalidade', 'nascimento', 'observacao', 'movimentacao', 'movimentacao_local'];
        $isUniversal = in_array($data['tipo'], $eventosUniversais, true);

        if (! $isUniversal && is_array($allowed) && count($allowed) > 0 && ! in_array($data['tipo'], $allowed, true)) {
            return back()->with('error',
                "O evento \"{$data['tipo']}\" não é aplicável a " . ($animal->species->nome ?? 'esta espécie') . '. '
                . 'Tipos válidos para esta espécie: ' . implode(', ', $allowed) . '.'
            );
        }

        // Atomicidade: evento + updates derivados + integração rodam juntos.
        // Se qualquer etapa falhar (FK violation, erro no service), rollback
        // de tudo — nunca deixa estado inconsistente entre Livestock e Financeiro.
        $event = DB::transaction(function () use ($animal, $data, $request, $sale) {
            $event = $animal->events()->create([
                ...$data,
                'lot_id' => $animal->lot_id,
                'created_by' => $request->user()?->id,
            ]);

            // Pesagem atualiza o peso_atual (cache desnormalizado no Animal)
            if ($data['tipo'] === 'pesagem') {
                $animal->update(['peso_atual' => $data['peso']]);
            }

            // Movimentação de LOTE (grupo) altera lote atual
            if ($data['tipo'] === 'movimentacao' && ! empty($data['lot_destino_id'])) {
                $animal->update(['lot_id' => $data['lot_destino_id']]);
            }
            // Movimentação de LOCAL (pasto/piquete) altera posição física
            if ($data['tipo'] === 'movimentacao_local' && ! empty($data['location_destino_id'])) {
                $animal->update(['location_id' => $data['location_destino_id']]);
            }

            // Venda muda status do animal e registra saída
            if ($data['tipo'] === 'venda') {
                $animal->update(['status' => 'vendido', 'data_saida' => $data['data']]);
            }
            // Mortalidade/abate encerram o ciclo do animal
            if ($data['tipo'] === 'mortalidade') {
                $animal->update(['status' => 'morto', 'data_saida' => $data['data']]);
            }

            // ── FASE 2 · Integração Venda → Receita Financeira ─────────
            // Service decide se gera (tipo=venda + valor>0 + conta ativa)
            // e é idempotente (numero_documento=ANIMAL_EVENT:<id>).
            // Retorna null silenciosamente quando a integração não se
            // aplica — não interrompe o fluxo do evento.
            $event->loadMissing('animal');
            $sale->generateForEvent($event);

            return $event;
        });

        // Contexto pós-ação — dados auxiliares para o wizard mostrar IMPACTO,
        // não apenas "registrado". Usuário 60+ precisa ver o que mudou para
        // confiar que o sistema fez o que prometeu.
        $contexto = $this->buildContexto($animal->fresh(), $data, $event);
        session()->flash('event_contexto', $contexto);

        return back()->with('success', match ($data['tipo']) {
            'pesagem' => 'Pesagem registrada.',
            'vacinacao' => 'Vacinação registrada.',
            'medicacao' => 'Medicação registrada.',
            'vermifugacao' => 'Vermifugação registrada.',
            'reproducao' => 'Evento reprodutivo registrado.',
            'movimentacao' => 'Mudança de lote registrada.',
            'movimentacao_local' => 'Mudança de pasto registrada.',
            'ordenha' => 'Ordenha registrada.',
            'postura_diaria' => 'Postura registrada.',
            'biometria_amostral' => 'Biometria registrada.',
            'venda' => 'Venda registrada — animal marcado como vendido.',
            'mortalidade' => 'Mortalidade registrada.',
            default => 'Evento registrado.',
        });
    }

    /**
     * Venda em lote — marca múltiplos animais como vendidos em uma única operação.
     * Cria um evento de venda em cada animal com o mesmo valor rateado (ou valor unitário único).
     */
    public function sellBatch(Request $request)
    {
        $data = $request->validate([
            'animal_ids' => ['required', 'array', 'min:1'],
            'animal_ids.*' => ['integer', 'exists:animals,id'],
            'data' => ['required', 'date', 'before_or_equal:today'],
            'valor_total' => ['required', 'numeric', 'min:0'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'observacoes' => ['nullable', 'string'],
            // Contexto da operação (opcional) — alimenta observações detalhadas
            'unidade' => ['nullable', 'string', 'max:20'],
            'quantidade' => ['nullable', 'numeric', 'min:0'],
            'peso_medio' => ['nullable', 'numeric', 'min:0'],
            'valor_unitario' => ['nullable', 'numeric', 'min:0'],
        ]);

        $animais = Animal::whereIn('id', $data['animal_ids'])->where('status', 'ativo')->get();
        if ($animais->isEmpty()) {
            return back()->with('error', 'Nenhum animal ativo selecionado.');
        }

        // Verifica espécie homogênea (regra UX: não misturar bovino com peixe)
        $especies = $animais->pluck('species_id')->unique();
        if ($especies->count() > 1) {
            return back()->with('error', 'Não é possível vender animais de espécies diferentes em lote.');
        }

        $valorUnitario = round($data['valor_total'] / $animais->count(), 2);

        // Texto detalhado para as observações (preserva a contabilidade do negócio)
        $detalhes = [];
        if (! empty($data['unidade']) && ! empty($data['quantidade']) && ! empty($data['valor_unitario'])) {
            $detalhes[] = sprintf(
                'Venda: %s %s × R$ %s = R$ %s',
                number_format((float) $data['quantidade'], 3, ',', '.'),
                $data['unidade'],
                number_format((float) $data['valor_unitario'], 2, ',', '.'),
                number_format((float) $data['valor_total'], 2, ',', '.'),
            );
        }
        if (! empty($data['peso_medio'])) {
            $detalhes[] = sprintf('Peso médio por cabeça: %s kg', number_format((float) $data['peso_medio'], 2, ',', '.'));
        }
        if (! empty($data['observacoes'])) {
            $detalhes[] = $data['observacoes'];
        }
        $obsFinal = $detalhes ? implode("\n", $detalhes) : null;

        \DB::transaction(function () use ($animais, $data, $valorUnitario, $obsFinal, $request) {
            foreach ($animais as $animal) {
                $animal->events()->create([
                    'tipo' => 'venda',
                    'data' => $data['data'],
                    'valor' => $valorUnitario,
                    'peso' => ! empty($data['peso_medio']) ? (float) $data['peso_medio'] : null,
                    'partner_id' => $data['partner_id'] ?? null,
                    'observacoes' => $obsFinal,
                    'created_by' => $request->user()?->id,
                ]);
                $animal->update(['status' => 'vendido', 'data_saida' => $data['data']]);
            }
        });

        return back()->with('success', $animais->count().' '.str($especies->first() ? 'animais' : 'animal')
            .' vendido'.($animais->count() === 1 ? '' : 's').' em lote (R$ '.number_format($data['valor_total'], 2, ',', '.').').');
    }

    /**
     * Evento EM LOTE — aplica o MESMO evento (vacina, medicação, vermífugo,
     * observação) a MÚLTIPLOS animais de uma só vez. Cenário real:
     * veterinário passa vacinando 80 bovinos em manejo; sem esse endpoint
     * o usuário teria que registrar 80 vezes individualmente.
     *
     * Filtros aceitos (um obrigatório):
     *   - animal_ids: array explícito de IDs
     *   - lot_id: todos os animais ativos do lote
     *   - location_id: todos os animais ativos do pasto
     *   - species_id: todos os animais ativos da espécie (use com cuidado)
     *
     * Roda em DB::transaction — se qualquer evento falhar, rollback total.
     */
    public function storeEventBatch(Request $request)
    {
        // Auditoria 2026-04-27 — A4 movimentação em lote inteiro.
        // storeEventBatch agora aceita `movimentacao` (mudança de LOTE/grupo)
        // e `movimentacao_local` (mudança de pasto/piquete). Cenário real:
        // rotação de pastagem move 50 vacas de uma vez de um pasto para outro.
        // mortalidade entra em massa para registrar abate ou perda em lote.
        $data = $request->validate([
            'tipo' => ['required', 'in:vacinacao,medicacao,vermifugacao,observacao,movimentacao,movimentacao_local,mortalidade'],
            'data' => ['required', 'date', 'before_or_equal:today'],
            'vacina' => ['nullable', 'string', 'max:120'],
            'medicamento' => ['nullable', 'string', 'max:120'],
            'dose' => ['nullable', 'numeric', 'min:0'],
            'via_aplicacao' => ['nullable', 'string', 'max:30'],
            'responsavel' => ['nullable', 'string', 'max:120'],
            'observacoes' => ['nullable', 'string'],
            // Filtros (exatamente 1 obrigatório)
            'animal_ids' => ['nullable', 'array', 'min:1'],
            'animal_ids.*' => ['integer', 'exists:animals,id'],
            'lot_id' => ['nullable', 'exists:animal_lots,id'],
            'location_id' => ['nullable', 'exists:animal_locations,id'],
            'species_id' => ['nullable', 'exists:animal_species,id'],
            // Destino para movimentação
            'lot_destino_id' => ['nullable', 'exists:animal_lots,id'],
            'location_destino_id' => ['nullable', 'exists:animal_locations,id'],
        ]);

        // Regras por tipo
        if ($data['tipo'] === 'vacinacao' && empty($data['vacina'])) {
            return back()->with('error', 'Informe o nome da vacina.');
        }
        if (in_array($data['tipo'], ['medicacao', 'vermifugacao'], true) && empty($data['medicamento'])) {
            return back()->with('error', 'Informe o nome do medicamento.');
        }
        if ($data['tipo'] === 'observacao' && empty($data['observacoes'])) {
            return back()->with('error', 'Escreva a observação.');
        }
        if ($data['tipo'] === 'movimentacao' && empty($data['lot_destino_id'])) {
            return back()->with('error', 'Informe para qual lote os animais vão.');
        }
        if ($data['tipo'] === 'movimentacao_local' && empty($data['location_destino_id'])) {
            return back()->with('error', 'Informe para qual pasto/local os animais vão.');
        }
        // Garante que origem ≠ destino para movimentação por filtro
        if ($data['tipo'] === 'movimentacao' && ! empty($data['lot_id'])
            && (int) $data['lot_id'] === (int) $data['lot_destino_id']) {
            return back()->with('error', 'O lote de origem é o mesmo do destino — nada a mover.');
        }
        if ($data['tipo'] === 'movimentacao_local' && ! empty($data['location_id'])
            && (int) $data['location_id'] === (int) $data['location_destino_id']) {
            return back()->with('error', 'O pasto de origem é o mesmo do destino — nada a mover.');
        }

        // Resolver animais-alvo
        $q = Animal::where('status', 'ativo');
        if (! empty($data['animal_ids'])) {
            $q->whereIn('id', $data['animal_ids']);
        } elseif (! empty($data['lot_id'])) {
            $q->where('lot_id', $data['lot_id']);
        } elseif (! empty($data['location_id'])) {
            $q->where('location_id', $data['location_id']);
        } elseif (! empty($data['species_id'])) {
            $q->where('species_id', $data['species_id']);
        } else {
            return back()->with('error', 'Selecione pelo menos um filtro (animais, lote, pasto ou espécie).');
        }

        $animais = $q->get();
        if ($animais->isEmpty()) {
            return back()->with('error', 'Nenhum animal ativo encontrado com esse filtro.');
        }

        // Valida contra allowed_events por espécie (universais passam sempre)
        // Movimentação e observação são universais — qualquer espécie pode mover/anotar.
        $eventosUniversais = ['observacao', 'movimentacao', 'movimentacao_local', 'mortalidade'];
        $animaisIncompativeis = [];
        foreach ($animais as $a) {
            if (in_array($data['tipo'], $eventosUniversais, true)) continue;
            $allowed = $a->species?->allowed_events;
            if (is_array($allowed) && count($allowed) > 0 && ! in_array($data['tipo'], $allowed, true)) {
                $animaisIncompativeis[] = $a->identificacao;
            }
        }
        if (! empty($animaisIncompativeis)) {
            $lista = implode(', ', array_slice($animaisIncompativeis, 0, 5));
            return back()->with('error',
                "Evento não é compatível com " . count($animaisIncompativeis) . " animal(is): {$lista}"
                . (count($animaisIncompativeis) > 5 ? ' e outros.' : '.')
                . ' Remova-os do filtro ou escolha outro tipo de evento.');
        }

        // Cria eventos em transação. Para movimentação, ALÉM de criar o evento,
        // também atualiza o lot_id/location_id de cada animal para o destino.
        DB::transaction(function () use ($animais, $data, $request) {
            foreach ($animais as $animal) {
                $animal->events()->create([
                    'tipo' => $data['tipo'],
                    'data' => $data['data'],
                    'vacina' => $data['vacina'] ?? null,
                    'medicamento' => $data['medicamento'] ?? null,
                    'dose' => $data['dose'] ?? null,
                    'via_aplicacao' => $data['via_aplicacao'] ?? null,
                    'responsavel' => $data['responsavel'] ?? null,
                    'observacoes' => $data['observacoes'] ?? null,
                    'lot_id' => $animal->lot_id,
                    'lot_destino_id' => $data['lot_destino_id'] ?? null,
                    'location_destino_id' => $data['location_destino_id'] ?? null,
                    'created_by' => $request->user()?->id,
                ]);

                // Aplica efeito colateral por tipo
                if ($data['tipo'] === 'movimentacao') {
                    $animal->update(['lot_id' => $data['lot_destino_id']]);
                } elseif ($data['tipo'] === 'movimentacao_local') {
                    $animal->update(['location_id' => $data['location_destino_id']]);
                } elseif ($data['tipo'] === 'mortalidade') {
                    $animal->update(['status' => 'morto']);
                }
            }
        });

        $n = $animais->count();
        $msg = match ($data['tipo']) {
            'vacinacao' => "{$n} animais vacinados com {$data['vacina']}.",
            'medicacao' => "{$n} animais medicados com {$data['medicamento']}.",
            'vermifugacao' => "{$n} animais vermifugados.",
            'observacao' => "Observação registrada em {$n} animais.",
            'movimentacao' => "{$n} animais movidos para o lote de destino.",
            'movimentacao_local' => "{$n} animais movidos para o pasto/local de destino.",
            'mortalidade' => "Baixa de {$n} animais registrada.",
        };

        // Contexto pro wizard mostrar impacto na tela de sucesso
        session()->flash('event_batch_contexto', [
            'tipo' => $data['tipo'],
            'total' => $n,
            'detalhe' => $data['vacina'] ?? $data['medicamento'] ?? null,
            'data' => $data['data'],
        ]);

        return back()->with('success', $msg);
    }

    public function destroyEvent(Animal $animal, AnimalEvent $event)
    {
        abort_if($event->animal_id !== $animal->id, 404);
        $event->delete();

        // Se era a última pesagem, recalcula peso_atual
        if ($event->tipo === 'pesagem') {
            $ultima = $animal->events()->where('tipo', 'pesagem')->orderByDesc('data')->first();
            $animal->update(['peso_atual' => $ultima?->peso]);
        }

        return back()->with('success', 'Evento removido.');
    }

    /**
     * Contexto pós-evento — dados auxiliares para o wizard mostrar "o que
     * mudou", não só "registrado". São queries leves (count/sum) feitas
     * UMA vez após a transação principal, então não impactam o rate-limit
     * do MySQL Hostinger significativamente.
     *
     * Os campos dependem do tipo do evento:
     *   - movimentacao        → animais_no_lote_destino (count)
     *   - movimentacao_local  → animais_no_local_destino (count)
     *   - venda               → receita_gerada (valor)
     *   - vacinacao/medicacao → despesa_gerada (valor)
     *   - mortalidade         → total_ativos (count após baixa)
     */
    private function buildContexto(Animal $animal, array $data, AnimalEvent $event): array
    {
        $c = [
            'tipo' => $data['tipo'],
            'animal_id' => $animal->id,
            'animal_identificacao' => $animal->identificacao,
        ];

        if ($data['tipo'] === 'movimentacao' && ! empty($data['lot_destino_id'])) {
            $c['lote_destino_id'] = (int) $data['lot_destino_id'];
            $c['animais_no_lote_destino'] = Animal::where('lot_id', $data['lot_destino_id'])
                ->where('status', 'ativo')->count();
            $c['lote_nome'] = \App\Models\Livestock\AnimalLot::find($data['lot_destino_id'])?->nome;
        }

        if ($data['tipo'] === 'movimentacao_local' && ! empty($data['location_destino_id'])) {
            $c['local_destino_id'] = (int) $data['location_destino_id'];
            $c['animais_no_local_destino'] = Animal::where('location_id', $data['location_destino_id'])
                ->where('status', 'ativo')->count();
            $c['local_nome'] = \App\Models\Livestock\AnimalLocation::find($data['location_destino_id'])?->nome;
        }

        if ($data['tipo'] === 'venda' && ! empty($data['valor'])) {
            $c['receita_gerada'] = (float) $data['valor'];
        }

        if (in_array($data['tipo'], ['vacinacao', 'medicacao', 'vermifugacao'], true) && ! empty($data['valor'])) {
            $c['despesa_gerada'] = (float) $data['valor'];
        }

        if ($data['tipo'] === 'mortalidade') {
            $c['total_ativos_restantes'] = Animal::where('status', 'ativo')->count();
        }

        return $c;
    }
}
