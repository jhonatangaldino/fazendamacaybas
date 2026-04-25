<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Domain\Integration\Services\HarvestToRevenueService;
use App\Http\Controllers\Controller;
use App\Models\Agricultural\Harvest;
use App\Models\Agricultural\Planting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assistente guiado — Registrar colheita.
 *
 * Fluxo (3 passos + sucesso):
 *   1 · Qual plantio?    (lista plantios em_andamento; se zero, mensagem)
 *   2 · Quanto colheu?   (qtd + unidade + valor opcional)
 *   3 · Conferência      (mostra produtividade + gera receita se valor)
 *   4 · Pronto!          (encerra plantio + receita criada)
 */
class ColheitaWizardController extends Controller
{
    public function create(): Response
    {
        $plantings = Planting::with(['field:id,nome', 'crop:id,nome,unidade_producao'])
            ->whereIn('status', ['em_andamento'])
            ->orderByDesc('data_plantio')
            ->get(['id', 'field_id', 'crop_id', 'data_plantio', 'previsao_colheita', 'area_plantada_ha', 'status']);

        return Inertia::render('Admin/Wizards/Colheita', [
            'plantings' => $plantings->map(fn ($p) => [
                'id' => $p->id,
                'field_nome' => $p->field?->nome,
                'crop_nome' => $p->crop?->nome,
                'crop_unidade' => $p->crop?->unidade_producao,
                'data_plantio' => $p->data_plantio?->format('Y-m-d'),
                'previsao_colheita' => $p->previsao_colheita?->format('Y-m-d'),
                'area_plantada_ha' => (float) $p->area_plantada_ha,
                'rotulo' => sprintf('%s em %s · %s ha', $p->crop?->nome, $p->field?->nome, $p->area_plantada_ha),
            ]),
        ]);
    }

    public function store(Request $request, HarvestToRevenueService $harvestRevenue): RedirectResponse
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
        $data['tenant_id'] = $planting->field?->tenant_id;
        $data['farm_id'] = $planting->field?->farm_id;

        $harvest = DB::transaction(function () use ($data, $planting, $harvestRevenue) {
            $harvest = Harvest::create($data);
            $planting->update(['status' => 'colhido']);
            $harvest->loadMissing(['planting.field', 'planting.crop']);
            $harvestRevenue->generateForHarvest($harvest);
            return $harvest;
        });

        session()->flash('colheita_contexto', [
            'id' => $harvest->id,
            'planting_id' => $planting->id,
            'crop' => $planting->crop?->nome,
            'field' => $planting->field?->nome,
            'quantidade' => (float) $data['quantidade_colhida'],
            'unidade' => $data['unidade'],
            'produtividade_por_ha' => round($data['produtividade_por_ha'], 2),
            'valor_total' => (float) ($data['valor_total'] ?? 0),
            'gerou_receita' => ($data['valor_total'] ?? 0) > 0,
        ]);

        return back()->with('success', 'Colheita registrada e plantio fechado.');
    }
}
