<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tutorial;
use App\Models\UserTutorialState;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TutorialController — sistema de tutorial in-app contextual.
 *
 *  GET  /admin/tutorials/active?rota=/admin/inicio
 *      → retorna tutorial pendente (se houver) para o user na rota,
 *        respeitando permissões e janela de retry (15d).
 *
 *  POST /admin/tutorials/{key}/complete   → user terminou o tour
 *  POST /admin/tutorials/{key}/dismiss    → "não exibir mais" (permanente)
 *  POST /admin/tutorials/{key}/snooze     → fechou sem completar (volta em 15d)
 */
class TutorialController extends Controller
{
    private const RETRY_DAYS = 15;

    public function active(Request $request): JsonResponse
    {
        $rota = $request->query('rota', '');
        $user = $request->user();
        if (! $user) return response()->json(['tutorial' => null]);

        $tutorial = Tutorial::query()
            ->ativos()
            ->where('rota', $rota)
            ->orderBy('order_column')
            ->get()
            ->first(function (Tutorial $t) use ($user) {
                // 1. Permissões: se o tutorial requer permissão que o user não tem, pula.
                if (! empty($t->permissions_required)) {
                    foreach ($t->permissions_required as $perm) {
                        if (! $user->can($perm)) return false;
                    }
                }
                // 2. Estado do user para este tutorial
                $state = UserTutorialState::where('user_id', $user->id)
                    ->where('tutorial_key', $t->key)
                    ->first();
                if (! $state) return true; // nunca exibido → mostrar
                if ($state->status === 'dispensado') return false;
                if ($state->status === 'completado') return false;
                // pendente: respeitar next_retry_at
                if ($state->next_retry_at && $state->next_retry_at->isFuture()) return false;
                return true;
            });

        if (! $tutorial) return response()->json(['tutorial' => null]);

        return response()->json([
            'tutorial' => [
                'key' => $tutorial->key,
                'titulo' => $tutorial->titulo,
                'passos' => $tutorial->passos,
            ],
        ]);
    }

    public function complete(Request $request, string $key): JsonResponse
    {
        $this->upsertState($request, $key, 'completado');
        return response()->json(['ok' => true]);
    }

    public function dismiss(Request $request, string $key): JsonResponse
    {
        $this->upsertState($request, $key, 'dispensado');
        return response()->json(['ok' => true]);
    }

    public function snooze(Request $request, string $key): JsonResponse
    {
        $this->upsertState($request, $key, 'pendente', Carbon::now()->addDays(self::RETRY_DAYS));
        return response()->json(['ok' => true]);
    }

    private function upsertState(Request $request, string $key, string $status, ?Carbon $nextRetry = null): void
    {
        $user = $request->user();
        UserTutorialState::updateOrCreate(
            ['user_id' => $user->id, 'tutorial_key' => $key],
            [
                'status' => $status,
                'completed_at' => $status === 'completado' ? now() : null,
                'dismissed_at' => $status === 'dispensado' ? now() : null,
                'next_retry_at' => $nextRetry,
            ]
        );
    }
}
