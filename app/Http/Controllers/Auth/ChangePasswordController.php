<?php

namespace App\Http\Controllers\Auth;

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

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $rules = [
            'password' => ['required', 'confirmed', Rules\Password::defaults()->min(8)->letters()->numbers()->symbols()],
        ];

        if (! $user->must_change_password) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $request->validate($rules);

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        return redirect()->route('admin.dashboard')->with('success', 'Senha alterada com sucesso.');
    }
}
