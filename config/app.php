<?php

return [

    'name' => env('APP_NAME', 'Fazenda Macaybas'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'https://fazendamacaybas.com.br'),

    /*
    |--------------------------------------------------------------------------
    | Timezone — Brasília (UTC-3)
    |--------------------------------------------------------------------------
    | America/Sao_Paulo cobre horário de Brasília sem ajuste manual.
    | Em 2019 o DST foi extinto; hoje o offset é fixo -03:00 o ano todo.
    */
    'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),

    /*
    |--------------------------------------------------------------------------
    | Locale pt-BR — padrão único do sistema
    |--------------------------------------------------------------------------
    */
    'locale' => env('APP_LOCALE', 'pt_BR'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'pt_BR'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'pt_BR'),

    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
