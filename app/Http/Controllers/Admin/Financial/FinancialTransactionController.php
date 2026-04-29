<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CostCenter;
use App\Models\Financial\FinancialAccount;
use App\Models\Financial\FinancialTransaction;
use App\Models\Partner;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinancialTransactionController extends Controller
{
    public function index(Request $request)
    {
        $q = FinancialTransaction::query()
            ->with(['account:id,nome', 'category:id,nome,tipo', 'costCenter:id,nome', 'partner:id,nome'])
            ->when($request->tipo, fn ($qq) => $qq->where('tipo', $request->tipo))
            ->when($request->status, fn ($qq) => $qq->where('status', $request->status))
            ->when($request->account_id, fn ($qq) => $qq->where('account_id', $request->account_id))
            ->when($request->from, fn ($qq) => $qq->where('data_vencimento', '>=', $request->from))
            ->when($request->to, fn ($qq) => $qq->where('data_vencimento', '<=', $request->to))
            ->orderByDesc('data_vencimento');

        $receitasTotal = (clone $q)->where('tipo', 'receita')->sum('valor');
        $despesasTotal = (clone $q)->where('tipo', 'despesa')->sum('valor');

        return Inertia::render('Admin/Financial/Transactions/Index', [
            'transactions' => $q->paginate(25)->withQueryString(),
            'filters' => $request->only(['tipo', 'status', 'account_id', 'from', 'to']),
            'accounts' => FinancialAccount::where('is_active', true)->get(['id', 'nome']),
            'totais' => [
                'receitas' => (float) $receitasTotal,
                'despesas' => (float) $despesasTotal,
                'saldo' => (float) $receitasTotal - (float) $despesasTotal,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Financial/Transactions/Form', [
            'transaction' => null,
            'accounts' => FinancialAccount::where('is_active', true)->get(['id', 'nome']),
            'categoriasReceita' => Category::tipo('financeiro_receita')->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'categoriasDespesa' => Category::tipo('financeiro_despesa')->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'costCenters' => CostCenter::where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'codigo']),
            'partners' => Partner::where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'tipo']),
        ]);
    }

    /**
     * ═════ D6 — Consolidação de Domínio · FINANCEIRO ═════
     *
     * Garante coerência contábil entre o `tipo` do lançamento
     * (receita | despesa | transferencia) e o `category_id` escolhido.
     * Categorias são tipificadas em `categories.tipo` com valores
     * `financeiro_receita` e `financeiro_despesa` (seedados pelo
     * CategorySeeder). Transferências entre contas são permitidas sem
     * categoria (fora do escopo contábil de receita/despesa).
     *
     * RETROCOMPAT:
     *   - Em UPDATE, regra só dispara quando `tipo` OU `category_id`
     *     mudou. Lançamentos legados com categoria incoerente continuam
     *     editáveis sem forçar retrabalho enquanto o usuário não mexer
     *     nos campos afetados.
     *   - `category_id` continua nullable no validator base — a regra
     *     só valida se a categoria FOI informada.
     */

    /** Mapa: tipo de transação → tipo de categoria esperado em `categories.tipo`. */
    private const CATEGORIA_ESPERADA_POR_TIPO_TRANSACAO = [
        'receita' => 'financeiro_receita',
        'despesa' => 'financeiro_despesa',
        // 'transferencia' intencionalmente fora — não se enquadra no
        // plano de contas receita/despesa.
    ];

    public function store(Request $request)
    {
        $data = $this->validateTransaction($request);

        // D6 · Coerência tipo × categoria
        if ($err = $this->validateDomainCoherence($data, null)) {
            return back()->withInput()->with('error', $err);
        }

        $data['created_by'] = $request->user()->id;
        FinancialTransaction::create($data);

        return redirect()->route('admin.financeiro.transacoes.index')->with('success', 'Lançamento criado.');
    }

    public function edit(FinancialTransaction $transacao)
    {
        return Inertia::render('Admin/Financial/Transactions/Form', [
            'transaction' => $transacao,
            'accounts' => FinancialAccount::where('is_active', true)->get(['id', 'nome']),
            'categoriasReceita' => Category::tipo('financeiro_receita')->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'categoriasDespesa' => Category::tipo('financeiro_despesa')->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
            'costCenters' => CostCenter::where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'codigo']),
            'partners' => Partner::where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'tipo']),
        ]);
    }

    public function update(Request $request, FinancialTransaction $transacao)
    {
        $data = $this->validateTransaction($request);

        // D6 · Coerência também em update — só aplica se tipo ou category_id mudou
        if ($err = $this->validateDomainCoherence($data, $transacao)) {
            return back()->withInput()->with('error', $err);
        }

        $transacao->update($data);

        return redirect()->route('admin.financeiro.transacoes.index')->with('success', 'Lançamento atualizado.');
    }

    /**
     * F8-B2 (QA Deep 2026-04-29) · transação PAGA não pode ser excluída
     * sem estorno. Antes deletava direto, deixando saldo da conta
     * falsamente alto (caixa "ressuscitava" R$ pagos).
     *
     * Agora:
     *   - Pendente/atrasada → soft-delete normal
     *   - Paga → exige estorno explícito (?estorno=1) que cria
     *     contra-lançamento com mesma quantia e tipo invertido (despesa
     *     paga → receita "estorno", receita paga → despesa "estorno"),
     *     marca a transação como 'estornada' e PRESERVA o histórico.
     *   - Bloqueio sem ?estorno=1 retorna erro descritivo.
     */
    public function destroy(Request $request, FinancialTransaction $transacao)
    {
        if ($transacao->status === 'pago' && ! $request->boolean('estorno')) {
            return back()->with('error', 'Esta transação já foi paga. Use a opção "Estornar" para reverter o pagamento e preservar o histórico.');
        }

        if ($transacao->status === 'pago' && $request->boolean('estorno')) {
            // Cria contra-lançamento de estorno + marca original como 'estornada'.
            // Ambas as ações na mesma transação DB pra atomicidade.
            \DB::transaction(function () use ($transacao) {
                FinancialTransaction::create([
                    'tenant_id' => $transacao->tenant_id,
                    'farm_id' => $transacao->farm_id,
                    'tipo' => $transacao->tipo === 'despesa' ? 'receita' : 'despesa',
                    'descricao' => 'Estorno · '.$transacao->descricao,
                    'valor' => $transacao->valor,
                    'data_vencimento' => now()->toDateString(),
                    'data_pagamento' => now()->toDateString(),
                    'status' => 'pago',
                    'category_id' => $transacao->category_id,
                    'partner_id' => $transacao->partner_id,
                    'numero_documento' => 'ESTORNO:'.$transacao->id,
                    'observacoes' => 'Estorno automático da transação #'.$transacao->id,
                ]);

                $transacao->update(['status' => 'estornada']);
            });

            return back()->with('success', 'Pagamento estornado · contra-lançamento criado · histórico preservado.');
        }

        $transacao->delete();

        return back()->with('success', 'Lançamento excluído.');
    }

    public function pay(Request $request, FinancialTransaction $transacao)
    {
        $request->validate([
            'data_pagamento' => ['required', 'date'],
            'forma_pagamento' => ['nullable', 'string'],
        ]);

        $transacao->update([
            'status' => 'pago',
            'data_pagamento' => $request->data_pagamento,
            'forma_pagamento' => $request->forma_pagamento,
        ]);

        return back()->with('success', 'Lançamento quitado.');
    }

    protected function validateTransaction(Request $request): array
    {
        return $request->validate([
            'account_id' => ['required', 'exists:financial_accounts,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'tipo' => ['required', 'in:receita,despesa,transferencia'],
            'descricao' => ['required', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
            'valor' => ['required', 'numeric', 'gt:0'],
            'data_vencimento' => ['required', 'date'],
            'data_pagamento' => ['nullable', 'date'],
            'status' => ['required', 'in:pendente,pago,atrasado,cancelado'],
            'forma_pagamento' => ['nullable', 'string', 'max:30'],
            'numero_documento' => ['nullable', 'string', 'max:50'],
        ]);
    }

    /**
     * D6 · Coerência contábil: o `tipo` do lançamento dita o tipo da
     * categoria aceita.
     *
     * Regras:
     *   - receita → category.tipo deve ser `financeiro_receita`
     *   - despesa → category.tipo deve ser `financeiro_despesa`
     *   - transferencia → sem regra (fora do plano contábil receita/despesa)
     *
     * Só valida quando `category_id` é informada (o validator base mantém
     * `nullable`). Em update, a regra só dispara se `tipo` ou
     * `category_id` mudaram — lançamentos legados com categoria incoerente
     * continuam editáveis sem forçar correção retroativa.
     */
    protected function validateDomainCoherence(array $data, ?FinancialTransaction $existing): ?string
    {
        $tipo = $data['tipo'] ?? null;
        $catId = $data['category_id'] ?? null;

        // Só receita/despesa têm regra. Transferência e tipos inesperados passam.
        if (! array_key_exists($tipo, self::CATEGORIA_ESPERADA_POR_TIPO_TRANSACAO)) {
            return null;
        }

        // Retrocompat em update: se nem tipo nem categoria mudaram, nada a validar.
        if ($existing !== null) {
            $tipoMudou = $tipo !== $existing->tipo;
            $catMudou = (int) $catId !== (int) $existing->category_id;
            if (! $tipoMudou && ! $catMudou) {
                return null;
            }
        }

        // Sem categoria informada → passa (categoria continua nullable por design).
        // O brief foca em COERÊNCIA quando a categoria existe, não em forçar seleção.
        if (! $catId) {
            return null;
        }

        $category = Category::find($catId);
        if (! $category) {
            return null; // validator base já trata via 'exists'
        }

        $esperado = self::CATEGORIA_ESPERADA_POR_TIPO_TRANSACAO[$tipo];
        if ($category->tipo === $esperado) {
            return null; // coerente
        }

        // Mensagem prescritiva: explica qual categoria foi usada e o que fazer.
        $tipoHuman = $tipo === 'receita' ? 'Receitas' : 'Despesas';
        $catTipoHuman = match ($category->tipo) {
            'financeiro_receita' => 'receitas',
            'financeiro_despesa' => 'despesas',
            default => $category->tipo,
        };

        return "{$tipoHuman} devem utilizar categorias de " . ($tipo === 'receita' ? 'receita' : 'despesa') . '. '
            . "A categoria informada ('" . $category->nome . "') pertence a {$catTipoHuman}. "
            . 'Abra a lista de categorias e escolha uma compatível com o tipo do lançamento. '
            . 'Se o lançamento está classificado errado, troque o tipo (receita ↔ despesa) em vez de forçar a categoria.';
    }
}
