<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocaleTimezone
{
    /**
     * Garante pt-BR e America/Sao_Paulo em cada requisição.
     * Redundância defensiva: config/app.php já aplica, mas alguns drivers de queue/console
     * podem esquecer. Aqui é a última garantia.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        App::setLocale('pt_BR');
        date_default_timezone_set('America/Sao_Paulo');
        Carbon::setLocale('pt_BR');

        return $next($request);
    }
}
