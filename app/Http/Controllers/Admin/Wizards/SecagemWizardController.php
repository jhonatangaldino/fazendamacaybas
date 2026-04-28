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
 * Wizard "Secar vaca" — registra cessação da lactação antes do parto.
 *
 * As vacas devem ser secadas no final da gestação (≈2 meses antes do parto)
 * ou se estiverem dando pouco leite. O sistema:
 *   - Lista vacas em lactação (não secas, idade adulta, fêmea)
 *   - Sugere primeiro as que têm exame de toque com data de parto próxima de 60 dias
 *   - Salva evento secagem com tratamento aplicado
 */
class SecagemWizardController extends Controller
{
    public function create(\Illuminate\Http\Request $request): Response
    {
        $preselectId = (int) $request->query('animal_id');
        // Mostra TODAS as vacas (fêmeas bovinas ativas) — sem filtro de idade.
        $vacas = Animal::ativos()
            ->where('sexo', 'F')
            ->whereHas('species', fn ($q) => $q->withoutGlobalScopes()->whereIn('slug', ['bovino', 'bovino-leite']))
            ->with(['lot:id,nome'])
            ->select('id', 'identificacao', 'nome', 'sexo', 'species_id', 'lot_id', 'data_nascimento')
            ->orderBy('identificacao')
            ->get()
            ->map(function ($a) {
                // Última secagem
                $ultimaSecagem = DB::table('animal_events')
                    ->where('animal_id', $a->id)
                    ->where('tipo', 'secagem')
                    ->orderByDesc('data')
                    ->value('data');

                // Último exame de toque (pra calcular sugestão de prazo de secagem)
                $ultimoToque = DB::table('animal_events')
                    ->where('animal_id', $a->id)
                    ->where('tipo', 'exame_toque')
                    ->where('gestacao_status', 'prenhe')
                    ->orderByDesc('data')
                    ->first(['data', 'data_prevista_parto']);

                $sugerida = false;
                $diasParaParto = null;
                if ($ultimoToque && $ultimoToque->data_prevista_parto) {
                    $dpp = \Carbon\Carbon::parse($ultimoToque->data_prevista_parto);
                    $diasParaParto = now()->diffInDays($dpp, false);
                    // Sugerida quando faltam ≤ 75 dias para o parto E ainda não foi secada
                    if ($diasParaParto <= 75 && $diasParaParto >= 0) {
                        $sugerida = true;
                    }
                }

                return [
                    'id' => $a->id,
                    'identificacao' => $a->identificacao,
                    'nome' => $a->nome,
                    'lote' => $a->lot?->nome,
                    'ultima_secagem' => $ultimaSecagem,
                    'dias_para_parto' => $diasParaParto,
                    'sugerida' => $sugerida,
                ];
            })
            ->sortByDesc('sugerida') // sugeridas primeiro
            ->values();

        return Inertia::render('Admin/Wizards/Secagem', [
            'vacas' => $vacas,
            'data_hoje' => now()->toDateString(),
            'preselectId' => $preselectId ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Vue renomeou 'data' pra 'data_secagem' (palavra reservada). Mapeia de volta.
        if ($request->has('data_secagem') && ! $request->has('data')) {
            $request->merge(['data' => $request->input('data_secagem')]);
        }
        $validated = $request->validate([
            'animal_id' => ['required', 'exists:animals,id'],
            'data' => ['required', 'date', 'before_or_equal:today'],
            'medicamento' => ['nullable', 'string', 'max:120'],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ]);

        $animal = Animal::findOrFail($validated['animal_id']);
        $animal->events()->create([
            'tipo' => 'secagem',
            'data' => $validated['data'],
            'lot_id' => $animal->lot_id,
            'medicamento' => $validated['medicamento'] ?? null,
            'observacoes' => $validated['observacoes'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        // back() em vez de redirect() — Vue mostra passo 4 "Pronto!" e o usuário
        // clica "Voltar ao início" que respeita return_to manualmente.
        return back()->with('success', "Vaca {$animal->identificacao} marcada como SECA em " . \Carbon\Carbon::parse($validated['data'])->format('d/m/Y') . '.');
    }
}
