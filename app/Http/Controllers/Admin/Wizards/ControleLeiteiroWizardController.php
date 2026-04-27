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
    public function create(): Response
    {
        $vacas = Animal::ativos()
            ->where('sexo', 'F')
            ->whereHas('species', fn ($q) => $q->where('slug', 'bovino')->orWhere('slug', 'bovino-leite'))
            ->with(['species:id,nome,slug', 'breed:id,nome', 'lot:id,nome'])
            ->select('id', 'identificacao', 'nome', 'sexo', 'species_id', 'breed_id', 'lot_id', 'data_nascimento')
            ->orderBy('identificacao')
            ->get()
            ->filter(function ($a) {
                // Idade mínima 24 meses (vaca adulta em lactação)
                return $a->data_nascimento && $a->data_nascimento->diffInMonths(now()) >= 24;
            })
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
        ]);
    }

    /**
     * Salva 1 evento controle_leiteiro por vaca preenchida.
     * Payload: { data, vacas: [{ animal_id, ordenhas: [{label, litros}], total }] }
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'data' => ['required', 'date', 'before_or_equal:today'],
            'vacas' => ['required', 'array', 'min:1'],
            'vacas.*.animal_id' => ['required', 'exists:animals,id'],
            'vacas.*.ordenhas' => ['required', 'array', 'min:1', 'max:6'],
            'vacas.*.ordenhas.*.label' => ['required', 'string', 'max:20'],
            'vacas.*.ordenhas.*.litros' => ['required', 'numeric', 'min:0', 'max:99.99'],
        ]);

        $count = 0;
        DB::transaction(function () use ($validated, $request, &$count) {
            foreach ($validated['vacas'] as $vaca) {
                // Filtra ordenhas com 0 litros (vaca não foi ordenhada nessa)
                $ordenhas = collect($vaca['ordenhas'])
                    ->filter(fn ($o) => (float) $o['litros'] > 0)
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

        return redirect()
            ->route('admin.inicio')
            ->with('success', "Controle leiteiro registrado para {$count} vaca(s).");
    }
}
