<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ======= Identidade =======
            ['group' => 'identidade', 'key' => 'site.nome', 'value' => 'Fazenda Macaybas', 'type' => 'string', 'label' => 'Nome do site', 'is_public' => true],
            ['group' => 'identidade', 'key' => 'site.tagline', 'value' => 'Tradição, produção e cuidado com a terra.', 'type' => 'string', 'label' => 'Slogan', 'is_public' => true],
            ['group' => 'identidade', 'key' => 'site.descricao', 'value' => 'Fazenda Macaybas — produção rural com qualidade e compromisso desde a origem.', 'type' => 'text', 'label' => 'Descrição institucional', 'is_public' => true],
            ['group' => 'identidade', 'key' => 'site.logo', 'value' => null, 'type' => 'image', 'label' => 'Logomarca', 'is_public' => true],
            ['group' => 'identidade', 'key' => 'site.favicon', 'value' => null, 'type' => 'image', 'label' => 'Favicon', 'is_public' => true],

            // ======= Contato =======
            ['group' => 'contato', 'key' => 'contato.email', 'value' => 'contato@fazendamacaybas.com.br', 'type' => 'string', 'label' => 'E-mail de contato', 'is_public' => true],
            ['group' => 'contato', 'key' => 'contato.telefone', 'value' => '', 'type' => 'string', 'label' => 'Telefone', 'is_public' => true],
            ['group' => 'contato', 'key' => 'contato.whatsapp', 'value' => '', 'type' => 'string', 'label' => 'WhatsApp', 'is_public' => true],
            ['group' => 'contato', 'key' => 'contato.endereco', 'value' => 'Itabirito, Minas Gerais', 'type' => 'string', 'label' => 'Endereço', 'is_public' => true],

            // ======= Redes sociais =======
            ['group' => 'social', 'key' => 'social.instagram', 'value' => 'https://instagram.com/fazendamacaybas', 'type' => 'string', 'label' => 'Instagram', 'is_public' => true],
            ['group' => 'social', 'key' => 'social.facebook', 'value' => '', 'type' => 'string', 'label' => 'Facebook', 'is_public' => true],
            ['group' => 'social', 'key' => 'social.youtube', 'value' => '', 'type' => 'string', 'label' => 'YouTube', 'is_public' => true],

            // ======= Tema =======
            ['group' => 'tema', 'key' => 'tema.cor_primaria', 'value' => '#166534', 'type' => 'string', 'label' => 'Cor primária', 'is_public' => true], // verde escuro
            ['group' => 'tema', 'key' => 'tema.cor_secundaria', 'value' => '#ca8a04', 'type' => 'string', 'label' => 'Cor secundária', 'is_public' => true], // amarelo/ocre
            ['group' => 'tema', 'key' => 'tema.cor_acento', 'value' => '#78350f', 'type' => 'string', 'label' => 'Cor de acento', 'is_public' => true], // marrom

            // ======= SEO padrão =======
            ['group' => 'seo', 'key' => 'seo.default_title', 'value' => 'Fazenda Macaybas', 'type' => 'string', 'label' => 'Título padrão', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo.default_description', 'value' => 'Produção rural com tradição e qualidade em Itabirito/MG.', 'type' => 'text', 'label' => 'Descrição padrão', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo.google_analytics', 'value' => '', 'type' => 'string', 'label' => 'Google Analytics ID', 'is_public' => false],
        ];

        foreach ($settings as $i => $s) {
            Setting::updateOrCreate(
                ['key' => $s['key']],
                array_merge($s, ['order_column' => $i])
            );
        }
    }
}
