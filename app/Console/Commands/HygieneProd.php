<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\Tenant;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * macaybas:hygiene-prod {--dry-run} {--force}
 *
 * DESTRUTIVO — limpa produção e deixa o sistema em estado pristine
 * comercializável, **preservando o tenant master e seus dados estruturais**:
 *
 * MANTÉM:
 *   - Tenant master existente (id=1, nome "Fazenda Macaybas", marcado is_master_tenant=true)
 *   - 1 farm do master ("Fazenda Macaybas")
 *   - User dono do master (Antônio Galdino) com senha redefinida
 *   - Master admins reais (você)
 *   - CMS do tenant master (páginas, seções, menus) — vira landing pública
 *   - BAU global: animal_species, animal_breeds (catálogo compartilhado)
 *   - Catálogo: plans, roles, permissions, settings, tutorials
 *
 * APAGA:
 *   - Outros tenants e tudo associado (users, farms, dados operacionais)
 *   - Dados operacionais do master (animais, transações, estoque, etc.)
 *   - Categorias financeiras (não há BAU global; cada tenant cria as suas)
 *   - Faturas e assinaturas (vão ser geradas conforme uso)
 *   - Master admins de teste (qa_saas@*.test)
 *   - Farms extras do master (mantém só a sede)
 *   - Sessions, jobs, activity_log, tutorial_states
 *   - CMS dos OUTROS tenants
 */
class HygieneProd extends Command
{
    protected $signature = 'macaybas:hygiene-prod
        {--dry-run}
        {--force}
        {--master-tenant-id=1}
        {--master-farm-name=Fazenda Macaybas}
        {--master-owner-email=antonio.galdino90@gmail.com}
        {--master-owner-name=Antônio Galdino}
        {--master-owner-password=}
        {--master-tenant-slug=fazenda-macaybas}';

    protected $description = 'DESTRUTIVO — reseta produção, preserva tenant master + dados estruturais';

    private bool $dry;

    public function handle(): int
    {
        $this->dry = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $this->dry && ! $force) {
            $this->error('Use --dry-run para listar ou --force para executar.');
            return 1;
        }

        $masterId = (int) $this->option('master-tenant-id');
        $masterFarmName = (string) $this->option('master-farm-name');
        $ownerEmail = (string) $this->option('master-owner-email');
        $ownerName = (string) $this->option('master-owner-name');
        $ownerPassword = (string) $this->option('master-owner-password');
        $masterSlug = (string) $this->option('master-tenant-slug');

        $this->info('═══ Macaybas · Higienização Pristine ═══');
        $this->info($this->dry ? 'MODO: DRY-RUN' : 'MODO: EXECUÇÃO REAL');
        $this->newLine();

        // Validações
        $masterTenant = Tenant::find($masterId);
        if (! $masterTenant) {
            $this->error("Tenant master id={$masterId} não encontrado.");
            return 1;
        }
        $this->info("Tenant master: #{$masterTenant->id} {$masterTenant->nome} (slug: {$masterTenant->slug})");

        $owner = User::where('email', $ownerEmail)->first();
        if (! $owner) {
            $this->error("Owner email '{$ownerEmail}' não encontrado.");
            return 1;
        }
        $this->info("Owner: #{$owner->id} {$owner->name} <{$owner->email}> (tenant_id={$owner->tenant_id})");

        $masterFarm = Farm::where('tenant_id', $masterId)->where('nome', $masterFarmName)->first();
        if (! $masterFarm) {
            $this->error("Farm '{$masterFarmName}' não encontrada no tenant master.");
            return 1;
        }
        $this->info("Master farm: #{$masterFarm->id} {$masterFarm->nome}");

        $this->newLine();
        $this->info('═══ Plano de execução ═══');

        // ============================================================
        // 1. Coletar IDs de tenants/farms/users para apagar
        // ============================================================
        $tenantsToDelete = Tenant::where('id', '!=', $masterId)->pluck('id')->toArray();
        $farmsToDelete = array_merge(
            Farm::where('tenant_id', '!=', $masterId)->pluck('id')->toArray(),
            Farm::where('tenant_id', $masterId)->where('id', '!=', $masterFarm->id)->pluck('id')->toArray()
        );
        $usersToDelete = User::query()
            ->where(function ($q) use ($masterId, $owner) {
                // Users de outros tenants
                $q->where('tenant_id', '!=', $masterId)->orWhereNull('tenant_id');
            })
            ->where('id', '!=', 1) // mantém você (id=1)
            ->where('id', '!=', $owner->id) // mantém Antônio
            ->where(function ($q) {
                // Apaga users de teste e users de tenants apagados
                $q->where('email', 'like', '%@fazendamacaybas.test')
                  ->orWhere('email', 'like', '%@*.test')
                  ->orWhereNotNull('tenant_id');
            })
            ->pluck('id')->toArray();

        $this->info('Tenants a apagar: ' . count($tenantsToDelete));
        $this->info('Farms a apagar: ' . count($farmsToDelete) . ' (inclui farms extras do master)');
        $this->info('Users a apagar: ' . count($usersToDelete));
        $this->newLine();

        // ============================================================
        // 2. Tabelas operacionais — TRUNCATE total (apaga master tb)
        // ============================================================
        $tabelasTruncate = [
            // Sessões / fila / telemetria — apaga geral
            ['sessions', 'sessões web'],
            ['jobs', 'fila de jobs'],
            ['failed_jobs', 'jobs falhados'],
            ['activity_log', 'auditoria Spatie'],
            ['user_tutorial_states', 'estado dos tutoriais por user'],

            // Operacional · estoque (master também recomeça do zero)
            ['stock_movements', 'movimentações de estoque'],
            ['stock_items', 'itens de estoque'],
            ['warehouses', 'armazéns'],

            // Operacional · veículos
            ['vehicles', 'veículos/máquinas'],

            // Operacional · rebanho dados (mantém species/breeds = BAU)
            ['animal_events', 'eventos do rebanho'],
            ['animals', 'animais'],
            ['animal_lots', 'lotes'],
            ['animal_locations', 'locais (pastos)'],

            // Operacional · gestão
            ['tasks', 'tarefas'],
            ['documents', 'documentos'],
            ['document_categories', 'categorias de documento'],

            // Operacional · pessoas
            ['employees', 'funcionários'],
            ['partners', 'parceiros'],

            // Operacional · financeiro (sem BAU global → apaga tudo)
            ['financial_transaction_attachments', 'anexos de transações'],
            ['financial_transactions', 'transações financeiras'],
            ['financial_accounts', 'contas financeiras'],
            ['categories', 'categorias financeiras'],
            ['cost_centers', 'centros de custo'],

            // Billing
            ['invoices', 'faturas'],
            ['subscriptions', 'assinaturas'],

            // Mídia
            ['media', 'mídias (Spatie)'],
        ];

        foreach ($tabelasTruncate as [$tabela, $desc]) {
            if (! Schema::hasTable($tabela)) {
                $this->line("  • {$tabela}: NÃO EXISTE — pulando");
                continue;
            }
            $count = DB::table($tabela)->count();
            $this->line("  • {$tabela} ({$desc}): TRUNCATE · {$count} registros");
        }

        // ============================================================
        // 3. CMS — apaga apenas dos outros tenants (mantém master)
        // ============================================================
        $tabelasCmsDelete = [
            ['cms_blocks', 'blocos CMS'],
            ['cms_sections', 'seções CMS'],
            ['cms_pages', 'páginas CMS'],
            ['cms_menu_items', 'itens menu CMS'],
            ['cms_menus', 'menus CMS'],
            ['cms_settings', 'settings CMS'],
        ];
        $this->info('CMS — DELETE WHERE tenant_id != ' . $masterId . ' (preserva master):');
        foreach ($tabelasCmsDelete as [$tabela, $desc]) {
            if (! Schema::hasTable($tabela)) {
                $this->line("  • {$tabela}: NÃO EXISTE — pulando");
                continue;
            }
            $hasTenantCol = Schema::hasColumn($tabela, 'tenant_id');
            if (! $hasTenantCol) {
                // cms_blocks/cms_sections podem ser por page_id, não por tenant_id direto
                $this->line("  • {$tabela}: SEM tenant_id — usando JOIN com cms_pages");
            } else {
                $count = DB::table($tabela)->where('tenant_id', '!=', $masterId)->count();
                $this->line("  • {$tabela} ({$desc}): {$count} registros de outros tenants");
            }
        }

        // ============================================================
        // 4. Users / Farms / Tenants — DELETE seletivo
        // ============================================================
        $this->newLine();
        $this->info('Apagar seletivo:');
        $this->line("  • {$this->countMaster($usersToDelete)} users (mantém você + Antônio + outros master admins reais)");
        $this->line("  • {$this->countMaster($farmsToDelete)} farms (mantém só '{$masterFarmName}' do master)");
        $this->line("  • {$this->countMaster($tenantsToDelete)} tenants (mantém apenas master id={$masterId})");

        // ============================================================
        // 5. Atualizar tenant master (slug, is_master_tenant)
        // ============================================================
        $this->newLine();
        $this->info('Atualizar tenant master:');
        $this->line("  • slug: '{$masterTenant->slug}' → '{$masterSlug}' (se diferente)");
        $this->line("  • is_master_tenant: false → true");

        // ============================================================
        // 6. Atualizar owner (Antônio Galdino)
        // ============================================================
        $this->newLine();
        $this->info('Atualizar owner:');
        $this->line("  • name: '{$owner->name}' → '{$ownerName}'");
        $this->line("  • password: redefinida (must_change_password=true)");
        $this->line("  • is_active: true");

        if ($this->dry) {
            $this->newLine();
            $this->info('DRY-RUN concluído. Use --force para executar.');
            return 0;
        }

        if (! $force) {
            $this->error('Sem --force, abortando.');
            return 1;
        }

        // Confirmação dupla em modo execução
        if (! $this->confirm("CONFIRMAR? Backup pré-higienização foi feito? Esta ação é IRREVERSÍVEL.")) {
            return 1;
        }

        $this->newLine();
        $this->info('═══ Executando ═══');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            // 1. Truncate tabelas operacionais
            foreach ($tabelasTruncate as [$tabela, $desc]) {
                if (! Schema::hasTable($tabela)) continue;
                DB::table($tabela)->truncate();
                $this->line("  ✓ TRUNCATE {$tabela}");
            }

            // 2. CMS dos outros tenants — ordem FK-safe (filhos antes)
            // cms_blocks e cms_sections referenciam cms_pages
            // cms_menu_items referenciam cms_menus
            $masterPageIds = DB::table('cms_pages')->where('tenant_id', $masterId)->pluck('id')->toArray();
            $masterMenuIds = DB::table('cms_menus')->where('tenant_id', $masterId)->pluck('id')->toArray();

            if (Schema::hasTable('cms_blocks')) {
                if (Schema::hasColumn('cms_blocks', 'tenant_id')) {
                    DB::table('cms_blocks')->where('tenant_id', '!=', $masterId)->delete();
                } else {
                    // por page_id
                    DB::table('cms_blocks')->whereNotIn('page_id', $masterPageIds)->delete();
                }
                $this->line('  ✓ cms_blocks (outros tenants)');
            }
            if (Schema::hasTable('cms_sections')) {
                if (Schema::hasColumn('cms_sections', 'tenant_id')) {
                    DB::table('cms_sections')->where('tenant_id', '!=', $masterId)->delete();
                } else {
                    DB::table('cms_sections')->whereNotIn('page_id', $masterPageIds)->delete();
                }
                $this->line('  ✓ cms_sections (outros tenants)');
            }
            if (Schema::hasTable('cms_pages')) {
                DB::table('cms_pages')->where('tenant_id', '!=', $masterId)->delete();
                $this->line('  ✓ cms_pages (outros tenants)');
            }
            if (Schema::hasTable('cms_menu_items')) {
                if (Schema::hasColumn('cms_menu_items', 'tenant_id')) {
                    DB::table('cms_menu_items')->where('tenant_id', '!=', $masterId)->delete();
                } else {
                    DB::table('cms_menu_items')->whereNotIn('menu_id', $masterMenuIds)->delete();
                }
                $this->line('  ✓ cms_menu_items (outros tenants)');
            }
            if (Schema::hasTable('cms_menus')) {
                DB::table('cms_menus')->where('tenant_id', '!=', $masterId)->delete();
                $this->line('  ✓ cms_menus (outros tenants)');
            }
            if (Schema::hasTable('cms_settings')) {
                DB::table('cms_settings')->where('tenant_id', '!=', $masterId)->delete();
                $this->line('  ✓ cms_settings (outros tenants)');
            }

            // 3. Pivots Spatie órfãos preventivos (antes de apagar users)
            // 4. Apagar users (exceto master admins reais e Antônio)
            $usersDeletados = User::query()
                ->where(function ($q) use ($masterId, $owner) {
                    $q->where(function ($q) use ($masterId) {
                        $q->where('tenant_id', '!=', $masterId)->orWhereNull('tenant_id');
                    });
                })
                ->where('id', '!=', 1)
                ->where('id', '!=', $owner->id)
                ->where(function ($q) {
                    $q->where('email', 'like', '%fazendamacaybas.test')
                      ->orWhereNotNull('tenant_id');
                })
                ->delete();
            $this->line("  ✓ users apagados: {$usersDeletados}");

            // 5. Apagar farms (de outros tenants + extras do master)
            $farmsDeletados = Farm::query()
                ->where(function ($q) use ($masterId, $masterFarm) {
                    $q->where('tenant_id', '!=', $masterId)
                      ->orWhere(function ($q) use ($masterId, $masterFarm) {
                          $q->where('tenant_id', $masterId)
                            ->where('id', '!=', $masterFarm->id);
                      });
                })
                ->delete();
            $this->line("  ✓ farms apagadas: {$farmsDeletados}");

            // 6. Apagar outros tenants
            $tenantsDeletados = Tenant::where('id', '!=', $masterId)->delete();
            $this->line("  ✓ tenants apagados: {$tenantsDeletados}");

            // 7. Limpar pivots Spatie órfãos (model_has_roles, model_has_permissions)
            $remainingUserIds = User::pluck('id')->toArray();
            DB::table('model_has_roles')->whereNotIn('model_id', $remainingUserIds)->delete();
            DB::table('model_has_permissions')->whereNotIn('model_id', $remainingUserIds)->delete();
            $this->line('  ✓ pivots Spatie órfãos limpos');

            // 8. Atualizar tenant master
            $masterTenant->refresh();
            $changes = ['is_master_tenant' => true, 'is_active' => true, 'status' => 'active'];
            if ($masterTenant->slug !== $masterSlug) {
                $changes['slug'] = $masterSlug;
            }
            $masterTenant->update($changes);
            $this->line("  ✓ tenant master atualizado (slug={$masterTenant->slug}, is_master_tenant=true)");

            // 9. Atualizar owner
            $ownerUpdates = [
                'name' => $ownerName,
                'is_active' => true,
                'tenant_id' => $masterId,
            ];
            if (! empty($ownerPassword)) {
                $ownerUpdates['password'] = Hash::make($ownerPassword);
                $ownerUpdates['must_change_password'] = true;
            }
            $owner->update($ownerUpdates);

            // Garantir role dono_fazenda
            if (\Spatie\Permission\Models\Role::where('name', 'dono_fazenda')->exists()) {
                $owner->syncRoles(['dono_fazenda']);
            }
            $this->line('  ✓ owner Antônio atualizado (senha redefinida, must_change_password=true)');

            Tenant::clearMasterCache();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->info('═══ Higienização concluída ═══');
        $this->info('Estado atual:');
        $this->info('  Tenants: ' . Tenant::count() . ' (deve ser 1: master)');
        $this->info('  Farms: ' . Farm::count() . ' (deve ser 1: Fazenda Macaybas)');
        $this->info('  Users: ' . User::count() . ' (você + Antônio = 2)');
        $this->info('  Animais: ' . DB::table('animals')->count() . ' (deve ser 0)');
        $this->info('  Categorias: ' . DB::table('categories')->count() . ' (deve ser 0)');
        $this->info('  Espécies: ' . DB::table('animal_species')->count() . ' (BAU preservado)');
        $this->info('  Raças: ' . DB::table('animal_breeds')->count() . ' (BAU preservado)');
        return 0;
    }

    private function countMaster(array $arr): int
    {
        return count($arr);
    }
}
