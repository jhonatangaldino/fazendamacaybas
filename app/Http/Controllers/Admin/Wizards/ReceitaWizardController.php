<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Financial\FinancialAccount;
use App\Models\Partner;
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
}
