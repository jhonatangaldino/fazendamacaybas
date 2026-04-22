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

    public function destroy(Employee $employee)
    {
        $employee->update(['is_active' => false, 'data_demissao' => $employee->data_demissao ?? now()]);

        return back()->with('success', 'Funcionário desligado (inativado).');
    }

    public function toggle(Employee $employee)
    {
        $employee->update(['is_active' => ! $employee->is_active]);

        return back();
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
            'email' => ['nullable', 'email'],
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
