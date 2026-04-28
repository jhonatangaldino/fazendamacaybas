<?php

namespace App\Http\Controllers\Admin\Livestock;

use App\Http\Controllers\Controller;
use App\Models\Livestock\AnimalLot;
use App\Models\Livestock\AnimalLocation;
use App\Services\Livestock\LivestockMetricsService;
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
 *
 * Refatorado 2026-04-28 — agora consome LivestockMetricsService como fonte
 * única de verdade, em vez de fórmula inline. Antes o Bovino mostrava 345
 * no card e 344 no Dashboard de espécie; ambos agora chamam o service.
 */
class LivestockIndexController extends Controller
{
    public function __invoke(LivestockMetricsService $metrics): Response
    {
        return Inertia::render('Admin/Livestock/Hub', [
            'totalAnimais' => $metrics->totalCabecasTodasEspecies(),
            'totalLotes' => AnimalLot::where('is_active', true)->count(),
            'totalLocais' => AnimalLocation::where('is_active', true)->count(),
            'temManejoLeiteiro' => $metrics->temManejoLeiteiro(),
        ]);
    }
}
