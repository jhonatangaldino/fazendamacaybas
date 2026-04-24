<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Livestock\Animal;
use App\Models\Partner;
use App\Models\Task\Task;
use App\Models\Vehicle\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assistente guiado — Criar tarefa.
 *
 * Fluxo:
 *   1 · O que é pra fazer?      (título + descrição)
 *   2 · Sobre o quê?            (módulo + entidade vinculada)
 *   3 · Quem faz e quando?      (responsável + prazo)
 *   4 · Confere?                (resumo)
 *   5 · Pronto!                 (sucesso)
 *
 * Submit próprio retorna `back()` (TaskController::store redireciona
 * para `tarefas.index` — não serviria ao fluxo do wizard).
 */
class TarefaWizardController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Wizards/Tarefa', [
            'funcionarios' => Employee::where('is_active', true)
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'linkables' => [
                'parceiros' => Partner::orderBy('nome')->get(['id', 'nome']),
                'animais' => Animal::ativos()->orderBy('identificacao')->get(['id', 'identificacao', 'nome']),
                'veiculos' => Vehicle::where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string'],
            'modulo' => ['required', 'in:rebanho,agricola,estoque,maquinas,financeiro,geral'],
            'prioridade' => ['required', 'in:baixa,media,alta,urgente'],
            'data_vencimento' => ['nullable', 'date'],
            'related_type' => ['required', 'string'],
            'related_id' => ['required', 'integer'],
            'assignees' => ['required', 'array', 'min:1'],
            'assignees.*' => ['integer', 'exists:employees,id'],
        ]);

        $task = Task::create([
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'] ?? null,
            'modulo' => $data['modulo'],
            'prioridade' => $data['prioridade'],
            'status' => 'pendente',
            'data_vencimento' => $data['data_vencimento'] ?? null,
            'related_type' => $data['related_type'],
            'related_id' => $data['related_id'],
            'created_by' => $request->user()->id,
        ]);
        $task->assignees()->sync($data['assignees']);

        return back()->with('success', 'Tarefa criada.');
    }
}
