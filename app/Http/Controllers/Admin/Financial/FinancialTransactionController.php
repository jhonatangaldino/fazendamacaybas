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

    public function store(Request $request)
    {
        $data = $this->validateTransaction($request);
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
        $transacao->update($data);

        return redirect()->route('admin.financeiro.transacoes.index')->with('success', 'Lançamento atualizado.');
    }

    public function destroy(FinancialTransaction $transacao)
    {
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
}
