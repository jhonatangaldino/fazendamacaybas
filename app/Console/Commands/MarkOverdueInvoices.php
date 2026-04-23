<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use Illuminate\Console\Command;

/**
 * billing:mark-overdue — varre invoices vencidas e atualiza status.
 *
 * Regras:
 *   1. Invoice `pending` com `data_vencimento < today` → `overdue`
 *   2. Tenant com pelo menos 1 invoice `overdue` e subscription `active`
 *      → subscription `overdue`
 *   3. Tenant sem nenhuma invoice `overdue` e subscription `overdue`
 *      → subscription `active` (regularização após pagamento)
 *
 * Idempotente — pode rodar várias vezes por dia sem efeito colateral.
 *
 * RECOMENDAÇÃO:
 *   Configurar cron diário (01:00 America/Sao_Paulo) no hPanel Hostinger:
 *     0 1 * * * cd /home/.../current && php artisan billing:mark-overdue
 *
 * Útil também para execução manual via SSH quando o master quer forçar
 * re-avaliação após alterar invoices em massa.
 */
class MarkOverdueInvoices extends Command
{
    protected $signature = 'billing:mark-overdue';
    protected $description = 'Marca invoices pendentes e vencidas como overdue, e atualiza subscriptions';

    public function handle(): int
    {
        $hoje = today()->toDateString();
        $this->info("Data de corte: {$hoje}");

        // ─── 1. Invoices pending vencidas → overdue ──────────────────
        $toMark = Invoice::where('status', 'pending')
            ->whereDate('data_vencimento', '<', $hoje)
            ->get();

        $count = $toMark->count();
        if ($count === 0) {
            $this->info('Nenhuma invoice pendente está vencida.');
        } else {
            foreach ($toMark as $inv) {
                $inv->update(['status' => 'overdue']);
                $this->line("  • Invoice #{$inv->numero} (tenant={$inv->tenant_id}) → overdue (venceu em {$inv->data_vencimento->toDateString()})");
            }
            $this->info("Total marcado como overdue: {$count}");
        }

        // ─── 2. Subscriptions: reconciliação em massa ────────────────
        // Tenants com ao menos 1 invoice overdue → subscription overdue
        $tenantsComOverdue = Invoice::where('status', 'overdue')
            ->distinct()
            ->pluck('tenant_id');

        $subToOverdue = Subscription::whereIn('tenant_id', $tenantsComOverdue)
            ->where('status', 'active')
            ->get();

        foreach ($subToOverdue as $sub) {
            $sub->update(['status' => 'overdue']);
            $this->warn("  • Tenant {$sub->tenant_id}: subscription active → overdue");
        }

        // Tenants sem overdue → subscription active (se estava em overdue)
        $subToReactivate = Subscription::where('status', 'overdue')
            ->whereNotIn('tenant_id', $tenantsComOverdue)
            ->get();

        foreach ($subToReactivate as $sub) {
            $sub->update(['status' => 'active']);
            $this->info("  • Tenant {$sub->tenant_id}: subscription overdue → active (sem pendências)");
        }

        $this->info("Subscriptions atualizadas: {$subToOverdue->count()} → overdue, {$subToReactivate->count()} → active");

        return self::SUCCESS;
    }
}
