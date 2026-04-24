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
        // photo_url é ACCESSOR (virtual), não coluna — por isso fica fora do select().
        // O Vue só precisa dos campos abaixo; accessors apareceriam automaticamente
        // se o front os pedisse, mas listamos colunas reais pra economizar bytes.
        $animais = Animal::ativos()
            ->with(['species:id,nome', 'breed:id,nome', 'lot:id,nome'])
            ->select('id', 'identificacao', 'nome', 'sexo', 'species_id', 'breed_id', 'lot_id', 'peso_atual', 'data_nascimento', 'photo_path', 'updated_at')
            ->orderByDesc('updated_at')
            ->orderBy('identificacao')
            ->limit(200)
            ->get()
            ->append('photo_url'); // hydrate o accessor pra envio ao Inertia

        return Inertia::render('Admin/Wizards/Pesagem', [
            'animais' => $animais,
        ]);
    }
}
