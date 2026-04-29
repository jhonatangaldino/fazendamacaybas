<?php

namespace App\Services\Metrics;

use App\Models\Document\Document;
use Illuminate\Support\Facades\DB;

/**
 * DocumentosMetrics — fonte única de verdade para KPIs de documentos.
 *
 * Substitui as fórmulas hoje em:
 *   - Admin\DocumentController::index (Lista)
 *   - Services\AlertsService (Badge "documentos vencendo")
 *
 * Decisões de produto registradas:
 *   - "Total" = COUNT(Document WHERE NOT soft-deleted).
 *   - "Vencendo em N dias" = data_vencimento BETWEEN [hoje, hoje+N].
 *     Disjunto de "vencidos" (vencidos usa < hoje).
 *     Cobre 30 dias por padrão (alinhado com filtro UI ?venc=proximos).
 *   - "Vencidos" = data_vencimento < hoje.
 *   - Documentos sem data_vencimento NÃO contam em nenhuma das duas
 *     categorias (não têm vencimento — não há o que alertar).
 */
class DocumentosMetrics extends BaseMetrics
{
    public const MODULE = 'documentos';

    /** Total de documentos (não soft-deleted). */
    public function total(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "total." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_STANDARD,
            fn () => $this->scope(Document::query(), $tenantId, $farmId)->count()
        );
    }

    /**
     * Vencendo nos próximos N dias — data_vencimento BETWEEN [hoje, hoje+N].
     * Ignora documentos sem data_vencimento.
     */
    public function vencendoEm(int $dias = 30, ?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "vencendo_{$dias}d." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => $this->scope(Document::query(), $tenantId, $farmId)
                ->whereNotNull('data_vencimento')
                ->whereBetween('data_vencimento', [today(), today()->copy()->addDays($dias)])
                ->count()
        );
    }

    /** Atalho: vencendo em 30 dias (filtro padrão da UI). */
    public function vencendo30d(?int $tenantId = null, ?int $farmId = null): int
    {
        return $this->vencendoEm(30, $tenantId, $farmId);
    }

    /**
     * Vencidos — data_vencimento < hoje.
     * Ignora docs sem data_vencimento.
     */
    public function vencidos(?int $tenantId = null, ?int $farmId = null): int
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "vencidos." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_FAST,
            fn () => $this->scope(Document::query(), $tenantId, $farmId)
                ->whereNotNull('data_vencimento')
                ->whereDate('data_vencimento', '<', today())
                ->count()
        );
    }

    /** Documentos por categoria — Collection<['categoria','total']>. */
    public function porCategoria(?int $tenantId = null, ?int $farmId = null): \Illuminate\Support\Collection
    {
        $tenantId = $this->resolveTenantId($tenantId);

        return $this->remember(self::MODULE, "por_categoria." . MetricsCache::suffix($tenantId, $farmId), MetricsCache::TTL_STANDARD,
            fn () => $this->scope(Document::query(), $tenantId, $farmId)
                ->leftJoin('document_categories', 'documents.category_id', '=', 'document_categories.id')
                ->select(DB::raw("COALESCE(document_categories.nome, 'Sem categoria') as categoria"), DB::raw('COUNT(*) as total'))
                ->groupBy('categoria')
                ->orderByDesc('total')
                ->get()
        );
    }

    /** Resumo agregado. */
    public function resumo(?int $tenantId = null, ?int $farmId = null): array
    {
        return [
            'total'        => $this->total($tenantId, $farmId),
            'vencendo_30d' => $this->vencendo30d($tenantId, $farmId),
            'vencidos'     => $this->vencidos($tenantId, $farmId),
        ];
    }
}
