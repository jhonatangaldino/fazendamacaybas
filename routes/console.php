<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler do Laravel.
//
// queue:work roda a cada 5 min (não a cada minuto) para reduzir consumo de
// conexões MySQL no plano Hostinger Business (limite 500/hora). Trade-off:
// e-mails da fila demoram até 5 min pra sair em vez de 1 min — aceitável
// para emails de boas-vindas/notificações que NÃO são tempo-real.
//
// Se algum dia precisar de processamento mais rápido (ex.: notificação
// crítica), volte para ->everyMinute() e considere upgrade do plano.
Schedule::command('queue:work --stop-when-empty --max-time=60')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('activitylog:clean --days=90')->dailyAt('02:00');

// Congela o ranking do menu às 3h (junto com o backup). A ordem da sidebar
// só muda após este snapshot — evita reordenação em tempo de uso.
Schedule::command('menu:snapshot')->dailyAt('03:00')->timezone('America/Sao_Paulo');
