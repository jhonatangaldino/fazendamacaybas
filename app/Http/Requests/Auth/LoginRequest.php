<?php

namespace App\Http\Requests\Auth;

use App\Domain\Auth\Services\TemporaryPasswordService;
use App\Models\User;
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

        // ────────────────────────────────────────────────────────────────
        // Pré-check: senha temporária expirada.
        //
        // Se o user existe E está com must_change_password=true E
        // password_expires_at < agora, a senha temp atual NÃO funciona mais.
        // Regeneramos automaticamente uma nova + reenviamos email + recusamos
        // o login com mensagem específica.
        //
        // Rodamos este check ANTES do Auth::attempt para que credenciais
        // expiradas não consumam tentativa de rate-limit.
        // ────────────────────────────────────────────────────────────────
        $userPreCheck = User::where('email', $email)->first();
        if ($userPreCheck && $userPreCheck->temporaryPasswordIsExpired()) {
            // Verifica se a senha digitada bate com a temp atual antes de
            // regenerar — assim usuário só recebe email se realmente está
            // tentando logar com uma temp expirada (não se digitou outra senha qualquer).
            if (\Illuminate\Support\Facades\Hash::check($password, $userPreCheck->password)) {
                app(TemporaryPasswordService::class)->regenerate($userPreCheck);
                throw ValidationException::withMessages([
                    'email' => 'Sua senha temporária expirou. Uma nova foi enviada para o seu email.',
                ]);
            }
        }

        // Reestruturação 2026-04-27: login isolado por contexto.
        // Master admin sempre passa; tenant user precisa bater com expected_tenant_id.
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

        // F5-S04 (QA Deep 2026-04-29): bloqueia master logando em
        // contexto tenant (app.fazendamacaybas.com.br). Antes passava com
        // status 200, violando o "login isolado por contexto" descrito na
        // reestruturação 2026-04-27. Master deve logar APENAS na raiz
        // (fazendamacaybas.com.br), tenants APENAS em app.* ou subdomínio.
        if ($isMasterAdmin && $expectedTenantId !== null) {
            Auth::logout();
            $this->session()->invalidate();
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => 'Master deve logar em fazendamacaybas.com.br/login (não em app.*).',
            ]);
        }

        if ($expectedTenantId !== null && ! $isMasterAdmin) {
            if ((int) $user->tenant_id !== (int) $expectedTenantId) {
                Auth::logout();
                $this->session()->invalidate();
                RateLimiter::hit($this->throttleKey());
                throw ValidationException::withMessages([
                    'email' => __('auth.failed'),
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
