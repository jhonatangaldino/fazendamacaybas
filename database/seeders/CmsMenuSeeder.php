<?php

namespace Database\Seeders;

use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use Illuminate\Database\Seeder;

class CmsMenuSeeder extends Seeder
{
    public function run(): void
    {
        $header = Menu::updateOrCreate(
            ['slug' => 'header-principal'],
            ['nome' => 'Menu do topo', 'local' => 'header', 'is_active' => true]
        );

        $headerItems = [
            ['label' => 'Início', 'url' => '/', 'order_column' => 1],
            ['label' => 'Sobre', 'url' => '/#sobre', 'order_column' => 2],
            ['label' => 'Galeria', 'url' => '/#galeria', 'order_column' => 3],
            ['label' => 'Contato', 'url' => '/#contato', 'order_column' => 4],
        ];

        foreach ($headerItems as $item) {
            MenuItem::updateOrCreate(
                ['menu_id' => $header->id, 'label' => $item['label']],
                array_merge($item, ['target' => '_self', 'is_active' => true])
            );
        }

        $footer = Menu::updateOrCreate(
            ['slug' => 'footer-institucional'],
            ['nome' => 'Menu do rodapé', 'local' => 'footer', 'is_active' => true]
        );

        $footerItems = [
            ['label' => 'Início', 'url' => '/', 'order_column' => 1],
            ['label' => 'Sobre', 'url' => '/#sobre', 'order_column' => 2],
            ['label' => 'Contato', 'url' => '/#contato', 'order_column' => 3],
            ['label' => 'Política de Privacidade', 'url' => '/politica-de-privacidade', 'order_column' => 4],
        ];

        foreach ($footerItems as $item) {
            MenuItem::updateOrCreate(
                ['menu_id' => $footer->id, 'label' => $item['label']],
                array_merge($item, ['target' => '_self', 'is_active' => true])
            );
        }
    }
}
