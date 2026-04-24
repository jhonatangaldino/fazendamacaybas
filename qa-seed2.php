<?php
$cwd = '/home/u931382046/domains/fazendamacaybas.com.br/releases/current';
require $cwd.'/vendor/autoload.php';
$app = require $cwd.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$u = App\Models\User::updateOrCreate(['email' => 'qa-dono@fazendamacaybas.local'], [
  'name' => 'QA Dono', 'password' => Illuminate\Support\Facades\Hash::make('QADono#2026'),
  'tenant_id' => 1, 'email_verified_at' => now(),
]);
$u->syncRoles(['dono_fazenda']);
echo "ok\n";
