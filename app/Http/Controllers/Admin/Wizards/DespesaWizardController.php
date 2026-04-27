<?php

namespace App\Http\Controllers\Admin\Wizards;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Financial\FinancialAccount;
use App\Models\Financial\FinancialTransaction;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
 * Submit: gera 1 transaction (à vista) ou N transactions (parcelado).
 *
 * Auditoria 2026-04-27 — A1 PARCELADO:
 * Compras grandes na fazenda (maquinário, ração, sementes) raramente são à
 * vista. Wizard agora aceita `parcelado=true` + `total_parcelas` + `data_vencimento`
 * (1ª parcela), e gera 12, 24, etc. transactions com vencimentos mensais.
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
            'parcelado' => ['nullable', 'boolean'],
            'total_parcelas' => ['nullable', 'integer', 'min:2', 'max:60'],
        ]);

        $userId = $request->user()->id;
        $parcelado = (bool) ($data['parcelado'] ?? false) && ($data['total_parcelas'] ?? 0) > 1;

        if ($parcelado) {
            $this->criarParcelas($data, $userId);
        } else {
            $payload = collect($data)
                ->except(['parcelado', 'total_parcelas'])
                ->merge(['tipo' => 'despesa', 'created_by' => $userId])
                ->all();

            FinancialTransaction::create($payload);
        }

        // Contexto financeiro pós-lançamento — soma TODAS as parcelas no mês.
        $inicioMes = now()->startOfMonth();
        $fimMes = now()->endOfMonth();

        $totalDespesasMes = FinancialTransaction::where('tipo', 'despesa')
            ->whereBetween('data_vencimento', [$inicioMes, $fimMes])
            ->sum('valor');
        $totalReceitasMes = FinancialTransaction::where('tipo', 'receita')
            ->whereBetween('data_vencimento', [$inicioMes, $fimMes])
            ->sum('valor');

        session()->flash('financeiro_contexto', [
            'tipo' => 'despesa',
            'valor' => (float) $data['valor'],
            'parcelado' => $parcelado,
            'total_parcelas' => $parcelado ? (int) $data['total_parcelas'] : 1,
            'despesas_mes' => (float) $totalDespesasMes,
            'receitas_mes' => (float) $totalReceitasMes,
            'saldo_mes' => (float) $totalReceitasMes - (float) $totalDespesasMes,
        ]);

        return back()->with('success', $parcelado
            ? 'Despesa parcelada em ' . $data['total_parcelas'] . 'x registrada.'
            : 'Despesa registrada.');
    }

    /**
     * Gera N transactions, vinculadas via parent_transaction_id.
     *
     * Regras de cálculo:
     *  - valor de cada parcela = round(total / N, 2)
     *  - parcela 1 absorve a diferença de arredondamento (em centavos)
     *  - vencimentos: 1ª na data informada, 2ª-Nª = +1 mês a cada uma
     *  - status: só a parcela 1 pode estar marcada como 'pago' (se status='pago').
     *           Demais parcelas sempre 'pendente'.
     *  - descricao: "X (1/12)", "X (2/12)", …
     */
    private function criarParcelas(array $data, int $userId): void
    {
        $total = (int) $data['total_parcelas'];
        $valorTotal = (float) $data['valor'];
        $valorParcela = round($valorTotal / $total, 2);
        $diferenca = round($valorTotal - ($valorParcela * $total), 2);

        $dataInicial = Carbon::parse($data['data_vencimento']);
        $statusPrimeira = $data['status'] ?? 'pendente';
        $dataPagamentoPrimeira = $statusPrimeira === 'pago'
            ? ($data['data_pagamento'] ?? $dataInicial->toDateString())
            : null;

        DB::transaction(function () use (
            $data, $total, $valorParcela, $diferenca, $dataInicial,
            $statusPrimeira, $dataPagamentoPrimeira, $userId, &$primeira
        ) {
            $primeira = FinancialTransaction::create([
                'account_id' => $data['account_id'],
                'category_id' => $data['category_id'] ?? null,
                'partner_id' => $data['partner_id'] ?? null,
                'tipo' => 'despesa',
                'descricao' => $data['descricao'] . " (1/{$total})",
                'observacoes' => $data['observacoes'] ?? null,
                'valor' => $valorParcela + $diferenca,
                'data_vencimento' => $dataInicial->toDateString(),
                'data_pagamento' => $dataPagamentoPrimeira,
                'status' => $statusPrimeira,
                'parcela_atual' => 1,
                'total_parcelas' => $total,
                'created_by' => $userId,
            ]);

            // A parcela 1 é "matriz" — aponta para si mesma para que .parcelas() retorne todas.
            $primeira->update(['parent_transaction_id' => $primeira->id]);

            for ($i = 2; $i <= $total; $i++) {
                FinancialTransaction::create([
                    'account_id' => $data['account_id'],
                    'category_id' => $data['category_id'] ?? null,
                    'partner_id' => $data['partner_id'] ?? null,
                    'tipo' => 'despesa',
                    'descricao' => $data['descricao'] . " ({$i}/{$total})",
                    'observacoes' => $data['observacoes'] ?? null,
                    'valor' => $valorParcela,
                    'data_vencimento' => $dataInicial->copy()->addMonths($i - 1)->toDateString(),
                    'data_pagamento' => null,
                    'status' => 'pendente',
                    'parent_transaction_id' => $primeira->id,
                    'parcela_atual' => $i,
                    'total_parcelas' => $total,
                    'created_by' => $userId,
                ]);
            }
        });
    }
}
