<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetAdminMaster extends Command
{
    protected $signature = 'macaybas:reset-admin
                            {--email= : E-mail do admin master (padrão: env ADMIN_MASTER_EMAIL)}
                            {--password= : Senha temporária (padrão: aleatória)}';

    protected $description = 'Reseta a senha do Admin Master e força troca no próximo login';

    public function handle(): int
    {
        $email = $this->option('email') ?: env('ADMIN_MASTER_EMAIL');
        if (! $email) {
            $this->error('Informe --email ou configure ADMIN_MASTER_EMAIL no .env');
            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("Usuário com e-mail {$email} não encontrado.");
            return self::FAILURE;
        }

        $senha = $this->option('password') ?: Str::password(14);
        $user->update([
            'password' => Hash::make($senha),
            'must_change_password' => true,
            'is_active' => true,
        ]);

        if (! $user->hasRole('admin_master')) {
            $user->assignRole('admin_master');
            $this->info("Perfil admin_master atribuído.");
        }

        $this->info("Senha resetada para {$email}.");
        $this->line("Senha temporária: {$senha}");
        $this->warn("O usuário será obrigado a trocá-la no próximo login.");

        return self::SUCCESS;
    }
}
