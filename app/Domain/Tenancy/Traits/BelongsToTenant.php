<?php

namespace App\Domain\Tenancy\Traits;

use App\Domain\Tenancy\Detector\TenancyDetectorRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Trait BelongsToTenant — modo DETECTOR-APENAS (R1.5).
 *
 * RESPONSABILIDADE:
 *   Observar queries e writes em models multi-tenant, logando warnings
 *   quando o contexto de tenant está ausente ou divergente.
 *
 * O QUE O TRAIT **NÃO FAZ** (propositalmente — R2 entrega):
 *   - Não aplica global scope (WHERE tenant_id = ?)
 *   - Não filtra nenhuma query
 *   - Não preenche tenant_id em models criados
 *   - Não bloqueia saves cross-tenant
 *   - Não lança exceções
 *
 * CENÁRIOS DETECTADOS:
 *   1. `retrieved_without_context` — model carregado mas app('tenant_id')
 *      não foi resolvido. Indica que o middleware ResolveTenant (R2) ainda
 *      não rodou, ou a rota não está dentro do grupo esperado.
 *   2. `retrieved_cross_tenant` — model carregado tem tenant_id diferente
 *      do container. Indica vazamento real entre tenants (alerta crítico).
 *   3. `creating_cross_tenant` — save de um model com tenant_id != container.
 *   4. `updating_cross_tenant` — update de model cross-tenant.
 *
 * OBSERVABILIDADE:
 *   - Canal de log: `tenancy` (storage/logs/tenancy-detector.log)
 *   - Nível: warning
 *   - Deduplicação via TenancyDetectorRegistry — 1 log por `(classe × kind × par)`
 *     por request HTTP
 *
 * SKIP AUTOMÁTICO:
 *   - CLI (migrations, seeders, artisan, queue workers)
 *   - User não autenticado (rotas públicas, login, landing)
 *   - User master global (tenant_id = NULL) — contexto platform-wide legítimo
 *   - Quando `config('tenancy.detector.enabled')` = false
 */
trait BelongsToTenant
{
    /**
     * Hook de boot chamado automaticamente pelo Laravel ao usar o trait.
     * O nome `bootTraitName` é convenção do Eloquent.
     *
     * R2.3: o observer `creating` agora faz enforcement — preenche
     * model->tenant_id = app('tenant_id'), sobrescrevendo qualquer valor
     * vindo do request. Isso fecha o risco de mass-assignment cross-tenant.
     *
     * Os observers de `retrieved` e `updating` continuam apenas como detector
     * (R1.5). Enforcement de leitura entra em R2.4, de update em R2.5.
     */
    public static function bootBelongsToTenant(): void
    {
        static::retrieved(function (Model $model) {
            if (static::tenancyDetectorIsActive()) {
                static::tenancyDetectorObserve($model, 'retrieved');
            }
        });

        static::creating(function (Model $model) {
            // R2.3 — ENFORCEMENT no creating (sempre roda, independente do detector)
            static::enforceTenantOnCreating($model);

            // Detector continua observando depois do enforcement
            if (static::tenancyDetectorIsActive()) {
                static::tenancyDetectorObserve($model, 'creating');
            }
        });

        static::updating(function (Model $model) {
            if (static::tenancyDetectorIsActive()) {
                static::tenancyDetectorObserve($model, 'updating');
            }
        });
    }

    /**
     * R2.3 — Enforcement de tenant_id no create.
     *
     * Se o container tem tenant_id resolvido (ResolveTenant middleware rodou),
     * sobrescreve tenant_id do model com o valor do container — protegendo
     * contra mass-assignment cross-tenant via request body.
     *
     * Quando o container NÃO tem tenant_id (CLI, master global, rota sem
     * ResolveTenant), não faz nada — respeitando o valor atual ou deixando
     * que o DEFAULT 1 do banco (R1.2) atue.
     *
     * Esta função nunca lança exceção — é defensiva por design.
     */
    protected static function enforceTenantOnCreating(Model $model): void
    {
        if (! app()->bound('tenant_id')) {
            return;
        }

        $containerTenant = app('tenant_id');

        if ($containerTenant === null) {
            return;
        }

        // Sobrescreve sempre — qualquer valor injetado via request é descartado
        $model->tenant_id = (int) $containerTenant;
    }

    /**
     * Avalia se o detector deve rodar neste contexto (não em CLI, auth OK, etc).
     */
    protected static function tenancyDetectorIsActive(): bool
    {
        // Config master kill-switch
        if (! config('tenancy.detector.enabled', true)) {
            return false;
        }

        // CLI / queue workers / artisan — opt-out por padrão
        if (config('tenancy.detector.skip_console', true) && app()->runningInConsole()) {
            return false;
        }

        return true;
    }

    /**
     * Análise do evento. Decide se há algo a logar; se sim, delega ao logger.
     * Nada aqui modifica o model, query, ou retorno.
     */
    protected static function tenancyDetectorObserve(Model $model, string $event): void
    {
        // Requer user autenticado — rotas públicas não têm contexto esperado
        if (! auth()->check()) {
            return;
        }

        // Master global (tenant_id NULL) opera platform-wide; ignorar
        if (config('tenancy.detector.skip_master_global', true)
            && auth()->user()->tenant_id === null) {
            return;
        }

        // Leitura defensiva do contexto
        $containerTenant = app()->bound('tenant_id') ? app('tenant_id') : null;
        $modelTenant = isset($model->tenant_id) ? (int) $model->tenant_id : null;

        // Classifica o tipo de anomalia
        $kind = null;

        if ($containerTenant === null) {
            // Middleware ResolveTenant ainda não rodou (esperado em R1;
            // vira anomalia em R2 quando o middleware entrar em vigor)
            $kind = $event . '_without_context';
        } elseif ($modelTenant !== null && (int) $containerTenant !== $modelTenant) {
            // Container tem tenant A, model pertence ao tenant B: vazamento
            $kind = $event . '_cross_tenant';
        }

        if ($kind === null) {
            return; // estado OK, nada a registrar
        }

        static::tenancyDetectorLog($model, $kind, $containerTenant, $modelTenant);
    }

    /**
     * Registra o warning com deduplicação.
     */
    protected static function tenancyDetectorLog(
        Model $model,
        string $kind,
        mixed $containerTenant,
        ?int $modelTenant
    ): void {
        $dedupeKey = sprintf(
            '%s:%s:%s->%s',
            static::class,
            $kind,
            $containerTenant ?? 'null',
            $modelTenant ?? 'null'
        );

        if (! TenancyDetectorRegistry::shouldLog($dedupeKey)) {
            return;
        }

        $request = request();

        Log::channel(config('tenancy.detector.channel', 'tenancy'))
            ->warning('[TENANCY-DETECTOR] ' . $kind, [
                'model' => static::class,
                'model_id' => $model->getKey(),
                'container_tenant_id' => $containerTenant,
                'model_tenant_id' => $modelTenant,
                'user_id' => auth()->id(),
                'farm_id' => app()->bound('farm_id') ? app('farm_id') : null,
                'method' => $request?->method(),
                'url' => $request?->fullUrl(),
                'route' => $request?->route()?->getName(),
            ]);
    }
}
