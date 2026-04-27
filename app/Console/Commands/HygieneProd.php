<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * macaybas:hygiene-prod
 *
 * DESTRUTIVO — apaga todos os dados de teste de produção e deixa o sistema
 * em estado "pristine" comercializável. Mantém:
 *   - Schema (todas migrations)
 *   - Master admins (users com tenant_id NULL)
 *   - Plans (catálogo de planos comerciais)
 *   - Roles + Permissions (catálogo Spatie)
 *   - Settings globais
 *   - Tutorials (catálogo, sem o estado de leitura)
 *
 * Apaga:
 *   - Todos os tenants e dados associados (animais, transações, etc.)
 *   - Sessions, jobs, activity_log, tenancy_detector_log
 *   - User tutorial states
 *
 * Uso:
 *   php artisan macaybas:hygiene-prod --dry-run
 *   php artisan macaybas:hygiene-prod --force
 *
 * --dry-run apenas LISTA o que seria apagado, sem deletar.
 * --force confirma que você quer rodar em produção.
 */
class HygieneProd extends Command
{
    protected $signature = 'macaybas:hygiene-prod {--dry-run} {--force}';
    protected $description = 'DESTRUTIVO — apaga dados de teste e prepara base limpa';

    /**
     * Tabelas a TRUNCATE em ordem (FK-safe).
     * Cada item: ['tabela', 'descrição'].
     */
    private array $tabelasParaApagar = [
        // Sessões / fila / telemetria
        ['sessions', 'sessões web'],
        ['jobs', 'fila de jobs pendentes'],
        ['failed_jobs', 'jobs falhados'],
        ['activity_log', 'auditoria Spatie'],
        ['tenancy_detector_logs', 'logs do detector R2.5'],
        ['user_tutorial_states', 'estado dos tutoriais por user'],
        ['impersonation_audits', 'audit de impersonação'],

        // Operacional · agrícola
        ['agricultural_applications', 'aplicações agrícolas'],
        ['agricultural_harvests', 'colheitas'],
        ['agricultural_plantings', 'plantios'],
        ['agricultural_crops', 'culturas'],
        ['agricultural_fields', 'talhões'],

        // Operacional · estoque
        ['stock_movements', 'movimentações de estoque'],
        ['stock_items', 'itens de estoque'],
        ['warehouses', 'armazéns'],

        // Operacional · veículos
        ['vehicle_maintenances', 'manutenções'],
        ['vehicles', 'veículos/máquinas'],

        // Operacional · rebanho
        ['animal_events', 'eventos do rebanho'],
        ['animal_lots_history', 'histórico de movimentação de lotes'],
        ['animals', 'animais'],
        ['animal_lots', 'lotes'],
        ['animal_locations', 'locais (pastos)'],
        ['animal_breeds', 'raças'],
        ['animal_species', 'espécies'],

        // Operacional · gestão
        ['tasks', 'tarefas'],
        ['documents', 'documentos'],
        ['document_categories', 'categorias de documento'],

        // Operacional · pessoas
        ['employees', 'funcionários'],
        ['partners', 'parceiros'],

        // Operacional · financeiro
        ['financial_transaction_attachments', 'anexos de transações'],
        ['financial_transactions', 'transações financeiras'],
        ['financial_accounts', 'contas financeiras'],
        ['categories', 'categorias financeiras'],
        ['cost_centers', 'centros de custo'],

        // CMS dos tenants
        ['cms_blocks', 'blocos CMS'],
        ['cms_sections', 'seções CMS'],
        ['cms_pages', 'páginas CMS'],
        ['cms_menu_items', 'itens de menu CMS'],
        ['cms_menus', 'menus CMS'],
        ['cms_settings', 'settings CMS por tenant'],
        ['media', 'mídias (Spatie medialibrary)'],

        // Billing · invoices e subscriptions são de tenants
        ['invoices', 'faturas'],
        ['subscriptions', 'assinaturas'],

        // Identidade do tenant
        ['farms', 'fazendas'],

        // Pivot users-roles dos não-master (limpeza preventiva)
        // Pivots Spatie limpos seletivamente — model_has_roles + model_has_permissions
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (! $dryRun && ! $force) {
            $this->error('Operação destrutiva. Use --dry-run para listar ou --force para executar.');
            return 1;
        }

        $this->info('═══ Macaybas · Higienização de produção ═══');
        $this->info($dryRun ? 'MODO: DRY-RUN (nada será apagado)' : 'MODO: EXECUÇÃO REAL');
        $this->newLine();

        // ─── Snapshot pré-higienização ───
        $masterAdmins = User::whereNull('tenant_id')->count();
        $tenants = Tenant::count();
        $plans = Plan::count();
        $this->info("Snapshot atual:");
        $this->info("  Master admins (preservados): {$masterAdmins}");
        $this->info("  Tenants (serão apagados): {$tenants}");
        $this->info("  Planos (preservados): {$plans}");
        $this->newLine();

        // ─── Listar tabelas e seus counts ───
        $totalRecordsToDrop = 0;
        $this->info("Tabelas afetadas:");
        foreach ($this->tabelasParaApagar as [$tabela, $desc]) {
            if (! Schema::hasTable($tabela)) {
                $this->line("  • {$tabela} ({$desc}): TABELA NÃO EXISTE — pulando");
                continue;
            }
            $count = DB::table($tabela)->count();
            $totalRecordsToDrop += $count;
            $this->line("  • {$tabela} ({$desc}): {$count} registros");
        }

        // Tenants e users serão tratados separadamente
        $usersToDrop = User::whereNotNull('tenant_id')->count();
        $totalRecordsToDrop += $usersToDrop + $tenants;
        $this->line("  • users (tenant_id != NULL): {$usersToDrop} registros");
        $this->line("  • tenants: {$tenants} registros");

        $this->newLine();
        $this->info("Total: {$totalRecordsToDrop} registros para apagar");
        $this->newLine();

        if ($dryRun) {
            $this->info('DRY-RUN concluído. Nenhuma alteração feita.');
            return 0;
        }

        // ─── Confirmação dupla ───
        if (! $this->confirm("CONFIRMAR? Esta ação é IRREVERSÍVEL. Você fez backup recente?")) {
            $this->info('Cancelado pelo usuário.');
            return 1;
        }

        // ─── Execução ───
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            // Apaga tabelas listadas
            foreach ($this->tabelasParaApagar as [$tabela, $desc]) {
                if (! Schema::hasTable($tabela)) continue;
                DB::table($tabela)->truncate();
                $this->line("  ✓ {$tabela} truncado");
            }

            // Apaga users de tenants (preserva master admins)
            $usersDropped = User::whereNotNull('tenant_id')->delete();
            $this->line("  ✓ users (tenant_id != NULL): {$usersDropped} apagados");

            // Apaga tenants
            $tenantsDropped = Tenant::query()->delete();
            $this->line("  ✓ tenants: {$tenantsDropped} apagados");

            // Limpa pivots Spatie órfãos (model_has_roles, model_has_permissions
            // que apontam para users apagados)
            DB::table('model_has_roles')
                ->whereNotIn('model_id', User::pluck('id'))
                ->delete();
            DB::table('model_has_permissions')
                ->whereNotIn('model_id', User::pluck('id'))
                ->delete();
            $this->line("  ✓ pivots Spatie órfãos limpos");
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->info('✓ Higienização concluída.');
        $this->info('Próximo passo: criar tenant master via macaybas:create-master-tenant');
        return 0;
    }
}
