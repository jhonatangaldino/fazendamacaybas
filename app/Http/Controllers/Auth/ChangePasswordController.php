<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Services\TemporaryPasswordService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class ChangePasswordController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ChangePassword', [
            'forced' => (bool) auth()->user()?->must_change_password,
        ]);
    }

    public function update(Request $request, TemporaryPasswordService $tempPassword): RedirectResponse
    {
        $user = $request->user();

        $rules = [
            'password' => ['required', 'confirmed', Rules\Password::defaults()->min(8)->letters()->numbers()->symbols()],
        ];

        if (! $user->must_change_password) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $request->validate($rules);

        // SEGURANÇA · F5-S03 (QA Deep 2026-04-29): bloqueia troca pra senha
        // igual à atual/temporária. Antes o user podia "trocar" pra mesma
        // senha temp e o flag must_change_password virava false sem trocar
        // nada de fato. Vale tanto pra forced quanto pra troca normal.
        if (Hash::check($request->password, $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'password' => ['A nova senha precisa ser diferente da atual. Escolha uma nova.'],
            ]);
        }

        // Atualiza senha definitiva
        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        // Limpa estado de senha temporária (plaintext + expiração + flag must_change)
        $tempPassword->clearOnPasswordChange($user->fresh());

        return redirect()->route('admin.dashboard')->with('success', 'Senha alterada com sucesso.');
    }
}
