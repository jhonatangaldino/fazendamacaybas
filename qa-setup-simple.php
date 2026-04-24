<?php
$cwd = '/home/u931382046/domains/fazendamacaybas.com.br/releases/current';
require $cwd.'/vendor/autoload.php';
$app = require $cwd.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$email = $argv[2] ?? 'qa-dono@fazendamacaybas.local';
if ($argv[1] === 'setup') {
  $u = App\Models\User::updateOrCreate(['email' => $email], ['name' => 'QA Dono', 'password' => Illuminate\Support\Facades\Hash::make('QADono#2026'), 'tenant_id' => 1, 'email_verified_at' => now()]);
  $u->syncRoles(['dono_fazenda']);
  echo "created id={$u->id}\n";
} else {
  App\Models\User::where('email', $email)->forceDelete();
  App\Models\Livestock\AnimalEvent::whereHas('animal', fn($q) => $q->where('identificacao', 'LIKE', '__QA%'))->forceDelete();
  App\Models\Livestock\Animal::where('identificacao', 'LIKE', '__QA%')->forceDelete();
  echo "cleaned\n";
}
