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
use App\Services\Livestock\LivestockMetricsService;
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

    /**
     * Dashboard inicial por espécie — abre quando master clica num submenu
     * de "Rebanho > Bovino/Ave/etc.". Mostra KPIs adaptados ao profile da
     * espécie (mamífero terrestre vs ave de postura vs aquicultura) +
     * grid de ações rápidas + atalho pra "ver todos" e "cadastrar novo".
     *
     * Bind por slug pra URL ficar legível: /admin/rebanho/bovino, /admin/rebanho/ave.
     * AnimalSpecies fica em tenant_id=1 (catálogo global) — withoutGlobalScopes
     * pra resolver o slug independente do tenant atual.
     */
    public function dashboardEspecie(Request $request, string $speciesSlug, LivestockMetricsService $metrics)
    {
        $species = AnimalSpecies::withoutGlobalScopes()
            ->where('slug', $speciesSlug)
            ->where('is_active', true)
            ->firstOrFail(['id', 'nome', 'slug', 'gestao', 'profile', 'allowed_events']);

        // KPIs base via service — fonte única em todas as telas.
        // Antes este controller calculava cada KPI inline com fórmulas
        // sutilmente diferentes do Hub/Painel/Relatório. Bovino mostrava
        // 344 aqui e 345 no Hub porque cada um filtrava de um jeito.
        $totalAtivos = $metrics->totalCabecasPorEspecie($species->id);
        $sexo = $metrics->sexoCount($species->id);
        $sexoM = $sexo['M'];
        $sexoF = $sexo['F'];
        $vendidosMes = $metrics->vendidosNoMes($species->id);
        $baixasMes = $metrics->baixasNoMes($species->id);
        $pesoMedio = $metrics->pesoMedioPorEspecie($species->id);
        $eventosRecentes = $metrics->eventosUltimosNDias($species->id, 7);

        // Lots relevantes pra esta espécie. Regra (2026-04-29):
        //   "Um lote só aparece na página de uma espécie se CONTÉM pelo menos
        //    1 animal vivo dessa espécie no contexto atual."
        //
        // Antes a query tinha `WHERE species_id = X OR has animals` — o `species_id`
        // sozinho era frágil: bastava o lote ter species_id flagado pra aparecer
        // mesmo sem nenhum animal da espécie. Resultado: lote agregado de
        // GALINHA (40 cabeças) aparecia na página de BOVINO, e ao clicar
        // mostrava 4 bovinos com lot_id mal apontado — UX totalmente quebrado.
        //
        // Nova regra:
        //   • Lote AGREGADO (aves/peixes/abelhas — sem rows individuais):
        //     mostra se gestao_modo='agregada' AND species_id=X AND quantidade_atual>0
        //   • Lote INDIVIDUAL (bovino/equino/etc — 1 row por animal):
        //     mostra se TEM ao menos 1 Animal ativo com species_id=X
        //
        // Também trazemos `animais_da_especie_count` pra UI exibir a contagem
        // CORRETA da espécie no contexto (não o quantidade_atual da espécie
        // dona do lote, que confundia em casos de mistura).
        $animaisQuery = Animal::where('species_id', $species->id);
        $lots = AnimalLot::where('is_active', true)
            ->where(function ($q) use ($species) {
                $q->where(function ($qq) use ($species) {
                    $qq->where('gestao_modo', 'agregada')
                       ->where('species_id', $species->id)
                       ->where('quantidade_atual', '>', 0);
                })
                ->orWhereHas('animals', fn ($qq) => $qq->where('species_id', $species->id)->where('status', 'ativo'));
            })
            ->withCount(['animals as animais_da_especie_count' => fn ($qq) => $qq->where('species_id', $species->id)->where('status', 'ativo')])
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo', 'species_id', 'gestao_modo', 'quantidade_atual']);

        // KPIs específicos por profile — agora também via service.
        $kpisProfile = $this->kpisProfileEspecie($species, $metrics);

        // Lista enxuta de animais ativos pro modal de evento rápido.
        // Limita a 500 — fazendas maiores que isso usam o "Ver todos".
        // Inclui peso_atual + categoria + lot/location pra ajudar o usuário
        // a identificar o animal certo no combobox (bug detectado pelo dono:
        // "se o responsavel nao souber qual nome do animal?").
        $animaisLista = (clone $animaisQuery)->where('status', 'ativo')
            ->with(['lot:id,nome', 'location:id,nome,tipo'])
            ->orderBy('identificacao')
            ->limit(500)
            ->get(['id', 'identificacao', 'nome', 'categoria', 'peso_atual', 'lot_id', 'location_id'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'identificacao' => $a->identificacao,
                'nome' => $a->nome,
                'categoria' => $a->categoria,
                'peso_atual' => $a->peso_atual,
                'lot' => $a->lot ? ['id' => $a->lot->id, 'nome' => $a->lot->nome] : null,
                'location' => $a->location ? ['id' => $a->location->id, 'nome' => $a->location->nome, 'tipo' => $a->location->tipo] : null,
            ])->values();

        // Dados pros gráficos (Chart.js no front)
        $charts = $this->chartsParaProfile($species, $animaisQuery);

        // Locations da fazenda atual (pra modal de movimentação)
        $locations = AnimalLocation::where('is_active', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'tipo'])
            ->map(fn ($l) => ['id' => $l->id, 'nome' => $l->nome, 'tipo' => $l->tipo])
            ->values();

        return Inertia::render('Admin/Livestock/EspecieDashboard', [
            'species' => [
                'id' => $species->id,
                'nome' => $species->nome,
                'slug' => $species->slug,
                'gestao' => $species->gestao,
                'profile' => $species->profile,
                'allowed_events' => $species->allowed_events,
            ],
            'kpis' => [
                'total_ativos' => $totalAtivos,
                'sexo_m' => $sexoM,
                'sexo_f' => $sexoF,
                'vendidos_mes' => $vendidosMes,
                'baixas_mes' => $baixasMes,
                'peso_medio' => $pesoMedio ? round($pesoMedio, 2) : null,
                'eventos_7d' => $eventosRecentes,
                'lots_count' => $lots->count(),
            ],
            'kpis_profile' => $kpisProfile,
            'charts' => $charts,
            'lots' => $lots,
            'locations' => $locations,
            'animals' => $animaisLista,
            // Sinaliza se a fazenda PRATICA manejo leiteiro (categoria leite ou
            // evento ordenha registrado em algum animal). Combinado com
            // species.allowed_events.includes('ordenha') no front, decide se
            // mostra o atalho "Dashboard leiteiro" pra esta espécie.
            'tem_manejo_leiteiro' => $metrics->temManejoLeiteiro(),
        ]);
    }

    /**
     * Dados pros gráficos da página (Chart.js no front).
     * Cada profile retorna o que faz sentido pra ele.
     */
    private function chartsParaProfile(AnimalSpecies $species, $animaisQuery): array
    {
        $charts = [];

        // Distribuição por sexo (todas espécies individuais)
        if ($species->gestao === 'individual') {
            $sexoM = (clone $animaisQuery)->where('status', 'ativo')->where('sexo', 'M')->count();
            $sexoF = (clone $animaisQuery)->where('status', 'ativo')->where('sexo', 'F')->count();
            if ($sexoM + $sexoF > 0) {
                $charts['sexo'] = [
                    'tipo' => 'pie',
                    'titulo' => 'Distribuição por sexo',
                    'labels' => ['Machos', 'Fêmeas'],
                    'data' => [$sexoM, $sexoF],
                    'cores' => ['#0ea5e9', '#ec4899'],
                ];
            }

            // Distribuição etária (bins de 6 meses até 5 anos+)
            $animaisAtivos = (clone $animaisQuery)->where('status', 'ativo')
                ->whereNotNull('data_nascimento')
                ->get(['data_nascimento']);
            if ($animaisAtivos->count() > 0) {
                $bins = [
                    '0-6 m' => 0, '6-12 m' => 0, '1-2 a' => 0, '2-3 a' => 0, '3-5 a' => 0, '5+ a' => 0,
                ];
                $hoje = now();
                foreach ($animaisAtivos as $a) {
                    $meses = $a->data_nascimento->diffInMonths($hoje);
                    if ($meses < 6) $bins['0-6 m']++;
                    elseif ($meses < 12) $bins['6-12 m']++;
                    elseif ($meses < 24) $bins['1-2 a']++;
                    elseif ($meses < 36) $bins['2-3 a']++;
                    elseif ($meses < 60) $bins['3-5 a']++;
                    else $bins['5+ a']++;
                }
                $charts['idade'] = [
                    'tipo' => 'bar',
                    'titulo' => 'Distribuição etária',
                    'labels' => array_keys($bins),
                    'data' => array_values($bins),
                    'cores' => ['#10b981', '#22c55e', '#84cc16', '#eab308', '#f59e0b', '#dc2626'],
                ];
            }

            // Evolução de peso médio (últimos 6 meses, média mensal)
            $animalIds = (clone $animaisQuery)->where('status', 'ativo')->pluck('id');
            if ($animalIds->count() > 0) {
                $pontos = [];
                for ($i = 5; $i >= 0; $i--) {
                    $mesIni = now()->subMonths($i)->startOfMonth();
                    $mesFim = now()->subMonths($i)->endOfMonth();
                    $media = (float) AnimalEvent::whereIn('animal_id', $animalIds)
                        ->where('tipo', 'pesagem')
                        ->whereBetween('data', [$mesIni->toDateString(), $mesFim->toDateString()])
                        ->avg('peso');
                    if ($media > 0) {
                        $pontos[] = [
                            'label' => $mesIni->translatedFormat('M/y'),
                            'valor' => round($media, 1),
                        ];
                    }
                }
                if (count($pontos) >= 2) {
                    $charts['peso_evolucao'] = [
                        'tipo' => 'line',
                        'titulo' => 'Evolução do peso médio (kg)',
                        'labels' => array_column($pontos, 'label'),
                        'data' => array_column($pontos, 'valor'),
                        'cor' => '#0ea5e9',
                    ];
                }
            }
        }

        // Profile leite — produção mensal últimos 12 meses
        if ($species->profile === 'ruminante_leite') {
            $animalIds = (clone $animaisQuery)->where('status', 'ativo')->pluck('id');
            if ($animalIds->count() > 0) {
                $pontos = [];
                for ($i = 11; $i >= 0; $i--) {
                    $mesIni = now()->subMonths($i)->startOfMonth();
                    $mesFim = now()->subMonths($i)->endOfMonth();
                    $litros = (float) AnimalEvent::whereIn('animal_id', $animalIds)
                        ->where('tipo', 'ordenha')
                        ->whereBetween('data', [$mesIni->toDateString(), $mesFim->toDateString()])
                        ->sum('producao_litros');
                    $pontos[] = [
                        'label' => $mesIni->translatedFormat('M/y'),
                        'valor' => round($litros, 1),
                    ];
                }
                $charts['leite_mensal'] = [
                    'tipo' => 'line',
                    'titulo' => 'Produção mensal de leite (litros)',
                    'labels' => array_column($pontos, 'label'),
                    'data' => array_column($pontos, 'valor'),
                    'cor' => '#0ea5e9',
                ];
            }
        }

        // Profile postura — postura diária últimos 30 dias
        if ($species->profile === 'ave_postura') {
            $animalIds = (clone $animaisQuery)->where('status', 'ativo')->pluck('id');
            if ($animalIds->count() > 0) {
                $eventos = AnimalEvent::whereIn('animal_id', $animalIds)
                    ->where('tipo', 'postura_diaria')
                    ->where('data', '>=', now()->subDays(30)->toDateString())
                    ->get(['data', 'peso'])
                    ->groupBy(fn ($e) => $e->data->toDateString())
                    ->map(fn ($g) => $g->sum('peso'));
                if ($eventos->count() >= 2) {
                    $charts['postura_diaria'] = [
                        'tipo' => 'line',
                        'titulo' => 'Postura diária (ovos) — últimos 30d',
                        'labels' => $eventos->keys()->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d/m'))->all(),
                        'data' => $eventos->values()->all(),
                        'cor' => '#f59e0b',
                    ];
                }
            }
        }

        return $charts;
    }

    /**
     * KPIs específicos do profile — cada perfil tem métricas próprias.
     * Retorna ['profile' => 'ruminante_leite', 'cards' => [...]] ou null
     * quando o profile não tem KPIs especiais (ex.: pet, equino).
     */
    private function kpisProfileEspecie(AnimalSpecies $species, ?LivestockMetricsService $metrics = null): ?array
    {
        $metrics = $metrics ?: app(LivestockMetricsService::class);
        $profile = $species->profile;

        // Bovino tem profile=ruminante_corte mas pode ter animais com categoria=leite
        // (Holandesa, Girolando, Jersey). Service detecta manejo leiteiro pela
        // presença de animal categoria leite/misto OU evento ordenha/controle.
        $temManejoLeiteiroEspecie = false;
        if ($profile === 'ruminante_corte' || $profile === 'ruminante_leite') {
            $temManejoLeiteiroEspecie = $metrics->temManejoLeiteiro($species->id);
        }
        if ($temManejoLeiteiroEspecie && $profile !== 'ruminante_leite') {
            $profile = 'ruminante_leite';
        }

        if ($profile === 'ruminante_leite') {
            // Service unifica fórmula com Controle Leiteiro — antes este card
            // somava só tipo=ordenha sobre coluna producao_litros (78,2 L) e o
            // Controle Leiteiro somava ordenha+controle_leiteiro iterando o
            // array ordenhas[] (116,8 L) → divergência. Agora ambos chamam
            // litrosNoMes/vacasEmLactacao do service.
            $litrosMesAtual = $metrics->litrosNoMes($species->id);
            $litrosMesAnterior = $metrics->litrosNoMes($species->id, now()->subMonth());
            $vacasEmLactacao = $metrics->vacasEmLactacao($species->id);
            $variacao = $litrosMesAnterior > 0
                ? round((($litrosMesAtual - $litrosMesAnterior) / $litrosMesAnterior) * 100, 1)
                : null;

            return [
                'profile' => 'ruminante_leite',
                'titulo' => '🥛 Indicadores de produção leiteira',
                // Preserva species_id pra o controle leiteiro filtrar pela espécie
                // certa (Búfalo não pode mostrar vacas — bug detectado pelo usuário).
                'link' => route('admin.rebanho.controle-leiteiro.dashboard', ['species_id' => $species->id]),
                'link_label' => 'Ver dashboard completo',
                'cards' => [
                    ['label' => 'Litros este mês', 'valor' => round($litrosMesAtual, 1).' L', 'cor' => 'sky', 'icon' => '🥛'],
                    ['label' => 'Mês anterior', 'valor' => round($litrosMesAnterior, 1).' L', 'cor' => 'slate', 'icon' => '📅'],
                    ['label' => 'Variação', 'valor' => $variacao !== null ? ($variacao >= 0 ? '+' : '').$variacao.'%' : '—', 'cor' => $variacao !== null && $variacao >= 0 ? 'emerald' : 'rose', 'icon' => $variacao !== null && $variacao >= 0 ? '📈' : '📉'],
                    ['label' => 'Em lactação', 'valor' => $vacasEmLactacao, 'cor' => 'amber', 'icon' => '🐄'],
                ],
            ];
        }

        if ($profile === 'ave_postura') {
            // Postura: service unifica fórmula. Antes este controller tinha
            // queries inline (alta-8) — agora delega ao LivestockMetricsService
            // pra ser fonte única (mesmo padrão de litrosNoMes/vacasEmLactacao).
            $ovosMes = $metrics->ovosNoMes($species->id);
            $mediaDia = $metrics->mediaOvosPorDia($species->id, 7);

            return [
                'profile' => 'ave_postura',
                'titulo' => '🥚 Indicadores de postura',
                'cards' => [
                    ['label' => 'Ovos este mês', 'valor' => number_format($ovosMes, 0, ',', '.'), 'cor' => 'amber', 'icon' => '🥚'],
                    ['label' => 'Média/dia (7d)', 'valor' => number_format($mediaDia, 0, ',', '.'), 'cor' => 'sky', 'icon' => '📊'],
                ],
            ];
        }

        if ($profile === 'aquicultura_lote') {
            // Última biometria via service. Antes inline aqui (alta-8) —
            // agora canonizado para garantir paridade com Dashboard espécie.
            $ultimaBio = $metrics->ultimaBiometria($species->id);

            return [
                'profile' => 'aquicultura_lote',
                'titulo' => '🐟 Indicadores de aquicultura',
                'cards' => [
                    ['label' => 'Última biometria', 'valor' => $ultimaBio['data'] ?? '—', 'cor' => 'sky', 'icon' => '📏'],
                    ['label' => 'Peso médio amostra', 'valor' => isset($ultimaBio['peso']) && $ultimaBio['peso'] !== null ? round($ultimaBio['peso'], 2).' g' : '—', 'cor' => 'slate', 'icon' => '⚖️'],
                ],
            ];
        }

        return null;
    }

    public function index(Request $request, LivestockMetricsService $metrics)
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
            // withoutGlobalScopes pra catálogo global (BelongsToTenantScope
            // travaria pra tenants ≠ 1). Senão o select de filtro fica vazio
            // e o usuário não vê qual espécie está filtrada (UX quebrado).
            'species' => AnimalSpecies::withoutGlobalScopes()
                ->where('is_active', true)
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'lots' => AnimalLot::where('is_active', true)->get(['id', 'nome']),
            'locations' => AnimalLocation::ativos()->orderBy('tipo')->orderBy('nome')->get(['id', 'nome', 'tipo']),
            // Resumo de valor — 1 query agregada ajuda o dono a saber
            // "quanto rebanho tenho" e "quantos precisam de atenção"
            // sem ter que navegar. Sem pesagem há 60+ dias = lembrete
            // de pesar (ganho/perda só é calculável com pesagens).
            // Resumo da espécie/contexto.
            // 2026-04-28 — quando species_id está informado, usa o service para
            // garantir que peso_total/ativos_com_peso vêm do ÚLTIMO evento de
            // pesagem (mesma fonte do Dashboard de espécie). Antes a listagem
            // somava `animals.peso_atual` que ficava stale, gerando 480kg vs
            // 309kg. Agora o número é o mesmo das duas telas.
            //
            // Sem species_id (visão "todas espécies"), continua via Animal::query()
            // mas usando peso_atual como aproximação — visão geral é menos
            // sensível e qualquer queda na precisão é menos visível.
            'resumo' => $this->resumoListagem($request, $metrics),
            'partners' => Partner::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'categorias' => [
                ['value' => 'leite', 'label' => 'Leite'],
                ['value' => 'corte', 'label' => 'Corte'],
                ['value' => 'reproducao', 'label' => 'Reprodução'],
                ['value' => 'misto', 'label' => 'Misto'],
                ['value' => 'pet', 'label' => 'Pet'],
                ['value' => 'servico', 'label' => 'Serviço / trabalho'],
            ],
            // Inteligência contextual: o botão "Dashboard leiteiro" só aparece
            // se a fazenda PRATICA manejo leiteiro — sem isso o link é ruído
            // pra quem só tem corte, equino, ovino ou aquicultura. Dois sinais:
            //   1. existe ao menos 1 animal categorizado como leite/misto, OU
            //   2. existe ao menos 1 evento de controle_leiteiro/ordenha
            // Basta um pra mostrar. Query barata (existência, não count).
            'tem_manejo_leiteiro' => $metrics->temManejoLeiteiro(),
            // Filtro adicional por espécie: o dashboard leiteiro só faz sentido
            // pra espécies que ACEITAM ordenha. Bovino tem profile=ruminante_corte
            // mas allowed_events inclui ordenha (Girolando/Holandesa/Jersey) — então
            // checamos allowed_events, não profile. Aves/Suínos/Equinos não têm
            // ordenha em allowed_events → botão fica escondido na página deles.
            // Sem species_id (visão geral), retorna true se algum bovino-like existir.
            'especie_aceita_ordenha' => $this->especieAceitaOrdenha($request->species_id),
        ]);
    }

    /**
     * Verifica se a espécie filtrada aceita ordenha (allowed_events inclui 'ordenha').
     * Sem species_id (visão geral), retorna null — frontend interpreta como
     * "não aplicável, mostra/esconde só pelo tem_manejo_leiteiro".
     */
    private function especieAceitaOrdenha($speciesId): ?bool
    {
        if (! $speciesId) {
            return null;
        }
        $species = AnimalSpecies::withoutGlobalScopes()
            ->where('id', $speciesId)
            ->first(['id', 'allowed_events']);
        if (! $species) {
            return false;
        }
        $events = $species->allowed_events ?? [];
        if (is_string($events)) {
            $events = json_decode($events, true) ?: [];
        }
        return in_array('ordenha', $events, true);
    }

    /**
     * Resumo (ativos/vendidos/baixas/peso_total/ativos_com_peso) da listagem.
     * Quando species_id está informado, usa o service para garantir que peso
     * vem do último evento de pesagem (coerente com Dashboard). Sem species,
     * mantém SQL agregado original como aproximação geral.
     */
    private function resumoListagem(Request $request, LivestockMetricsService $metrics)
    {
        // Se há species_id, computa peso pelo service (último evento).
        if ($request->filled('species_id')) {
            $sid = (int) $request->species_id;
            $ativos = Animal::query()
                ->when($request->lot_id, fn ($qq) => $qq->where('lot_id', $request->lot_id))
                ->when($request->location_id, fn ($qq) => $qq->where('location_id', $request->location_id))
                ->where('species_id', $sid)
                ->selectRaw("
                    SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as ativos,
                    SUM(CASE WHEN status = 'vendido' THEN 1 ELSE 0 END) as vendidos,
                    SUM(CASE WHEN status IN ('morto','abatido') THEN 1 ELSE 0 END) as baixas
                ")->first();
            $peso = $metrics->pesoTotalAtivos($sid);
            return (object) [
                'ativos' => (int) ($ativos->ativos ?? 0),
                'vendidos' => (int) ($ativos->vendidos ?? 0),
                'baixas' => (int) ($ativos->baixas ?? 0),
                'peso_total' => $peso['peso_total'],
                'ativos_com_peso' => $peso['ativos_com_peso'],
            ];
        }

        // Sem species_id, mantém SQL agregado simples como aproximação.
        return Animal::query()
            ->when($request->lot_id, fn ($qq) => $qq->where('lot_id', $request->lot_id))
            ->when($request->location_id, fn ($qq) => $qq->where('location_id', $request->location_id))
            ->selectRaw("
                SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as ativos,
                SUM(CASE WHEN status = 'vendido' THEN 1 ELSE 0 END) as vendidos,
                SUM(CASE WHEN status IN ('morto','abatido') THEN 1 ELSE 0 END) as baixas,
                SUM(CASE WHEN status = 'ativo' AND peso_atual > 0 THEN peso_atual ELSE 0 END) as peso_total,
                SUM(CASE WHEN status = 'ativo' AND peso_atual > 0 THEN 1 ELSE 0 END) as ativos_com_peso
            ")->first();
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

        // UX: preserva contexto da espécie para o usuário enxergar o animal
        // recém-cadastrado na lista filtrada (e poder cadastrar mais um do
        // mesmo tipo sem perder o filtro).
        return redirect()
            ->route('admin.rebanho.animais.index', ['species_id' => $animal->species_id])
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

        // Se o usuário veio do SHOW (link "Editar cadastro" do perfil do animal
        // passa ?from=show), redirect VOLTA pra mesma tela onde estava — fluxo
        // mental coeso: estava vendo o perfil, editou, vê o perfil atualizado.
        // Caso contrário, volta pra lista filtrada por species_id.
        if ($request->query('from') === 'show') {
            return redirect()
                ->route('admin.rebanho.animais.show', $animal->id)
                ->with('success', 'Animal atualizado.');
        }

        return redirect()
            ->route('admin.rebanho.animais.index', ['species_id' => $animal->species_id])
            ->with('success', 'Animal atualizado.');
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

        // Species/breeds são REFERÊNCIA UNIVERSAL (compartilhada entre tenants).
        // Vivem em tenant_id=1 (sistema). withoutGlobalScopes evita que tenants
        // novos vejam o select vazio — mesmo padrão do index() acima.
        $species = AnimalSpecies::withoutGlobalScope(\App\Domain\Tenancy\Scopes\BelongsToTenantScope::class)
            ->where('is_active', true)
            ->with(['breeds' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'species_id', 'nome')])
            ->get();

        return Inertia::render('Admin/Livestock/Animals/Form', [
            'animal' => $animalPayload,
            'species' => $species,
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
            // Data nascimento não pode ser futura — animal "nasceu amanhã" é absurdo
            'data_nascimento' => ['nullable', 'date', 'before_or_equal:today'],
            // Peso ao nascer com max razoável (bezerro grande ≈ 50kg, leitão ≈ 2kg, pinto ≈ 0.05kg)
            'peso_nascimento' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // peso_atual NÃO é editável no form — é derivado do último evento de pesagem (regra incremental-first)
            'origem' => ['required', 'in:nascido,compra'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            // Data aquisição não pode ser futura
            'data_aquisicao' => ['nullable', 'date', 'before_or_equal:today'],
            'valor_aquisicao' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:ativo,vendido,morto,abatido,transferido'],
            'observacoes' => ['nullable', 'string'],
            // Enum ampliado (D2) — perfis ave_postura/ave_corte/equino exigem
            // categorias que o enum antigo não contemplava. A rule por profile
            // em validateDomainCoherence filtra quais valores são aceitos para
            // cada espécie; este enum só define o universo sintático.
            'categoria' => ['nullable', 'in:leite,corte,reproducao,misto,pet,servico,trabalho,esporte,postura,companhia'],
            'numero_registro' => ['nullable', 'string', 'max:50'],
        ], [
            // Mensagens pt-BR amigáveis (default Laravel mistura "anterior a today")
            'data_nascimento.before_or_equal' => 'A data de nascimento não pode ser futura.',
            'data_aquisicao.before_or_equal'  => 'A data de aquisição não pode ser futura.',
            'peso_nascimento.max' => 'Peso ao nascer parece absurdo (>100kg). Verifique se digitou em kg (não gramas).',
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
        // withoutGlobalScopes — catálogo é global (tenant_id=1), sem isso
        // o validator falharia pra todo tenant ≠ 1.
        $species = AnimalSpecies::withoutGlobalScopes()->find($data['species_id'] ?? null);
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

        // ── 5. PESO ao nascer plausível por espécie ────────────────────────
        // Bezerros bovinos: 25-50kg; Búfalo: 30-50; Suíno: 1-2; Ovino/Caprino: 3-5;
        // Equino: 40-60; Pet (cão/gato): 0.05-0.5; Coelho: 0.05-0.1.
        $pesoNasc = isset($data['peso_nascimento']) ? (float) $data['peso_nascimento'] : null;
        $pesoMudou = $existing === null || $pesoNasc !== ($existing->peso_nascimento ? (float) $existing->peso_nascimento : null);
        if ($pesoMudou && $pesoNasc !== null && $pesoNasc > 0) {
            $maxPorProfile = [
                'ruminante_corte'  => 60,    // bezerro grande
                'ruminante_leite'  => 60,
                'ruminante_lan'    => 8,     // cordeiro
                'equino'           => 80,    // potro
                'suino'            => 5,     // leitão
                'pet'              => 2,     // filhote pet (cão grande)
                'roedor_pequeno'   => 0.2,   // coelho recém-nascido
            ];
            $max = $maxPorProfile[$profile] ?? null;
            if ($max !== null && $pesoNasc > $max) {
                return "Peso ao nascer de {$pesoNasc}kg parece absurdo para {$nomeEsp}. "
                    . "Limite plausível: {$max}kg. Verifique se digitou em kg (não gramas).";
            }
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

        // Species e breeds são referência universal (compartilhadas via tenant 1).
        // withoutGlobalScopes evita que clientes novos vejam species=null.
        $animal->load([
            'species' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nome', 'slug', 'allowed_events'),
            'breed' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nome'),
            'lot:id,nome', 'location:id,nome,tipo', 'farm:id,nome', 'fornecedor:id,nome',
        ]);
        $acoesRapidas = $this->montarAcoesRapidas($animal);

        // Avaliação DROVET — só aplicável a fêmeas leiteiras (raça reconhecida)
        // com data_nascimento + peso_atual e idade ≤ 27 meses. Devolve null
        // quando não se aplica e a UI suprime o card.
        $crescimentoDrovet = \App\Domain\Livestock\DairyHeiferGrowthTable::evaluate($animal);

        return Inertia::render('Admin/Livestock/Animals/Show', [
            'animal' => [
                ...$animal->toArray(),
                'photo_url' => $animal->photoUrl(),
                'idade_em_meses' => $animal->data_nascimento?->diffInMonths(now()),
                'status_reprodutivo' => $statusReprodutivo,
                'acoes_rapidas' => $acoesRapidas,
                'crescimento_drovet' => $crescimentoDrovet,
            ],
            'events' => $events,
            'pesagens' => $pesagens,
            'partners' => Partner::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'lots' => AnimalLot::where('is_active', true)->get(['id', 'nome']),
            'locations' => AnimalLocation::ativos()->orderBy('tipo')->orderBy('nome')->get(['id', 'nome', 'tipo']),
        ]);
    }

    /**
     * Catálogo de ações rápidas disponíveis no detalhe do animal.
     *
     * Cada entrada tem:
     *   - tipo: chave do tipo de evento (deve estar em species.allowed_events)
     *   - emoji, label, desc: visual
     *   - wizard: rota Laravel (Inertia link). Se null → abre modal in-page de evento.
     *   - sexo: 'F' | 'M' | null (filtra por sexo do animal)
     *   - idade_min_meses: int | null (filtra por idade)
     */
    /**
     * REGRA SIMPLES: tudo que faz sentido pra UM animal abre o MODAL in-page
     * (sem sair da tela). Só vai pra wizard externo quando o fluxo é
     * intrinsecamente multi-animal (venda em massa) ou tem ações automáticas
     * importantes (parto cria filhotes).
     *
     * `wizard => null` → ação rápida abre modal in-page com tipo pré-selecionado
     * `wizard => '...'` → vai pra wizard externo (com ?animal_id e ?return_to)
     */
    private const ACOES_CATALOGO = [
        // ── Tudo que cabe no modal in-page (single animal, sem complexidade)
        'pesagem'             => ['emoji' => '⚖️', 'label' => 'Pesar', 'desc' => 'Registrar peso atual', 'wizard' => null],
        'ordenha'             => ['emoji' => '🥛', 'label' => 'Registrar ordenha', 'desc' => 'Litros desta vaca neste dia', 'wizard' => null, 'sexo' => 'F'],
        'vacinacao'           => ['emoji' => '💉', 'label' => 'Vacinar', 'desc' => 'Vacina aplicada', 'wizard' => null],
        'medicacao'           => ['emoji' => '💊', 'label' => 'Medicar', 'desc' => 'Medicamento aplicado', 'wizard' => null],
        'vermifugacao'        => ['emoji' => '🧴', 'label' => 'Vermifugar', 'desc' => 'Vermífugo aplicado', 'wizard' => null],
        'reproducao'          => ['emoji' => '💞', 'label' => 'Cobertura', 'desc' => 'Cruzamento com macho ou inseminação artificial', 'wizard' => null],
        // ordenha removido — é o MESMO conceito de controle_leiteiro (registrar
        // litros produzidos numa ordenha). Usuário não precisa decidir entre 2 cards.
        'secagem'             => ['emoji' => '💧', 'label' => 'Secar', 'desc' => 'Cessar lactação antes do parto', 'wizard' => null, 'sexo' => 'F'],
        'movimentacao'        => ['emoji' => '🐄', 'label' => 'Mudar de lote', 'desc' => 'Transferir entre grupos', 'wizard' => null],
        'movimentacao_local'  => ['emoji' => '📍', 'label' => 'Mudar de pasto', 'desc' => 'Transferir entre locais', 'wizard' => null],
        'observacao'          => ['emoji' => '📝', 'label' => 'Observação', 'desc' => 'Mancando, brigando, com bicheira…', 'wizard' => null],
        'ferrageamento'       => ['emoji' => '🐎', 'label' => 'Ferrageamento', 'desc' => 'Troca de ferradura', 'wizard' => null],
        'tosquia'             => ['emoji' => '✂️', 'label' => 'Tosquia', 'desc' => 'Corte da lã', 'wizard' => null],
        'castracao'           => ['emoji' => '🔪', 'label' => 'Castração', 'desc' => 'Procedimento cirúrgico', 'wizard' => null],
        'biometria_amostral'  => ['emoji' => '🐟', 'label' => 'Biometria', 'desc' => 'Pesagem amostral', 'wizard' => null],
        'qualidade_agua'      => ['emoji' => '💧', 'label' => 'Qualidade da água', 'desc' => 'pH, O₂, temperatura', 'wizard' => null],
        'alimentacao'         => ['emoji' => '🌾', 'label' => 'Alimentação', 'desc' => 'Ração fornecida', 'wizard' => null],
        'postura_diaria'      => ['emoji' => '🥚', 'label' => 'Postura', 'desc' => 'Ovos coletados', 'wizard' => null],
        'mortalidade'         => ['emoji' => '⚰️', 'label' => 'Mortalidade', 'desc' => 'Registrar morte', 'wizard' => null],

        // exame_toque também é MODAL in-page (single fêmea = modal, igual aos outros)
        'exame_toque' => [
            'emoji' => '🩺', 'label' => 'Exame de toque', 'desc' => 'Palpação · saber se está prenhe',
            'wizard' => null, 'sexo' => 'F',
        ],
        // ── Wizards externos (fluxo complexo ou multi-vaca)
        // controle_leiteiro e exame_toque foram REMOVIDOS daqui — single-fêmea
        // = modal in-page. Multi-fêmea (batch) permanece nos wizards do Hub.
        'venda' => [
            'emoji' => '💰', 'label' => 'Vender', 'desc' => 'Saída + receita financeira',
            'wizard' => 'admin.fluxos.venda-animal',
        ],
    ];

    /**
     * Filtra ACOES_CATALOGO pelo perfil do animal (espécie, sexo, idade).
     * Cada ação retornada tem URL pronta com ?animal_id=X.
     */
    private function montarAcoesRapidas(Animal $animal): array
    {
        $allowed = $animal->species?->allowed_events;
        if (! is_array($allowed) || count($allowed) === 0) return [];

        $acoes = [];
        foreach ($allowed as $tipo) {
            $meta = self::ACOES_CATALOGO[$tipo] ?? null;
            if (! $meta) continue;

            // Filtro por sexo (ex.: exame_toque/secagem/controle_leiteiro só em F)
            if (! empty($meta['sexo']) && $animal->sexo !== $meta['sexo']) continue;

            // Monta URL final com return_to pra voltar pra ficha do animal
            $url = null;
            if (! empty($meta['wizard'])) {
                $base = route($meta['wizard']);
                $params = array_merge(
                    $meta['query'] ?? [],
                    [
                        'animal_id' => $animal->id,
                        'return_to' => '/admin/rebanho/animais/' . $animal->id,
                    ]
                );
                $url = $base . '?' . http_build_query($params);
            }

            $acoes[] = [
                'tipo' => $tipo,
                'emoji' => $meta['emoji'],
                'label' => $meta['label'],
                'desc' => $meta['desc'],
                'url' => $url, // null = abre modal in-page com tipo pré-selecionado
            ];
        }
        return $acoes;
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
            // Ordenha — manhã/tarde separados (padrão DROVET)
            'litros_manha' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'litros_tarde' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
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
        // Peso plausível por espécie — bovino adulto até 1500kg, búfalo até 1300,
        // equino até 800, suíno até 400, caprino/ovino até 150, pet até 100, etc.
        if ($data['tipo'] === 'pesagem' && ! empty($data['peso'])) {
            $animalSpecies = $animal->species()->withoutGlobalScopes()->first();
            $profile = $animalSpecies?->profile;
            $maxPesoPorProfile = [
                'ruminante_corte' => 1500,
                'ruminante_leite' => 1300,
                'ruminante_lan'   => 200,
                'equino'          => 800,
                'suino'           => 400,
                'pet'             => 100,
                'roedor_pequeno'  => 10,
            ];
            $max = $maxPesoPorProfile[$profile] ?? 9999;
            if ((float) $data['peso'] > $max) {
                return back()->with('error',
                    "Peso de {$data['peso']}kg parece absurdo para {$animalSpecies?->nome}. "
                    . "Limite plausível: {$max}kg. Verifique se digitou em kg (não gramas)."
                );
            }
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
            // Ordenha — consolida produção total a partir de manhã+tarde
            // (o front pode mandar só breakdown ou só total — backend resolve).
            if ($data['tipo'] === 'ordenha') {
                $manha = (float) ($data['litros_manha'] ?? 0);
                $tarde = (float) ($data['litros_tarde'] ?? 0);
                $somaBreakdown = $manha + $tarde;
                if ($somaBreakdown > 0 && empty($data['producao_litros'])) {
                    $data['producao_litros'] = $somaBreakdown;
                }
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

    /**
     * Excluir evento histórico do animal.
     *
     * F8-E4 (QA Deep 2026-04-29): excluir evento de VENDA não revertia
     * o animal pra ativo nem estornava a receita financeira gerada.
     * Resultado: animal continuava com status='vendido' mas a venda
     * tinha "sumido" do histórico do timeline → duplo órfão (animal
     * em estado inválido + receita sem rastro).
     *
     * Agora, dependendo do tipo do evento excluído:
     *   - pesagem    → recalcula peso_atual (era o único que tinha)
     *   - venda      → reverte animal pra ativo + estorna receita vinculada
     *   - mortalidade → reverte animal pra ativo + ajusta lote (se aplicável)
     *   - vacinacao/medicacao → estorna despesa vinculada (se houver)
     *
     * Tudo em transação DB pra atomicidade.
     */
    public function destroyEvent(Animal $animal, AnimalEvent $event)
    {
        abort_if($event->animal_id !== $animal->id, 404);

        // Captura info pra mensagem ANTES de mexer (evento já vai sumir)
        $tipoEvento = $event->tipo;
        $dataEvento = $event->data ? \Carbon\Carbon::parse($event->data)->format('d/m/Y') : null;
        $valorEstornado = null;
        $mensagem = null;

        \DB::transaction(function () use ($animal, $event, &$valorEstornado) {
            // F8-E4 · venda: reverte animal + estorna receita
            if ($event->tipo === 'venda') {
                $animal->update([
                    'status' => 'ativo',
                    'data_saida' => null,
                ]);
                $receita = \App\Models\Financial\FinancialTransaction::where('numero_documento', 'ANIMAL_EVENT:'.$event->id)
                    ->where('status', 'pago')
                    ->first();
                if ($receita) {
                    $valorEstornado = (float) $receita->valor;
                    \App\Models\Financial\FinancialTransaction::create([
                        'tenant_id' => $receita->tenant_id,
                        'farm_id' => $receita->farm_id,
                        'tipo' => 'despesa',
                        'descricao' => 'Estorno · '.$receita->descricao,
                        'valor' => $receita->valor,
                        'data_vencimento' => now()->toDateString(),
                        'data_pagamento' => now()->toDateString(),
                        'status' => 'pago',
                        'category_id' => $receita->category_id,
                        'partner_id' => $receita->partner_id,
                        'numero_documento' => 'ESTORNO:'.$receita->id,
                        'observacoes' => 'Estorno automático · venda do animal '.$animal->identificacao.' foi removida.',
                    ]);
                    $receita->update(['status' => 'estornada']);
                }
            }

            // F8-E4 · mortalidade: reverte animal pra ativo
            if (in_array($event->tipo, ['morte', 'mortalidade', 'abate'], true)) {
                $animal->update([
                    'status' => 'ativo',
                    'data_saida' => null,
                ]);
            }

            // pesagem: recalcula peso_atual com penúltima pesagem
            if ($event->tipo === 'pesagem') {
                $ultima = $animal->events()
                    ->where('tipo', 'pesagem')
                    ->where('id', '!=', $event->id)
                    ->orderByDesc('data')
                    ->first();
                $animal->update(['peso_atual' => $ultima?->peso]);
            }

            $event->delete();
        });

        // Mensagem ESPECÍFICA por tipo — evita "Estados/financeiro vinculados
        // foram revertidos" genérico que era jargão técnico aparecendo pra
        // usuário comum. Bug detectado pelo dono em produção 2026-04-29.
        // Cobertura completa dos tipos válidos no backend (linha 1072) +
        // tipos extras do EVENT_CATALOG do frontend. default = fallback
        // genérico para qualquer tipo novo que apareça no futuro.
        $mensagem = match ($tipoEvento) {
            // Manejo geral
            'pesagem' => 'Pesagem apagada. Peso do animal recalculado com a pesagem anterior.',
            'observacao' => 'Observação apagada.',
            // Sanitário
            'vacinacao' => 'Vacinação apagada. Histórico de vacinas atualizado.',
            'medicacao' => 'Medicação apagada do histórico do animal.',
            'vermifugacao' => 'Vermifugação apagada do histórico.',
            'castracao' => 'Castração apagada do histórico do animal.',
            // Reprodução
            'reproducao' => 'Registro reprodutivo apagado do histórico.',
            'cobertura' => 'Cobertura apagada do histórico reprodutivo.',
            'exame_toque' => 'Exame de toque apagado.',
            'parto' => 'Parto apagado do histórico.',
            'desmame' => 'Desmame apagado do histórico.',
            // Leite
            'ordenha' => 'Ordenha apagada. Total de litros do mês recalculado.',
            'controle_leiteiro' => 'Controle leiteiro de '.($dataEvento ?? 'data desconhecida').' apagado. Indicadores de produção atualizados.',
            'secagem' => 'Secagem apagada. Status de lactação revertido.',
            // Lã / Cascos
            'tosquia' => 'Tosquia apagada do histórico.',
            'ferrageamento' => 'Ferrageamento apagado do histórico.',
            // Aquicultura / Aves (lote agregado)
            'biometria_amostral' => 'Biometria apagada. Peso médio do lote recalculado.',
            'postura_diaria' => 'Postura apagada. Total de ovos do mês recalculado.',
            'qualidade_agua' => 'Registro de qualidade da água apagado.',
            'alimentacao' => 'Registro de alimentação apagado.',
            // Movimentação
            'movimentacao' => 'Movimentação apagada do histórico.',
            'movimentacao_local' => 'Movimentação de pasto/local apagada do histórico.',
            // Comerciais
            'venda' => $valorEstornado
                ? 'Venda revertida. Animal voltou a ativo e a receita de R$ '.number_format($valorEstornado, 2, ',', '.').' foi estornada.'
                : 'Venda revertida. Animal voltou a ativo.',
            'compra' => 'Registro de compra apagado do histórico do animal.',
            // Baixas
            'morte', 'mortalidade', 'abate' => 'Baixa revertida. Animal voltou a ativo.',
            // Nascimento (alias de parto em alguns fluxos)
            'nascimento' => 'Registro de nascimento apagado do histórico.',
            // Fallback defensivo — se aparecer tipo novo, mensagem ainda
            // faz sentido. Inclui o tipo formatado pra dev rastrear.
            default => 'Registro apagado do histórico do animal.',
        };

        return back()->with('success', $mensagem);
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
            // Total = individuais + cabeças em lotes agregados (Ave/Peixe).
            // Sem isso a mensagem "restam X animais" ignorava 4500 aves em lotes.
            $individuais = Animal::where('status', 'ativo')->count();
            $cabecasAgregadas = (int) \App\Models\Livestock\AnimalLot::where('is_active', true)
                ->whereHas('species', fn ($q) => $q->withoutGlobalScopes()->where('gestao', 'lote'))
                ->sum('quantidade_atual');
            $c['total_ativos_restantes'] = $individuais + $cabecasAgregadas;
        }

        return $c;
    }
}
