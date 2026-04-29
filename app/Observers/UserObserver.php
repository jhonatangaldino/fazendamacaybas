<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Metrics\PlatformMetrics;

/**
 * UserObserver
 *
 * Invalida o cache de KPIs do master sempre que usuários mudam.
 * Antes disso, depois de criar/deletar um user o dashboard ficava
 * mostrando o número antigo até o TTL_STANDARD expirar.
 */
class UserObserver
{
    public function created(User $user): void
    {
        $this->bustCache();
    }

    public function updated(User $user): void
    {
        // Só bust cache se mudou tenant_id ou is_active (impactam contagem)
        if ($user->wasChanged(['tenant_id', 'is_active', 'deleted_at'])) {
            $this->bustCache();
        }
    }

    public function deleted(User $user): void
    {
        $this->bustCache();
    }

    public function restored(User $user): void
    {
        $this->bustCache();
    }

    public function forceDeleted(User $user): void
    {
        $this->bustCache();
    }

    private function bustCache(): void
    {
        try {
            app(PlatformMetrics::class)->forgetMasterCache();
        } catch (\Throwable $e) {
            // Silencioso — invalidação de cache não pode quebrar o fluxo principal
        }
    }
}
