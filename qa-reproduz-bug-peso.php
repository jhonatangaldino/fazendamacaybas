<?php
$cwd = '/home/u931382046/domains/fazendamacaybas.com.br/releases/current';
require $cwd.'/vendor/autoload.php';
$app = require $cwd.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Livestock\Animal;
use App\Models\Livestock\AnimalEvent;

// Limpar animal 2
AnimalEvent::where('animal_id', 2)->forceDelete();
Animal::where('id', 2)->update(['status' => 'ativo', 'data_saida' => null]);

// Cenário: usuário pesa em 3 datas
// Situação que reproduz o bug "500 → 700 exibindo negativo":
// - Pesagem antiga: 500 kg
// - Pesagem média: 720 kg
// - Pesagem recente: 700 kg (animal perdeu 20kg entre as 2 últimas)
// Resultado esperado: ganho total = 700 - 500 = +200

AnimalEvent::create([
    'animal_id' => 2, 'tipo' => 'pesagem',
    'data' => '2026-01-15', 'peso' => 500,
    'created_by' => 2,
]);
AnimalEvent::create([
    'animal_id' => 2, 'tipo' => 'pesagem',
    'data' => '2026-03-10', 'peso' => 720,
    'created_by' => 2,
]);
AnimalEvent::create([
    'animal_id' => 2, 'tipo' => 'pesagem',
    'data' => '2026-04-20', 'peso' => 700,
    'created_by' => 2,
]);

// Simular também pesagem retroativa (usuário esqueceu e cadastrou depois)
// Esta deve ficar no meio se ordenarmos por data, mesmo sendo criada por último
AnimalEvent::create([
    'animal_id' => 2, 'tipo' => 'pesagem',
    'data' => '2026-02-20', 'peso' => 610,
    'created_by' => 2,
]);

// Recalcular peso_atual (última pesagem por DATA, não por criação)
$ultima = AnimalEvent::where('animal_id', 2)
    ->where('tipo', 'pesagem')
    ->orderByDesc('data')
    ->orderByDesc('id')
    ->first();
Animal::where('id', 2)->update(['peso_atual' => $ultima->peso]);

echo "Setup: 4 pesagens no animal 2\n";
echo "  2026-01-15 · 500 kg\n";
echo "  2026-02-20 · 610 kg (criada por último, retroativa)\n";
echo "  2026-03-10 · 720 kg\n";
echo "  2026-04-20 · 700 kg  ← última\n";
echo "\n";
echo "Cálculo correto: ganho = última - primeira = 700 - 500 = +200 kg\n";
