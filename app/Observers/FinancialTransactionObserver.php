<?php

namespace App\Observers;

use App\Models\Financial\FinancialTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Mantém `financial_accounts.saldo_atual` sincronizado com as transações pagas.
 *
 * Regra de negócio:
 *   saldo_atual = saldo_inicial
 *               + SUM(receitas pagas)
 *               - SUM(despesas pagas)
 *
 * Transações pendentes/canceladas NÃO entram no saldo (são "a pagar/receber",
 * mostradas em KPI separado).
 *
 * Antes deste observer: o saldo ficava preso em saldo_inicial (zero pra contas
 * recém-criadas) mesmo com transações já pagas. Página do Financeiro mostrava
 * R$ 0,00 com Receitas R$ 500 e Despesas R$ 250 — inconsistência grave.
 */
class FinancialTransactionObserver
{
    public function created(FinancialTransaction $t): void
    {
        $this->recalcularContasAfetadas($t);
    }

    public function updated(FinancialTransaction $t): void
    {
        // Se mudou conta, status, valor, tipo ou data_pagamento → recalcula
        $rastreados = ['account_id', 'status', 'valor', 'tipo', 'data_pagamento'];
        $mudou = false;
        foreach ($rastreados as $col) {
            if ($t->wasChanged($col)) { $mudou = true; break; }
        }
        if ($mudou) $this->recalcularContasAfetadas($t);
    }

    public function deleted(FinancialTransaction $t): void
    {
        $this->recalcularContasAfetadas($t);
    }

    public function restored(FinancialTransaction $t): void
    {
        $this->recalcularContasAfetadas($t);
    }

    /**
     * Recalcula saldo da conta atual + da conta anterior (caso conta tenha mudado).
     */
    private function recalcularContasAfetadas(FinancialTransaction $t): void
    {
        $contas = collect([$t->account_id, $t->getOriginal('account_id')])
            ->filter()
            ->unique()
            ->values();

        foreach ($contas as $accountId) {
            $this->recalcularConta((int) $accountId);
        }
    }

    /**
     * Recalcula saldo_atual de uma conta com base no saldo_inicial + transações pagas.
     */
    public static function recalcularConta(int $accountId): void
    {
        $conta = DB::table('financial_accounts')->where('id', $accountId)->first();
        if (! $conta) return;

        $receitas = (float) DB::table('financial_transactions')
            ->where('account_id', $accountId)
            ->where('tipo', 'receita')
            ->where('status', 'pago')
            ->whereNull('deleted_at')
            ->sum('valor');

        $despesas = (float) DB::table('financial_transactions')
            ->where('account_id', $accountId)
            ->where('tipo', 'despesa')
            ->where('status', 'pago')
            ->whereNull('deleted_at')
            ->sum('valor');

        $saldoAtual = (float) $conta->saldo_inicial + $receitas - $despesas;

        DB::table('financial_accounts')
            ->where('id', $accountId)
            ->update([
                'saldo_atual' => $saldoAtual,
                'updated_at' => now(),
            ]);
    }
}
