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
 * Assistente guiado — Registrar despesa.
 *
 * Expõe apenas o que o wizard precisa:
 *   - contas financeiras ativas (pra dar "onde vai descontar")
 *   - categorias do tipo despesa (pra classificar)
 *   - fornecedores (opcional no wizard, porém pedido no passo final)
 *
 * Submit: reutiliza `admin.financeiro.transacoes.store` com tipo=despesa.
 */
class DespesaWizardController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Wizards/Despesa', [
            'contas' => FinancialAccount::where('is_active', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'tipo']),
            'categorias' => Category::where('tipo', 'financeiro_despesa')
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'fornecedores' => Partner::whereIn('tipo', ['fornecedor', 'ambos'])
                ->orderBy('nome')
                ->get(['id', 'nome']),
        ]);
    }

    /**
     * Endpoint de submit do wizard — precisa retornar `back()` para que o
     * `onSuccess` do Inertia dispare e o wizard avance para o passo "Pronto!".
     * O controller financeiro original redireciona para a lista; aqui
     * mantemos a URL do wizard.
     *
     * Validação espelha a do FinancialTransactionController, com `tipo`
     * forçado a `despesa`.
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

        $data['tipo'] = 'despesa';
        $data['created_by'] = $request->user()->id;

        FinancialTransaction::create($data);

        return back()->with('success', 'Despesa registrada.');
    }
}
