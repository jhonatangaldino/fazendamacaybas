<?php

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Controller;
use App\Models\Financial\FinancialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BLOCO 4.3 RN3 — CRUD UI mínimo de Contas Financeiras.
 *
 * Antes: o sistema NÃO tinha caminho UI para criar conta. Wizards
 * Despesa/Receita carregavam FinancialAccount mas se a conta não existisse
 * o fluxo paralisava — usuário tinha que sair, ir num lugar inexistente,
 * voltar.
 *
 * Agora: rota /admin/financeiro/contas com index/store/update/toggle.
 * Plus storeInline (RN2) p/ uso dentro dos wizards.
 */
class FinancialAccountController extends Controller
{
    public function index(): Response
    {
        $contas = FinancialAccount::query()
            ->orderByDesc('is_active')
            ->orderBy('nome')
            ->get(['id', 'nome', 'tipo', 'banco', 'saldo_inicial', 'saldo_atual', 'is_active']);

        return Inertia::render('Admin/Financial/Accounts/Index', [
            'contas' => $contas,
            'tipos' => $this->tipos(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        FinancialAccount::create($data + ['saldo_atual' => $data['saldo_inicial'] ?? 0, 'is_active' => true]);
        return back()->with('success', 'Conta criada.');
    }

    public function update(Request $request, FinancialAccount $conta): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $conta->update($data);
        return back()->with('success', 'Conta atualizada.');
    }

    public function toggle(FinancialAccount $conta): RedirectResponse
    {
        $conta->update(['is_active' => ! $conta->is_active]);
        return back()->with('success', $conta->is_active ? 'Conta ativada.' : 'Conta desativada.');
    }

    /**
     * RN2 — endpoint inline JSON p/ uso dentro de wizards.
     * Wizards Despesa/Receita podem criar conta sem sair do fluxo.
     */
    public function storeInline(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        $conta = FinancialAccount::create(
            $data + ['saldo_atual' => $data['saldo_inicial'] ?? 0, 'is_active' => true]
        );
        return response()->json([
            'id' => $conta->id,
            'nome' => $conta->nome,
            'tipo' => $conta->tipo,
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(array_keys($this->tipos()))],
            'banco' => ['nullable', 'string', 'max:80'],
            'agencia' => ['nullable', 'string', 'max:20'],
            'conta' => ['nullable', 'string', 'max:30'],
            'saldo_inicial' => ['nullable', 'numeric'],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ], [
            'nome.required' => 'Informe o nome da conta (ex.: "Banco do Brasil PJ").',
            'tipo.required' => 'Informe o tipo (corrente, poupança, caixa, dinheiro).',
        ]);
    }

    private function tipos(): array
    {
        return [
            'corrente' => 'Conta corrente',
            'poupanca' => 'Poupança',
            'caixa' => 'Caixa interno',
            'dinheiro' => 'Dinheiro em espécie',
            'cartao' => 'Cartão de crédito',
            'outro' => 'Outro',
        ];
    }
}
