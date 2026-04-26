<?php
// Diagnóstico do tenant QA b3mf924907 — quantas farms, users, dados
use App\Models\User;
use App\Models\Farm;
use App\Domain\Billing\Models\Tenant;

$tenant = Tenant::where('slug', 'b3mf924907-tenant')->first();
if (! $tenant) { echo "Tenant não encontrado\n"; return; }
echo "Tenant: {$tenant->id} - {$tenant->nome}\n";

$farms = Farm::where('tenant_id', $tenant->id)->get();
echo "Farms: " . $farms->count() . "\n";
foreach ($farms as $f) {
    echo "  - id={$f->id} nome={$f->nome} active=" . ($f->is_active ? 'sim' : 'nao') . "\n";
}

$users = User::where('tenant_id', $tenant->id)->get();
echo "Users: " . $users->count() . "\n";
foreach ($users as $u) {
    $roleNames = $u->roles->pluck('name')->join(',');
    echo "  - id={$u->id} email={$u->email} farm={$u->current_farm_id} roles={$roleNames}\n";
}
