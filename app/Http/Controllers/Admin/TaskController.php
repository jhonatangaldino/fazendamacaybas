<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agricultural\Field;
use App\Models\Agricultural\Planting;
use App\Models\Employee;
use App\Models\Financial\FinancialTransaction;
use App\Models\Livestock\Animal;
use App\Models\Partner;
use App\Models\Stock\StockItem;
use App\Models\Task\Checklist;
use App\Models\Task\ChecklistItem;
use App\Models\Task\Task;
use App\Models\Task\TaskAssignment;
use App\Models\Vehicle\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TaskController extends Controller
{
    /**
     * ═════ F3 · UX anti-erro — TAREFAS ═════
     *
     * Schema já tinha `nullableMorphs('related')` desde a migration inicial,
     * mas o validator NÃO expunha related_type/related_id. Resultado: a UI
     * não conseguia gravar vínculo polimórfico, e toda tarefa nascia "órfã".
     *
     * Esta refatoração adiciona:
     *   1. Whitelist de related_type no validator (espelho do padrão D8).
     *   2. Persistência dos campos em store/update.
     *   3. Exposição de `linkables` no payload Inertia (mesmo padrão usado
     *      em Documents, Financial e Animais).
     *
     * RETROCOMPAT:
     *   - related_type/related_id continuam NULLABLE — tarefas legadas sem
     *     vínculo continuam funcionando.
     *   - A regra "toda tarefa DEVE ter vínculo + responsável" fica na UI
     *     (F3). Backend permanece permissivo para não quebrar automações
     *     ou APIs externas atuais.
     */

    /**
     * Modelos aceitos como vínculo polimórfico de tarefa.
     * FQCN idêntico ao que o Eloquent persiste em `related_type`.
     */
    private const RELATED_TYPES_WHITELIST = [
        'App\\Models\\Partner',
        'App\\Models\\Livestock\\Animal',
        'App\\Models\\Vehicle\\Vehicle',
        'App\\Models\\Financial\\FinancialTransaction',
        'App\\Models\\Agricultural\\Planting',
        'App\\Models\\Agricultural\\Field',
        'App\\Models\\Stock\\StockItem',
    ];

    public function index(Request $request)
    {
        $q = Task::with(['assignees:id,nome', 'checklists.items'])
            ->when($request->status, fn ($qq) => $qq->where('status', $request->status))
            ->when($request->prioridade, fn ($qq) => $qq->where('prioridade', $request->prioridade))
            ->when($request->modulo, fn ($qq) => $qq->where('modulo', $request->modulo))
            ->when($request->employee_id, fn ($qq) => $qq->whereHas('assignees', fn ($a) => $a->where('employees.id', $request->employee_id)))
            ->orderByRaw("CASE WHEN status = 'pendente' THEN 1 WHEN status = 'em_andamento' THEN 2 WHEN status = 'atrasada' THEN 3 ELSE 4 END")
            ->orderBy('data_vencimento');

        $tasksPaginated = $q->paginate(25)->withQueryString();

        // Pre-carrega resumo do related (Animal/Lote/Field/StockItem) numa query
        // por tipo — evita N+1. Antes a UI mostrava só "Animal #78" sem nome.
        $relatedResumos = $this->resolverRelatedResumos($tasksPaginated->items());

        $tasksPaginated->through(function ($t) use ($relatedResumos) {
            $relatedKey = $t->related_type ? "{$t->related_type}::{$t->related_id}" : null;
            return [
                'id' => $t->id,
                'titulo' => $t->titulo,
                'descricao' => $t->descricao,
                'prioridade' => $t->prioridade,
                'status' => $t->status,
                'modulo' => $t->modulo,
                'data_inicio' => $t->data_inicio,
                'data_vencimento' => $t->data_vencimento,
                'concluida_em' => $t->concluida_em,
                // F3: expor vínculo para UI pré-preencher ao editar
                'related_type' => $t->related_type,
                'related_id' => $t->related_id,
                // Resumo amigável: { tipo: 'Animal', label: 'BR-001 · Mimosa', url: '/admin/...', meta: '450kg · Vacas Leiteiras' }
                'related' => $relatedKey ? ($relatedResumos[$relatedKey] ?? null) : null,
                'assignees' => $t->assignees->map(fn ($a) => ['id' => $a->id, 'nome' => $a->nome]),
                'checklists' => $t->checklists->map(fn ($c) => [
                    'id' => $c->id,
                    'titulo' => $c->titulo,
                    'items' => $c->items->map(fn ($i) => [
                        'id' => $i->id, 'descricao' => $i->descricao, 'is_done' => $i->is_done,
                    ]),
                ]),
            ];
        });

        return Inertia::render('Admin/Tasks/Index', [
            'tasks' => $tasksPaginated,
            'filters' => $request->only(['status', 'prioridade', 'modulo', 'employee_id']),
            'employees' => Employee::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),

            // Resumo de valor — ajuda a decidir o que resolver PRIMEIRO.
            // 1 query agregada com CASE — barata para Hostinger.
            'resumo' => Task::selectRaw("
                SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
                SUM(CASE WHEN status IN ('pendente','em_andamento') AND data_vencimento < CURDATE() THEN 1 ELSE 0 END) as atrasadas,
                SUM(CASE WHEN status IN ('pendente','em_andamento') AND data_vencimento = CURDATE() THEN 1 ELSE 0 END) as hoje,
                SUM(CASE WHEN status = 'concluida' AND concluida_em >= CURDATE() THEN 1 ELSE 0 END) as concluidas_hoje
            ")->first(),

            // F3 · Entidades linkáveis para vínculo polimórfico.
            // Mesmo padrão de Documents: carregar listas moderadas.
            // Transactions limitadas a 200 mais recentes (o típico é
            // vincular tarefa a lançamento recente — ex.: "cobrar este
            // boleto", "conciliar esta NF").
            'linkables' => [
                'partners' => Partner::where('is_active', true)
                    ->orderBy('nome')
                    ->get(['id', 'nome', 'pessoa']),
                'animals' => Animal::where('status', 'ativo')
                    ->orderBy('identificacao')
                    ->get(['id', 'identificacao', 'nome']),
                'vehicles' => Vehicle::where('is_active', true)
                    ->orderBy('nome')
                    ->get(['id', 'nome', 'tipo', 'placa']),
                'plantings' => Planting::with(['field:id,nome', 'crop:id,nome'])
                    ->orderByDesc('data_plantio')
                    ->limit(500)
                    ->get(['id', 'field_id', 'crop_id', 'data_plantio', 'status']),
                'fields' => Field::where('is_active', true)
                    ->orderBy('nome')
                    ->get(['id', 'nome']),
                'transactions' => FinancialTransaction::orderByDesc('data_vencimento')
                    ->limit(200)
                    ->get(['id', 'descricao', 'valor', 'tipo', 'data_vencimento', 'status']),
                'stock_items' => StockItem::where('is_active', true)
                    ->orderBy('nome')
                    ->limit(500)
                    ->get(['id', 'nome', 'codigo', 'tipo']),
            ],
        ]);
    }

    /**
     * Resolve resumos amigáveis dos vínculos polimórficos de tarefas.
     * Antes a UI mostrava só "Animal #78" — agora vê "BR-001 · Mimosa · 450kg".
     * Carrega 1 query por TIPO (não N+1) e devolve indexado por "type::id".
     */
    private function resolverRelatedResumos(array $tasks): array
    {
        $byType = [];
        foreach ($tasks as $t) {
            if (! $t->related_type || ! $t->related_id) continue;
            $byType[$t->related_type][] = $t->related_id;
        }
        $resumos = [];

        // Animal — incluir nome + peso + lote
        if (! empty($byType['App\\Models\\Livestock\\Animal'])) {
            $animals = \App\Models\Livestock\Animal::with([
                    'lot:id,nome',
                    'species' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nome', 'slug'),
                ])
                ->whereIn('id', array_unique($byType['App\\Models\\Livestock\\Animal']))
                ->get(['id', 'identificacao', 'nome', 'peso_atual', 'lot_id', 'species_id', 'status']);
            foreach ($animals as $a) {
                $meta = [];
                if ($a->peso_atual) $meta[] = number_format((float) $a->peso_atual, 1, ',', '.') . ' kg';
                if ($a->lot?->nome) $meta[] = '🏷 ' . $a->lot->nome;
                if ($a->species?->nome) $meta[] = $a->species->nome;
                $resumos["App\\Models\\Livestock\\Animal::{$a->id}"] = [
                    'tipo' => 'Animal',
                    'icon' => '🐾',
                    'label' => trim($a->identificacao . ($a->nome ? ' · ' . $a->nome : '')),
                    'meta' => implode(' · ', $meta),
                    'url' => route('admin.rebanho.animais.show', $a->id),
                    'status' => $a->status,
                ];
            }
        }

        // AnimalLot
        if (! empty($byType['App\\Models\\Livestock\\AnimalLot'])) {
            $lots = \App\Models\Livestock\AnimalLot::with([
                    'species' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nome'),
                ])
                ->whereIn('id', array_unique($byType['App\\Models\\Livestock\\AnimalLot']))
                ->get(['id', 'nome', 'codigo', 'quantidade_atual', 'species_id', 'is_active']);
            foreach ($lots as $l) {
                $meta = [];
                if ($l->quantidade_atual) $meta[] = (int) $l->quantidade_atual . ' cabeças';
                if ($l->species?->nome) $meta[] = $l->species->nome;
                $resumos["App\\Models\\Livestock\\AnimalLot::{$l->id}"] = [
                    'tipo' => 'Lote',
                    'icon' => '🏷',
                    'label' => $l->nome,
                    'meta' => implode(' · ', $meta),
                    'url' => route('admin.rebanho.lotes.edit', $l->id),
                    'status' => $l->is_active ? 'ativo' : 'inativo',
                ];
            }
        }

        // Outros tipos podem entrar aqui no futuro (Field, StockItem...)
        return $resumos;
    }

    public function store(Request $request)
    {
        $data = $this->validateTask($request);
        $assignees = $request->input('assignees', []);
        $checklistItems = $request->input('checklist_items', []);

        $task = Task::create(array_merge($data, ['created_by' => $request->user()->id]));

        foreach ($assignees as $empId) {
            TaskAssignment::create(['task_id' => $task->id, 'employee_id' => $empId]);
        }

        if (! empty($checklistItems)) {
            $cl = Checklist::create(['task_id' => $task->id, 'titulo' => 'Checklist']);
            foreach ($checklistItems as $i => $desc) {
                if (trim($desc) === '') continue;
                ChecklistItem::create(['checklist_id' => $cl->id, 'descricao' => $desc, 'order_column' => $i]);
            }
        }

        return back()->with('success', 'Tarefa criada.');
    }

    public function update(Request $request, Task $task)
    {
        $data = $this->validateTask($request);
        $task->update($data);

        if ($request->has('assignees')) {
            $assignees = $request->input('assignees', []);
            $task->assignments()->delete();
            foreach ($assignees as $empId) {
                TaskAssignment::create(['task_id' => $task->id, 'employee_id' => $empId]);
            }
        }

        return back()->with('success', 'Tarefa atualizada.');
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return back()->with('success', 'Tarefa excluída.');
    }

    public function complete(Task $task)
    {
        // Se a tarefa tem auto_action, em vez de simplesmente fechar, redireciona
        // para o wizard correspondente (que vai fechar a tarefa após o submit).
        // Caso de uso: tarefa "Registrar parto" com auto_action='parto' criada
        // pelo ExameToqueController → abre wizard pra cadastrar filhotes.
        if ($task->auto_action === 'parto' && $task->status !== 'concluida') {
            return redirect()->route('admin.fluxos.registrar-parto', ['task_id' => $task->id]);
        }

        $task->update(['status' => 'concluida', 'concluida_em' => now()]);

        return back()->with('success', 'Tarefa concluída.');
    }

    public function reopen(Task $task)
    {
        $task->update(['status' => 'pendente', 'concluida_em' => null]);

        return back()->with('success', 'Tarefa reaberta.');
    }

    public function toggleChecklistItem(ChecklistItem $item)
    {
        $item->update([
            'is_done' => ! $item->is_done,
            'done_at' => ! $item->is_done ? now() : null,
            'done_by' => ! $item->is_done ? auth()->id() : null,
        ]);

        return back();
    }

    protected function validateTask(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'prioridade' => ['required', 'in:baixa,media,alta,urgente'],
            'status' => ['required', 'in:pendente,em_andamento,concluida,cancelada,atrasada'],
            'data_inicio' => ['nullable', 'date'],
            'data_vencimento' => ['nullable', 'date'],
            'modulo' => ['nullable', 'string', 'max:30'],
            'farm_id' => ['nullable', 'exists:farms,id'],

            // F3: vínculo polimórfico (nullable para retrocompat — tarefas
            // legadas/automações continuam válidas sem vínculo. A exigência
            // é imposta na UI).
            'related_type' => ['nullable', 'string', 'max:191', Rule::in(self::RELATED_TYPES_WHITELIST)],
            'related_id' => ['nullable', 'integer'],
        ]);
    }
}
