<?php
use App\Models\Financial\FinancialTransaction;
use App\Models\Livestock\Animal;

echo "=== ISOLAMENTO MULTI-FARM ===\n";
foreach ([61, 62, 63] as $farmId) {
    $trxCount = FinancialTransaction::withoutGlobalScope('farm')->where('farm_id', $farmId)->count();
    $trxIds = FinancialTransaction::withoutGlobalScope('farm')->where('farm_id', $farmId)->pluck('id')->take(3)->join(',');
    $animais = Animal::withoutGlobalScope('farm')->where('farm_id', $farmId)->count();
    $animaisIds = Animal::withoutGlobalScope('farm')->where('farm_id', $farmId)->pluck('identificacao')->take(3)->join(',');
    echo "Farm {$farmId}: trx={$trxCount} (ids={$trxIds}) | animais={$animais} (ids={$animaisIds})\n";
}
