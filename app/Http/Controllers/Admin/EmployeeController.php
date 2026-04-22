<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $q = Employee::with('farm:id,nome')
            ->when($request->search, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('nome', 'like', "%{$request->search}%")
                ->orWhere('cpf', 'like', "%{$request->search}%")))
            ->when($request->setor, fn ($qq) => $qq->where('setor', $request->setor))
            ->when($request->status === 'inativos', fn ($qq) => $qq->where('is_active', false))
            ->when(! $request->status || $request->status === 'ativos', fn ($qq) => $qq->where('is_active', true))
            ->orderBy('nome');

        return Inertia::render('Admin/Employees/Index', [
            'employees' => $q->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'setor', 'status']),
            'farms' => Farm::where('is_active', true)->get(['id', 'nome']),
            'setores' => Employee::query()->whereNotNull('setor')->distinct()->pluck('setor'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateEmployee($request);
        Employee::create($data);

        return back()->with('success', 'Funcionário cadastrado.');
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validateEmployee($request, $employee->id);
        $employee->update($data);

        return back()->with('success', 'Funcionário atualizado.');
    }

    public function destroy(Request $request, Employee $employee)
    {
        if (! $employee->is_active) {
            return back()->with('error', 'Este funcionário já está desligado.');
        }

        $data = $request->validate([
            'data_demissao' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.($employee->data_admissao?->toDateString() ?? '1900-01-01')],
            'motivo_demissao' => ['nullable', 'string', 'max:255'],
        ], [
            'data_demissao.required' => 'Informe a data de desligamento.',
            'data_demissao.before_or_equal' => 'A data de desligamento não pode ser futura.',
            'data_demissao.after_or_equal' => 'A data de desligamento não pode ser anterior à data de admissão.',
        ]);

        $employee->update([
            'is_active' => false,
            'data_demissao' => $data['data_demissao'],
            'observacoes' => $data['motivo_demissao']
                ? trim(($employee->observacoes ? $employee->observacoes."\n" : '')."[Desligamento em ".$data['data_demissao']."] ".$data['motivo_demissao'])
                : $employee->observacoes,
        ]);

        return back()->with('success', 'Funcionário desligado em '.\Carbon\Carbon::parse($data['data_demissao'])->format('d/m/Y').'.');
    }

    /**
     * Reativa um funcionário previamente desligado (limpa data_demissao).
     */
    public function toggle(Employee $employee)
    {
        if ($employee->is_active) {
            // Usar o endpoint de destroy para "desligar" com data exige ir pelo modal
            return back()->with('error', 'Para desligar, clique em "Desligar" e informe a data.');
        }

        $employee->update([
            'is_active' => true,
            'data_demissao' => null,
        ]);

        return back()->with('success', 'Funcionário reativado.');
    }

    protected function validateEmployee(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'farm_id' => ['nullable', 'exists:farms,id'],
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:14', Rule::unique('employees', 'cpf')->ignore($id)->whereNull('deleted_at')],
            'rg' => ['nullable', 'string', 'max:20'],
            'data_nascimento' => ['nullable', 'date'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'celular' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'setor' => ['nullable', 'string', 'max:100'],
            'funcao' => ['nullable', 'string', 'max:100'],
            'salario' => ['nullable', 'numeric', 'min:0'],
            'data_admissao' => ['nullable', 'date'],
            'data_demissao' => ['nullable', 'date'],
            'cep' => ['nullable', 'string', 'max:9'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'size:2'],
            'observacoes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
