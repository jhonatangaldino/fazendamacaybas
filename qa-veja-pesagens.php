<?php
$cwd = '/home/u931382046/domains/fazendamacaybas.com.br/releases/current';
require $cwd.'/vendor/autoload.php';
$app = require $cwd.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Livestock\AnimalEvent;

// Simular o que o controller faz
$events = AnimalEvent::where('animal_id', 2)
    ->orderByDesc('data')
    ->orderByDesc('id')
    ->get();

$pesagens = $events->where('tipo', 'pesagem')
    ->sortBy('data')
    ->values()
    ->map(fn ($e) => [
        'id' => $e->id,
        'data' => $e->data?->toDateString(),
        'peso' => (float) $e->peso,
    ]);

echo "Pesagens ordenadas (como o Show.vue recebe):\n";
foreach ($pesagens as $p) {
    echo "  id={$p['id']} data={$p['data']} peso={$p['peso']}\n";
}

echo "\nPrimeira: {$pesagens[0]['peso']}\n";
echo "Última: " . $pesagens[count($pesagens) - 1]['peso'] . "\n";
$ganho = $pesagens[count($pesagens) - 1]['peso'] - $pesagens[0]['peso'];
echo "Ganho total: {$ganho} kg\n";
