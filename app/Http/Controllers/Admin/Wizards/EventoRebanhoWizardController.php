<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Livestock\Animal;
use App\Models\Livestock\AnimalLocation;
use App\Models\Livestock\AnimalLot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assistente guiado polimórfico — Eventos de rebanho.
 *
 * UM wizard serve 6 cards do Hub, parametrizado por ?tipo=X:
 *   - vacinar    → tipo=vacinacao
 *   - medicar    → tipo=medicacao
 *   - vermifugar → tipo=vermifugacao
 *   - mover      → tipo=movimentacao (exige lot_destino_id)
 *   - morte      → tipo=mortalidade
 *   - observar   → tipo=observacao
 *
 * O Vue lê o tipo, muda os campos contextuais do passo 2, e envia para
 * a rota existente `admin.rebanho.animais.eventos.store` (zero duplicação
 * de lógica de negócio).
 */
class EventoRebanhoWizardController extends Controller
{
    // Tipos que o wizard sabe tratar (subset dos aceitos pelo backend).
    // `movimentacao` = muda de LOTE (grupo); `movimentacao_local` = muda de PASTO (local físico).
    private const TIPOS_ACEITOS = [
        'vacinacao', 'medicacao', 'vermifugacao',
        'movimentacao', 'movimentacao_local',
        'mortalidade', 'observacao',
    ];

    public function create(Request $request): Response
    {
        $tipo = $request->query('tipo', 'vacinacao');
        if (! in_array($tipo, self::TIPOS_ACEITOS, true)) {
            $tipo = 'vacinacao';
        }

        $animais = Animal::ativos()
            ->with(['species:id,nome', 'breed:id,nome', 'lot:id,nome'])
            ->select('id', 'identificacao', 'nome', 'sexo', 'species_id', 'breed_id', 'lot_id', 'location_id', 'peso_atual', 'data_nascimento', 'photo_path', 'updated_at')
            ->orderByDesc('updated_at')
            ->orderBy('identificacao')
            ->limit(200)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'identificacao' => $a->identificacao,
                'nome' => $a->nome,
                'sexo' => $a->sexo,
                'peso_atual' => $a->peso_atual,
                'data_nascimento' => $a->data_nascimento?->toDateString(),
                'photo_url' => $a->photoUrl(),
                'species' => $a->species ? ['id' => $a->species->id, 'nome' => $a->species->nome] : null,
                'breed' => $a->breed ? ['id' => $a->breed->id, 'nome' => $a->breed->nome] : null,
                'lot' => $a->lot ? ['id' => $a->lot->id, 'nome' => $a->lot->nome] : null,
                'location_id' => $a->location_id,
            ]);

        // Auditoria 2026-04-27 — eventos AGREGADOS para lotes de aves/peixes
        // (gestao_modo='agregada'). Permite vacinar/medicar/mortalidade em
        // X de Y animais sem identificação individual.
        $lotesAgregados = AnimalLot::where('is_active', true)
            ->where('gestao_modo', 'agregada')
            ->where('quantidade_atual', '>', 0)
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo', 'quantidade_atual', 'peso_medio_kg', 'finalidade']);

        return Inertia::render('Admin/Wizards/EventoRebanho', [
            'tipoInicial' => $tipo,
            'animais' => $animais,
            'lotes' => AnimalLot::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'locais' => AnimalLocation::ativos()->orderBy('tipo')->orderBy('nome')->get(['id', 'nome', 'tipo']),
            'lotesAgregados' => $lotesAgregados,
        ]);
    }
}
