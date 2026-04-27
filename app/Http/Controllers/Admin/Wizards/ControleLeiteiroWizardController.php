<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Livestock\Animal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Wizard "Controle Leiteiro" — registra produção mensal das vacas em lactação.
 *
 * Filtra automaticamente:
 *   - Espécie bovino (perfil leite)
 *   - Sexo F
 *   - Idade ≥ 24 meses (vaca adulta, em produção)
 *   - Status ativo
 *   - NÃO tem evento de secagem mais recente que último parto
 *
 * Flow do form:
 *   1. Master escolhe a data (default hoje)
 *   2. Aparece a lista de vacas em lactação
 *   3. Por vaca: 1ª ordenha (litros) + 2ª ordenha (litros) — botão "+ ordenha" pra mais
 *   4. Total calculado automaticamente
 *   5. Submit gera 1 evento controle_leiteiro por vaca preenchida
 */
class ControleLeiteiroWizardController extends Controller
{
    public function create(\Illuminate\Http\Request $request): Response
    {
        $preselectId = (int) $request->query('animal_id');
        // Mostra TODAS as vacas (fêmeas bovinas ativas) — sem filtro de idade.
        // Motivo: idade mínima virou um filtro frustrante para fazendas que não
        // têm data_nascimento de animais comprados adultos. Mostra todas; a
        // realidade da produção (litros > 0) já filtra naturalmente.
        $vacas = Animal::ativos()
            ->where('sexo', 'F')
            ->whereHas('species', fn ($q) => $q->withoutGlobalScopes()->whereIn('slug', ['bovino', 'bovino-leite']))
            ->with(['species:id,nome,slug', 'breed:id,nome', 'lot:id,nome'])
            ->select('id', 'identificacao', 'nome', 'sexo', 'species_id', 'breed_id', 'lot_id', 'data_nascimento')
            ->orderBy('identificacao')
            ->get()
            ->map(function ($a) {
                // Última produção registrada (pra mostrar referência "mês passado")
                $ultimaProducao = DB::table('animal_events')
                    ->where('animal_id', $a->id)
                    ->where('tipo', 'controle_leiteiro')
                    ->orderByDesc('data')
                    ->value('producao_litros');

                return [
                    'id' => $a->id,
                    'identificacao' => $a->identificacao,
                    'nome' => $a->nome,
                    'lote' => $a->lot?->nome,
                    'producao_anterior_litros' => $ultimaProducao ? (float) $ultimaProducao : null,
                ];
            })
            ->values();

        return Inertia::render('Admin/Wizards/ControleLeiteiro', [
            'vacas' => $vacas,
            'data_hoje' => now()->toDateString(),
            'preselectId' => $preselectId ?: null,
        ]);
    }

    /**
     * Salva 1 evento controle_leiteiro por vaca preenchida.
     * Payload: { data, vacas: [{ animal_id, ordenhas: [{label, litros}], total }] }
     */
    public function store(Request $request): RedirectResponse
    {
        // Aceita 'data' OU 'data_controle' (Vue renomeou pra evitar conflito com palavra reservada)
        if ($request->has('data_controle') && ! $request->has('data')) {
            $request->merge(['data' => $request->input('data_controle')]);
        }
        $validated = $request->validate([
            'data' => ['required', 'date', 'before_or_equal:today'],
            'vacas' => ['required', 'array', 'min:1'],
            'vacas.*.animal_id' => ['required', 'exists:animals,id'],
            'vacas.*.ordenhas' => ['required', 'array', 'min:1', 'max:6'],
            'vacas.*.ordenhas.*.label' => ['required', 'string', 'max:20'],
            'vacas.*.ordenhas.*.hora' => ['nullable', 'string', 'max:5'], // formato HH:MM
            'vacas.*.ordenhas.*.litros' => ['required', 'numeric', 'min:0', 'max:99.99'],
        ]);

        $count = 0;
        DB::transaction(function () use ($validated, $request, &$count) {
            foreach ($validated['vacas'] as $vaca) {
                // Filtra ordenhas com 0 litros (vaca não foi ordenhada nessa)
                // Mantém label + hora + litros no JSON
                $ordenhas = collect($vaca['ordenhas'])
                    ->filter(fn ($o) => (float) $o['litros'] > 0)
                    ->map(fn ($o) => [
                        'label' => $o['label'],
                        'hora' => $o['hora'] ?? null,
                        'litros' => (float) $o['litros'],
                    ])
                    ->values()
                    ->all();

                if (empty($ordenhas)) continue;

                $total = collect($ordenhas)->sum(fn ($o) => (float) $o['litros']);
                $animal = Animal::findOrFail($vaca['animal_id']);
                $animal->events()->create([
                    'tipo' => 'controle_leiteiro',
                    'data' => $validated['data'],
                    'lot_id' => $animal->lot_id,
                    'ordenhas' => $ordenhas,
                    'producao_litros' => round($total, 2),
                    'created_by' => $request->user()?->id,
                ]);
                $count++;
            }
        });

        // return_to: caminho relativo passado pelo Animal show pra voltar pra ficha
        $returnTo = $request->input('return_to');
        $url = ($returnTo && str_starts_with($returnTo, '/admin/'))
            ? $returnTo
            : route('admin.inicio');

        return redirect($url)->with('success', "Leite registrado para {$count} vaca(s).");
    }
}
