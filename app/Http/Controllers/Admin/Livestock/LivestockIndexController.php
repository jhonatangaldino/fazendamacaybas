<?php

namespace App\Http\Controllers\Admin\Livestock;

use App\Http\Controllers\Controller;
use App\Models\Livestock\Animal;
use App\Models\Livestock\AnimalEvent;
use App\Models\Livestock\AnimalLot;
use App\Models\Livestock\AnimalLocation;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hub do Rebanho — visão geral por espécie + atalhos pra Lotes/Locais.
 *
 * Antes redirecionava direto pra lista geral de animais, o que jogava o
 * usuário num cenário misturado (todas as espécies juntas) e perdia o
 * contexto. Agora é um Hub real.
 *
 * As contagens por espécie chegam via `tenantSpecies` (HandleInertiaRequests)
 * — aqui só calculamos KPI total e contagens auxiliares (lotes, locais).
 */
class LivestockIndexController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Livestock/Hub', [
            'totalAnimais' => Animal::where('status', 'ativo')->count(),
            'totalLotes' => AnimalLot::where('is_active', true)->count(),
            'totalLocais' => AnimalLocation::where('is_active', true)->count(),
            // Igual ao critério da Animals/Index: existem animais leite/misto OU
            // já houve eventos de ordenha/controle leiteiro.
            'temManejoLeiteiro' => Animal::whereIn('categoria', ['leite', 'misto'])->exists()
                || AnimalEvent::whereIn('tipo', ['controle_leiteiro', 'ordenha'])->exists(),
        ]);
    }
}
