<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Semeia 4 settings do PIX SaaS no group `billing_pix`:
 *   - tipo_chave: cpf | cnpj | email | telefone | aleatoria
 *   - chave:      valor da chave PIX (recebe pagamentos das mensalidades)
 *   - nome:       nome do recebedor (max 25 chars no BR Code)
 *   - cidade:     cidade do recebedor (max 15 chars no BR Code)
 *
 * Quem edita: master, em /master/cobrancas/configuracoes (UI dedicada).
 * Quem usa: InvoiceGenerationService → PixPayloadGenerator quando emite fatura.
 *
 * Idempotente: usa updateOrCreate por key. Não destrói valores já preenchidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            [
                'group' => 'billing_pix',
                'key' => 'billing_pix.tipo_chave',
                'value' => 'email',
                'type' => 'string',
                'label' => 'Tipo de chave PIX',
                'description' => 'cpf, cnpj, email, telefone ou aleatoria',
                'order_column' => 1,
                'is_public' => false,
            ],
            [
                'group' => 'billing_pix',
                'key' => 'billing_pix.chave',
                'value' => '',
                'type' => 'string',
                'label' => 'Chave PIX',
                'description' => 'A chave que vai receber os pagamentos das mensalidades dos clientes.',
                'order_column' => 2,
                'is_public' => false,
            ],
            [
                'group' => 'billing_pix',
                'key' => 'billing_pix.nome_recebedor',
                'value' => 'FAZENDA MACAYBAS SAAS',
                'type' => 'string',
                'label' => 'Nome do recebedor',
                'description' => 'Aparece no app PIX do cliente. Máximo 25 caracteres, sem acentos.',
                'order_column' => 3,
                'is_public' => false,
            ],
            [
                'group' => 'billing_pix',
                'key' => 'billing_pix.cidade_recebedor',
                'value' => 'JANAUBA',
                'type' => 'string',
                'label' => 'Cidade do recebedor',
                'description' => 'Aparece no app PIX. Máximo 15 caracteres, sem acentos.',
                'order_column' => 4,
                'is_public' => false,
            ],
        ];

        foreach ($defaults as $attrs) {
            Setting::updateOrCreate(
                ['key' => $attrs['key']],
                $attrs,
            );
        }
    }

    public function down(): void
    {
        Setting::where('group', 'billing_pix')->delete();
    }
};
