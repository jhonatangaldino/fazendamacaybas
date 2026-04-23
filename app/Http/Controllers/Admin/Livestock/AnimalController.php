<?php

namespace App\Http\Controllers\Admin\Livestock;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Livestock\Animal;
use App\Models\Livestock\AnimalBreed;
use App\Models\Livestock\AnimalEvent;
use App\Models\Livestock\AnimalLot;
use App\Models\Livestock\AnimalSpecies;
use App\Models\Partner;
use Illuminate\Http\Request;
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
        $q = Animal::with(['species:id,nome,gestao,profile,allowed_events', 'breed:id,nome', 'lot:id,nome', 'farm:id,nome'])
            ->when($request->search, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('identificacao', 'like', "%{$request->search}%")
                ->orWhere('nome', 'like', "%{$request->search}%")))
            ->when($request->species_id, fn ($qq) => $qq->where('species_id', $request->species_id))
            ->when($request->lot_id, fn ($qq) => $qq->where('lot_id', $request->lot_id))
            ->when($request->status, fn ($qq) => $qq->where('status', $request->status))
            ->when($request->categoria, fn ($qq) => $qq->where('categoria', $request->categoria))
            ->orderBy('identificacao');

        return Inertia::render('Admin/Livestock/Animals/Index', [
            'animals' => $q->paginate(25)->withQueryString()->through(fn (Animal $a) => [
                'id' => $a->id,
                'identificacao' => $a->identificacao,
                'nome' => $a->nome,
                'sexo' => $a->sexo,
                'categoria' => $a->categoria,
                'status' => $a->status,
                'data_nascimento' => $a->data_nascimento,
                'peso_atual' => $a->peso_atual,
                'photo_url' => $a->photoUrl(),
                'species' => $a->species ? [
                    'id' => $a->species->id,
                    'nome' => $a->species->nome,
                    'gestao' => $a->species->gestao,
                    'profile' => $a->species->profile,
                    'allowed_events' => $a->species->allowed_events,
                ] : null,
                'breed' => $a->breed ? ['nome' => $a->breed->nome] : null,
                'lot' => $a->lot ? ['nome' => $a->lot->nome] : null,
            ]),
            'filters' => $request->only(['search', 'species_id', 'lot_id', 'status', 'categoria']),
            'species' => AnimalSpecies::where('is_active', true)->get(['id', 'nome']),
            'lots' => AnimalLot::where('is_active', true)->get(['id', 'nome']),
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

        Animal::create($data);

        return redirect()->route('admin.rebanho.animais.index')->with('success', 'Animal cadastrado.');
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
            ->with(['partner:id,nome', 'lotOrigem:id,nome', 'lotDestino:id,nome', 'creator:id,name'])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        // Série para gráfico de evolução de peso (ordem cronológica)
        $pesagens = $events->where('tipo', 'pesagem')
            ->sortBy('data')
            ->values()
            ->map(fn ($e) => [
                'data' => $e->data?->toDateString(),
                'peso' => (float) $e->peso,
            ]);

        return Inertia::render('Admin/Livestock/Animals/Show', [
            'animal' => [
                ...$animal->load(['species:id,nome', 'breed:id,nome', 'lot:id,nome', 'farm:id,nome', 'fornecedor:id,nome'])->toArray(),
                'photo_url' => $animal->photoUrl(),
                'idade_em_meses' => $animal->data_nascimento?->diffInMonths(now()),
            ],
            'events' => $events,
            'pesagens' => $pesagens,
            'partners' => Partner::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'lots' => AnimalLot::where('is_active', true)->get(['id', 'nome']),
        ]);
    }

    /**
     * Registra um evento no animal (pesagem, vacinação, medicação, reprodução, etc.).
     * Valor e partner opcionais — quando informados, alimentam o ecossistema financeiro.
     */
    public function storeEvent(Request $request, Animal $animal)
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:pesagem,vacinacao,medicacao,vermifugacao,reproducao,movimentacao,observacao,ordenha,tosquia,ferrageamento,castracao,postura_diaria,biometria_amostral,qualidade_agua,alimentacao,mortalidade,venda,compra,secagem'],
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
            'observacoes' => ['nullable', 'string'],
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
        // passa sem validação — não quebra dados existentes nem tenants que
        // ainda não rodaram o seed atualizado.
        $allowed = $animal->species?->allowed_events;
        if (is_array($allowed) && count($allowed) > 0 && ! in_array($data['tipo'], $allowed, true)) {
            return back()->with('error',
                "O evento \"{$data['tipo']}\" não é aplicável a " . ($animal->species->nome ?? 'esta espécie') . '. '
                . 'Tipos válidos para esta espécie: ' . implode(', ', $allowed) . '.'
            );
        }

        $event = $animal->events()->create([
            ...$data,
            'lot_id' => $animal->lot_id,
            'created_by' => $request->user()?->id,
        ]);

        // Pesagem atualiza o peso_atual (cache desnormalizado no Animal)
        if ($data['tipo'] === 'pesagem') {
            $animal->update(['peso_atual' => $data['peso']]);
        }

        // Movimentação altera lote atual
        if ($data['tipo'] === 'movimentacao' && ! empty($data['lot_destino_id'])) {
            $animal->update(['lot_id' => $data['lot_destino_id']]);
        }

        // Venda muda status do animal e registra saída
        if ($data['tipo'] === 'venda') {
            $animal->update(['status' => 'vendido', 'data_saida' => $data['data']]);
        }
        // Mortalidade/abate encerram o ciclo do animal
        if ($data['tipo'] === 'mortalidade') {
            $animal->update(['status' => 'morto', 'data_saida' => $data['data']]);
        }

        return back()->with('success', match ($data['tipo']) {
            'pesagem' => 'Pesagem registrada.',
            'vacinacao' => 'Vacinação registrada.',
            'medicacao' => 'Medicação registrada.',
            'vermifugacao' => 'Vermifugação registrada.',
            'reproducao' => 'Evento reprodutivo registrado.',
            'movimentacao' => 'Movimentação registrada.',
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
}
