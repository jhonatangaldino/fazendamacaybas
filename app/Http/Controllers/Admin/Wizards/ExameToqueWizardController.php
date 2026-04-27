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
 * Wizard "Exame de toque" — palpação retal para diagnóstico de gestação.
 *
 * Filtra fêmeas em idade reprodutiva (bovino ≥18 meses, ovino/caprino ≥10 meses,
 * suíno ≥8 meses, equino ≥36 meses). Resultado:
 *   - prenhe → calcula DPP automaticamente (gestação por espécie)
 *               + cria tarefas de secagem e maternidade (auto-controle)
 *   - vazia → registra apenas
 *   - duvida → registra com flag para reexame
 *
 * Períodos de gestação (em dias) por espécie:
 *   bovino     280   ovino     150   caprino    150
 *   equino     340   suíno     114
 */
class ExameToqueWizardController extends Controller
{
    /**
     * Período de gestação em dias por slug da espécie.
     * Override editável pelo veterinário no form (campo gestacao_dias).
     */
    public const GESTACAO_DIAS = [
        'bovino'       => 280,
        'bovino-leite' => 280,
        'bovino-corte' => 280,
        'equino'       => 340,
        'caprino'      => 150,
        'ovino'        => 150,
        'suino'        => 114,
    ];

    public const IDADE_MIN_REPRODUTIVA_MESES = [
        'bovino'       => 18,
        'bovino-leite' => 18,
        'bovino-corte' => 18,
        'equino'       => 36,
        'caprino'      => 10,
        'ovino'        => 10,
        'suino'        => 8,
    ];

    public function create(\Illuminate\Http\Request $request): Response
    {
        $preselectId = (int) $request->query('animal_id');
        // Mostra TODAS as fêmeas ativas — filtro por idade vira soft warning
        // (animal sem data_nascimento ou abaixo da idade mínima fica visível
        // mas marcado com ⚠). Motivo: vaca comprada adulta sem data, novilha
        // próxima da cobertura, situações reais que não podem ser escondidas.
        $femeas = Animal::ativos()
            ->where('sexo', 'F')
            ->with(['species:id,nome,slug', 'lot:id,nome'])
            ->select('id', 'identificacao', 'nome', 'sexo', 'species_id', 'lot_id', 'data_nascimento')
            ->orderBy('identificacao')
            ->get()
            ->map(function ($a) {
                // Último exame de toque
                $ultimoToque = DB::table('animal_events')
                    ->where('animal_id', $a->id)
                    ->where('tipo', 'exame_toque')
                    ->orderByDesc('data')
                    ->first(['data', 'gestacao_status', 'data_prevista_parto']);

                $idadeMeses = $a->data_nascimento ? (int) $a->data_nascimento->diffInMonths(now()) : null;
                $minMeses = self::IDADE_MIN_REPRODUTIVA_MESES[$a->species?->slug ?? 'bovino'] ?? 18;
                $tooYoung = $idadeMeses !== null && $idadeMeses < $minMeses;

                return [
                    'id' => $a->id,
                    'identificacao' => $a->identificacao,
                    'nome' => $a->nome,
                    'especie' => $a->species?->nome,
                    'especie_slug' => $a->species?->slug,
                    'gestacao_dias_padrao' => self::GESTACAO_DIAS[$a->species?->slug ?? 'bovino'] ?? 280,
                    'lote' => $a->lot?->nome,
                    'idade_meses' => $idadeMeses,
                    'idade_desconhecida' => $idadeMeses === null,
                    'too_young' => $tooYoung,
                    'idade_min_meses' => $minMeses,
                    'ultimo_toque' => $ultimoToque,
                ];
            })
            ->values();

        // Veterinários cadastrados como parceiros (tipo prestador)
        $veterinarios = DB::table('partners')
            ->where('tipo', 'prestador')
            ->where('is_active', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return Inertia::render('Admin/Wizards/ExameToque', [
            'femeas' => $femeas,
            'veterinarios' => $veterinarios,
            'data_hoje' => now()->toDateString(),
            'preselectId' => $preselectId ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Vue renomeou 'data' pra 'data_exame' (palavra reservada). Mapeia.
        if ($request->has('data_exame') && ! $request->has('data')) {
            $request->merge(['data' => $request->input('data_exame')]);
        }
        $validated = $request->validate([
            'animal_ids' => ['required', 'array', 'min:1'],
            'animal_ids.*' => ['exists:animals,id'],
            'data' => ['required', 'date', 'before_or_equal:today'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'gestacao_status' => ['required', 'in:prenhe,vazia,duvida'],
            'gestacao_dias' => ['nullable', 'integer', 'min:0', 'max:340'],
            'data_prevista_parto' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ]);

        $count = 0;
        $tarefasCriadas = 0;
        DB::transaction(function () use ($validated, $request, &$count, &$tarefasCriadas) {
            foreach ($validated['animal_ids'] as $animalId) {
                $animal = Animal::with('species')->findOrFail($animalId);

                $dataExame = \Carbon\Carbon::parse($validated['data']);
                $dpp = null;
                if ($validated['gestacao_status'] === 'prenhe') {
                    if (! empty($validated['data_prevista_parto'])) {
                        $dpp = \Carbon\Carbon::parse($validated['data_prevista_parto']);
                    } elseif (! empty($validated['gestacao_dias'])) {
                        $diasGestacao = self::GESTACAO_DIAS[$animal->species?->slug] ?? 280;
                        $dpp = $dataExame->copy()->addDays($diasGestacao - $validated['gestacao_dias']);
                    }
                }

                $animal->events()->create([
                    'tipo' => 'exame_toque',
                    'data' => $validated['data'],
                    'lot_id' => $animal->lot_id,
                    'partner_id' => $validated['partner_id'] ?? null,
                    'gestacao_status' => $validated['gestacao_status'],
                    'gestacao_dias' => $validated['gestacao_dias'] ?? null,
                    'data_prevista_parto' => $dpp?->toDateString(),
                    'observacoes' => $validated['observacoes'] ?? null,
                    'created_by' => $request->user()?->id,
                ]);
                $count++;

                // Auto-controles: se prenhe, cria tarefas de manejo
                if ($validated['gestacao_status'] === 'prenhe' && $dpp) {
                    $tarefasCriadas += $this->criarTarefasGestacao($animal, $dpp, $request->user()?->id);
                }
            }
        });

        $msg = "Exame de toque registrado para {$count} fêmea(s).";
        if ($tarefasCriadas > 0) {
            $msg .= " {$tarefasCriadas} tarefa(s) automática(s) criada(s) (secagem + maternidade).";
        }

        $returnTo = $request->input('return_to');
        $url = ($returnTo && str_starts_with($returnTo, '/admin/'))
            ? $returnTo
            : route('admin.inicio');

        return redirect($url)->with('success', $msg);
    }

    /**
     * Cria tarefas automáticas para uma vaca prenhe:
     *   - Secar a vaca 60 dias antes do parto (regra DRovet)
     *   - Preparar maternidade 15 dias antes do parto
     */
    private function criarTarefasGestacao(Animal $animal, \Carbon\Carbon $dpp, ?int $userId): int
    {
        $criadas = 0;
        $tarefas = [
            [
                'titulo' => "Secar vaca {$animal->identificacao}" . ($animal->nome ? " ({$animal->nome})" : ''),
                'data_vencimento' => $dpp->copy()->subDays(60),
                'descricao' => "Secagem programada — 60 dias antes do parto previsto em " . $dpp->format('d/m/Y') . ".",
                'prioridade' => 'alta',
                'auto_action' => null, // não dispara wizard, é manual
            ],
            [
                'titulo' => "Registrar parto · {$animal->identificacao}" . ($animal->nome ? " ({$animal->nome})" : ''),
                'data_vencimento' => $dpp->copy(),
                'descricao' => "Parto previsto em " . $dpp->format('d/m/Y') . ". Ao concluir esta tarefa, o sistema vai abrir o wizard para cadastrar o(s) filhote(s) automaticamente como filho(s) da " . ($animal->nome ?: $animal->identificacao) . ".",
                'prioridade' => 'alta',
                'auto_action' => 'parto', // dispara wizard ao concluir
            ],
        ];

        foreach ($tarefas as $t) {
            // Não duplicar se já existe tarefa idêntica (mesmo título + animal)
            $existe = DB::table('tasks')
                ->where('tenant_id', $animal->tenant_id)
                ->where('titulo', $t['titulo'])
                ->whereNull('deleted_at')
                ->exists();
            if ($existe) continue;

            DB::table('tasks')->insert([
                'tenant_id' => $animal->tenant_id,
                'farm_id' => $animal->farm_id,
                'titulo' => $t['titulo'],
                'descricao' => $t['descricao'],
                'prioridade' => $t['prioridade'],
                'status' => 'pendente',
                'data_vencimento' => $t['data_vencimento']->toDateString(),
                'modulo' => 'rebanho',
                'auto_action' => $t['auto_action'] ?? null,
                'related_type' => \App\Models\Livestock\Animal::class,
                'related_id' => $animal->id,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $criadas++;
        }

        return $criadas;
    }
}
