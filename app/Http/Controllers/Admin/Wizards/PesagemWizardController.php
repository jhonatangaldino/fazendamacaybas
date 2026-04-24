<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Livestock\Animal;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assistente guiado — Registrar peso do animal.
 *
 * Esse controller SÓ expõe os dados necessários para renderizar o wizard.
 * O submit reutiliza a rota existente `admin.rebanho.animais.eventos.store`
 * com `tipo=pesagem` (zero duplicação de lógica de negócio).
 *
 * A ordem de apresentação dos animais:
 *   1. Atualizados recentemente (têm pesagem ou evento nos últimos 30 dias) primeiro
 *   2. Depois os demais, por identificação alfabética
 *
 * Isso torna o "último animal que você mexeu" o mais provável de aparecer no topo.
 */
class PesagemWizardController extends Controller
{
    public function create(): Response
    {
        // photo_url NÃO é accessor via convenção — é método `photoUrl()` no model.
        // Por isso precisamos construir manualmente o payload pro Vue.
        $animais = Animal::ativos()
            ->with(['species:id,nome', 'breed:id,nome', 'lot:id,nome'])
            ->select('id', 'identificacao', 'nome', 'sexo', 'species_id', 'breed_id', 'lot_id', 'peso_atual', 'data_nascimento', 'photo_path', 'updated_at')
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
            ]);

        return Inertia::render('Admin/Wizards/Pesagem', [
            'animais' => $animais,
        ]);
    }
}
