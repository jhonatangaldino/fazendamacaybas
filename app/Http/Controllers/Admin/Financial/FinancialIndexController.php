<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Models\Financial\FinancialAccount;
use App\Http\Controllers\Controller;
use App\Services\Metrics\FinancialMetrics;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FinancialIndexController — Hub Financeiro
 *
 * REFATORADO 2026-04-28 (METRICS-AUDIT): KPIs agora consumidos de
 * FinancialMetrics (fonte única de verdade). Antes este controller tinha
 * fórmulas inline que divergiam sutilmente do Painel/Lista/Relatório:
 *   - `contas_pagar`/`contas_receber` aceitavam vencimentos passados (sem
 *     filtro `>= hoje`); Painel exigia `>= hoje`.
 *   - `atrasadas` contava receita+despesa; AlertsService só despesa.
 * Ambos os bugs corrigidos pela centralização.
 */
class FinancialIndexController extends Controller
{
    public function __invoke(FinancialMetrics $metrics): Response
    {
        $saldoTotal = $metrics->saldoTotalContas();
        $contasAtivas = FinancialAccount::query()->where('is_active', true)->count();

        $receitasMes = $metrics->receitasNoPeriodo('mes_atual');
        $despesasMes = $metrics->despesasNoPeriodo('mes_atual');

        // aPagar/aReceber retornam ['count', 'valor', 'lista']
        $aPagar = $metrics->aPagar(30);
        $aReceber = $metrics->aReceber(30);

        // atrasadas SÓ DESPESAS (decisão de produto)
        $atrasadas = $metrics->atrasadas();

        return Inertia::render('Admin/Financial/Hub', [
            'kpis' => [
                'saldo_total' => $saldoTotal,
                'contas_ativas' => $contasAtivas,
                'receitas_mes' => $receitasMes,
                'despesas_mes' => $despesasMes,
                'saldo_mes' => $receitasMes - $despesasMes,
                'contas_pagar' => $aPagar['count'],
                'contas_receber' => $aReceber['count'],
                'atrasadas' => $atrasadas['count'],
            ],
        ]);
    }
}
