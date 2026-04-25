<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Agricultural\Crop;
use App\Models\Agricultural\Field;
use App\Models\Agricultural\Planting;
use App\Models\Agricultural\Season;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assistente guiado — Registrar plantio.
 *
 * 5 passos: cultura → talhão+área → datas → custo → pronto.
 * Inline create de talhão e cultura quando o tenant é zero-data.
 */
class PlantioWizardController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Wizards/Plantio', [
            'crops' => Crop::where('is_active', true)->orderBy('nome')
                ->get(['id', 'nome', 'ciclo_dias', 'unidade_producao']),
            'fields' => Field::where('is_active', true)->orderBy('nome')
                ->get(['id', 'nome', 'area_ha']),
            'seasons' => Season::orderByDesc('id')->get(['id', 'nome']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'field_id' => ['required', 'exists:fields,id'],
            'crop_id' => ['required', 'exists:crops,id'],
            'season_id' => ['nullable', 'exists:seasons,id'],
            'data_plantio' => ['required', 'date'],
            'previsao_colheita' => ['nullable', 'date', 'after_or_equal:data_plantio'],
            'area_plantada_ha' => ['required', 'numeric', 'gt:0'],
            'custo_previsto' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $planting = Planting::create([
            ...$data,
            'status' => 'em_andamento',
        ]);

        session()->flash('plantio_contexto', [
            'id' => $planting->id,
            'data_plantio' => $planting->data_plantio?->format('Y-m-d'),
            'previsao_colheita' => $planting->previsao_colheita?->format('Y-m-d'),
            'area_plantada_ha' => $planting->area_plantada_ha,
        ]);

        return back()->with('success', 'Plantio registrado.');
    }

    /** Inline create de talhão — JSON. */
    public function fieldInline(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'area_ha' => ['required', 'numeric', 'gt:0'],
            'descricao' => ['nullable', 'string'],
        ]);

        $codigo = strtoupper(Str::slug($data['nome'], '-'));
        $base = $codigo;
        $i = 0;
        while (Field::where('codigo', $codigo)->exists()) {
            $i++;
            $codigo = $base.'-'.$i;
        }

        // Bloco 3 — multi-fazenda: SEMPRE usar farm ativa do contexto.
        // EnforceFarm middleware DEVE ter bindado app('farm_id') antes de chegar
        // aqui. Sem fallback — se faltar, é bug de boot e não escolhemos farm.
        abort_unless(app()->bound('farm_id'), 500, 'Contexto de fazenda não resolvido (EnforceFarm).');
        $farmId = app('farm_id');

        $field = Field::create([
            'farm_id' => $farmId,
            'codigo' => $codigo,
            'nome' => $data['nome'],
            'area_ha' => $data['area_ha'],
            'descricao' => $data['descricao'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'id' => $field->id,
            'nome' => $field->nome,
            'area_ha' => $field->area_ha,
        ]);
    }

    /** Inline create de cultura — JSON. */
    public function cropInline(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'unidade_producao' => ['required', 'string', 'max:20'],
            'ciclo_dias' => ['nullable', 'integer', 'min:1'],
        ]);

        $slug = Str::slug($data['nome']);
        $base = $slug;
        $i = 0;
        while (Crop::where('slug', $slug)->exists()) {
            $i++;
            $slug = $base.'-'.$i;
        }

        $crop = Crop::create([
            'nome' => $data['nome'],
            'slug' => $slug,
            'unidade_producao' => $data['unidade_producao'],
            'ciclo_dias' => $data['ciclo_dias'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'id' => $crop->id,
            'nome' => $crop->nome,
            'ciclo_dias' => $crop->ciclo_dias,
            'unidade_producao' => $crop->unidade_producao,
        ]);
    }
}
