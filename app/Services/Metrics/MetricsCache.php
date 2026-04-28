<?php

namespace App\Services\Metrics;

use Illuminate\Support\Facades\Cache;

/**
 * MetricsCache — chaves padronizadas + invalidação centralizada para o
 * conjunto `App\Services\Metrics\*`.
 *
 * POR QUÊ EXISTE:
 *   Antes desta abstração, cada controller calculava sua própria versão da
 *   métrica e cacheava ad-hoc (ou nem cacheava). Resultado: 22 divergências
 *   detectadas em auditoria 2026-04-28 (METRICS-AUDIT.md). Esta classe
 *   garante que invalidação atinja TODAS as métricas relacionadas a um
 *   tenant em uma única chamada.
 *
 * COMO USAR:
 *   Em models que afetam métricas, registre um `booted` hook:
 *     static::created($cb = fn ($m) => MetricsCache::forgetForTenant($m->tenant_id, 'livestock'));
 *     static::updated($cb);
 *     static::deleted($cb);
 *
 *   Em services, use `MetricsCache::key($module, $rest)` para evitar typos.
 *
 * TTLs:
 *   TTL_REALTIME (60s)  — métricas que mudam a cada minuto (raríssimo)
 *   TTL_FAST     (300s) — KPIs operacionais (rebanho/financeiro/tarefas)
 *   TTL_STANDARD (900s) — relatórios/gráficos
 *   TTL_SLOW     (3600s)— métricas pesadas com tolerância (MRR, fluxo caixa)
 */
class MetricsCache
{
    public const TTL_REALTIME  = 60;
    public const TTL_FAST      = 300;
    public const TTL_STANDARD  = 900;
    public const TTL_SLOW      = 3600;

    /**
     * Constrói a chave canônica de cache para uma métrica.
     *
     * Formato: metrics.{module}.{rest}
     * Exemplos:
     *   metrics.livestock.total_cabecas.t1061
     *   metrics.financial.receitas_mes.t1061.f5
     */
    public static function key(string $module, string $rest): string
    {
        return "metrics.{$module}.{$rest}";
    }

    /**
     * Compõe sufixo padrão `t{tenantId}.f{farmId}` — usado pela maioria das chaves.
     * Quando farmId é null, omite o segmento .f{x} pra cache global do tenant.
     */
    public static function suffix(?int $tenantId, ?int $farmId = null, ?string $extra = null): string
    {
        $parts = [];
        $parts[] = 't' . ($tenantId ?? 'master');
        if ($farmId !== null) {
            $parts[] = 'f' . $farmId;
        }
        if ($extra !== null && $extra !== '') {
            $parts[] = $extra;
        }
        return implode('.', $parts);
    }

    /**
     * Invalida TODAS as métricas de um tenant. Chamado por model `booted`
     * hooks após CRUD de qualquer entidade que afete métricas.
     *
     * Quando $module é informado, invalida apenas o módulo (mais barato).
     * Sem $module, invalida tudo (após CRUD que afeta múltiplos módulos —
     * ex.: gerar receita a partir de colheita afeta financial+agricola).
     */
    public static function forgetForTenant(int $tenantId, ?string $module = null): void
    {
        $modules = $module
            ? [$module]
            : ['livestock', 'financial', 'agricola', 'estoque', 'maquinas', 'tarefas', 'documentos'];

        foreach ($modules as $mod) {
            foreach (self::knownKeysFor($mod) as $template) {
                // Substitui placeholders pelas dimensões conhecidas
                $key = strtr($template, [
                    '{t}' => $tenantId,
                    '{f}' => '*', // farm-specific ainda é forget global no MVP
                ]);
                // Para chaves com {f}, geramos sem farm também
                $keysToForget = [
                    self::key($mod, str_replace('.{f}', '', $template) === $template
                        ? str_replace('{t}', (string) $tenantId, $template)
                        : str_replace(['{t}', '{f}'], [$tenantId, ''], $template)),
                ];
                foreach ($keysToForget as $k) {
                    Cache::forget($k);
                }
            }
        }

        // Master KPIs sempre invalidam junto (qualquer tenant CRUD afeta tenants count etc.)
        Cache::forget(self::key('platform', 'master_kpis'));

        // BillingCache é separado e deve ser limpo lá quando relevante.
    }

    /**
     * Invalida só métricas master (CRUDs puramente plataforma — Plan, Tenant ativação).
     */
    public static function forgetMaster(): void
    {
        foreach (self::knownKeysFor('platform') as $template) {
            Cache::forget(self::key('platform', $template));
        }
    }

    /**
     * Lista canônica de templates de chaves por módulo. Mantido em sync com
     * os métodos cached dos sub-services. Quando adicionar nova métrica
     * cacheada, registre o template aqui também — caso contrário a invalidação
     * via `forgetForTenant` perderá esta métrica.
     *
     * Placeholders aceitos: `{t}` (tenant_id), `{f}` (farm_id).
     */
    private static function knownKeysFor(string $module): array
    {
        return match ($module) {
            'livestock' => [
                'total_cabecas.t{t}',
                'total_por_especie.t{t}',
                'sexo_breakdown.t{t}',
                'peso_medio.t{t}',
                'litros_mes_atual.t{t}',
                'litros_mes_anterior.t{t}',
                'vacas_em_lactacao.t{t}',
                'eventos_recentes_7d.t{t}',
                'ovos_no_mes.t{t}',
                'ultima_biometria.t{t}',
            ],
            'financial' => [
                'receitas_mes.t{t}',
                'despesas_mes.t{t}',
                'saldo_total_contas.t{t}',
                'a_pagar_30d.t{t}',
                'a_receber_30d.t{t}',
                'atrasadas.t{t}',
                'vencendo_hoje.t{t}',
                'despesas_por_categoria.t{t}',
                'receitas_por_categoria.t{t}',
            ],
            'agricola' => [
                'total_talhoes.t{t}',
                'total_hectares.t{t}',
                'plantios_ativos.t{t}',
                'safras_ativas.t{t}',
                'aplicacoes_mes.t{t}',
                'produtividade_periodo.t{t}',
            ],
            'estoque' => [
                'total_itens.t{t}',
                'saldo_valorizado.t{t}',
                'abaixo_minimo.t{t}',
                'sem_estoque.t{t}',
                'movimentacoes_mes.t{t}',
            ],
            'maquinas' => [
                'veiculos_ativos.t{t}',
                'veiculos_em_manutencao.t{t}',
                'manutencoes_abertas.t{t}',
                'custo_manutencao_mes.t{t}',
            ],
            'tarefas' => [
                'pendentes.t{t}',
                'atrasadas.t{t}',
                'vencendo_hoje.t{t}',
                'concluidas_hoje.t{t}',
                'concluidas_mes.t{t}',
                'total_funcionarios.t{t}',
            ],
            'documentos' => [
                'total.t{t}',
                'vencendo_30d.t{t}',
                'vencidos.t{t}',
            ],
            'platform' => [
                'master_kpis',
                'tenants_count',
                'mrr',
                'cobrancas_overdue_plano',
                'cobrancas_aguardando_validacao',
            ],
            default => [],
        };
    }
}
