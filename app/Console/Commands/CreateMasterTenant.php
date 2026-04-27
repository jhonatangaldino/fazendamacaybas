<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Tenant;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * macaybas:create-master-tenant
 *
 * Cria o tenant master "Fazenda Macaybas" + farm + user dono.
 * Marca o tenant como is_master_tenant=true (a landing pública do
 * domínio raiz passa a renderizar o CMS desse tenant).
 *
 * Uso:
 *   php artisan macaybas:create-master-tenant \
 *      --nome="Fazenda Macaybas" \
 *      --slug=fazenda-macaybas \
 *      --farm-nome="Fazenda Macaybas (sede)" \
 *      --owner-email=dono@fazendamacaybas.com.br \
 *      --owner-nome="Dono da Fazenda" \
 *      --owner-senha=TempSenha2026
 *
 * Ações:
 *   1. Cria o tenant com is_master_tenant=true
 *   2. Atribui plano (default: profissional, ou --plan=slug)
 *   3. Cria farm dentro do tenant
 *   4. Cria user dono com role 'dono_fazenda'
 *   5. Inicializa páginas CMS default (clone da landing template)
 */
class CreateMasterTenant extends Command
{
    protected $signature = 'macaybas:create-master-tenant
        {--nome=Fazenda Macaybas}
        {--slug=fazenda-macaybas}
        {--farm-nome=Fazenda Macaybas (sede)}
        {--owner-email=}
        {--owner-nome=}
        {--owner-senha=}
        {--plan=profissional}
        {--force}';

    protected $description = 'Cria o tenant master "Fazenda Macaybas" + dono';

    public function handle(): int
    {
        // Validação de inputs obrigatórios
        $email = $this->option('owner-email') ?: $this->ask('Email do dono?');
        $nome = $this->option('owner-nome') ?: $this->ask('Nome do dono?');
        $senha = $this->option('owner-senha') ?: $this->secret('Senha do dono (mín 8 caracteres)?');

        if (! $email || ! $nome || ! $senha) {
            $this->error('owner-email, owner-nome e owner-senha são obrigatórios.');
            return 1;
        }
        if (strlen($senha) < 8) {
            $this->error('Senha deve ter no mínimo 8 caracteres.');
            return 1;
        }

        $tenantNome = $this->option('nome');
        $tenantSlug = $this->option('slug');
        $farmNome = $this->option('farm-nome');
        $planSlug = $this->option('plan');

        // Verifica se já existe master
        $masterAtual = Tenant::master();
        if ($masterAtual !== null && ! $this->option('force')) {
            $this->error("Já existe um tenant master: '{$masterAtual->nome}' (slug={$masterAtual->slug}). Use --force para sobrescrever.");
            return 1;
        }

        // Verifica se slug já existe
        if (Tenant::where('slug', $tenantSlug)->exists()) {
            $this->error("Já existe tenant com slug '{$tenantSlug}'.");
            return 1;
        }

        // Verifica se email já existe
        if (User::where('email', $email)->exists()) {
            $this->error("Já existe user com email '{$email}'.");
            return 1;
        }

        // Resolve plano
        $plan = Plan::where('slug', $planSlug)->first();
        if (! $plan) {
            $this->error("Plano '{$planSlug}' não encontrado.");
            return 1;
        }

        $this->info("═══ Macaybas · Criação do tenant master ═══");
        $this->info("Tenant: {$tenantNome} (slug: {$tenantSlug})");
        $this->info("Farm: {$farmNome}");
        $this->info("Owner: {$nome} <{$email}>");
        $this->info("Plano: {$plan->nome}");

        if (! $this->confirm('Confirmar criação?')) {
            $this->info('Cancelado.');
            return 1;
        }

        DB::transaction(function () use ($tenantNome, $tenantSlug, $farmNome, $email, $nome, $senha, $plan) {
            // Desmarca master anterior se houver
            Tenant::where('is_master_tenant', true)->update(['is_master_tenant' => false]);

            // 1. Tenant
            $tenant = Tenant::create([
                'nome' => $tenantNome,
                'slug' => $tenantSlug,
                'plan_id' => $plan->id,
                'status' => 'active',
                'is_active' => true,
                'is_master_tenant' => true,
            ]);

            // 2. Farm
            $farm = Farm::create([
                'tenant_id' => $tenant->id,
                'nome' => $farmNome,
                'is_active' => true,
            ]);

            // 3. User dono
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $nome,
                'email' => $email,
                'password' => Hash::make($senha),
                'is_active' => true,
                'must_change_password' => true,
            ]);
            // Role dono_fazenda (se existe)
            if (\Spatie\Permission\Models\Role::where('name', 'dono_fazenda')->exists()) {
                $user->assignRole('dono_fazenda');
            }

            $this->newLine();
            $this->info("✓ Tenant master criado · ID: {$tenant->id}");
            $this->info("✓ Farm criada · ID: {$farm->id}");
            $this->info("✓ User dono criado · ID: {$user->id}");
        });

        Tenant::clearMasterCache();

        $this->newLine();
        $this->info('═══ Próximos passos ═══');
        $this->info('1. Login em fazendamacaybas.com.br/login com o email e senha do dono');
        $this->info('2. Sistema vai pedir troca de senha (must_change_password=true)');
        $this->info('3. Editar CMS via /admin/cms para personalizar a landing pública');

        return 0;
    }
}
