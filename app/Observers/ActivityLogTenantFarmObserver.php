<?php

namespace App\Observers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Spatie\Activitylog\Models\Activity;

/**
 * ActivityLogTenantFarmObserver
 *
 * Popula automaticamente `tenant_id` e `farm_id` em todo registro do
 * activity_log no momento da criação. Garante que o Master, ao listar
 * auditoria, consiga filtrar por cliente + fazenda mesmo em ações feitas
 * via impersonação (sessão master entrando como cliente).
 *
 * Ordem de prioridade (a primeira fonte que retornar valor vence):
 *
 *   1. SUBJECT (entidade afetada) — se tem tenant_id/farm_id, é a fonte
 *      mais confiável (representa onde o efeito da ação aconteceu).
 *
 *   2. SESSÃO DE IMPERSONAÇÃO — quando o master está logado COMO um
 *      cliente, a sessão tem `impersonation.tenant_id` e
 *      `impersonation.farm_id`. Esses valores representam o cliente
 *      REAL afetado (não o master).
 *
 *   3. CAUSER (usuário que disparou a ação) — fallback. Master tem
 *      tenant_id NULL, então só funciona pra usuários de tenant.
 *
 *   4. CONTEXTO BIND — `app('tenant_id')` se algum middleware ja' bind.
 */
class ActivityLogTenantFarmObserver
{
    public function creating(Activity $activity): void
    {
        // 1ª prioridade · subject (entidade afetada)
        if (! $activity->tenant_id) {
            $activity->tenant_id = $this->resolveTenantFromSubject($activity);
        }
        if (! $activity->getAttribute('farm_id')) {
            $activity->setAttribute('farm_id', $this->resolveFarmFromSubject($activity));
        }

        // 2ª prioridade · sessão de impersonação
        if (! $activity->tenant_id && Session::has('impersonation.tenant_id')) {
            $activity->tenant_id = (int) Session::get('impersonation.tenant_id');
        }
        if (! $activity->getAttribute('farm_id') && Session::has('impersonation.farm_id')) {
            $activity->setAttribute('farm_id', (int) Session::get('impersonation.farm_id'));
        }

        // 3ª prioridade · causer (usuário comum)
        if (! $activity->tenant_id && $activity->causer_id) {
            $causer = $activity->causer;
            if ($causer && isset($causer->tenant_id)) {
                $activity->tenant_id = (int) $causer->tenant_id;
            }
        }
        if (! $activity->getAttribute('farm_id') && $activity->causer_id) {
            $causer = $activity->causer;
            if ($causer && isset($causer->current_farm_id)) {
                $activity->setAttribute('farm_id', (int) $causer->current_farm_id);
            }
        }

        // 4ª prioridade · contexto bind no container
        if (! $activity->tenant_id && app()->bound('tenant_id')) {
            $tid = app('tenant_id');
            if ($tid) $activity->tenant_id = (int) $tid;
        }
    }

    /**
     * Tenta extrair tenant_id do subject (modelo afetado pelo evento).
     */
    private function resolveTenantFromSubject(Activity $activity): ?int
    {
        if (! $activity->subject) return null;
        $subject = $activity->subject;
        // subject tem propriedade tenant_id?
        if (isset($subject->tenant_id) && $subject->tenant_id) {
            return (int) $subject->tenant_id;
        }
        return null;
    }

    /**
     * Tenta extrair farm_id do subject. Útil pra entidades com BelongsToFarm
     * (animals, financial_transactions, etc.).
     */
    private function resolveFarmFromSubject(Activity $activity): ?int
    {
        if (! $activity->subject) return null;
        $subject = $activity->subject;
        if (isset($subject->farm_id) && $subject->farm_id) {
            return (int) $subject->farm_id;
        }
        return null;
    }
}
