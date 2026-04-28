<?php

namespace App\Services\Metrics;

use App\Models\Employee;
use App\Models\Task\Task;

/**
 * TarefasMetrics — fonte única de verdade para KPIs de tarefas e funcionários.
 *
 * Substitui as fórmulas hoje em:
 *   - Admin\DashboardController::buildPayload (Painel)
 *   - Admin\TaskController::index (Lista)
 *   - Services\AlertsService (Badges/Alerts)
 *   - Admin\ReportController (Relatório)
 *
 * Decisão de produto registrada (METRICS-DESIGN.md):
 *   - "Atrasadas" = status IN (pendente, em_andamento) AND data_vencimento < hoje.
 *     Tarefa em_andamento mas vencida AINDA é tarefa atrasada — vai concluir tarde.
 *     Bug corrigido: AlertsService só contava 'pendente'; Painel contava ambos.
 *     Padronizamos no MAIS amplo (em_andamento + pendente) — mais conservador.
 *   - "Vencendo hoje" = mesmo status mask AND data_vencimento = hoje.
 *     Disjunto de atrasadas.
 */
class TarefasMetrics extends BaseMetrics
{
    public const MODULE = 'tarefas';

    /** Status que contam como "abertas" (não concluídas, não canceladas). */
    private const STATUS_ABERTAS = ['pendente', 'em_andamento'];

    /**
     * Total de tarefas pendentes (status IN [pendente, em_andamento]).
     */
    public function totalPendentes(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "pendentes." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => $this->scope(Task::query(), $tenantId, $farmId)
                ->whereIn('status', self::STATUS_ABERTAS)
                ->count()
        );
    }

    /**
     * Atrasadas — status aberto + data_vencimento < hoje.
     * Decisão de produto: inclui em_andamento (tarefa que está sendo feita
     * mas vai terminar tarde ainda é atrasada).
     */
    public function atrasadas(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "atrasadas." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => $this->scope(Task::query(), $tenantId, $farmId)
                ->whereIn('status', self::STATUS_ABERTAS)
                ->whereDate('data_vencimento', '<', today())
                ->count()
        );
    }

    /** Vencendo hoje — status aberto + data_vencimento = hoje. */
    public function vencendoHoje(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "vencendo_hoje." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => $this->scope(Task::query(), $tenantId, $farmId)
                ->whereIn('status', self::STATUS_ABERTAS)
                ->whereDate('data_vencimento', today())
                ->count()
        );
    }

    /** Concluídas hoje — concluida_em >= hoje 00:00. */
    public function concluidasHoje(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "concluidas_hoje." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => $this->scope(Task::query(), $tenantId, $farmId)
                ->where('status', 'concluida')
                ->whereDate('concluida_em', '>=', today())
                ->count()
        );
    }

    /** Concluídas no período — fecha gap (Painel só tinha "hoje"). */
    public function concluidasNoPeriodo(string|array $period = 'mes_atual', ?int $tenantId = null, ?int $farmId = null): int
    {
        [$from, $to] = $this->period($period);
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "concluidas_periodo." . MetricsCache::suffix($tenantId, $farmId, $from->toDateString() . '_' . $to->toDateString()), MetricsCache::TTL_FAST,
            fn () => $this->scope(Task::query(), $tenantId, $farmId)
                ->where('status', 'concluida')
                ->whereBetween('concluida_em', [$from, $to])
                ->count()
        );
    }

    /**
     * Resumo agregado — equivale ao `selectRaw` enorme em TaskController::index.
     * 1 query consolidada com CASE para reduzir round-trips no MySQL Hostinger.
     */
    public function resumo(?int $tenantId = null, ?int $farmId = null): array
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "resumo." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            function () use ($tenantId, $farmId) {
                $row = $this->scope(Task::query(), $tenantId, $farmId)
                    ->selectRaw("
                        SUM(CASE WHEN status IN ('pendente','em_andamento') THEN 1 ELSE 0 END) as pendentes,
                        SUM(CASE WHEN status IN ('pendente','em_andamento') AND data_vencimento < CURDATE() THEN 1 ELSE 0 END) as atrasadas,
                        SUM(CASE WHEN status IN ('pendente','em_andamento') AND data_vencimento = CURDATE() THEN 1 ELSE 0 END) as hoje,
                        SUM(CASE WHEN status = 'concluida' AND concluida_em >= CURDATE() THEN 1 ELSE 0 END) as concluidas_hoje
                    ")
                    ->first();

                return [
                    'pendentes'        => (int) ($row->pendentes ?? 0),
                    'atrasadas'        => (int) ($row->atrasadas ?? 0),
                    'hoje'             => (int) ($row->hoje ?? 0),
                    'concluidas_hoje'  => (int) ($row->concluidas_hoje ?? 0),
                ];
            }
        );
    }

    /** Total funcionários ativos no tenant. */
    public function totalFuncionariosAtivos(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "total_funcionarios." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_STANDARD,
            fn () => $this->scope(Employee::query(), $tenantId, $farmId)
                ->where('is_active', true)
                ->count()
        );
    }
}
