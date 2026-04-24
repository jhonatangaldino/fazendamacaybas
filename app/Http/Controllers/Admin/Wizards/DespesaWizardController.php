<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Financial\FinancialAccount;
use App\Models\Partner;
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
}
