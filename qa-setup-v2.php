<?php
$cwd = '/home/u931382046/domains/fazendamacaybas.com.br/releases/current';
require $cwd.'/vendor/autoload.php';
$app = require $cwd.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$act = $argv[1] ?? 'setup';
if ($act === 'setup') {
  $u = App\Models\User::updateOrCreate(['email' => 'qa-dono@fazendamacaybas.local'], [
    'name' => 'QA Dono',
    'password' => Illuminate\Support\Facades\Hash::make('QADono#2026'),
    'tenant_id' => 1,
    'email_verified_at' => now(),
  ]);
  $u->syncRoles(['dono_fazenda']);
  echo "created dono id={$u->id}\n";
} else {
  App\Models\User::where('email', 'qa-dono@fazendamacaybas.local')->forceDelete();
  foreach ([
    App\Models\Partner::where('nome', 'LIKE', '__QA%'),
    App\Models\Livestock\Animal::where('identificacao', 'LIKE', '__QA%'),
    App\Models\Stock\StockItem::where('nome', 'LIKE', '__QA%'),
    App\Models\Financial\FinancialTransaction::where('descricao', 'LIKE', '__QA%'),
    App\Models\Financial\FinancialAccount::where('nome', 'LIKE', '__QA%'),
    App\Models\Task\Task::where('titulo', 'LIKE', '__QA%'),
    App\Models\Vehicle\Vehicle::where('nome', 'LIKE', '__QA%'),
    App\Models\Employee::where('nome', 'LIKE', '__QA%'),
  ] as $q) { $q->forceDelete(); }
  App\Models\Livestock\AnimalEvent::whereHas('animal', fn($q) => $q->where('identificacao', 'LIKE', '__QA%'))->forceDelete();
  echo "cleaned\n";
}
