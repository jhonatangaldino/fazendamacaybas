<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\Tenant;
use Carbon\Carbon;

/**
 * BillingStatusService — calcula e aplica o status de billing por tenant.
 *
 * Responsável por traduzir o estado das faturas + vigência da subscription
 * em um estado canônico do tenant:
 *
 *   ativo / em_dia          → vigência cobre hoje + sem invoices em atraso
 *   proximo_vencimento      → vigência cobre hoje + invoice pending vence em ≤ 7 dias
 *   vencido                 → existe invoice overdue (mas vigência ainda cobre hoje)
 *   em_carencia             → vigência expirou; estamos dentro dos 10 dias de carência
 *   bloqueado               → vigência expirou e carência (10 dias) também
 *   sem_assinatura          → tenant sem subscription
 *
 * SUBSCRIPTION STATUS (persistido):
 *   active | trial | overdue | grace | blocked | canceled
 *
 * Regra de bloqueio (10 dias de carência):
 *   Se subscription.current_period_end < today, entra em carência.
 *   grace_until = current_period_end + 10 dias (se ainda não setado).
 *   Quando today > grace_until → status='blocked'.
 *
 * Regularização: se aparece nova subscription com vigência > hoje (porque
 * uma fatura de período subsequente foi paga e estendeu a vigência), o
 * service desbloqueia automaticamente.
 */
class BillingStatusService
{
    public const GRACE_DAYS = 10;

    /**
     * Reavalia e atualiza status persistido da subscription do tenant.
     * Idempotente — pode rodar quantas vezes quiser sem efeito colateral.
     *
     * @return string status final aplicado
     */
    public function reconcile(int $tenantId): string
    {
        $sub = Subscription::where('tenant_id', $tenantId)->first();
        if ($sub === null) {
            return 'sem_assinatura';
        }

        $hoje = today();
        $patch = [];
        $status = $sub->status;

        // Cancelado é estado terminal — só master pode reativar via UI
        if ($status === 'canceled') {
            return 'canceled';
        }

        $vigenciaFim = $sub->current_period_end;
        $vigenciaCobreHoje = $vigenciaFim !== null && $vigenciaFim->gte($hoje->copy()->endOfDay());

        if ($vigenciaCobreHoje) {
            // Vigência OK. Verifica overdue de fatura individual.
            // CRÍTICO: só faturas do PLANO (tipo='mensal') disparam bloqueio.
            // Cobranças avulsas (tipo='unica') são extras e NÃO bloqueiam o
            // tenant — quem cobra avulso resolve por fora (cobrança direta,
            // protesto, etc.). Acordo confirmado pelo PO em 2026-04-28.
            $hasOverdue = Invoice::where('tenant_id', $tenantId)
                ->where('status', 'overdue')
                ->where('tipo', 'mensal')
                ->exists();
            if ($hasOverdue) {
                if ($status !== 'overdue') $patch['status'] = 'overdue';
            } else {
                if (in_array($status, ['overdue', 'grace', 'blocked'], true)) {
                    $patch['status'] = 'active';
                }
            }
            // Sai de qualquer carência se vigência cobre hoje
            if ($sub->grace_until !== null) $patch['grace_until'] = null;
        } else {
            // Vigência expirou. Aplica carência ou bloqueio.
            // Se vigência nunca foi setada, tratamos como ativo (tenant ainda não cobrado)
            if ($vigenciaFim === null) {
                if ($status !== 'active') $patch['status'] = 'active';
            } else {
                // Setamos grace_until se ainda não existe
                if ($sub->grace_until === null) {
                    $patch['grace_until'] = $vigenciaFim->copy()->addDays(self::GRACE_DAYS);
                    $graceUntil = $patch['grace_until'];
                } else {
                    $graceUntil = $sub->grace_until;
                }

                if ($hoje->gt($graceUntil)) {
                    if ($status !== 'blocked') $patch['status'] = 'blocked';
                } else {
                    if ($status !== 'grace') $patch['status'] = 'grace';
                }
            }
        }

        if (! empty($patch)) {
            $sub->update($patch);
            $sub->refresh();
        }

        return $sub->status;
    }

    /**
     * Status legível (snake_case) para exibir na UI.
     * Não persiste; deriva de subscription + invoices em tempo real.
     */
    public function derive(Tenant $tenant): string
    {
        $sub = $tenant->subscription;
        if ($sub === null) return 'sem_assinatura';
        if ($sub->status === 'canceled') return 'cancelado';
        if ($sub->status === 'blocked') return 'bloqueado';
        if ($sub->status === 'grace') return 'em_carencia';
        if ($sub->status === 'overdue') return 'vencido';

        $vigenciaFim = $sub->current_period_end;
        if ($vigenciaFim === null) return 'em_dia';

        // próximo vencimento (≤ 7 dias)
        $diasRestantes = today()->diffInDays($vigenciaFim, false);
        if ($diasRestantes >= 0 && $diasRestantes <= 7) return 'proximo_vencimento';

        return 'em_dia';
    }

    /** Reavalia todos os tenants. Usado pelo command diário. */
    public function reconcileAll(): array
    {
        $resumo = ['active' => 0, 'overdue' => 0, 'grace' => 0, 'blocked' => 0, 'canceled' => 0];
        Tenant::query()->chunkById(100, function ($tenants) use (&$resumo) {
            foreach ($tenants as $t) {
                $st = $this->reconcile($t->id);
                if (isset($resumo[$st])) $resumo[$st]++;
            }
        });
        return $resumo;
    }
}
