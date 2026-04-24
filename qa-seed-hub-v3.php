<?php
// Seed robusto pra QA E2E do Hub v3/v4.
$cwd = '/home/u931382046/domains/fazendamacaybas.com.br/releases/current';
require $cwd.'/vendor/autoload.php';
$app = require $cwd.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$acao = $argv[1] ?? 'seed';
$tenantId = 1;

if ($acao === 'cleanup') {
    DB::table('menu_usage')->where('menu_key', 'like', 'hub:%')->delete();
    App\Models\User::where('email', 'qa-dono@fazendamacaybas.local')->forceDelete();
    echo "cleanup ok\n";
    return;
}

$u = App\Models\User::updateOrCreate(
    ['email' => 'qa-dono@fazendamacaybas.local'],
    ['name' => 'Carlos QA', 'password' => Hash::make('QADono#2026'),
     'tenant_id' => $tenantId, 'email_verified_at' => now()]
);
$u->syncRoles(['dono_fazenda']);
echo "- user qa-dono id=$u->id\n";

$farmId = App\Models\Farm::where('tenant_id', $tenantId)->value('id');
echo "- farm_id=$farmId\n";

// Parceiros
$forn = App\Models\Partner::where('tenant_id', $tenantId)->whereIn('tipo', ['fornecedor','ambos'])->first()
    ?? App\Models\Partner::create(['tenant_id'=>$tenantId,'nome'=>'Agropecuária QA','tipo'=>'fornecedor','pessoa'=>'pj','documento'=>'12345678000100']);
$cli = App\Models\Partner::where('tenant_id', $tenantId)->whereIn('tipo', ['cliente','ambos'])->first()
    ?? App\Models\Partner::create(['tenant_id'=>$tenantId,'nome'=>'Frigorífico QA','tipo'=>'cliente','pessoa'=>'pj','documento'=>'98765432000100']);

// Conta
$conta = App\Models\Financial\FinancialAccount::where('tenant_id', $tenantId)->first()
    ?? App\Models\Financial\FinancialAccount::create(['tenant_id'=>$tenantId,'nome'=>'Caixa QA','tipo'=>'caixa','saldo_inicial'=>10000,'is_active'=>true]);

// Armazém + item
$arm = App\Models\Stock\Warehouse::where('tenant_id', $tenantId)->first()
    ?? App\Models\Stock\Warehouse::create(['tenant_id'=>$tenantId,'farm_id'=>$farmId,'nome'=>'Galpão QA','is_active'=>true]);
$item = App\Models\Stock\StockItem::where('tenant_id', $tenantId)->first()
    ?? App\Models\Stock\StockItem::create(['tenant_id'=>$tenantId,'codigo'=>'UREIA-QA','nome'=>'Ureia 45% QA','tipo'=>'insumo','unidade'=>'kg','estoque_minimo'=>50,'is_active'=>true]);

// Cultura/safra/talhão
$crop = App\Models\Agricultural\Crop::where('tenant_id', $tenantId)->first()
    ?? App\Models\Agricultural\Crop::create(['tenant_id'=>$tenantId,'nome'=>'Milho QA','slug'=>'milho-qa']);
$season = App\Models\Agricultural\Season::where('tenant_id', $tenantId)->first()
    ?? App\Models\Agricultural\Season::create(['tenant_id'=>$tenantId,'nome'=>'Safra 25/26 QA','data_inicio'=>'2025-08-01','data_fim'=>'2026-07-31']);
$talhao = App\Models\Agricultural\Field::where('tenant_id', $tenantId)->first()
    ?? App\Models\Agricultural\Field::create(['tenant_id'=>$tenantId,'farm_id'=>$farmId,'nome'=>'Talhão QA','area_ha'=>25.5,'is_active'=>true]);

// Veículo
$vei = App\Models\Vehicle\Vehicle::where('tenant_id', $tenantId)->first()
    ?? App\Models\Vehicle\Vehicle::create(['tenant_id'=>$tenantId,'farm_id'=>$farmId,'nome'=>'Trator QA','tipo'=>'trator','is_active'=>true]);

// Funcionário
$func = App\Models\Employee::where('tenant_id', $tenantId)->where('is_active', true)->first()
    ?? App\Models\Employee::create(['tenant_id'=>$tenantId,'farm_id'=>$farmId,'nome'=>'José QA','is_active'=>true,'setor'=>'Campo','data_admissao'=>'2024-01-15']);

// Animal
$animal = App\Models\Livestock\Animal::where('tenant_id', $tenantId)->where('status', 'ativo')->first();
if (! $animal) {
    $esp = App\Models\Livestock\AnimalSpecies::firstOrCreate(['nome' => 'Bovino'], []);
    $animal = App\Models\Livestock\Animal::create([
        'tenant_id' => $tenantId, 'farm_id' => $farmId,
        'identificacao' => 'QA-001', 'nome' => 'Mimosa QA',
        'species_id' => $esp->id, 'sexo' => 'F', 'categoria' => 'corte',
        'peso_atual' => 400, 'status' => 'ativo',
    ]);
}

// Categoria financeira receita + despesa (algumas telas usam)
App\Models\Category::firstOrCreate(
    ['tenant_id' => $tenantId, 'nome' => 'Combustível', 'tipo' => 'financeiro_despesa'],
    ['slug' => 'combustivel']
);
App\Models\Category::firstOrCreate(
    ['tenant_id' => $tenantId, 'nome' => 'Venda de leite', 'tipo' => 'financeiro_receita'],
    ['slug' => 'venda-de-leite']
);

echo "- seed ok · animal=$animal->id · talhao=$talhao->id · veiculo=$vei->id · conta=$conta->id\n";
