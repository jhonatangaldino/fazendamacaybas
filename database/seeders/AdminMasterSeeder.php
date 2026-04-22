<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminMasterSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_MASTER_EMAIL', 'admin@fazendamacaybas.com.br');
        $name = env('ADMIN_MASTER_NAME', 'Admin Master');
        $password = env('ADMIN_MASTER_PASSWORD', 'TrocarNoPrimeiroLogin@2026');

        $user = User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'cargo' => 'Admin Master',
                'must_change_password' => false, // Jhonatan já escolheu senha; deixar false
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if ($user->trashed()) {
            $user->restore();
        }

        $user->syncRoles(['admin_master']);
    }
}
