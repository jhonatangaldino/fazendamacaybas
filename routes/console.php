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
