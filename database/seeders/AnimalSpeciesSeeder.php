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
        // Perfis de manejo: cada espécie só expõe eventos/ações compatíveis com seu ciclo produtivo.
        // Ver memória feedback_context_aware_forms.md — regra de UX.
        $eventos = [
            'ruminante_corte'  => ['pesagem', 'vacinacao', 'medicacao', 'vermifugacao', 'reproducao', 'movimentacao', 'observacao'],
            'ruminante_leite'  => ['pesagem', 'vacinacao', 'medicacao', 'vermifugacao', 'reproducao', 'movimentacao', 'ordenha', 'secagem', 'observacao'],
            'ruminante_lan'    => ['pesagem', 'vacinacao', 'medicacao', 'vermifugacao', 'reproducao', 'movimentacao', 'tosquia', 'observacao'],
            'suino'            => ['pesagem', 'vacinacao', 'medicacao', 'vermifugacao', 'reproducao', 'movimentacao', 'observacao'],
            'equino'           => ['pesagem', 'vacinacao', 'medicacao', 'vermifugacao', 'ferrageamento', 'reproducao', 'movimentacao', 'observacao'],
            'pet'              => ['pesagem', 'vacinacao', 'medicacao', 'vermifugacao', 'castracao', 'observacao'],
            'ave_lote'         => ['biometria_amostral', 'alimentacao', 'mortalidade', 'vacinacao', 'movimentacao', 'observacao'],
            'ave_postura'      => ['postura_diaria', 'biometria_amostral', 'alimentacao', 'mortalidade', 'vacinacao', 'observacao'],
            'aquicultura_lote' => ['biometria_amostral', 'alimentacao', 'qualidade_agua', 'mortalidade', 'movimentacao', 'observacao'],
            'roedor_pequeno'   => ['pesagem', 'vacinacao', 'reproducao', 'observacao'],
        ];

        // [especie => [raças, gestao, profile]]
        $data = [
            'Bovino' => [
                'racas' => ['Nelore', 'Angus', 'Brahman', 'Girolando', 'Gir', 'Guzerá', 'Holandesa', 'Jersey', 'Senepol', 'Tabapuã', 'Canchim'],
                'gestao' => 'individual', 'profile' => 'ruminante_corte',
                // Girolando/Holandesa/Jersey são leite mas profile base é corte; o usuário pode ajustar categoria=leite
                // Eventos do perfil leite também ficam disponíveis (union via categoria "leite" na UI)
                'allowed_events' => $eventos['ruminante_leite'],
            ],
            'Equino' => [
                'racas' => ['Mangalarga Marchador', 'Quarto de Milha', 'Crioulo', 'Andaluz', 'Campolina', 'Sem raça definida'],
                'gestao' => 'individual', 'profile' => 'equino',
                'allowed_events' => $eventos['equino'],
            ],
            'Suíno' => [
                'racas' => ['Landrace', 'Large White', 'Duroc', 'Pietrain'],
                'gestao' => 'individual', 'profile' => 'suino',
                'allowed_events' => $eventos['suino'],
            ],
            'Caprino' => [
                'racas' => ['Saanen', 'Boer', 'Anglo-Nubiana'],
                'gestao' => 'individual', 'profile' => 'ruminante_leite',
                'allowed_events' => $eventos['ruminante_leite'],
            ],
            'Ovino' => [
                'racas' => ['Santa Inês', 'Dorper', 'Morada Nova'],
                'gestao' => 'individual', 'profile' => 'ruminante_lan',
                'allowed_events' => $eventos['ruminante_lan'],
            ],
            'Ave' => [
                'racas' => ['Caipira', 'Poedeira Vermelha', 'Cobb', 'Galinha d\'Angola'],
                'gestao' => 'lote', 'profile' => 'ave_postura',
                'allowed_events' => $eventos['ave_postura'],
            ],
            'Cão' => [
                'racas' => ['Sem raça definida (SRD)', 'Labrador', 'Pastor Alemão', 'Border Collie', 'Rottweiler', 'Pitbull', 'Boiadeiro Australiano', 'Blue Heeler', 'Fila Brasileiro', 'Dogo Argentino'],
                'gestao' => 'individual', 'profile' => 'pet',
                'allowed_events' => $eventos['pet'],
            ],
            'Gato' => [
                'racas' => ['Sem raça definida (SRD)', 'Siamês', 'Persa', 'Maine Coon'],
                'gestao' => 'individual', 'profile' => 'pet',
                'allowed_events' => $eventos['pet'],
            ],
            'Búfalo' => [
                'racas' => ['Murrah', 'Mediterrâneo', 'Jafarabadi', 'Carabao'],
                'gestao' => 'individual', 'profile' => 'ruminante_leite',
                'allowed_events' => $eventos['ruminante_leite'],
            ],
            'Coelho' => [
                'racas' => ['Nova Zelândia Branco', 'Califórnia'],
                'gestao' => 'individual', 'profile' => 'roedor_pequeno',
                'allowed_events' => $eventos['roedor_pequeno'],
            ],
            'Peixe' => [
                'racas' => ['Tilápia', 'Tambaqui', 'Pirarucu', 'Pacu', 'Carpa'],
                'gestao' => 'lote', 'profile' => 'aquicultura_lote',
                'allowed_events' => $eventos['aquicultura_lote'],
            ],
        ];

        foreach ($data as $especie => $cfg) {
            $s = AnimalSpecies::updateOrCreate(
                ['slug' => Str::slug($especie)],
                [
                    'nome' => $especie,
                    'is_active' => true,
                    'gestao' => $cfg['gestao'],
                    'profile' => $cfg['profile'],
                    'allowed_events' => $cfg['allowed_events'],
                ]
            );

            foreach ($cfg['racas'] as $raca) {
                AnimalBreed::updateOrCreate(
                    ['species_id' => $s->id, 'nome' => $raca],
                    ['is_active' => true]
                );
            }
        }
    }
}
