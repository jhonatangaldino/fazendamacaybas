<?php

namespace App\Http\Controllers\Admin\Agricultural;

use App\Http\Controllers\Controller;
use App\Models\Agricultural\Crop;
use App\Models\Agricultural\Field;
use App\Models\Agricultural\FieldApplication;
use App\Models\Agricultural\Harvest;
use App\Models\Agricultural\Planting;
use App\Models\Agricultural\Season;
use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AgriculturalController extends Controller
{
    public function index()
    {
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
                'fields' => $fields->count(),
                'plantings_ativos' => $plantings->count(),
                'seasons' => $seasons->count(),
                'area_total' => (float) $fields->sum('area_ha'),
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

    public function plantingStore(Request $request)
    {
        $data = $this->validatePlanting($request);
        Planting::create($data);

        return back()->with('success', 'Plantio registrado.');
    }

    public function plantingUpdate(Request $request, Planting $planting)
    {
        $data = $this->validatePlanting($request);
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

    public function harvestStore(Request $request)
    {
        $data = $request->validate([
            'planting_id' => ['required', 'exists:plantings,id'],
            'data_colheita' => ['required', 'date'],
            'quantidade_colhida' => ['required', 'numeric', 'gt:0'],
            'unidade' => ['required', 'string', 'max:10'],
            'valor_total' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $planting = Planting::with('field')->findOrFail($data['planting_id']);
        $data['produtividade_por_ha'] = $data['quantidade_colhida'] / max(0.0001, (float) $planting->area_plantada_ha);

        Harvest::create($data);
        $planting->update(['status' => 'colhido']);

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

    public function applicationStore(Request $request)
    {
        $data = $request->validate([
            'field_id' => ['required', 'exists:fields,id'],
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
        FieldApplication::create($data);

        return back()->with('success', 'Aplicação registrada.');
    }

    public function applicationDestroy(FieldApplication $application)
    {
        $application->delete();

        return back()->with('success', 'Aplicação excluída.');
    }
}
