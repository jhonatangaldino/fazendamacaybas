<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Models\Financial\FinancialAccount;
use App\Models\Financial\FinancialTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FinancialIndexController — B4.4 fix
 *
 * Antes: /admin/financeiro fazia redirect para /admin/financeiro/transacoes
 * (2 URLs para a mesma tela, identificado como bug arquitetural).
 *
 * Agora: hub financeiro com cards de navegação + KPIs rápidos.
 * /admin/financeiro/transacoes continua sendo a lista detalhada.
 */
class FinancialIndexController extends Controller
{
    public function __invoke(): Response
    {
        $hoje = now()->startOfDay();
        $primeiroDiaMes = now()->startOfMonth();
        $ultimoDiaMes = now()->endOfMonth();

        $saldoTotal = (float) FinancialAccount::query()
            ->where('is_active', true)
            ->sum('saldo_atual');

        $contasAtivas = FinancialAccount::query()->where('is_active', true)->count();

        $receitasMes = (float) FinancialTransaction::query()
            ->where('tipo', 'receita')
            ->where('status', 'pago')
            ->whereBetween('data_pagamento', [$primeiroDiaMes, $ultimoDiaMes])
            ->sum('valor');

        $despesasMes = (float) FinancialTransaction::query()
            ->where('tipo', 'despesa')
            ->where('status', 'pago')
            ->whereBetween('data_pagamento', [$primeiroDiaMes, $ultimoDiaMes])
            ->sum('valor');

        $contasPagar = FinancialTransaction::query()
            ->where('tipo', 'despesa')
            ->where('status', 'pendente')
            ->where('data_vencimento', '<=', $hoje->copy()->addDays(30))
            ->count();

        $contasReceber = FinancialTransaction::query()
            ->where('tipo', 'receita')
            ->where('status', 'pendente')
            ->where('data_vencimento', '<=', $hoje->copy()->addDays(30))
            ->count();

        $atrasadas = FinancialTransaction::query()
            ->where('status', 'pendente')
            ->where('data_vencimento', '<', $hoje)
            ->count();

        return Inertia::render('Admin/Financial/Hub', [
            'kpis' => [
                'saldo_total' => $saldoTotal,
                'contas_ativas' => $contasAtivas,
                'receitas_mes' => $receitasMes,
                'despesas_mes' => $despesasMes,
                'saldo_mes' => $receitasMes - $despesasMes,
                'contas_pagar' => $contasPagar,
                'contas_receber' => $contasReceber,
                'atrasadas' => $atrasadas,
            ],
        ]);
    }
}
