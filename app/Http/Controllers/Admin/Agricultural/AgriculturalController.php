<?php

namespace App\Http\Controllers\Admin\Agricultural;

use App\Domain\Integration\Services\FieldApplicationToStockMovementService;
use App\Domain\Integration\Services\HarvestToRevenueService;
use App\Http\Controllers\Controller;
use App\Models\Agricultural\Crop;
use App\Models\Agricultural\Field;
use App\Models\Agricultural\FieldApplication;
use App\Models\Agricultural\Harvest;
use App\Models\Agricultural\Planting;
use App\Models\Agricultural\Season;
use App\Models\Farm;
use App\Models\Stock\StockItem;
use App\Services\Metrics\AgricolaMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AgriculturalController extends Controller
{
    public function index(AgricolaMetrics $metrics)
    {
        // KPIs canônicos via service — alinha Hub Agrícola com Painel/Listas.
        // Antes: cada controller calculava sua versão (Hub vs Lista divergiam
        // em "área total" porque Lista só somava ativos e Hub somava todos).
        $resumo = $metrics->resumoHub();

        $fields = Field::where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'codigo', 'area_ha']);
        $plantings = Planting::with(['field:id,nome', 'crop:id,nome'])
            ->where('status', 'em_andamento')
            ->orderByDesc('data_plantio')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'field_nome' => $p->field?->nome,
                'crop_nome' => $p->crop?->nome,
                'data_plantio' => $p->data_plantio,
                'area_plantada' => (float) $p->area_plantada,
                'status' => $p->status,
            ]);
        $seasons = Season::where('is_active', true)
            ->orderByDesc('data_inicio')
            ->get(['id', 'nome', 'data_inicio', 'data_fim']);

        return Inertia::render('Admin/Agricultural/Index', [
            'totals' => [
                'fields'           => $resumo['total_talhoes'],
                'plantings_ativos' => $resumo['plantios_ativos'],
                'seasons'          => $resumo['safras_andamento'],
                'area_total'       => $resumo['total_hectares'],
                'aplicacoes_mes'   => $resumo['aplicacoes_mes'],
            ],
            // Listas leves pra alimentar os drawers de drill-down (clique no KPI)
            'drillFields' => $fields,
            'drillPlantings' => $plantings,
            'drillSeasons' => $seasons,
        ]);
    }

    // ============ FIELDS (Talhões) ============

    public function fields(Request $request)
    {
        $q = Field::with('farm:id,nome')
            ->when($request->search, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('nome', 'like', "%{$request->search}%")
                ->orWhere('codigo', 'like', "%{$request->search}%")))
            ->when($request->status === 'inativos', fn ($qq) => $qq->where('is_active', false))
            ->when(! $request->status || $request->status === 'ativos', fn ($qq) => $qq->where('is_active', true))
            ->orderBy('nome');

        return Inertia::render('Admin/Agricultural/Fields/Index', [
            'fields' => $q->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
            'farms' => Farm::where('is_active', true)->get(['id', 'nome']),
        ]);
    }

    public function fieldStore(Request $request)
    {
        $data = $this->validateField($request);
        Field::create($data);

        return back()->with('success', 'Talhão criado.');
    }

    public function fieldUpdate(Request $request, Field $field)
    {
        $data = $this->validateField($request, $field->id);
        $field->update($data);

        return back()->with('success', 'Talhão atualizado.');
    }

    public function fieldDestroy(Field $field)
    {
        if ($field->plantings()->exists()) {
            $field->update(['is_active' => false]);

            return back()->with('warning', 'Talhão tem plantios — foi desativado.');
        }
        $field->delete();

        return back()->with('success', 'Talhão excluído.');
    }

    public function fieldToggle(Field $field)
    {
        $field->update(['is_active' => ! $field->is_active]);

        return back()->with('success', $field->is_active ? 'Ativado.' : 'Desativado.');
    }

    protected function validateField(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'farm_id' => ['required', 'exists:farms,id'],
            'codigo' => ['required', 'string', 'max:30', Rule::unique('fields', 'codigo')->ignore($id)->whereNull('deleted_at')],
            'nome' => ['required', 'string', 'max:100'],
            'area_ha' => ['required', 'numeric', 'gt:0'],
            'tipo_solo' => ['nullable', 'string', 'max:50'],
            'descricao' => ['nullable', 'string'],
            'localizacao' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'is_active' => ['boolean'],
        ]);
    }

    // ============ CROPS + SEASONS ============

    public function crops(Request $request)
    {
        return Inertia::render('Admin/Agricultural/Crops/Index', [
            'crops' => Crop::orderBy('nome')->get(),
            'seasons' => Season::orderByDesc('data_inicio')->get(),
        ]);
    }

    public function cropStore(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:100', 'unique:crops,nome'],
            'variedade' => ['nullable', 'string', 'max:100'],
            'ciclo_dias' => ['nullable', 'integer', 'min:1'],
            'unidade_producao' => ['required', 'in:kg,ton,sc,un,l'],
        ]);
        $data['slug'] = Str::slug($data['nome']);
        $data['is_active'] = true;
        Crop::create($data);

        return back()->with('success', 'Cultura criada.');
    }

    public function cropDestroy(Crop $crop)
    {
        $crop->delete();

        return back()->with('success', 'Cultura excluída.');
    }

    public function seasonStore(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:100', 'unique:seasons,nome'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['nullable', 'date', 'after:data_inicio'],
        ]);
        $data['is_active'] = true;
        Season::create($data);

        return back()->with('success', 'Safra criada.');
    }

    public function seasonDestroy(Season $season)
    {
        $season->delete();

        return back()->with('success', 'Safra excluída.');
    }

    // ============ PLANTINGS ============

    public function plantings(Request $request)
    {
        $q = Planting::with(['field:id,nome,area_ha', 'crop:id,nome', 'season:id,nome'])
            ->when($request->status, fn ($qq) => $qq->where('status', $request->status))
            ->when($request->field_id, fn ($qq) => $qq->where('field_id', $request->field_id))
            ->when($request->season_id, fn ($qq) => $qq->where('season_id', $request->season_id))
            ->orderByDesc('data_plantio');

        return Inertia::render('Admin/Agricultural/Plantings/Index', [
            'plantings' => $q->paginate(25)->withQueryString(),
            'filters' => $request->only(['status', 'field_id', 'season_id']),
            'fields' => Field::where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'area_ha']),
            'crops' => Crop::where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'unidade_producao']),
            'seasons' => Season::orderByDesc('data_inicio')->get(['id', 'nome']),
        ]);
    }

    /**
     * ═════ D7 — Consolidação de Domínio · AGRÍCOLA ═════
     *
     * Aplica regras de domínio em Planting e FieldApplication seguindo o
     * padrão já consolidado em D1-D6.
     *
     * DESAFIO DE SCHEMA: o brief propõe classificar crops por tipo
     * (grão/pastagem/hortifruti/outros), mas a tabela `crops` NÃO tem
     * coluna `tipo`. Como o brief proíbe alteração de schema, uso
     * `crops.ciclo_dias` (já existente, nullable) como PROXY:
     *
     *   - ciclo_dias > 0   → cultura com ciclo definido (grão/hortifruti)
     *                        → planting exige `previsao_colheita`
     *   - ciclo_dias NULL  → cultura perene/pastagem
     *                        → planting NÃO exige previsão de colheita
     *
     * Para FieldApplication, não existe FK `stock_item_id`. Valido por
     * match case-insensitive de `produto` com `stock_items.nome`. Isto
     * garante rastreabilidade sem alterar schema — o operador deve
     * cadastrar o insumo no estoque antes de registrar a aplicação.
     */

    /**
     * Tipos de aplicação que exigem que o produto esteja cadastrado no
     * estoque. Mapeamento brief → schema:
     *   - fertilizante → adubacao
     *   - corretivo    → calagem
     *   - defensivo (herbicida/fungicida/inseticida) → SEM exigência
     */
    private const TIPOS_APLICACAO_EXIGEM_ESTOQUE = ['adubacao', 'calagem'];

    public function plantingStore(Request $request)
    {
        $data = $this->validatePlanting($request);

        // D7 · Coerência do ciclo da cultura
        if ($err = $this->validatePlantingCoherence($data, null)) {
            return back()->withInput()->with('error', $err);
        }

        Planting::create($data);

        return back()->with('success', 'Plantio registrado.');
    }

    public function plantingUpdate(Request $request, Planting $planting)
    {
        $data = $this->validatePlanting($request);

        // D7 · Coerência também em update (retrocompat: só valida se
        // crop_id ou previsao_colheita mudou).
        if ($err = $this->validatePlantingCoherence($data, $planting)) {
            return back()->withInput()->with('error', $err);
        }

        $planting->update($data);

        return back()->with('success', 'Plantio atualizado.');
    }

    public function plantingDestroy(Planting $planting)
    {
        $planting->delete();

        return back()->with('success', 'Plantio excluído.');
    }

    protected function validatePlanting(Request $request): array
    {
        return $request->validate([
            'field_id' => ['required', 'exists:fields,id'],
            'crop_id' => ['required', 'exists:crops,id'],
            'season_id' => ['nullable', 'exists:seasons,id'],
            'data_plantio' => ['required', 'date'],
            'previsao_colheita' => ['nullable', 'date', 'after_or_equal:data_plantio'],
            'area_plantada_ha' => ['required', 'numeric', 'gt:0'],
            'custo_previsto' => ['nullable', 'numeric', 'min:0'],
            'custo_real' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:em_andamento,colhido,perdido,cancelado'],
            'observacoes' => ['nullable', 'string'],
        ]);
    }

    // ============ HARVESTS ============

    public function harvests(Request $request)
    {
        $q = Harvest::with(['planting.field:id,nome', 'planting.crop:id,nome', 'planting.season:id,nome'])
            ->when($request->from, fn ($qq) => $qq->where('data_colheita', '>=', $request->from))
            ->when($request->to, fn ($qq) => $qq->where('data_colheita', '<=', $request->to))
            ->orderByDesc('data_colheita');

        return Inertia::render('Admin/Agricultural/Harvests/Index', [
            'harvests' => $q->paginate(25)->withQueryString(),
            'filters' => $request->only(['from', 'to']),
            'plantings' => Planting::with(['field:id,nome', 'crop:id,nome'])
                ->whereIn('status', ['em_andamento', 'colhido'])
                ->orderByDesc('data_plantio')->get(),
        ]);
    }

    /**
     * FASE 2 · F2.5 — Integração cross-módulo:
     *   Quando a colheita é registrada com valor_total > 0, o
     *   HarvestToRevenueService gera automaticamente uma
     *   FinancialTransaction (tipo=receita). Tudo em DB::transaction —
     *   atomicidade entre criação da colheita, update do planting para
     *   "colhido" e geração da receita.
     */
    public function harvestStore(Request $request, HarvestToRevenueService $harvestRevenue)
    {
        $data = $request->validate([
            'planting_id' => ['required', 'exists:plantings,id'],
            'data_colheita' => ['required', 'date'],
            'quantidade_colhida' => ['required', 'numeric', 'gt:0'],
            'unidade' => ['required', 'string', 'max:10'],
            'valor_total' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $planting = Planting::with(['field', 'crop'])->findOrFail($data['planting_id']);
        $data['produtividade_por_ha'] = $data['quantidade_colhida'] / max(0.0001, (float) $planting->area_plantada_ha);
        // Propaga tenant/farm do field para a Harvest (coerência com a origem).
        $data['tenant_id'] = $data['tenant_id'] ?? $planting->field?->tenant_id;
        $data['farm_id'] = $data['farm_id'] ?? $planting->field?->farm_id;

        // Atomicidade: harvest + update planting + integração
        DB::transaction(function () use ($data, $planting, $harvestRevenue) {
            $harvest = Harvest::create($data);
            $planting->update(['status' => 'colhido']);

            // F2.5: service gera receita se quantidade > 0 e valor_total > 0
            $harvest->setRelation('planting', $planting);
            $harvestRevenue->generateForHarvest($harvest);
        });

        return back()->with('success', 'Colheita registrada.');
    }

    public function harvestDestroy(Harvest $harvest)
    {
        $harvest->delete();

        return back()->with('success', 'Colheita excluída.');
    }

    // ============ FIELD APPLICATIONS ============

    public function applications(Request $request)
    {
        $q = FieldApplication::with(['field:id,nome', 'planting.crop:id,nome'])
            ->when($request->tipo, fn ($qq) => $qq->where('tipo', $request->tipo))
            ->when($request->field_id, fn ($qq) => $qq->where('field_id', $request->field_id))
            ->orderByDesc('data_aplicacao');

        return Inertia::render('Admin/Agricultural/Applications/Index', [
            'applications' => $q->paginate(25)->withQueryString(),
            'filters' => $request->only(['tipo', 'field_id']),
            'fields' => Field::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'plantings' => Planting::with(['field:id,nome', 'crop:id,nome'])->whereIn('status', ['em_andamento'])->get(),
        ]);
    }

    /**
     * FASE 2 · F2.3 — Integração cross-módulo:
     *   Quando a aplicação é tipo=adubacao ou calagem (fertilizante/corretivo),
     *   o FieldApplicationToStockMovementService dá baixa automática no estoque
     *   do insumo (resolvido por nome do produto). Tudo em DB::transaction —
     *   atomicidade entre criação da aplicação e movimento de saída.
     */
    public function applicationStore(Request $request, FieldApplicationToStockMovementService $stockBaixa)
    {
        // Auditoria 2026-04-27 — A2 multi-talhão.
        // Aceita `field_ids[]` (array — wizard) OU `field_id` (legado — index page).
        // Quando array, cria 1 FieldApplication por talhão com quantidade
        // PROPORCIONAL À ÁREA (real do dia a dia: 1000kg de adubo num talhão
        // de 10ha + outro de 30ha → 250kg + 750kg).
        $data = $request->validate([
            'field_id' => ['nullable', 'exists:fields,id'],
            'field_ids' => ['nullable', 'array'],
            'field_ids.*' => ['integer', 'exists:fields,id'],
            'planting_id' => ['nullable', 'exists:plantings,id'],
            'data_aplicacao' => ['required', 'date'],
            'tipo' => ['required', 'in:adubacao,herbicida,fungicida,inseticida,calagem,irrigacao,outros'],
            'produto' => ['required', 'string', 'max:200'],
            'quantidade' => ['required', 'numeric', 'gt:0'],
            'unidade' => ['required', 'string', 'max:10'],
            'valor_total' => ['nullable', 'numeric', 'min:0'],
            'responsavel' => ['nullable', 'string', 'max:100'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $fieldIds = ! empty($data['field_ids'])
            ? array_values(array_unique($data['field_ids']))
            : (! empty($data['field_id']) ? [$data['field_id']] : []);

        if (empty($fieldIds)) {
            return back()->withInput()->with('error', 'Informe pelo menos um talhão.');
        }

        // Carrega áreas dos talhões selecionados para distribuir proporcionalmente
        $fields = \App\Models\Agricultural\Field::whereIn('id', $fieldIds)->get(['id', 'nome', 'area_ha']);
        $areaTotal = (float) $fields->sum('area_ha');

        // D7 · Coerência — valida UMA vez (mesmo produto vai pra todos)
        $coerenciaCheck = array_merge($data, ['field_id' => $fieldIds[0]]);
        if ($err = $this->validateApplicationCoherence($coerenciaCheck)) {
            return back()->withInput()->with('error', $err);
        }

        DB::transaction(function () use ($data, $fields, $areaTotal, $stockBaixa) {
            $multi = $fields->count() > 1;
            $valorTotalGeral = (float) ($data['valor_total'] ?? 0);
            $quantidadeTotalGeral = (float) $data['quantidade'];

            $somaQtd = 0.0;
            $somaValor = 0.0;
            $idx = 0;
            foreach ($fields as $field) {
                $idx++;
                $proporcao = $areaTotal > 0
                    ? ((float) $field->area_ha) / $areaTotal
                    : 1.0 / $fields->count();

                if ($multi && $idx < $fields->count()) {
                    $qtdField = round($quantidadeTotalGeral * $proporcao, 4);
                    $valorField = $valorTotalGeral > 0 ? round($valorTotalGeral * $proporcao, 2) : null;
                    $somaQtd += $qtdField;
                    if ($valorField !== null) $somaValor += $valorField;
                } elseif ($multi) {
                    // Última: absorve diferença de arredondamento
                    $qtdField = round($quantidadeTotalGeral - $somaQtd, 4);
                    $valorField = $valorTotalGeral > 0 ? round($valorTotalGeral - $somaValor, 2) : null;
                } else {
                    // Single: usa valores cheios
                    $qtdField = $quantidadeTotalGeral;
                    $valorField = $valorTotalGeral > 0 ? $valorTotalGeral : null;
                }

                $payload = [
                    'field_id' => $field->id,
                    'planting_id' => $data['planting_id'] ?? null,
                    'data_aplicacao' => $data['data_aplicacao'],
                    'tipo' => $data['tipo'],
                    'produto' => $data['produto'],
                    'quantidade' => $qtdField,
                    'unidade' => $data['unidade'],
                    'valor_total' => $valorField,
                    'responsavel' => $data['responsavel'] ?? null,
                    'observacoes' => $data['observacoes'] ?? null,
                ];

                $app = FieldApplication::create($payload);
                $stockBaixa->generateForApplication($app);
            }
        });

        $msg = count($fieldIds) > 1
            ? 'Aplicação registrada em ' . count($fieldIds) . ' talhões (proporcional à área).'
            : 'Aplicação registrada.';

        return back()->with('success', $msg);
    }

    public function applicationDestroy(FieldApplication $application)
    {
        $application->delete();

        return back()->with('success', 'Aplicação excluída.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // D7 · Métodos de coerência de domínio (seguem padrão D1-D6)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Valida coerência do plantio com o ciclo da cultura.
     *
     * Se a cultura tem `ciclo_dias > 0` (proxy para grão/hortifruti), o
     * plantio DEVE ter `previsao_colheita` para permitir acompanhamento
     * do ciclo. Culturas perenes/pastagem (ciclo_dias NULL) passam sem
     * regra.
     *
     * Retrocompat: em update, só dispara se `crop_id` ou `previsao_colheita`
     * mudou.
     */
    protected function validatePlantingCoherence(array $data, ?Planting $existing): ?string
    {
        $cropId = $data['crop_id'] ?? null;
        if (! $cropId) {
            return null;
        }

        $crop = Crop::find($cropId);
        if (! $crop) {
            return null; // validator base já trata via 'exists'
        }

        $temCiclo = ! empty($crop->ciclo_dias) && (int) $crop->ciclo_dias > 0;
        if (! $temCiclo) {
            return null; // pastagem / cultura perene — sem exigência
        }

        $previsao = trim((string) ($data['previsao_colheita'] ?? ''));

        // Retrocompat em update: só valida se crop ou previsão mudaram
        if ($existing !== null) {
            $cropMudou = (int) $cropId !== (int) $existing->crop_id;
            $prevMudou = $previsao !== ($existing->previsao_colheita?->format('Y-m-d') ?? '');
            if (! $cropMudou && ! $prevMudou) {
                return null;
            }
        }

        if ($previsao === '') {
            return "Culturas de ciclo definido (como {$crop->nome}, ciclo {$crop->ciclo_dias} dias) exigem previsão de colheita. "
                . "Preencha o campo 'Previsão de colheita' para acompanhar o ciclo produtivo. "
                . 'Pastagens e culturas perenes (sem ciclo_dias cadastrado na Cultura) não têm essa exigência — '
                . 'se esta cultura é pastagem, ajuste o cadastro de Cultura deixando ciclo_dias em branco.';
        }

        return null;
    }

    /**
     * Valida coerência da aplicação — fertilizante e corretivo exigem
     * que o `produto` esteja cadastrado em Estoque → Itens (match
     * case-insensitive por nome).
     *
     * Rastreabilidade: sem essa regra, aplicações viravam texto livre
     * sem vínculo com o consumo real de insumos. Forçar o match com
     * stock_items.nome garante que todo uso de adubo/cal é precedido
     * do cadastro do insumo, viabilizando custos reais da safra.
     *
     * Defensivos (herbicida, fungicida, inseticida) e irrigação passam
     * sem regra — brief expressamente exige só fertilizante/corretivo.
     */
    protected function validateApplicationCoherence(array $data): ?string
    {
        $tipo = $data['tipo'] ?? null;
        if (! in_array($tipo, self::TIPOS_APLICACAO_EXIGEM_ESTOQUE, true)) {
            return null; // defensivo/irrigação/outros — sem exigência
        }

        $produto = trim((string) ($data['produto'] ?? ''));
        if ($produto === '') {
            return null; // já validado pelo validator base (required)
        }

        $existe = StockItem::where('is_active', true)
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower($produto)])
            ->exists();

        if ($existe) {
            return null; // produto confere com item ativo no estoque
        }

        $rotulo = $tipo === 'adubacao' ? 'Adubação (fertilizante)' : 'Calagem (corretivo)';

        return "{$rotulo} exige que o produto esteja cadastrado em Estoque → Itens. "
            . "Não encontrei '{$produto}' entre os itens ativos do estoque. "
            . 'Cadastre o insumo primeiro em "Estoque" (tipo=insumo, com descrição contextual) e depois registre a aplicação com o mesmo nome. '
            . 'Este vínculo garante rastreabilidade do consumo e sincronização com o custo real da safra.';
    }
}
