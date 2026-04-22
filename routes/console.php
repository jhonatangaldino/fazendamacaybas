<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler do Laravel
Schedule::command('queue:work --stop-when-empty --max-time=60')->everyMinute()->withoutOverlapping();
Schedule::command('activitylog:clean --days=90')->dailyAt('02:00');

// Congela o ranking do menu às 3h (junto com o backup). A ordem da sidebar
// só muda após este snapshot — evita reordenação em tempo de uso.
Schedule::command('menu:snapshot')->dailyAt('03:00')->timezone('America/Sao_Paulo');
