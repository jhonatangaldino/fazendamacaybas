<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assistente guiado — Cadastrar funcionário.
 *
 * Fluxo:
 *   1 · Quem é?         (nome + tipo de contrato)
 *   2 · O que faz?      (cargo/setor/função)
 *   3 · Quanto e desde quando?  (salário + data admissão)
 *   4 · Contato (opcional) — telefone/email
 *   5 · Conferência + Pronto
 *
 * Submit aponta para `admin.funcionarios.store` (existente, não duplica regra).
 */
class FuncionarioWizardController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Wizards/Funcionario', [
            'tipos_contrato' => [
                ['id' => 'clt',      'rotulo' => 'CLT (carteira assinada)', 'desc' => 'Vínculo formal com salário fixo, FGTS, férias.'],
                ['id' => 'pj',       'rotulo' => 'Prestador (PJ)',          'desc' => 'Empresa contratada, NF mensal, sem vínculo CLT.'],
                ['id' => 'diarista', 'rotulo' => 'Diarista',                'desc' => 'Pago por dia trabalhado — sem vínculo permanente.'],
                ['id' => 'safrista', 'rotulo' => 'Safrista (temporário)',   'desc' => 'Contrato com início e fim definidos para a safra.'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:200'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'tipo_contrato' => ['nullable', 'in:clt,pj,diarista,safrista'],
            'funcao' => ['nullable', 'string', 'max:100'],
            'setor' => ['nullable', 'string', 'max:100'],
            'salario' => ['nullable', 'numeric', 'min:0'],
            'data_admissao' => ['nullable', 'date'],
            'data_demissao' => ['nullable', 'date', 'after_or_equal:data_admissao'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'celular' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email:rfc'],
            'observacoes' => ['nullable', 'string'],
        ]);

        // Empresa pode operar sem dados sensíveis ainda — UX cresce ao longo
        // do tempo. Wizard NÃO obriga CPF/admissão de cara; preenche depois.
        Employee::create([
            'nome' => $data['nome'],
            'cpf' => $data['cpf'] ?? null,
            'funcao' => $data['funcao'] ?? null,
            'setor' => $data['setor'] ?? null,
            'salario' => $data['salario'] ?? null,
            'data_admissao' => $data['data_admissao'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'celular' => $data['celular'] ?? null,
            'email' => $data['email'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Funcionário cadastrado.');
    }
}
