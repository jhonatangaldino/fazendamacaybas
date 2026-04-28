<?php

namespace App\Services;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\Tenant;
use App\Models\Financial\FinancialTransaction;
use App\Models\Task\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AlertsService — calcula alertas globais visíveis em TODA tela do tenant.
 *
 * Filosofia: o usuário não pode precisar entrar no Dashboard para descobrir
 * que tem coisa quebrando. A barra de alertas no topo do AdminLayout +
 * badges de contagem na sidebar resolvem esse caso.
 *
 * Tipos de alerta gerados:
 *   - subscription_blocked         (crítico)  acesso bloqueado por inadimplência
 *   - subscription_grace           (atenção)  vigência expirada, em carência
 *   - subscription_expira_breve    (atenção)  vigência termina em ≤ 7 dias
 *   - financeiro_vencidas          (crítico)  contas a pagar vencidas
 *   - financeiro_vence_hoje        (atenção)  contas vencendo hoje
 *   - tarefas_atrasadas            (crítico)  tarefas com data_vencimento < hoje
 *   - estoque_baixo                (atenção)  itens abaixo do mínimo
 *
 * Performance: 1 query agregada por bloco — leve. Retornos cachemed por tenant
 * + farm dentro do request (Inertia partial reload reusa).
 */
class AlertsService
{
    /** Severidades em ordem decrescente (usado para sort) */
    private const SEV_ORDER = [
        'critico' => 0,
        'atencao' => 1,
        'info' => 2,
    ];

    public function forTenant(int $tenantId, ?int $farmId = null): array
    {
        $alerts = [];

        // ─── BILLING (sempre roda — não depende de farm) ─────────
        $sub = Subscription::where('tenant_id', $tenantId)->first();
        if ($sub) {
            if ($sub->status === 'blocked') {
                $alerts[] = $this->mk('subscription_blocked', 'critico',
                    'Acesso bloqueado por inadimplência',
                    'Sua assinatura está vencida há mais de 10 dias. Pague para liberar o sistema.',
                    route('admin.faturas.index'), 'Ver faturas');
            } elseif ($sub->status === 'grace' && $sub->grace_until) {
                $diasRestantes = max(0, today()->diffInDays($sub->grace_until, false));
                $alerts[] = $this->mk('subscription_grace', 'atencao',
                    "Vigência expirada — {$diasRestantes} dias para regularizar",
                    'Pague uma fatura pendente antes da carência terminar.',
                    route('admin.faturas.index'), 'Pagar agora');
            } elseif ($sub->current_period_end) {
                $diasParaVencer = today()->diffInDays($sub->current_period_end, false);
                if ($diasParaVencer >= 0 && $diasParaVencer <= 7) {
                    $alerts[] = $this->mk('subscription_expira_breve', 'atencao',
                        "Assinatura vence em {$diasParaVencer} dia(s)",
                        'Renove para evitar interrupção do serviço.',
                        route('admin.faturas.index'), 'Ver faturas');
                }
            }
        }

        // ─── FINANCEIRO ───────────────────────────────────────────
        // Conta apenas da fazenda atualmente selecionada quando $farmId vem.
        // Sem isso, tenant com 2+ fazendas vazava contadores de uma na outra
        // (ex.: Filial QA mostrava "1 conta vence hoje" sendo despesa da Sede).
        if (Schema::hasTable('financial_transactions')) {
            $finQuery = FinancialTransaction::query()
                ->where('tenant_id', $tenantId)
                ->when($farmId, fn ($q) => $q->where('farm_id', $farmId))
                ->where('tipo', 'despesa')
                ->where('status', 'pendente');

            $vencidas = (clone $finQuery)->whereDate('data_vencimento', '<', today())->count();
            $hoje = (clone $finQuery)->whereDate('data_vencimento', today())->count();

            if ($vencidas > 0) {
                $alerts[] = $this->mk('financeiro_vencidas', 'critico',
                    "{$vencidas} conta(s) vencida(s)",
                    'Contas a pagar com data de vencimento ultrapassada.',
                    route('admin.financeiro.transacoes.index', ['status' => 'pendente', 'tipo' => 'despesa']),
                    'Pagar contas');
            }
            if ($hoje > 0) {
                $alerts[] = $this->mk('financeiro_vence_hoje', 'atencao',
                    "{$hoje} conta(s) vencem hoje",
                    'Pague para não atrasar.',
                    route('admin.financeiro.transacoes.index', ['status' => 'pendente', 'tipo' => 'despesa']),
                    'Ver contas');
            }
        }

        // ─── TAREFAS ──────────────────────────────────────────────
        if (Schema::hasTable('tasks')) {
            $atrasadas = Task::query()
                ->where('tenant_id', $tenantId)
                ->when($farmId, fn ($q) => $q->where('farm_id', $farmId))
                ->where('status', 'pendente')
                ->whereDate('data_vencimento', '<', today())
                ->count();

            if ($atrasadas > 0) {
                $alerts[] = $this->mk('tarefas_atrasadas', 'critico',
                    "{$atrasadas} tarefa(s) atrasada(s)",
                    'Prazo já passou — atualize ou conclua.',
                    route('admin.tarefas.index', ['status' => 'pendente']),
                    'Ver tarefas');
            }
        }

        // Ordena: críticos primeiro, depois atenção, depois info
        usort($alerts, fn ($a, $b) =>
            (self::SEV_ORDER[$a['severidade']] ?? 9) <=> (self::SEV_ORDER[$b['severidade']] ?? 9));

        return $alerts;
    }

    /**
     * Counters para badges no menu da sidebar.
     * Reaproveita as queries acima — estrutura otimizada para 1 query por bloco.
     *
     * IMPORTANTE: respeitar farm_id quando informado. Sem esse filtro, tenant
     * multi-farm mostrava badge "1" no Financeiro de uma fazenda mesmo a
     * despesa pertencendo a outra — vazamento entre fazendas detectado pelo
     * PO em 2026-04-28 (Filial QA com badge contando dívida da Sede).
     */
    public function menuBadgesForTenant(int $tenantId, ?int $farmId = null): array
    {
        $badges = [];

        if (Schema::hasTable('financial_transactions')) {
            $vencidas = FinancialTransaction::query()
                ->where('tenant_id', $tenantId)
                ->when($farmId, fn ($q) => $q->where('farm_id', $farmId))
                ->where('tipo', 'despesa')
                ->where('status', 'pendente')
                ->whereDate('data_vencimento', '<=', today())
                ->count();
            if ($vencidas > 0) {
                $badges['admin.financeiro.index'] = ['n' => $vencidas, 'sev' => 'critico'];
            }
        }

        if (Schema::hasTable('tasks')) {
            $atrasadas = Task::query()
                ->where('tenant_id', $tenantId)
                ->when($farmId, fn ($q) => $q->where('farm_id', $farmId))
                ->where('status', 'pendente')
                ->whereDate('data_vencimento', '<', today())
                ->count();
            if ($atrasadas > 0) {
                $badges['admin.tarefas.index'] = ['n' => $atrasadas, 'sev' => 'critico'];
            }
        }

        return $badges;
    }

    /* ──────────────────────────── MASTER ──────────────────────────── */

    /**
     * Alertas específicos para o master (operação SaaS, não tenant).
     *   - clientes_com_vencidas      crítico  → invoices.status=overdue agrupadas
     *   - clientes_sem_plano         atenção  → tenants.plan_id IS NULL e ativos
     *   - clientes_config_incompleta atenção  → tenant ativo sem nenhum setting de mapa/descricao
     */
    public function forMaster(): array
    {
        $alerts = [];

        // Clientes com faturas vencidas (overdue) — só faturas do PLANO
        // contam, avulsas (tipo='unica') não disparam o alerta de inadimplência.
        if (Schema::hasTable('invoices')) {
            $clientesVencidos = Invoice::where('status', 'overdue')
                ->where('tipo', 'mensal')
                ->distinct()
                ->count(DB::raw('tenant_id'));
            if ($clientesVencidos > 0) {
                $alerts[] = $this->mk('master_clientes_vencidos', 'critico',
                    "{$clientesVencidos} cliente(s) com fatura vencida",
                    'Pagamento em atraso — pode levar a bloqueio.',
                    route('master.cobrancas.index', ['status' => 'overdue']),
                    'Ver cobranças');
            }

            // Clientes que precisam de NOVO CICLO DE FATURAS — modelo
            // "paga pra usar" (pré-pago): master gera as faturas do período
            // (mensal/anual) e quando a vigência está acabando precisa gerar
            // o próximo ciclo. Sem esse alerta, master esquece e o cliente
            // perde acesso por bloqueio (PO 2026-04-28).
            //
            // Critério: subscription ativa/em-dia, current_period_end <= hoje + 15 dias,
            // E não há nenhuma fatura mensal cobrindo período POSTERIOR ao
            // current_period_end atual.
            $limiar = today()->addDays(15)->toDateString();
            $precisamCiclo = DB::table('subscriptions as s')
                ->whereIn('s.status', ['active', 'overdue', 'grace'])
                ->whereNotNull('s.current_period_end')
                ->where('s.current_period_end', '<=', $limiar)
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('invoices as i')
                      ->whereColumn('i.tenant_id', 's.tenant_id')
                      ->where('i.tipo', 'mensal')
                      ->whereColumn('i.periodo_inicio', '>', 's.current_period_end');
                })
                ->count();

            if ($precisamCiclo > 0) {
                $alerts[] = $this->mk('master_precisa_gerar_ciclo', 'atencao',
                    "{$precisamCiclo} cliente(s) precisam de novo ciclo de faturas",
                    'Vigência do plano termina em até 15 dias e não há faturas geradas pro próximo período.',
                    route('master.cobrancas.wizard.create'),
                    'Gerar faturas');
            }

            // Pagamentos enviados pelo cliente aguardando double-check do master.
            // Status `paid_pending_review` → cliente afirmou que pagou e enviou
            // comprovante; master precisa abrir, conferir e aprovar.
            $aguardandoReview = Invoice::where('status', 'paid_pending_review')->count();
            if ($aguardandoReview > 0) {
                $alerts[] = $this->mk('master_aguardando_validacao_pagamento', 'critico',
                    "{$aguardandoReview} pagamento(s) aguardando validação",
                    'Cliente enviou comprovante — confira o arquivo e aprove ou rejeite.',
                    route('master.cobrancas.index', ['status' => 'paid_pending_review']),
                    'Validar agora');
            }
        }

        // Clientes sem plano vinculado
        if (Schema::hasTable('tenants')) {
            $semPlano = Tenant::where('is_active', true)
                ->whereNull('plan_id')
                ->count();
            if ($semPlano > 0) {
                $alerts[] = $this->mk('master_clientes_sem_plano', 'atencao',
                    "{$semPlano} cliente(s) sem plano",
                    'Atribua um plano para liberar cobranças e funcionalidades.',
                    route('master.tenants.index'),
                    'Ver clientes');
            }

            // Clientes com configuração incompleta — espelha a regra do TenantController::index
            $readyKeys = [
                'landing.map.endereco',
                'landing.map.latitude',
                'landing.map.longitude',
                'landing.map.google_embed',
                'site.descricao',
            ];
            $readyTenantIds = DB::table('settings')
                ->whereIn('key', $readyKeys)
                ->whereNotNull('tenant_id')
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->distinct()
                ->pluck('tenant_id')
                ->all();
            $configIncompleta = Tenant::where('is_active', true)
                ->whereNotIn('id', $readyTenantIds ?: [0])
                ->count();
            if ($configIncompleta > 0) {
                $alerts[] = $this->mk('master_config_incompleta', 'atencao',
                    "{$configIncompleta} cliente(s) com configuração incompleta",
                    'Sem mapa ou descrição — landing ainda não pronta para entrega.',
                    route('master.tenants.index'),
                    'Configurar');
            }
        }

        usort($alerts, fn ($a, $b) =>
            (self::SEV_ORDER[$a['severidade']] ?? 9) <=> (self::SEV_ORDER[$b['severidade']] ?? 9));

        return $alerts;
    }

    /** Counters para badges no menu Master. */
    public function menuBadgesForMaster(): array
    {
        $badges = [];

        if (Schema::hasTable('invoices')) {
            $abertas = Invoice::whereIn('status', ['pending', 'overdue'])->count();
            if ($abertas > 0) {
                // Crítico só conta overdue do PLANO (avulsa não bloqueia → não eleva severidade)
                $sev = Invoice::where('status', 'overdue')->where('tipo', 'mensal')->exists() ? 'critico' : 'atencao';
                $badges['master.cobrancas.index'] = ['n' => $abertas, 'sev' => $sev];
            }
        }

        if (Schema::hasTable('tenants')) {
            $semPlano = Tenant::where('is_active', true)->whereNull('plan_id')->count();
            if ($semPlano > 0) {
                $badges['master.tenants.index'] = ['n' => $semPlano, 'sev' => 'atencao'];
            }
        }

        return $badges;
    }

    /** Helper para construir um alert estruturado uniforme. */
    private function mk(string $id, string $severidade, string $titulo, string $descricao, string $href, string $cta): array
    {
        return [
            'id' => $id,
            'severidade' => $severidade,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'href' => $href,
            'cta' => $cta,
        ];
    }
}
