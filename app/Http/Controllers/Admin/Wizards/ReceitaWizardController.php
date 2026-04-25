<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Financial\FinancialAccount;
use App\Models\Financial\FinancialTransaction;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assistente guiado — Registrar receita (dinheiro que entrou).
 *
 * Diferenças em relação ao DespesaWizard:
 *   - categorias do tipo financeiro_receita
 *   - parceiro é cliente (ou ambos) em vez de fornecedor
 *   - status default = "pago" (receita costuma já ter caído)
 *
 * Submit: reutiliza `admin.financeiro.transacoes.store` com tipo=receita.
 */
class ReceitaWizardController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Wizards/Receita', [
            'contas' => FinancialAccount::where('is_active', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'tipo']),
            'categorias' => Category::where('tipo', 'financeiro_receita')
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'clientes' => Partner::whereIn('tipo', ['cliente', 'ambos'])
                ->orderBy('nome')
                ->get(['id', 'nome']),
        ]);
    }

    /**
     * Endpoint de submit do wizard — usa `back()` pra manter URL do wizard
     * e acionar o `onSuccess` que avança ao passo "Pronto!".
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'exists:financial_accounts,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'descricao' => ['required', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'gt:0'],
            'data_vencimento' => ['required', 'date'],
            'data_pagamento' => ['nullable', 'date'],
            'status' => ['required', 'in:pendente,pago,atrasado,cancelado'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $data['tipo'] = 'receita';
        $data['created_by'] = $request->user()->id;

        FinancialTransaction::create($data);

        // Contexto financeiro pós-lançamento — espelha DespesaWizard para
        // mostrar IMPACTO no mês (total recebido, saldo atual).
        $inicioMes = now()->startOfMonth();
        $fimMes = now()->endOfMonth();

        $totalDespesasMes = FinancialTransaction::where('tipo', 'despesa')
            ->whereBetween('data_vencimento', [$inicioMes, $fimMes])
            ->sum('valor');
        $totalReceitasMes = FinancialTransaction::where('tipo', 'receita')
            ->whereBetween('data_vencimento', [$inicioMes, $fimMes])
            ->sum('valor');

        session()->flash('financeiro_contexto', [
            'tipo' => 'receita',
            'valor' => (float) $data['valor'],
            'despesas_mes' => (float) $totalDespesasMes,
            'receitas_mes' => (float) $totalReceitasMes,
            'saldo_mes' => (float) $totalReceitasMes - (float) $totalDespesasMes,
        ]);

        return back()->with('success', 'Receita registrada.');
    }
}
