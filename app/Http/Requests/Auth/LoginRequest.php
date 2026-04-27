<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $email = $this->string('email')->lower()->value();
        $password = $this->string('password')->value();

        // Reestruturação 2026-04-27: login isolado por contexto.
        //
        // O middleware RouteByHost já resolveu qual tenant é esperado para
        // este host/path. Se o user não bate com esse tenant (e não é
        // master admin), recusamos com mensagem GENÉRICA — não vazamos a
        // existência da conta em outro tenant.
        //
        // Regras:
        //   1. Master admin (tenant_id NULL + role admin_master) loga em
        //      qualquer contexto (decisão do PO).
        //   2. User com tenant_id preenchido só loga em contextos que
        //      resolveram para o mesmo tenant_id.
        //   3. Se context = 'app' (host = app.*, sem /c/{slug}), aceita
        //      qualquer user — mas redirect pós-login leva pra rota dele.
        $expectedTenantId = $this->attributes->get('expected_tenant_id');

        if (! Auth::attempt(
            ['email' => $email, 'password' => $password, 'is_active' => true],
            $this->boolean('remember')
        )) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Pós-Auth::attempt → user logado. Agora valida escopo do tenant.
        $user = Auth::user();
        $isMasterAdmin = $user && method_exists($user, 'isAdminMaster')
            ? $user->isAdminMaster()
            : ($user?->tenant_id === null);

        if ($expectedTenantId !== null && ! $isMasterAdmin) {
            // Contexto exige um tenant específico (raiz, /c/{slug}, ou domínio próprio)
            if ((int) $user->tenant_id !== (int) $expectedTenantId) {
                Auth::logout();
                $this->session()->invalidate();
                RateLimiter::hit($this->throttleKey());
                throw ValidationException::withMessages([
                    'email' => __('auth.failed'), // mesma mensagem genérica
                ]);
            }
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
