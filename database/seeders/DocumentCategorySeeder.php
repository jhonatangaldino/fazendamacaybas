<?php

namespace Database\Seeders;

use App\Models\Document\DocumentCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DocumentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['nome' => 'Notas fiscais', 'icon' => 'receipt', 'cor' => '#16a34a'],
            ['nome' => 'Contratos', 'icon' => 'file-signature', 'cor' => '#0ea5e9'],
            ['nome' => 'Comprovantes', 'icon' => 'receipt-text', 'cor' => '#f59e0b'],
            ['nome' => 'Documentos sanitários', 'icon' => 'stethoscope', 'cor' => '#dc2626'],
            ['nome' => 'Documentos patrimoniais', 'icon' => 'building', 'cor' => '#7c3aed'],
            ['nome' => 'Documentos de funcionários', 'icon' => 'user', 'cor' => '#6366f1'],
            ['nome' => 'Licenças e certificações', 'icon' => 'badge-check', 'cor' => '#10b981'],
            ['nome' => 'Outros', 'icon' => 'file', 'cor' => '#64748b'],
        ];

        foreach ($categorias as $cat) {
            DocumentCategory::updateOrCreate(
                ['slug' => Str::slug($cat['nome'])],
                ['nome' => $cat['nome'], 'icon' => $cat['icon'], 'cor' => $cat['cor'], 'is_active' => true]
            );
        }
    }
}
