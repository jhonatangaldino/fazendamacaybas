<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mede número de queries + tempo total por request.
 *
 * Ativo apenas quando `APP_DEBUG=true` OU header `X-Query-Probe: 1` presente.
 * Zero overhead em produção com debug off e sem header.
 *
 * Output:
 *   - Header `X-Query-Count: N`
 *   - Header `X-Query-Time-Ms: N.N`
 *   - Log entry em storage/logs/query-probe-YYYY-MM-DD.log
 *
 * Uso durante QA para validar redução pós-otimização (Hostinger 500 conn/h).
 */
class QueryCounter
{
    public function handle(Request $request, Closure $next)
    {
        $shouldProbe = config('app.debug') || $request->header('X-Query-Probe') === '1';

        if (! $shouldProbe) {
            return $next($request);
        }

        $queries = [];
        $totalTime = 0.0;

        DB::listen(function ($query) use (&$queries, &$totalTime) {
            $queries[] = [
                'sql' => substr($query->sql, 0, 120),
                'time_ms' => $query->time,
            ];
            $totalTime += $query->time;
        });

        $response = $next($request);

        $count = count($queries);
        $response->headers->set('X-Query-Count', (string) $count);
        $response->headers->set('X-Query-Time-Ms', sprintf('%.1f', $totalTime));

        // Log detalhado só quando header forçar (evita ruído do APP_DEBUG)
        if ($request->header('X-Query-Probe') === '1') {
            Log::channel('single')->info('QueryProbe', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'count' => $count,
                'time_ms' => sprintf('%.1f', $totalTime),
                'user' => $request->user()?->email ?? 'guest',
                'top_queries' => collect($queries)
                    ->sortByDesc('time_ms')
                    ->take(5)
                    ->values()
                    ->all(),
            ]);
        }

        return $response;
    }
}
