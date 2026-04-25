<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BillingConfigController — configurações de cobrança SaaS (chave PIX).
 *
 * Tudo persiste em `settings` (group=billing_pix). 4 campos:
 *   - tipo_chave (cpf|cnpj|email|telefone|aleatoria)
 *   - chave
 *   - nome_recebedor
 *   - cidade_recebedor
 *
 * Quem usa: InvoiceGenerationService (na hora de criar invoice).
 * Quem edita: somente master (rota /master/cobrancas/configuracoes).
 */
class BillingConfigController extends Controller
{
    private const KEYS = [
        'billing_pix.tipo_chave',
        'billing_pix.chave',
        'billing_pix.nome_recebedor',
        'billing_pix.cidade_recebedor',
    ];

    public function index(): Response
    {
        $settings = Setting::whereIn('key', self::KEYS)->get(['key', 'value', 'label', 'description']);

        $byKey = [];
        foreach ($settings as $s) {
            $byKey[$s->key] = [
                'value' => $s->value,
                'label' => $s->label,
                'description' => $s->description,
            ];
        }

        return Inertia::render('Master/Cobrancas/Configuracoes', [
            'config' => [
                'tipo_chave' => $byKey['billing_pix.tipo_chave']['value'] ?? 'email',
                'chave' => $byKey['billing_pix.chave']['value'] ?? '',
                'nome_recebedor' => $byKey['billing_pix.nome_recebedor']['value'] ?? '',
                'cidade_recebedor' => $byKey['billing_pix.cidade_recebedor']['value'] ?? '',
            ],
            'meta' => $byKey,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tipo_chave' => ['required', 'in:cpf,cnpj,email,telefone,aleatoria'],
            'chave' => ['required', 'string', 'max:80'],
            'nome_recebedor' => ['required', 'string', 'max:25'],
            'cidade_recebedor' => ['required', 'string', 'max:15'],
        ], [
            'tipo_chave.in' => 'Tipo de chave inválido.',
            'chave.required' => 'Informe a chave PIX.',
            'nome_recebedor.required' => 'Informe o nome do recebedor.',
            'nome_recebedor.max' => 'Nome do recebedor: máximo 25 caracteres (limite do BR Code).',
            'cidade_recebedor.max' => 'Cidade do recebedor: máximo 15 caracteres (limite do BR Code).',
        ]);

        // Validação de formato por tipo de chave (defesa adicional além do max:80)
        $erro = $this->validarChavePorTipo($validated['tipo_chave'], $validated['chave']);
        if ($erro !== null) {
            return back()->withErrors(['chave' => $erro])->withInput();
        }

        $map = [
            'billing_pix.tipo_chave' => $validated['tipo_chave'],
            'billing_pix.chave' => $validated['chave'],
            'billing_pix.nome_recebedor' => $validated['nome_recebedor'],
            'billing_pix.cidade_recebedor' => $validated['cidade_recebedor'],
        ];

        foreach ($map as $key => $value) {
            Setting::where('key', $key)->update(['value' => $value]);
            Cache::forget("setting.{$key}");
        }

        return back()->with('success', 'Configurações de cobrança atualizadas. Novas faturas usarão estes dados.');
    }

    private function validarChavePorTipo(string $tipo, string $chave): ?string
    {
        $clean = preg_replace('/\D/', '', $chave) ?? '';

        switch ($tipo) {
            case 'cpf':
                if (strlen($clean) !== 11) return 'CPF deve ter 11 dígitos.';
                break;
            case 'cnpj':
                if (strlen($clean) !== 14) return 'CNPJ deve ter 14 dígitos.';
                break;
            case 'email':
                if (! filter_var($chave, FILTER_VALIDATE_EMAIL)) return 'E-mail inválido.';
                break;
            case 'telefone':
                if (strlen($clean) < 10 || strlen($clean) > 13) return 'Telefone deve ter DDD + número (com ou sem +55).';
                break;
            case 'aleatoria':
                if (strlen($chave) < 32) return 'Chave aleatória deve ter pelo menos 32 caracteres.';
                break;
        }
        return null;
    }
}
