<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache diário do ranking de menu.
 * A coluna `hits` continua incrementando em tempo real (pra não perder estatística),
 * mas a UI da sidebar ordena por `hits_snapshot` — atualizado 1x por dia às 3h via
 * php artisan menu:snapshot (disparado no mesmo cron do backup).
 *
 * Isso evita que a ordem "mais usados" mude em tempo de execução — a memória muscular
 * do usuário permanece estável durante o dia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_usage', function (Blueprint $table) {
            $table->unsignedBigInteger('hits_snapshot')->default(0)->after('hits');
            $table->timestamp('snapshot_at')->nullable()->after('hits_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('menu_usage', function (Blueprint $table) {
            $table->dropColumn(['hits_snapshot', 'snapshot_at']);
        });
    }
};
