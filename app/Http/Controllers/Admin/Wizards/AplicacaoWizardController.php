<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Agricultural\Field;
use App\Models\Agricultural\Planting;
use App\Models\Stock\StockItem;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assistente guiado — Aplicar produto na plantação (defensivo ou adubo).
 *
 * O wizard permite ao usuário escolher por FINALIDADE ("vou passar
 * veneno" ou "vou adubar") em vez de pelo tipo técnico
 * (herbicida/fungicida/etc). Internamente mapeia para o `tipo` aceito
 * pelo backend.
 *
 * Query string opcional: `?tipo=adubacao|herbicida|...` pré-seleciona.
 *
 * Submit: reutiliza `admin.agricola.aplicacoes.store`.
 */
class AplicacaoWizardController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Wizards/Aplicacao', [
            'talhoes' => Field::where('is_active', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'area_ha']),
            'plantios' => Planting::where('status', 'em_andamento')
                ->with(['field:id,nome', 'crop:id,nome'])
                ->orderByDesc('data_plantio')
                ->get(['id', 'field_id', 'crop_id', 'data_plantio']),
            'produtosEstoque' => StockItem::where('is_active', true)
                ->whereIn('tipo', ['insumo', 'medicamento'])
                ->orderBy('nome')
                ->get(['id', 'nome', 'unidade']),
        ]);
    }
}
