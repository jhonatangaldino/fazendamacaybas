<?php

namespace App\Domain\Cms\Services;

use App\Domain\Billing\Models\Tenant;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Cms\Page;
use App\Models\Cms\Section;
use Illuminate\Support\Facades\DB;

/**
 * LandingScaffoldService — cria o esqueleto da landing de um cliente novo.
 *
 * É scaffold (snapshot inicial) — NÃO herança viva. O cliente edita depois
 * sem impacto sobre outros clientes, e mudanças neste template só atingem
 * clientes criados depois dela.
 *
 * Idempotência: toda persistência usa `updateOrCreate` com chave composta que
 * inclui `tenant_id`. Rodar duas vezes no mesmo cliente é no-op (não duplica
 * nem destrói edições já feitas).
 *
 * O que cria (se ainda não existir no cliente):
 *   - 1 Página com slug `home`, publicada
 *   - 6 seções padrão (hero, about, features, gallery, testimonials, contact)
 *     com conteúdo genérico — cliente personaliza via CMS depois
 *   - 2 menus: header-principal e footer-institucional com itens padrão
 *
 * O que NÃO cria:
 *   - Settings — ficam como overrides opcionais via UI. O fallback para os
 *     settings globais (tenant_id NULL) já cobre todo cliente novo.
 *
 * Uso típico:
 *   app(LandingScaffoldService::class)->scaffold($tenant);
 *
 * Chamado a partir de TenantController@store após `Tenant::create()`.
 */
class LandingScaffoldService
{
    public function scaffold(Tenant $cliente): void
    {
        DB::transaction(function () use ($cliente) {
            $page = $this->scaffoldHomePage($cliente);
            $this->scaffoldSections($page, $cliente);
            $this->scaffoldMenus($cliente);
        });
    }

    private function scaffoldHomePage(Tenant $cliente): Page
    {
        return Page::updateOrCreate(
            // Chave composta: tenant_id + slug.
            // O model Page NÃO tem unique DB em (tenant_id, slug), mas o
            // updateOrCreate do Eloquent faz where → first → create-or-update
            // respeitando os atributos passados. Se o cliente já tem home,
            // atualiza metadados SEM mexer em is_published (deixa como está).
            ['tenant_id' => $cliente->id, 'slug' => 'home'],
            [
                'titulo' => 'Início',
                'meta_title' => $cliente->nome,
                'meta_description' => 'Bem-vindo ao site de ' . $cliente->nome . '.',
                'meta_keywords' => null,
                'is_published' => true,
                'published_at' => now(),
            ]
        );
    }

    /**
     * 6 seções padrão. Conteúdo é placeholder — cliente edita via CMS.
     */
    private function scaffoldSections(Page $page, Tenant $cliente): void
    {
        $sections = [
            [
                'type' => 'hero',
                'nome' => 'Banner principal',
                'data' => [
                    'eyebrow' => 'Bem-vindo à',
                    'titulo' => $cliente->nome,
                    'subtitulo' => 'Qualidade e dedicação em cada detalhe.',
                    'cta_texto' => 'Conheça nossa história',
                    'cta_link' => '#sobre',
                    'imagem_fundo' => null,
                    'overlay_opacity' => 0.45,
                ],
            ],
            [
                'type' => 'about',
                'nome' => 'Sobre',
                'data' => [
                    'titulo' => 'Sobre ' . $cliente->nome,
                    'subtitulo' => 'Uma história construída com dedicação',
                    'texto' => 'Conte aqui a história e os valores de ' . $cliente->nome . '. Este é um texto padrão — edite-o no CMS.',
                    'cta_texto' => 'Saiba mais',
                    'cta_link' => '#fale-conosco',
                    'imagem' => null,
                ],
            ],
            [
                'type' => 'features',
                'nome' => 'Áreas de atuação',
                'data' => [
                    'titulo' => 'Áreas de atuação',
                    'subtitulo' => 'Principais atividades do negócio',
                    'items' => [
                        ['icon' => 'leaf', 'titulo' => 'Atividade 1', 'descricao' => 'Descrição curta da primeira área de atuação.'],
                        ['icon' => 'wheat', 'titulo' => 'Atividade 2', 'descricao' => 'Descrição curta da segunda área de atuação.'],
                        ['icon' => 'cow', 'titulo' => 'Atividade 3', 'descricao' => 'Descrição curta da terceira área de atuação.'],
                    ],
                ],
            ],
            [
                'type' => 'gallery',
                'nome' => 'Galeria',
                'data' => [
                    'titulo' => 'Galeria de imagens',
                    'subtitulo' => 'Clique para adicionar fotos do seu negócio',
                    'imagens' => [
                        ['path' => null, 'legenda' => 'Imagem 1'],
                        ['path' => null, 'legenda' => 'Imagem 2'],
                        ['path' => null, 'legenda' => 'Imagem 3'],
                    ],
                ],
            ],
            [
                'type' => 'testimonials',
                'nome' => 'Depoimentos',
                'data' => [
                    'titulo' => 'O que dizem sobre nós',
                    'subtitulo' => '',
                    'items' => [
                        ['nome' => 'Cliente', 'cargo' => '', 'texto' => 'Adicione um depoimento aqui.', 'foto' => null],
                    ],
                ],
            ],
            [
                'type' => 'contact',
                'nome' => 'Onde estamos',
                'data' => [
                    'titulo' => 'Onde estamos',
                    'subtitulo' => 'Venha nos visitar.',
                ],
            ],
        ];

        foreach ($sections as $i => $s) {
            Section::updateOrCreate(
                ['page_id' => $page->id, 'type' => $s['type']],
                [
                    'nome' => $s['nome'],
                    'published_data' => $s['data'],
                    'draft_data' => $s['data'],
                    'is_active' => true,
                    'has_draft' => false,
                    'order_column' => $i,
                    'published_at' => now(),
                ]
            );
        }
    }

    private function scaffoldMenus(Tenant $cliente): void
    {
        // Header
        $header = Menu::updateOrCreate(
            ['tenant_id' => $cliente->id, 'slug' => 'header-principal'],
            ['nome' => 'Menu do topo', 'local' => 'header', 'is_active' => true]
        );

        $this->scaffoldMenuItems($header, [
            ['label' => 'Início', 'url' => '/', 'order_column' => 1],
            ['label' => 'Sobre', 'url' => '/#sobre', 'order_column' => 2],
            ['label' => 'Galeria', 'url' => '/#galeria', 'order_column' => 3],
            ['label' => 'Contato', 'url' => '/#fale-conosco', 'order_column' => 4],
        ]);

        // Footer
        $footer = Menu::updateOrCreate(
            ['tenant_id' => $cliente->id, 'slug' => 'footer-institucional'],
            ['nome' => 'Menu do rodapé', 'local' => 'footer', 'is_active' => true]
        );

        $this->scaffoldMenuItems($footer, [
            ['label' => 'Início', 'url' => '/', 'order_column' => 1],
            ['label' => 'Sobre', 'url' => '/#sobre', 'order_column' => 2],
            ['label' => 'Contato', 'url' => '/#fale-conosco', 'order_column' => 3],
        ]);
    }

    private function scaffoldMenuItems(Menu $menu, array $items): void
    {
        foreach ($items as $item) {
            MenuItem::updateOrCreate(
                ['menu_id' => $menu->id, 'label' => $item['label']],
                array_merge($item, ['target' => '_self', 'is_active' => true])
            );
        }
    }
}
