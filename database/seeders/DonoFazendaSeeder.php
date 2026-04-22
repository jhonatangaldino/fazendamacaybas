<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DonoFazendaSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('DONO_FAZENDA_EMAIL', 'dono@fazendamacaybas.com.br');
        $name = env('DONO_FAZENDA_NAME', 'Dono da Fazenda');
        $password = env('DONO_FAZENDA_PASSWORD', 'MudarNoPrimeiroLogin@2026');

        $user = User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'cargo' => 'Dono da Fazenda',
                'must_change_password' => true, // força troca no 1º login
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if ($user->trashed()) {
            $user->restore();
        }

        $user->syncRoles(['dono_fazenda']);
    }
}
