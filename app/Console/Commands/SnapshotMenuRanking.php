<?php

namespace App\Console\Commands;

use App\Models\MenuUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Congela o ranking atual de uso de menu em hits_snapshot.
 * Roda diariamente às 3h (cron do servidor ou scheduler) junto com o backup.
 * A ordem visível na sidebar só muda após este snapshot — evita reordenação
 * em tempo de execução, que confundiria o usuário.
 */
class SnapshotMenuRanking extends Command
{
    protected $signature = 'menu:snapshot {--dry : Apenas mostra o que seria feito, sem escrever}';

    protected $description = 'Congela o ranking atual de hits do menu para uso da sidebar até o próximo snapshot';

    public function handle(): int
    {
        $total = MenuUsage::count();
        if ($total === 0) {
            $this->info('Nenhum registro de uso de menu ainda — nada para congelar.');

            return self::SUCCESS;
        }

        if ($this->option('dry')) {
            $this->info("[DRY-RUN] {$total} registros seriam atualizados com hits → hits_snapshot.");

            return self::SUCCESS;
        }

        $affected = DB::table('menu_usage')->update([
            'hits_snapshot' => DB::raw('hits'),
            'snapshot_at' => now(),
        ]);

        $this->info("Snapshot do ranking de menu concluído — {$affected} registros congelados.");

        return self::SUCCESS;
    }
}
