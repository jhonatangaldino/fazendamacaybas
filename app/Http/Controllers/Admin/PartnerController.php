<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PartnerController extends Controller
{
    /**
     * ═════ D4 — Consolidação de Domínio · PARCEIROS ═════
     *
     * Regras legais brasileiras: CPF (11 dígitos) para pessoa física, CNPJ
     * (14 dígitos, possivelmente alfanumérico por Resolução CGSIM 2026)
     * para pessoa jurídica. Validação com DV usando os helpers globais
     * `validarCpfStrict` e `validarCnpjStrict` de app/Support/br-validators.php.
     *
     * O schema usa `pessoa` (pf|pj) — campo que representa a natureza jurídica.
     * O brief chama de "tipo = pessoa_fisica/pessoa_juridica"; aplicamos a
     * regra no campo real `pessoa`.
     *
     * RETROCOMPAT:
     *   - Em UPDATE, as regras só disparam quando `pessoa` OU `documento`
     *     mudou. Parceiros legados com documento formatado, com CPF em
     *     PJ etc. continuam editáveis sem forçar retrabalho, desde que
     *     o usuário não mexa nos campos afetados.
     *   - Cadastros novos (store) sempre validam.
     */
    public function index(Request $request)
    {
        $q = Partner::query()
            ->when($request->search, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('nome', 'like', "%{$request->search}%")
                ->orWhere('documento', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")))
            ->when($request->tipo, fn ($qq) => $qq->where('tipo', $request->tipo))
            ->orderBy('nome');

        return Inertia::render('Admin/Partners/Index', [
            'partners' => $q->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'tipo']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Partners/Form', ['partner' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePartner($request);

        // D4 · Coerência pessoa ↔ documento
        if ($err = $this->validateDomainCoherence($data, null)) {
            return back()->withInput()->with('error', $err);
        }

        Partner::create($data);

        return redirect()->route('admin.parceiros.index')->with('success', 'Parceiro cadastrado.');
    }

    /**
     * Endpoint inline (AJAX/JSON) — cria um parceiro mínimo (nome + tipo)
     * sem redirect, para ser usado em modais dentro de outros fluxos
     * (venda de animal, registro de despesa/receita, etc.).
     *
     * Aceita só os campos essenciais; o parceiro pode ser completado depois
     * em /admin/parceiros/{id}/editar pela ficha completa.
     */
    public function storeInline(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:200'],
            'tipo' => ['required', 'in:cliente,fornecedor,ambos'],
            // `pessoa` no DB é varchar(2) — aceita valores curtos ('pf'/'pj')
            // OU os longos vindos do front ('fisica'/'juridica'). Convertemos.
            'pessoa' => ['nullable', 'in:fisica,juridica,pf,pj'],
            'documento' => ['nullable', 'string', 'max:30'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:200'],
        ]);

        $pessoa = match ($data['pessoa'] ?? 'fisica') {
            'fisica', 'pf' => 'pf',
            'juridica', 'pj' => 'pj',
            default => 'pf',
        };

        $partner = Partner::create([
            'nome' => $data['nome'],
            'tipo' => $data['tipo'],
            'pessoa' => $pessoa,
            'documento' => $data['documento'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'id' => $partner->id,
            'nome' => $partner->nome,
            'tipo' => $partner->tipo,
            'pessoa' => $partner->pessoa,
            'documento' => $partner->documento,
            'telefone' => $partner->telefone,
        ]);
    }

    public function edit(Partner $parceiro)
    {
        return Inertia::render('Admin/Partners/Form', ['partner' => $parceiro]);
    }

    public function update(Request $request, Partner $parceiro)
    {
        $data = $this->validatePartner($request, $parceiro->id);

        // D4 · Coerência pessoa ↔ documento — aplicada em update apenas se
        // `pessoa` OU `documento` mudou (retrocompat p/ cadastros legados).
        if ($err = $this->validateDomainCoherence($data, $parceiro)) {
            return back()->withInput()->with('error', $err);
        }

        $parceiro->update($data);

        return redirect()->route('admin.parceiros.index')->with('success', 'Parceiro atualizado.');
    }

    /**
     * F8-C1 (QA Deep 2026-04-29) · parceiro com transações financeiras
     * vinculadas não pode ser excluído. Antes soft-deletava direto e os
     * joins financeiros depois retornavam parceiro NULL, perdendo
     * rastreabilidade do histórico.
     *
     * Agora:
     *   - Tem transações → bloqueia, sugere inativar (toggle is_active)
     *   - Sem transações → soft-delete normal
     *
     * Coerente com o padrão de Vehicle/Field (já têm proteção).
     */
    public function destroy(Partner $parceiro)
    {
        $countTrans = \DB::table('financial_transactions')
            ->where('partner_id', $parceiro->id)
            ->count();

        if ($countTrans > 0) {
            return back()->with('error', "Este parceiro tem {$countTrans} transação(ões) vinculada(s). Inative-o ao invés de excluir, pra preservar o histórico financeiro.");
        }

        $parceiro->delete();

        return back()->with('success', 'Parceiro excluído.');
    }

    protected function validatePartner(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'tipo' => ['required', 'in:fornecedor,cliente,ambos'],
            'pessoa' => ['required', 'in:pf,pj'],
            'nome' => ['required', 'string', 'max:255'],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:18', Rule::unique('partners', 'documento')->ignore($id)->whereNull('deleted_at')],
            'inscricao_estadual' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'celular' => ['nullable', 'string', 'max:20'],
            'cep' => ['nullable', 'string', 'max:9'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'size:2'],
            'observacoes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * D4 · Valida coerência entre `pessoa` e `documento` conforme regras
     * legais brasileiras (CPF 11 dígitos p/ PF; CNPJ 14 alfanuméricos p/ PJ).
     *
     * Retorna string de erro amigável ou null se tudo coerente.
     *
     * Regras aplicadas:
     *   1. pessoa=pf → `documento` obrigatório, deve ser CPF válido com DV
     *   2. pessoa=pj → `documento` obrigatório, deve ser CNPJ válido com DV
     *      (aceita formato alfanumérico por Resolução CGSIM 2026)
     *   3. Bloqueia incoerência — CPF 11 dígitos em PJ ou CNPJ 14 em PF
     *
     * Em UPDATE, só dispara se `pessoa` ou `documento` MUDARAM.
     */
    protected function validateDomainCoherence(array $data, ?Partner $existing): ?string
    {
        $pessoa = $data['pessoa'] ?? null;
        $docNovo = trim((string) ($data['documento'] ?? ''));

        if (! in_array($pessoa, ['pf', 'pj'], true)) {
            return null; // já validado pelo validator base
        }

        // Em update, só valida se pessoa ou documento mudaram — preserva legado.
        if ($existing !== null) {
            $pessoaMudou = $pessoa !== $existing->pessoa;
            $docMudou = $docNovo !== (string) ($existing->documento ?? '');
            if (! $pessoaMudou && ! $docMudou) {
                return null;
            }
        }

        // Parceiro INFORMAL: nome só basta. Documento é opcional — só validamos
        // formato/coerência quando o usuário decidiu preenchê-lo. Caso real:
        // pequeno produtor que vende leite pro vizinho sem nota, ou comprador
        // ocasional na feira que não quer dar CPF.
        if ($docNovo === '') {
            return null;
        }

        // Normaliza — remove máscara (pontuação) para checar dígitos/alfa.
        $digitsOnly = apenasDigitos($docNovo);
        $alphaNum = apenasAlfaNum($docNovo);

        // ── Regra PF ────────────────────────────────────────────────────────
        if ($pessoa === 'pf') {
            if (strlen($digitsOnly) !== 11) {
                return 'Pessoa física exige CPF válido com 11 dígitos. '
                    . "O documento informado tem " . strlen($digitsOnly) . ' dígito(s). '
                    . 'Se digitou um CNPJ (14 dígitos), troque o tipo do parceiro para "Pessoa jurídica".';
            }
            if (! validarCpfStrict($docNovo)) {
                return 'O CPF informado é inválido (dígitos verificadores não conferem). '
                    . 'Verifique os números digitados e tente novamente.';
            }

            return null;
        }

        // ── Regra PJ ────────────────────────────────────────────────────────
        // $pessoa === 'pj'
        if (strlen($alphaNum) !== 14) {
            return 'Pessoa jurídica exige CNPJ válido com 14 caracteres. '
                . "O documento informado tem " . strlen($alphaNum) . ' caractere(s). '
                . 'Se digitou um CPF (11 dígitos), troque o tipo do parceiro para "Pessoa física".';
        }
        if (! validarCnpjStrict($docNovo)) {
            return 'O CNPJ informado é inválido (dígitos verificadores não conferem). '
                . 'Verifique os números digitados. O sistema aceita o formato alfanumérico '
                . 'conforme Resolução CGSIM 2026.';
        }

        return null;
    }
}
