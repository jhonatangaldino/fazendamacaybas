<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * macaybas:set-master-tenant {slug}
 *
 * Marca o tenant identificado pelo slug como "master tenant" da plataforma.
 * Apenas 1 tenant pode ter essa flag (constraint via partial unique index).
 * Se já houver outro com a flag, ele é desmarcado primeiro.
 *
 * Uso:
 *   php artisan macaybas:set-master-tenant fazenda-macaybas
 *   php artisan macaybas:set-master-tenant fazenda-macaybas --unset
 */
class SetMasterTenant extends Command
{
    protected $signature = 'macaybas:set-master-tenant {slug} {--unset}';
    protected $description = 'Marca/desmarca o tenant master único da plataforma';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $tenant = Tenant::where('slug', $slug)->first();
        if (! $tenant) {
            $this->error("Tenant com slug '{$slug}' não encontrado.");
            return 1;
        }

        if ($this->option('unset')) {
            $tenant->is_master_tenant = false;
            $tenant->save();
            $this->info("Tenant '{$tenant->nome}' (#{$tenant->id}) DESMARCADO como master.");
            Tenant::clearMasterCache();
            return 0;
        }

        DB::transaction(function () use ($tenant) {
            // Desmarca qualquer outro master existente (defensive — constraint
            // já impede 2 simultâneos, mas isso evita erro de unique violation).
            Tenant::where('is_master_tenant', true)
                ->where('id', '!=', $tenant->id)
                ->update(['is_master_tenant' => false]);

            $tenant->is_master_tenant = true;
            $tenant->save();
        });

        Tenant::clearMasterCache();
        $this->info("✓ Tenant '{$tenant->nome}' (#{$tenant->id}, slug={$tenant->slug}) marcado como MASTER.");
        $this->info("  A landing pública de fazendamacaybas.com.br renderizará o CMS deste tenant.");

        return 0;
    }
}
