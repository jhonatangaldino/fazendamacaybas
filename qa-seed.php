<?php
$cwd = '/home/u931382046/domains/fazendamacaybas.com.br/releases/current';
require $cwd.'/vendor/autoload.php';
$app = require $cwd.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Partner;
use App\Models\Livestock\Animal;
use App\Models\Livestock\AnimalSpecies;
use App\Models\Livestock\AnimalLot;
use App\Models\Financial\FinancialAccount;
use Illuminate\Support\Facades\Hash;

$act = $argv[1] ?? 'setup';

if ($act === 'setup') {
  // Usuário QA
  $u = User::updateOrCreate(['email' => 'qa-dono@fazendamacaybas.local'], [
    'name' => 'QA Dono', 'password' => Hash::make('QADono#2026'),
    'tenant_id' => 1, 'email_verified_at' => now(),
  ]);
  $u->syncRoles(['dono_fazenda']);

  // Parceiro cliente para testes de venda
  Partner::updateOrCreate(['nome' => '__QA_CLIENTE_FIXO'], [
    'tipo' => 'cliente', 'pessoa' => 'pf',
    'documento' => '529.982.247-25',
    'is_active' => true, 'tenant_id' => 1, 'farm_id' => 12,
  ]);

  // Parceiro fornecedor
  Partner::updateOrCreate(['nome' => '__QA_FORN_FIXO'], [
    'tipo' => 'fornecedor', 'pessoa' => 'pj',
    'documento' => '11.222.333/0001-81',
    'is_active' => true, 'tenant_id' => 1, 'farm_id' => 12,
  ]);

  // Lote de destino para movimentação
  $lote = AnimalLot::updateOrCreate(['nome' => '__QA_LOTE_DEST'], [
    'codigo' => 'QA-LT',
    'is_active' => true, 'tenant_id' => 1, 'farm_id' => 12,
  ]);

  // Animal ativo (restaurar o 2 se estiver vendido)
  Animal::where('id', 2)->update([
    'status' => 'ativo', 'data_saida' => null, 'peso_atual' => 500,
  ]);

  // Verifica que tem conta financeira ativa para integração F2.1 gerar FT
  if (! FinancialAccount::where('is_active', true)->exists()) {
    FinancialAccount::create([
      'nome' => '__QA Conta Corrente',
      'tipo' => 'corrente',
      'saldo_inicial' => 0,
      'is_active' => true,
      'tenant_id' => 1,
      'farm_id' => 12,
    ]);
  }

  echo "setup OK: user, partner cliente, partner fornecedor, lote, animal 2 restaurado\n";
}

if ($act === 'teardown') {
  User::where('email', 'qa-dono@fazendamacaybas.local')->forceDelete();
  Partner::where('nome', 'LIKE', '__QA%')->forceDelete();
  AnimalLot::where('nome', 'LIKE', '__QA%')->forceDelete();
  FinancialAccount::where('nome', 'LIKE', '__QA%')->forceDelete();
  Animal::where('id', 2)->update(['status' => 'ativo', 'data_saida' => null, 'peso_atual' => 500]);
  App\Models\Livestock\AnimalEvent::where('animal_id', 2)->forceDelete();
  echo "cleaned\n";
}
