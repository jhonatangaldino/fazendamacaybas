<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Incrementa o contador de uso de um item do menu para o usuário corrente.
 * Invocado fire-and-forget pelo front quando o usuário clica num link da sidebar.
 */
class MenuUsageController extends Controller
{
    public function bump(Request $request): JsonResponse
    {
        $key = (string) $request->input('key', '');
        if ($key === '' || strlen($key) > 80) {
            return response()->json(['ok' => false], 422);
        }

        $row = MenuUsage::firstOrNew([
            'user_id' => $request->user()->id,
            'menu_key' => $key,
        ]);
        $row->hits = ($row->hits ?? 0) + 1;
        $row->last_used_at = now();
        $row->save();

        return response()->json(['ok' => true]);
    }
}
