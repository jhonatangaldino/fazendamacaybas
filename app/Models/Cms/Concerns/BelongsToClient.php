<?php

namespace App\Models\Cms\Concerns;

/**
 * BelongsToClient — trait LEVE aplicado somente aos models do CMS.
 *
 * Responsabilidade ÚNICA: preencher `tenant_id` automaticamente no evento
 * `creating`, usando `app('tenant_id')` como fonte. Isso permite que o
 * controller apenas binde o contexto do cliente (ex.:
 * `app()->instance('tenant_id', $cliente->id)`) e todas as entidades CMS
 * criadas na sequência ganhem o `tenant_id` correto sem precisar passar
 * explicitamente.
 *
 * O QUE NÃO FAZ (deliberadamente — respeita o escopo fechado desta fase):
 *   - Não aplica global scope de leitura
 *   - Não faz enforcement em update
 *   - Não dispara detector/log
 *   - Não interage com o trait BelongsToTenant (R2) — é completamente
 *     independente, evitando acoplamento cruzado
 *
 * A filtragem de leitura por cliente é responsabilidade dos controllers
 * (WHERE tenant_id = ? explícito), mantendo a complexidade contida.
 *
 * APLICADO EM:
 *   - App\Models\Cms\Page
 *   - App\Models\Cms\Menu
 *
 * NÃO APLICADO EM:
 *   - Section, MenuItem → herdam tenant via FK para page/menu
 *   - Setting → usa fallback diferente (ver Setting::getValue)
 */
trait BelongsToClient
{
    public static function bootBelongsToClient(): void
    {
        static::creating(function ($model) {
            // Só preenche se ainda estiver vazio — permite criar via
            // seeder/tinker com tenant_id explícito sem ser sobrescrito.
            if (empty($model->tenant_id) && app()->bound('tenant_id')) {
                $tenantId = app('tenant_id');
                if ($tenantId !== null) {
                    $model->tenant_id = (int) $tenantId;
                }
            }
        });
    }
}
