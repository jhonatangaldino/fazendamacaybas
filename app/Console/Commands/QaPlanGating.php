<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\Tenant;
use Illuminate\Console\Command;

class QaPlanGating extends Command
{
    protected $signature = 'qa:plan-gating {action} {tenant_slug=b3mf924907-tenant} {--plan=6}';
    protected $description = 'QA · set/unset plan_id no tenant para testar gating';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant_slug'))->first();
        if (! $tenant) {
            $this->error('Tenant not found');
            return 1;
        }

        $action = $this->argument('action');
        $planId = (int) $this->option('plan');

        if ($action === 'set') {
            $tenant->plan_id = $planId;
            $tenant->save();
            $this->info("plan_id set to {$planId} for tenant {$tenant->id}");
            $this->info('hasFeature(agricola): '.($tenant->fresh()->hasFeature('agricola') ? 'YES' : 'NO'));
            $this->info('hasFeature(rebanho): '.($tenant->fresh()->hasFeature('rebanho') ? 'YES' : 'NO'));
        } elseif ($action === 'unset') {
            $tenant->plan_id = null;
            $tenant->save();
            $this->info("plan_id RESET to null for tenant {$tenant->id}");
        } else {
            $this->error('action must be: set | unset');
            return 1;
        }

        return 0;
    }
}
