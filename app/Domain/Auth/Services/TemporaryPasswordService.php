<?php

namespace App\Domain\Auth\Services;

use App\Mail\BoasVindasUsuario;
use App\Mail\SenhaRegenerada;
use App\Models\User;
use App\Support\PasswordGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * TemporaryPasswordService — orquestra senhas temporárias do fluxo
 * "convite por email".
 *
 * Responsabilidades:
 *   - assignNew(User): gera senha temp e seta no user (sem persistir email)
 *   - issueWelcome(User): gera senha + grava + envia email de boas-vindas
 *   - regenerate(User): gera NOVA senha + email de "senha regenerada"
 *                       (usado quando expira ou admin pede reenvio)
 *   - clearOnPasswordChange(User): limpa temp ao trocar senha definitiva
 *
 * Regras:
 *   - 8 chars alfanuméricos sem ambíguos (PasswordGenerator)
 *   - Expira em 2 horas
 *   - Plaintext criptografado at-rest (cast encrypted no model)
 *   - Email de envio: noreply@fazendamacaybas.com.br (config mail)
 *   - Falha de envio NÃO trava o cadastro (admin vê senha em tela como fallback)
 */
class TemporaryPasswordService
{
    /**
     * Validade da senha temporária — 2 horas.
     */
    public const VALIDITY_HOURS = 2;

    /**
     * Gera nova senha temporária e atualiza o user (não envia email).
     * Use issueWelcome() ou regenerate() quando quiser também enviar.
     *
     * @return string a senha em claro (para uso imediato em emails/UI)
     */
    public function assignNew(User $user): string
    {
        $plain = PasswordGenerator::make();

        $user->forceFill([
            'password' => Hash::make($plain),
            'temp_password_plaintext' => $plain,
            'password_expires_at' => Carbon::now()->addHours(self::VALIDITY_HOURS),
            'must_change_password' => true,
        ])->save();

        return $plain;
    }

    /**
     * Cadastro novo: gera senha + envia email de boas-vindas.
     * Retorna a senha em claro (para mostrar ao admin caso o email falhe).
     */
    public function issueWelcome(User $user): string
    {
        $plain = $this->assignNew($user);

        $this->sendMail($user, $plain, isWelcome: true);

        return $plain;
    }

    /**
     * Regenera a senha (expirou ou admin pediu reenvio).
     * Email de "senha temporária atualizada".
     */
    public function regenerate(User $user): string
    {
        $plain = $this->assignNew($user);

        $this->sendMail($user, $plain, isWelcome: false);

        return $plain;
    }

    /**
     * Limpa o estado de senha temporária — chamado quando user troca a
     * senha definitiva via /alterar-senha.
     */
    public function clearOnPasswordChange(User $user): void
    {
        $user->forceFill([
            'temp_password_plaintext' => null,
            'password_expires_at' => null,
            'must_change_password' => false,
        ])->save();
    }

    /**
     * Verifica e regenera senha expirada (chamado em pontos estratégicos:
     * tentativa de login, exibição na UI do admin).
     * Retorna true se foi regenerada agora.
     */
    public function regenerateIfExpired(User $user): bool
    {
        if (! $user->temporaryPasswordIsExpired()) return false;
        $this->regenerate($user);
        return true;
    }

    /**
     * Envia email com a senha em claro. Falhas são logadas mas não
     * propagadas — o admin tem a senha visível na UI como fallback.
     */
    private function sendMail(User $user, string $plain, bool $isWelcome): void
    {
        try {
            $mailable = $isWelcome
                ? new BoasVindasUsuario($user, $plain)
                : new SenhaRegenerada($user, $plain);

            Mail::to($user->email)->send($mailable);
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar email de senha temporária', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
            // Não relança — fluxo continua e admin vê senha em UI
        }
    }
}
