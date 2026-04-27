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
        // Auditoria 2026-04-27 — A3 multi-talhão.
        // Aceita `field_ids[]` (array — wizard multi) OU `field_id` (legado).
        // Quando array, cria 1 Planting por talhão usando sua área TOTAL.
        // Custo previsto é dividido proporcional à área de cada talhão.
        $data = $request->validate([
            'field_id' => ['nullable', 'exists:fields,id'],
            'field_ids' => ['nullable', 'array'],
            'field_ids.*' => ['integer', 'exists:fields,id'],
            'crop_id' => ['required', 'exists:crops,id'],
            'season_id' => ['nullable', 'exists:seasons,id'],
            'data_plantio' => ['required', 'date'],
            'previsao_colheita' => ['nullable', 'date', 'after_or_equal:data_plantio'],
            'area_plantada_ha' => ['nullable', 'numeric', 'gt:0'],
            'custo_previsto' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $fieldIds = ! empty($data['field_ids'])
            ? array_values(array_unique($data['field_ids']))
            : (! empty($data['field_id']) ? [$data['field_id']] : []);

        if (empty($fieldIds)) {
            return back()->withInput()->with('error', 'Informe pelo menos um talhão.');
        }

        $multi = count($fieldIds) > 1;
        $fields = Field::whereIn('id', $fieldIds)->get(['id', 'nome', 'area_ha']);
        $areaTotal = (float) $fields->sum('area_ha');
        $custoPrevisto = (float) ($data['custo_previsto'] ?? 0);

        $primeiraPlantation = null;
        \DB::transaction(function () use (
            $data, $fields, $areaTotal, $custoPrevisto, $multi, &$primeiraPlantation
        ) {
            $somaCusto = 0.0;
            $idx = 0;
            foreach ($fields as $field) {
                $idx++;
                if ($multi) {
                    $area = (float) $field->area_ha;
                    if ($idx < $fields->count() && $custoPrevisto > 0) {
                        $custoField = round($custoPrevisto * ($area / $areaTotal), 2);
                        $somaCusto += $custoField;
                    } elseif ($custoPrevisto > 0) {
                        $custoField = round($custoPrevisto - $somaCusto, 2);
                    } else {
                        $custoField = null;
                    }
                } else {
                    $area = $data['area_plantada_ha'] ?? (float) $field->area_ha;
                    $custoField = $custoPrevisto > 0 ? $custoPrevisto : null;
                }

                $planting = Planting::create([
                    'field_id' => $field->id,
                    'crop_id' => $data['crop_id'],
                    'season_id' => $data['season_id'] ?? null,
                    'data_plantio' => $data['data_plantio'],
                    'previsao_colheita' => $data['previsao_colheita'] ?? null,
                    'area_plantada_ha' => $area,
                    'custo_previsto' => $custoField,
                    'observacoes' => $data['observacoes'] ?? null,
                    'status' => 'em_andamento',
                ]);

                if ($primeiraPlantation === null) {
                    $primeiraPlantation = $planting;
                }
            }
        });

        session()->flash('plantio_contexto', [
            'id' => $primeiraPlantation?->id,
            'data_plantio' => $primeiraPlantation?->data_plantio?->format('Y-m-d'),
            'previsao_colheita' => $primeiraPlantation?->previsao_colheita?->format('Y-m-d'),
            'area_plantada_ha' => (float) $areaTotal,
            'total_talhoes' => count($fieldIds),
        ]);

        $msg = $multi
            ? 'Plantio registrado em ' . count($fieldIds) . ' talhões (' . number_format($areaTotal, 2, ',', '.') . ' ha).'
            : 'Plantio registrado.';

        return back()->with('success', $msg);
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
