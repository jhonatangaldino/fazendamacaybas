<?php

namespace Database\Seeders;

use App\Models\Cms\Page;
use App\Models\Cms\Section;
use Illuminate\Database\Seeder;

class CmsPageSeeder extends Seeder
{
    public function run(): void
    {
        $home = Page::updateOrCreate(
            ['slug' => 'home'],
            [
                'titulo' => 'Início',
                'meta_title' => 'Fazenda Macaybas — Produção rural em Itabirito/MG',
                'meta_description' => 'Conheça a Fazenda Macaybas: tradição, qualidade e compromisso com a terra no coração de Minas Gerais.',
                'meta_keywords' => 'fazenda, macaybas, itabirito, minas gerais, rural, produção',
                'is_published' => true,
                'published_at' => now(),
            ]
        );

        $sections = [
            [
                'type' => 'hero',
                'nome' => 'Banner principal',
                'data' => [
                    'eyebrow' => 'Bem-vindo à',
                    'titulo' => 'Fazenda Macaybas',
                    'subtitulo' => 'Tradição, produção e cuidado com a terra no coração de Minas Gerais.',
                    'cta_texto' => 'Conheça nossa história',
                    'cta_link' => '#sobre',
                    'imagem_fundo' => null,
                    'overlay_opacity' => 0.45,
                ],
            ],
            [
                'type' => 'about',
                'nome' => 'Sobre a fazenda',
                'data' => [
                    'titulo' => 'Sobre a Fazenda Macaybas',
                    'subtitulo' => 'Uma história construída com dedicação',
                    'texto' => "Localizada em Itabirito, Minas Gerais, a Fazenda Macaybas preserva a tradição da produção rural aliada ao cuidado com a terra e o bem-estar animal. Cada etapa da nossa operação é conduzida com responsabilidade e respeito à natureza.\n\nNossa equipe combina experiência de campo com gestão moderna para entregar qualidade em tudo que produzimos.",
                    'cta_texto' => 'Saiba mais',
                    'cta_link' => '/sobre',
                    'imagem' => null,
                ],
            ],
            [
                'type' => 'features',
                'nome' => 'Nossas áreas de atuação',
                'data' => [
                    'titulo' => 'Áreas de atuação',
                    'subtitulo' => 'Do rebanho à produção agrícola, com gestão e cuidado',
                    'items' => [
                        ['icon' => 'cow', 'titulo' => 'Rebanho', 'descricao' => 'Cuidado sanitário, rastreabilidade e genética de qualidade.'],
                        ['icon' => 'wheat', 'titulo' => 'Produção agrícola', 'descricao' => 'Manejo responsável dos talhões e das safras.'],
                        ['icon' => 'leaf', 'titulo' => 'Sustentabilidade', 'descricao' => 'Práticas que respeitam o solo, a água e o bioma local.'],
                    ],
                ],
            ],
            [
                'type' => 'gallery',
                'nome' => 'Galeria de imagens',
                'data' => [
                    'titulo' => 'A fazenda em imagens',
                    'subtitulo' => 'Um retrato do nosso dia a dia',
                    'imagens' => [
                        ['path' => null, 'legenda' => 'Paisagem da fazenda'],
                        ['path' => null, 'legenda' => 'Rebanho no pasto'],
                        ['path' => null, 'legenda' => 'Área de produção'],
                        ['path' => null, 'legenda' => 'Trabalho em campo'],
                        ['path' => null, 'legenda' => 'Estrutura'],
                        ['path' => null, 'legenda' => 'Pôr do sol na propriedade'],
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
                        ['nome' => 'Cliente Satisfeito', 'cargo' => 'Comprador parceiro', 'texto' => 'Parceria de confiança há anos.', 'foto' => null],
                        ['nome' => 'Visitante', 'cargo' => 'Itabirito/MG', 'texto' => 'Estrutura organizada e bem cuidada — dá orgulho ver o trabalho feito.', 'foto' => null],
                    ],
                ],
            ],
            [
                'type' => 'contact',
                'nome' => 'Onde estamos',
                'data' => [
                    'titulo' => 'Onde estamos',
                    'subtitulo' => 'Venha conhecer a Fazenda Macaybas em Itabirito, no coração de Minas Gerais.',
                ],
            ],
        ];

        foreach ($sections as $i => $s) {
            Section::updateOrCreate(
                ['page_id' => $home->id, 'type' => $s['type']],
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
}
