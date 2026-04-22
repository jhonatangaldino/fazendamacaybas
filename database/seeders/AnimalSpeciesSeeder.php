<?php

namespace Database\Seeders;

use App\Models\Livestock\AnimalBreed;
use App\Models\Livestock\AnimalSpecies;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AnimalSpeciesSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Bovino' => ['Nelore', 'Angus', 'Brahman', 'Girolando', 'Gir', 'Guzerá', 'Holandesa', 'Jersey', 'Senepol', 'Tabapuã', 'Canchim'],
            'Equino' => ['Mangalarga Marchador', 'Quarto de Milha', 'Crioulo', 'Andaluz'],
            'Suíno' => ['Landrace', 'Large White', 'Duroc', 'Pietrain'],
            'Caprino' => ['Saanen', 'Boer', 'Anglo-Nubiana'],
            'Ovino' => ['Santa Inês', 'Dorper', 'Morada Nova'],
            'Ave' => ['Caipira', 'Poedeira Vermelha', 'Cobb'],
        ];

        foreach ($data as $especie => $racas) {
            $s = AnimalSpecies::updateOrCreate(
                ['slug' => Str::slug($especie)],
                ['nome' => $especie, 'is_active' => true]
            );

            foreach ($racas as $raca) {
                AnimalBreed::updateOrCreate(
                    ['species_id' => $s->id, 'nome' => $raca],
                    ['is_active' => true]
                );
            }
        }
    }
}
