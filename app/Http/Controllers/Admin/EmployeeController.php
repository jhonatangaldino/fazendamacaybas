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

    /**
     * ═════ D9 — Consolidação de Domínio · FUNCIONÁRIOS ═════
     *
     * Regras por tipo de vínculo de trabalho (clt/pj/diarista/safrista).
     *
     * DESAFIO DE SCHEMA:
     *   `employees` NÃO tem coluna `tipo_contrato`, nem `cnpj`, nem
     *   colunas específicas para período de safrista. Mas:
     *     - `cpf` é VARCHAR(14) — acomoda CNPJ tecnicamente (14 chars)
     *     - `data_admissao` / `data_demissao` (nullable) cobrem período
     *
     * ESTRATÉGIA:
     *   - `tipo_contrato` é aceito NO VALIDATOR (enum clt/pj/diarista/safrista)
     *     mas NÃO é persistido — schema não tem a coluna, valor é extraído
     *     e usado apenas para validação.
     *   - PJ usa o campo `cpf` para armazenar CNPJ (mesmo padrão de
     *     `partners.documento`).
     *   - Safrista: período = data_admissao + data_demissao ambas preenchidas.
     *
     * RETROCOMPAT:
     *   - UI atual NÃO envia `tipo_contrato` → valor null → regra não dispara.
     *     Todos os funcionários legados e novos sem `tipo_contrato` passam.
     *   - Quando UI futura (D9.1) enviar `tipo_contrato`, regra aplica.
     */

    public function store(Request $request)
    {
        $data = $this->validateEmployee($request);

        // D9: extrai tipo_contrato (não persistido — schema não tem coluna)
        $tipoContrato = $data['tipo_contrato'] ?? null;
        unset($data['tipo_contrato']);

        if ($err = $this->validateDomainCoherence($data, $tipoContrato, null)) {
            return back()->withInput()->with('error', $err);
        }

        Employee::create($data);

        return back()->with('success', 'Funcionário cadastrado.');
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validateEmployee($request, $employee->id);

        $tipoContrato = $data['tipo_contrato'] ?? null;
        unset($data['tipo_contrato']);

        if ($err = $this->validateDomainCoherence($data, $tipoContrato, $employee)) {
            return back()->withInput()->with('error', $err);
        }

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
            // D9: aceito como nullable — UI atual não envia; quando
            // a UI expor o tipo de contrato, o valor é extraído em store/
            // update (via unset) e usado em validateDomainCoherence.
            // NÃO é persistido — schema não tem coluna tipo_contrato.
            'tipo_contrato' => ['nullable', 'string', Rule::in(['clt', 'pj', 'diarista', 'safrista'])],
        ]);
    }

    /**
     * D9 · Valida coerência entre `tipo_contrato` (informado pelo caller)
     * e os campos `cpf`, `data_admissao`, `data_demissao`.
     *
     * Regras:
     *   - clt       → cpf (CPF válido 11d) + data_admissao
     *   - pj        → cpf (com CNPJ válido 14c; aceita alfanumérico CGSIM 2026)
     *   - diarista  → cpf (CPF válido 11d); data_admissao opcional
     *   - safrista  → cpf (CPF válido 11d) + data_admissao + data_demissao
     *
     * Retrocompat:
     *   - Se `tipo_contrato` NÃO é informado → retorna null (passa).
     *     UI atual nunca envia o campo, então zero funcionários são
     *     afetados enquanto a UI não for evoluída.
     *   - Em UPDATE, regra só dispara se `tipo_contrato` foi informado
     *     no payload. Se UI não envia, update passa sem checagem extra.
     */
    protected function validateDomainCoherence(array $data, ?string $tipoContrato, ?Employee $existing): ?string
    {
        if (! $tipoContrato) {
            return null; // retrocompat: sem tipo → sem regra
        }

        $doc = trim((string) ($data['cpf'] ?? ''));
        $adm = $data['data_admissao'] ?? null;
        $dem = $data['data_demissao'] ?? null;

        switch ($tipoContrato) {
            case 'clt':
                if ($doc === '') {
                    return 'Funcionários CLT exigem CPF. Preencha o campo "CPF" com um CPF válido (11 dígitos). '
                        . 'CLT é vínculo formal de trabalho — sem CPF não é possível emitir holerite, INSS, FGTS etc.';
                }
                if (! validarCpfStrict($doc)) {
                    return 'O CPF informado é inválido (dígitos verificadores não conferem). '
                        . 'Funcionários CLT exigem CPF válido — verifique os números digitados. '
                        . 'Se esta pessoa é prestador pessoa jurídica, troque o tipo de contrato para "PJ".';
                }
                if (! $adm) {
                    return 'Funcionários CLT exigem data de admissão. Preencha "Data de admissão" — '
                        . 'esse marco é obrigatório para CLT (contagem de tempo de serviço, férias, 13º, etc.).';
                }

                return null;

            case 'pj':
                // Para PJ, o campo "cpf" armazena o CNPJ (schema não tem coluna
                // cnpj dedicada — mesma estratégia de partners.documento).
                if ($doc === '') {
                    return 'Prestadores PJ exigem CNPJ. Preencha o campo "CPF" (usado também para CNPJ, 14 caracteres) '
                        . 'com um CNPJ válido. PJ não é vínculo CLT — é prestação de serviço via pessoa jurídica.';
                }
                if (strlen(apenasAlfaNum($doc)) !== 14) {
                    return 'Prestadores PJ exigem CNPJ válido com 14 caracteres. O documento informado tem '
                        . strlen(apenasAlfaNum($doc)) . ' caractere(s). '
                        . 'Se digitou um CPF (11 dígitos), troque o tipo de contrato para "CLT", "Diarista" ou "Safrista".';
                }
                if (! validarCnpjStrict($doc)) {
                    return 'O CNPJ informado é inválido (dígitos verificadores não conferem). '
                        . 'O sistema aceita formato alfanumérico conforme Resolução CGSIM 2026.';
                }

                return null;

            case 'diarista':
                if ($doc === '') {
                    return 'Diaristas exigem CPF. Preencha o campo "CPF" com um CPF válido (11 dígitos). '
                        . 'Mesmo sem vínculo contínuo, o CPF é necessário para emitir recibo e declarar pagamento.';
                }
                if (! validarCpfStrict($doc)) {
                    return 'O CPF informado é inválido (dígitos verificadores não conferem). '
                        . 'Diaristas são pessoas físicas — exigem CPF válido. '
                        . 'Se é prestador PJ, troque o tipo para "PJ".';
                }

                return null;

            case 'safrista':
                if ($doc === '') {
                    return 'Safristas exigem CPF. Preencha o campo "CPF" com um CPF válido (11 dígitos).';
                }
                if (! validarCpfStrict($doc)) {
                    return 'O CPF informado é inválido (dígitos verificadores não conferem). '
                        . 'Safristas são pessoas físicas — exigem CPF válido.';
                }
                if (! $adm || ! $dem) {
                    $faltando = [];
                    if (! $adm) $faltando[] = 'data de início (admissão)';
                    if (! $dem) $faltando[] = 'data de fim (desligamento)';

                    return 'Safristas exigem período completo (início e fim da safra). Falta preencher: '
                        . implode(' e ', $faltando) . '. '
                        . 'Safra é contrato por tempo determinado — ambas as datas são obrigatórias. '
                        . 'Se o contrato é indeterminado, troque o tipo para "CLT".';
                }

                return null;
        }

        return null; // tipo fora do enum já foi bloqueado pelo validator base
    }
}
