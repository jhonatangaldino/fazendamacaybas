<?php

namespace App\Console\Commands;

use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class HealthCheck extends Command
{
    protected $signature = 'macaybas:health
                            {--mail= : Se informado, envia e-mail de teste para este endereço}';

    protected $description = 'Executa verificações de saúde do sistema (banco, cache, storage, mail, timezone).';

    public function handle(): int
    {
        $this->newLine();
        $this->info('=== Fazenda Macaybas — Health Check ===');
        $this->newLine();

        $results = [];

        $results['timezone'] = $this->check(
            'Timezone',
            fn () => config('app.timezone') === 'America/Sao_Paulo',
            'esperado America/Sao_Paulo, atual: '.config('app.timezone')
        );

        $results['locale'] = $this->check(
            'Locale',
            fn () => config('app.locale') === 'pt_BR',
            'esperado pt_BR, atual: '.config('app.locale')
        );

        $results['env'] = $this->check(
            'Ambiente = production',
            fn () => app()->environment('production'),
            'atual: '.app()->environment()
        );

        $results['debug_off'] = $this->check(
            'Debug desligado',
            fn () => config('app.debug') === false,
            'APP_DEBUG deve ser false em produção'
        );

        $results['db'] = $this->check(
            'Banco (MySQL)',
            function () {
                DB::connection()->getPdo();
                $tz = DB::selectOne('SELECT @@session.time_zone AS tz')->tz;

                return str_starts_with($tz, '-03') || $tz === 'America/Sao_Paulo';
            },
            'conexão ou timezone do MySQL'
        );

        $results['migrations'] = $this->check(
            'Migrations executadas',
            function () {
                return DB::table('migrations')->count() > 0;
            },
            'tabela migrations vazia'
        );

        $results['roles'] = $this->check(
            'Roles carregados (spatie/permission)',
            fn () => DB::table('roles')->count() >= 10,
            'esperado ≥ 10 roles'
        );

        $results['admin'] = $this->check(
            'Admin Master existe e ativo',
            function () {
                $u = User::role('admin_master')->where('is_active', true)->first();

                return $u !== null;
            },
            'nenhum usuário admin_master ativo'
        );

        $results['storage'] = $this->check(
            'Storage gravável',
            function () {
                Storage::disk('public')->put('.health-check', now()->toISOString());
                Storage::disk('public')->delete('.health-check');

                return true;
            },
            'disk public não gravável — verifique permissões do shared/storage'
        );

        $results['cache'] = $this->check(
            'Cache funcional',
            function () {
                cache()->put('.health-check', 'ok', 10);
                $v = cache()->get('.health-check');
                cache()->forget('.health-check');

                return $v === 'ok';
            },
            'cache não está persistindo'
        );

        $results['log'] = $this->check(
            'Logs graváveis',
            function () {
                $path = storage_path('logs');
                return is_dir($path) && is_writable($path);
            },
            'storage/logs não gravável'
        );

        if ($this->option('mail')) {
            $to = $this->option('mail');
            $results['mail'] = $this->check(
                "Envio SMTP para {$to}",
                function () use ($to) {
                    Mail::raw(
                        '[Macaybas] Teste de SMTP do comando macaybas:health em '.now()->format('d/m/Y H:i:s'),
                        fn ($m) => $m->to($to)->subject('[Macaybas] Teste de SMTP')
                    );

                    return true;
                },
                'falha ao enviar e-mail — cheque MAIL_* no .env'
            );
        }

        $this->newLine();
        $passed = count(array_filter($results));
        $total = count($results);

        if ($passed === $total) {
            $this->info("✅ Todos os {$total} checks passaram.");

            return self::SUCCESS;
        }

        $this->error("❌ {$passed}/{$total} checks passaram.");

        return self::FAILURE;
    }

    protected function check(string $label, callable $probe, string $errorHint = ''): bool
    {
        try {
            $ok = (bool) $probe();
        } catch (Exception $e) {
            $ok = false;
            $errorHint = $e->getMessage();
        }

        $icon = $ok ? '<fg=green>✓</>' : '<fg=red>✗</>';
        $this->line("  {$icon}  {$label}".($ok ? '' : " <fg=red>— {$errorHint}</>"));

        return $ok;
    }
}
