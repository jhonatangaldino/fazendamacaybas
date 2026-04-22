<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('pt_BR');
        date_default_timezone_set(config('app.timezone', 'America/Sao_Paulo'));

        Paginator::useTailwind();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
