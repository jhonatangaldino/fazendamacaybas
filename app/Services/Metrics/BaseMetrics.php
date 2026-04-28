<?php

namespace App\Services\Metrics;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * BaseMetrics — comportamento comum dos sub-services de métricas.
 *
 * Responsabilidades:
 *   1. Resolver tenant_id/farm_id efetivos (param explícito > app() bound).
 *   2. Aplicar filtros tenant/farm a builders Eloquent (defesa contra vazamento).
 *   3. Resolver intervalos canônicos (`mes_atual`, `ultimos_30d`, etc.).
 *   4. Encapsular `Cache::remember` com chaves padronizadas via `MetricsCache`.
 *
 * IMPORTANTE: este service NÃO faz query — só prepara builders e encapsula cache.
 * As fórmulas são responsabilidade dos sub-services (LivestockMetrics etc.).
 */
abstract class BaseMetrics
{
    /**
     * Aplica escopo tenant/farm a um builder Eloquent.
     *
     * - Quando $tenantId é null, tenta resolver via `app('tenant_id')`.
     * - Quando ambos são null e o context global não tem tenant_id, NÃO aplica
     *   filtro tenant (caso master sem impersonar). Em produção, este caminho
     *   raramente é usado por métricas tenant-scoped — quem chama deve passar
     *   explicitamente.
     * - Filtra a coluna `farm_id` apenas quando $farmId é informado.
     *
     * Chama `where('table.tenant_id')` em vez de `where('tenant_id')` para
     * evitar ambiguidade quando o builder tem joins (caso de queries com
     * leftJoin em species/categories etc.).
     */
    protected function scope(Builder $q, ?int $tenantId, ?int $farmId): Builder
    {
        $effectiveTenant = $tenantId ?? (app()->bound('tenant_id') ? (int) app('tenant_id') : null);
        $table = $q->getModel()->getTable();

        if ($effectiveTenant !== null) {
            $q->where("{$table}.tenant_id", $effectiveTenant);
        }
        if ($farmId !== null) {
            $q->where("{$table}.farm_id", $farmId);
        }
        return $q;
    }

    /**
     * Resolve tenant_id efetivo (param > app bound).
     *
     * Levanta exceção quando nenhum dos dois resolve — comportamento
     * intencional pra impedir métricas globais acidentais que retornariam
     * dados de TODOS os tenants.
     */
    protected function resolveTenantId(?int $tenantId): int
    {
        $effective = $tenantId ?? (app()->bound('tenant_id') ? (int) app('tenant_id') : null);
        if ($effective === null) {
            throw new \RuntimeException(
                'tenant_id não pôde ser resolvido. Passe explicitamente ou garanta '
                . 'que o middleware ResolveTenant rodou antes deste método.'
            );
        }
        return $effective;
    }

    /**
     * Resolve um intervalo de tempo a partir de uma string ou array.
     *
     * Aceita:
     *   'mes_atual'    → [startOfMonth, endOfMonth]
     *   'mes_anterior' → mês inteiro anterior
     *   'ultimos_7d'   → [now-7d, now]
     *   'ultimos_30d'  → [now-30d, now]
     *   'ultimos_60d'  → [now-60d, now]
     *   'ultimos_90d'  → [now-90d, now]
     *   ['Y-m-d','Y-m-d'] → custom (parseado como dia inteiro)
     */
    protected function period(string|array $period): array
    {
        if (is_array($period)) {
            return [
                Carbon::parse($period[0])->startOfDay(),
                Carbon::parse($period[1])->endOfDay(),
            ];
        }

        return match ($period) {
            'mes_atual'    => [now()->startOfMonth(), now()->endOfMonth()],
            'mes_anterior' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'ultimos_7d'   => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
            'ultimos_30d'  => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
            'ultimos_60d'  => [now()->subDays(60)->startOfDay(), now()->endOfDay()],
            'ultimos_90d'  => [now()->subDays(90)->startOfDay(), now()->endOfDay()],
            default        => throw new \InvalidArgumentException("Período inválido: {$period}"),
        };
    }

    /**
     * Atalho para `Cache::remember(MetricsCache::key($module, $rest), $ttl, $cb)`.
     *
     * Cada sub-service expõe seu `$module` constante.
     */
    protected function remember(string $module, string $rest, int $ttl, callable $cb)
    {
        return Cache::remember(MetricsCache::key($module, $rest), $ttl, $cb);
    }
}
