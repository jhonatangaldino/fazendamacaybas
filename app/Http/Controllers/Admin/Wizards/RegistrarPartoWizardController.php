<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Livestock\Animal;
use App\Models\Task\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Wizard "Registrar parto" — invocado quando o admin conclui uma tarefa
 * com auto_action='parto' (criada pelo ExameToqueController).
 *
 * Fluxo:
 *   1. Pega a mãe (task.related_id)
 *   2. Sabe a espécie → quantidade default + período de amamentação + lógica
 *      multi-fetal (perg "quantos filhotes?" pra suíno/cão/gato)
 *   3. Form: data parto + lista de filhotes (ID auto-sequencial editável)
 *   4. Salva: cria N animals (filhos da mãe), cria lote opcional, evento
 *      parto na mãe, fecha a tarefa, cria tarefa automática de desmame
 */
class RegistrarPartoWizardController extends Controller
{
    /**
     * Período de gestação (dias) — usado para validar data plausível
     */
    public const GESTACAO_DIAS = [
        'bovino'       => 280,
        'bovino-leite' => 280,
        'bovino-corte' => 280,
        'equino'       => 340,
        'caprino'      => 150,
        'ovino'        => 150,
        'suino'        => 114,
        'cao'          => 63,
        'gato'         => 65,
        'coelho'       => 31,
    ];

    /**
     * Espécies multi-fetais (parto múltiplo é a NORMA, não exceção).
     * Define a quantidade DEFAULT sugerida no form.
     */
    public const MULTI_FETAIS = [
        'suino'   => 8,   // leitegada típica
        'cao'     => 6,
        'gato'    => 4,
        'coelho'  => 6,
    ];

    /**
     * Período de amamentação até desmame natural — usado pra criar tarefa auto.
     */
    public const DESMAME_DIAS = [
        'bovino'       => 240,
        'bovino-leite' => 60,   // prática DRovet — apartar bezerro do leite cedo
        'bovino-corte' => 240,
        'equino'       => 180,
        'caprino'      => 90,
        'ovino'        => 90,
        'suino'        => 28,
        'cao'          => 45,
        'gato'         => 60,
        'coelho'       => 35,
    ];

    public function create(Request $request): Response
    {
        $taskId = (int) $request->query('task_id');
        $task = Task::with([])->find($taskId);
        if (! $task) {
            abort(404, 'Tarefa não encontrada.');
        }
        if ($task->related_type !== Animal::class || ! $task->related_id) {
            abort(400, 'Esta tarefa não está vinculada a um animal.');
        }
        if ($task->auto_action !== 'parto') {
            abort(400, 'Esta tarefa não dispara o wizard de parto.');
        }

        $mae = Animal::with(['species:id,nome,slug', 'breed:id,nome', 'lot:id,nome', 'location:id,nome'])
            ->findOrFail($task->related_id);

        $slug = $mae->species?->slug ?? 'bovino';
        $isMultiFetal = isset(self::MULTI_FETAIS[$slug]);
        $quantidadeDefault = self::MULTI_FETAIS[$slug] ?? 1;

        // Buscar machos ativos da mesma espécie pra select de pai
        $machos = Animal::ativos()
            ->where('sexo', 'M')
            ->where('species_id', $mae->species_id)
            ->select('id', 'identificacao', 'nome')
            ->orderBy('identificacao')
            ->get();

        // Lotes existentes (pra leitegada pode escolher um existente ou novo)
        $lotes = DB::table('animal_lots')
            ->where('tenant_id', $mae->tenant_id)
            ->where('farm_id', $mae->farm_id)
            ->where('is_active', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo']);

        // ID sequencial sugerido para os filhotes — gera N IDs no formato
        // [mae.identificacao]/F[NN]
        $proximosIds = [];
        for ($i = 1; $i <= max(15, $quantidadeDefault); $i++) {
            $proximosIds[] = "{$mae->identificacao}/F" . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        }

        return Inertia::render('Admin/Wizards/RegistrarParto', [
            'task' => [
                'id' => $task->id,
                'titulo' => $task->titulo,
            ],
            'mae' => [
                'id' => $mae->id,
                'identificacao' => $mae->identificacao,
                'nome' => $mae->nome,
                'especie' => $mae->species?->nome,
                'especie_slug' => $slug,
                'lote_id' => $mae->lot_id,
                'lote_nome' => $mae->lot?->nome,
                'location_id' => $mae->location_id,
                'location_nome' => $mae->location?->nome,
            ],
            'config' => [
                'is_multi_fetal' => $isMultiFetal,
                'quantidade_default' => $quantidadeDefault,
                'desmame_dias' => self::DESMAME_DIAS[$slug] ?? 60,
            ],
            'machos' => $machos,
            'lotes' => $lotes,
            'proximos_ids' => $proximosIds,
            'data_hoje' => now()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task_id' => ['required', 'exists:tasks,id'],
            'mae_id' => ['required', 'exists:animals,id'],
            'data_parto' => ['required', 'date', 'before_or_equal:today'],
            'pai_id' => ['nullable', 'exists:animals,id'],
            'lote_destino' => ['nullable', 'string'], // 'mesmo_da_mae' | 'novo:Nome' | 'existente:ID'
            'filhotes' => ['required', 'array', 'min:1', 'max:30'],
            'filhotes.*.identificacao' => ['required', 'string', 'max:50'],
            'filhotes.*.sexo' => ['required', 'in:M,F'],
            'filhotes.*.peso_nascimento' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'filhotes.*.status' => ['required', 'in:vivo,natimorto,morto_pos_parto'],
            'filhotes.*.observacao' => ['nullable', 'string', 'max:200'],
        ]);

        $task = Task::findOrFail($validated['task_id']);
        $mae = Animal::with('species', 'lot')->findOrFail($validated['mae_id']);

        $criados = 0;
        $mortos = 0;
        DB::transaction(function () use ($validated, $request, $mae, $task, &$criados, &$mortos) {

            // Resolver lote destino dos filhotes
            $lotDestinoId = $this->resolverLote(
                $validated['lote_destino'] ?? null,
                $mae,
                $validated['data_parto']
            );

            // Cadastrar cada filhote como Animal
            foreach ($validated['filhotes'] as $f) {
                $animal = Animal::create([
                    'tenant_id' => $mae->tenant_id,
                    'farm_id' => $mae->farm_id,
                    'species_id' => $mae->species_id,
                    'breed_id' => $mae->breed_id, // herda raça da mãe (editável depois)
                    'lot_id' => $lotDestinoId,
                    'location_id' => $mae->location_id,
                    'mae_id' => $mae->id,
                    'pai_id' => $validated['pai_id'] ?? null,
                    'identificacao' => $f['identificacao'],
                    'sexo' => $f['sexo'],
                    'data_nascimento' => $validated['data_parto'],
                    'peso_nascimento' => $f['peso_nascimento'] ?? null,
                    'peso_atual' => $f['peso_nascimento'] ?? null,
                    'origem' => 'nascido_propriedade',
                    'status' => $f['status'] === 'vivo' ? 'ativo' : 'morto',
                    'observacoes' => $f['observacao'] ?? null,
                    'data_saida' => $f['status'] !== 'vivo' ? $validated['data_parto'] : null,
                ]);
                $criados++;

                // Filhote morto → cria evento de mortalidade já no momento do parto
                if ($f['status'] !== 'vivo') {
                    $animal->events()->create([
                        'tipo' => 'mortalidade',
                        'data' => $validated['data_parto'],
                        'lot_id' => $lotDestinoId,
                        'observacoes' => $f['status'] === 'natimorto' ? 'Natimorto' : 'Morreu pós-parto',
                        'created_by' => $request->user()?->id,
                    ]);
                    $mortos++;
                }
            }

            // Evento PARTO no histórico da mãe
            $mae->events()->create([
                'tipo' => 'reproducao',
                'data' => $validated['data_parto'],
                'lot_id' => $mae->lot_id,
                'partner_id' => $validated['pai_id'] ?? null,
                'quantidade_animais' => count($validated['filhotes']),
                'observacoes' => 'Parto · ' . count($validated['filhotes']) . ' filhote(s) cadastrado(s)'
                    . ($mortos > 0 ? " · {$mortos} morto(s)" : '')
                    . '. Filhos: ' . collect($validated['filhotes'])->pluck('identificacao')->implode(', '),
                'created_by' => $request->user()?->id,
            ]);

            // Atualizar quantidade do lote da mãe (se gestao_modo='individual')
            if ($mae->lot_id && $lotDestinoId === $mae->lot_id) {
                $vivosNoLote = collect($validated['filhotes'])
                    ->where('status', 'vivo')
                    ->count();
                if ($vivosNoLote > 0) {
                    DB::table('animal_lots')
                        ->where('id', $mae->lot_id)
                        ->increment('quantidade_atual', $vivosNoLote);
                }
            }

            // Fechar a tarefa de parto
            $task->update([
                'status' => 'concluida',
                'concluida_em' => now(),
            ]);

            // Criar tarefa automática de desmame
            $slug = $mae->species?->slug ?? 'bovino';
            $diasDesmame = self::DESMAME_DIAS[$slug] ?? 60;
            $dataDesmame = \Carbon\Carbon::parse($validated['data_parto'])->addDays($diasDesmame);

            DB::table('tasks')->insert([
                'tenant_id' => $mae->tenant_id,
                'farm_id' => $mae->farm_id,
                'titulo' => "Desmame · filhotes da " . ($mae->nome ?: $mae->identificacao),
                'descricao' => "Apartar filhotes da mãe — {$diasDesmame} dias após o parto. "
                    . count($validated['filhotes']) . " filhote(s) registrado(s) em " . \Carbon\Carbon::parse($validated['data_parto'])->format('d/m/Y') . '.',
                'prioridade' => 'media',
                'status' => 'pendente',
                'data_vencimento' => $dataDesmame->toDateString(),
                'modulo' => 'rebanho',
                'auto_action' => null, // tarefa simples, conclui manual
                'related_type' => Animal::class,
                'related_id' => $mae->id,
                'created_by' => $request->user()?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $msg = "Parto registrado · {$criados} filhote(s) cadastrado(s) como filho(s) de "
            . ($mae->nome ?: $mae->identificacao);
        if ($mortos > 0) $msg .= " ({$mortos} morto(s))";
        $msg .= ". Tarefa de desmame criada automaticamente.";

        return redirect()->route('admin.rebanho.animais.show', $mae->id)->with('success', $msg);
    }

    /**
     * Resolve o lote de destino dos filhotes:
     *   - "mesmo_da_mae"   → lot_id da mãe
     *   - "novo:Nome"      → cria novo lote com esse nome
     *   - "existente:ID"   → usa o lote existente
     *   - null/vazio       → mesmo da mãe (fallback)
     */
    private function resolverLote(?string $opcao, Animal $mae, string $dataParto): ?int
    {
        if (! $opcao || $opcao === 'mesmo_da_mae') {
            return $mae->lot_id;
        }
        if (str_starts_with($opcao, 'existente:')) {
            return (int) substr($opcao, 10);
        }
        if (str_starts_with($opcao, 'novo:')) {
            $nome = trim(substr($opcao, 5));
            if ($nome === '') {
                $nome = 'Leitegada ' . ($mae->nome ?: $mae->identificacao) . ' ' . \Carbon\Carbon::parse($dataParto)->format('d/m/y');
            }
            $codigo = strtoupper(\Illuminate\Support\Str::slug(\Illuminate\Support\Str::limit($nome, 20, ''), '-'));
            return DB::table('animal_lots')->insertGetId([
                'tenant_id' => $mae->tenant_id,
                'farm_id' => $mae->farm_id,
                'codigo' => $codigo,
                'nome' => $nome,
                'finalidade' => 'cria',
                'gestao_modo' => 'individual',
                'quantidade_inicial' => 0,
                'quantidade_atual' => 0,
                'data_inicio' => $dataParto,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return $mae->lot_id;
    }
}
