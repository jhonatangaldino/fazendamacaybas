<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Task\Checklist;
use App\Models\Task\ChecklistItem;
use App\Models\Task\Task;
use App\Models\Task\TaskAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $q = Task::with(['assignees:id,nome', 'checklists.items'])
            ->when($request->status, fn ($qq) => $qq->where('status', $request->status))
            ->when($request->prioridade, fn ($qq) => $qq->where('prioridade', $request->prioridade))
            ->when($request->modulo, fn ($qq) => $qq->where('modulo', $request->modulo))
            ->when($request->employee_id, fn ($qq) => $qq->whereHas('assignees', fn ($a) => $a->where('employees.id', $request->employee_id)))
            ->orderByRaw("CASE WHEN status = 'pendente' THEN 1 WHEN status = 'em_andamento' THEN 2 WHEN status = 'atrasada' THEN 3 ELSE 4 END")
            ->orderBy('data_vencimento');

        return Inertia::render('Admin/Tasks/Index', [
            'tasks' => $q->paginate(25)->withQueryString()->through(fn ($t) => [
                'id' => $t->id,
                'titulo' => $t->titulo,
                'descricao' => $t->descricao,
                'prioridade' => $t->prioridade,
                'status' => $t->status,
                'modulo' => $t->modulo,
                'data_inicio' => $t->data_inicio,
                'data_vencimento' => $t->data_vencimento,
                'concluida_em' => $t->concluida_em,
                'assignees' => $t->assignees->map(fn ($a) => ['id' => $a->id, 'nome' => $a->nome]),
                'checklists' => $t->checklists->map(fn ($c) => [
                    'id' => $c->id,
                    'titulo' => $c->titulo,
                    'items' => $c->items->map(fn ($i) => [
                        'id' => $i->id, 'descricao' => $i->descricao, 'is_done' => $i->is_done,
                    ]),
                ]),
            ]),
            'filters' => $request->only(['status', 'prioridade', 'modulo', 'employee_id']),
            'employees' => Employee::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
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
        ]);
    }
}
