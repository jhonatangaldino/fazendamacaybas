<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Financial\FinancialAccount;
use App\Models\Partner;
use App\Models\Vehicle\Vehicle;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assistente guiado — Arrumar máquina.
 *
 * Submit: reutiliza `admin.maquinas.manutencoes.store`. Se o usuário
 * marcar "gerar despesa no financeiro", o wizard já passa os flags certos
 * e o controller antigo cuida de criar a FinancialTransaction vinculada.
 */
class ManutencaoWizardController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Wizards/Manutencao', [
            'veiculos' => Vehicle::where('is_active', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'tipo', 'placa']),
            'contas' => FinancialAccount::where('is_active', true)
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'oficinas' => Partner::whereIn('tipo', ['fornecedor', 'ambos'])
                ->orderBy('nome')
                ->get(['id', 'nome']),
        ]);
    }
}
