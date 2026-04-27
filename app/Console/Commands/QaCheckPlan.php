<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Tenant;
use Illuminate\Console\Command;

class QaCheckPlan extends Command
{
    protected $signature = 'qa:check-plan {slug=b3mf924907}';
    protected $description = 'QA · check tenant plan + features';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('slug'))->first();
        if (! $tenant) {
            $this->error('Tenant not found');
            return 1;
        }
        $this->info('Tenant: '.$tenant->id.' slug='.$tenant->slug);
        $this->info('Plan ID: '.($tenant->plan_id ?? 'null'));
        $plan = $tenant->plan;
        $this->info('Plan: '.($plan?->nome ?? 'null').' slug='.($plan?->slug ?? 'null'));
        $this->info('Plan features: '.json_encode($plan?->features ?? []));
        $this->info('hasFeature(agricola): '.($tenant->hasFeature('agricola') ? 'YES' : 'NO'));
        $this->info('hasFeature(rebanho): '.($tenant->hasFeature('rebanho') ? 'YES' : 'NO'));
        $this->info('hasFeature(maquinas): '.($tenant->hasFeature('maquinas') ? 'YES' : 'NO'));

        $this->line('--- Plans ---');
        foreach (Plan::all() as $p) {
            $this->line('#'.$p->id.' '.$p->slug.' = '.json_encode($p->features));
        }

        return 0;
    }
}
