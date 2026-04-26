<?php
// Verifica persistência dos modelos que cada wizard alimenta
use App\Models\Animal as AnimalAlt;
use App\Models\Livestock\Animal;
use App\Models\Livestock\AnimalEvent;
use App\Models\Financial\FinancialTransaction;
use App\Models\Stock\StockMovement;
use App\Models\Stock\StockItem;
use App\Models\Task\Task;
use App\Models\Vehicles\MaintenanceOrder;
use App\Domain\Billing\Models\Tenant;

$tenant = Tenant::where('slug', 'b3mf924907-tenant')->first();
echo "Tenant {$tenant->id}\n\n";

$resumo = [
    'Animais (Wizard CadastrarAnimal)' => Animal::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
    'Animal Events (Wizard Pesagem/Vacina/Medicamento/Vermifugo)' => class_exists(\App\Models\Livestock\AnimalEvent::class)
        ? \App\Models\Livestock\AnimalEvent::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count()
        : 'modelo inexistente',
    'Transações financeiras (Wizards Despesa/Receita)' => FinancialTransaction::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
    'Stock movements (Wizards Receber/Ajustar/Saída)' => StockMovement::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
    'Stock items (cadastrados via wizards/forms)' => StockItem::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
    'Tarefas (Wizard Tarefa)' => Task::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
];

foreach ($resumo as $k => $v) {
    $icon = is_numeric($v) && $v > 0 ? '✓' : '⚠';
    echo "{$icon} {$k}: {$v}\n";
}

echo "\n--- Últimos registros (5 mais recentes) ---\n";
echo "Transações:\n";
foreach (FinancialTransaction::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->orderByDesc('id')->take(5)->get() as $t) {
    echo "  #{$t->id} [{$t->tipo}] {$t->descricao} R$ {$t->valor} ({$t->status})\n";
}
