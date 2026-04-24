<?php
$cwd = '/home/u931382046/domains/fazendamacaybas.com.br/releases/current';
require $cwd.'/vendor/autoload.php';
$app = require $cwd.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$a = App\Models\Livestock\Animal::find(2);
echo "animal 2: identificacao={$a->identificacao} nome={$a->nome} status={$a->status} peso_atual={$a->peso_atual} species_id={$a->species_id}\n";
echo "species: " . ($a->species ? $a->species->nome.' allowed_events='.json_encode($a->species->allowed_events) : 'null') . "\n";
echo "eventos: " . App\Models\Livestock\AnimalEvent::where('animal_id', 2)->count() . "\n";
