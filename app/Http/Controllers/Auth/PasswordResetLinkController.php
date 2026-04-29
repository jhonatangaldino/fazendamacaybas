<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Solicita link de reset por email.
     *
     * SEGURANÇA · F5-S01 (QA Deep 2026-04-29): antes retornávamos 422 com
     * "Não encontramos nenhum usuário com esse e-mail" quando email não
     * existia. Isso permitia ENUMERAÇÃO de contas (atacante testa lista
     * de emails e descobre quais estão cadastrados).
     *
     * Agora respondemos sempre 200 genérico ("Se o e-mail estiver
     * cadastrado, você receberá um link"). Sucesso real (link enviado) e
     * email-não-existe geram a MESMA resposta. Atacante não consegue
     * distinguir.
     *
     * Erros internos (SMTP fora, throttle) ainda lançam exception
     * (servidor problema, não user input).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email:rfc|max:255']);

        $status = Password::sendResetLink($request->only('email'));

        // Mensagem genérica anti-enumeração — sempre 200 com mesma mensagem,
        // independente de email existir ou não no banco.
        $msg = 'Se o e-mail estiver cadastrado, você receberá um link de redefinição em alguns minutos.';

        // Throttle (ATTEMPTS de Password::RESET_THROTTLED) ainda surfaça erro
        // pra evitar spam — diferente de "email não existe".
        if ($status === Password::RESET_THROTTLED) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => [__('passwords.throttled')],
            ]);
        }

        return back()->with('status', $msg);
    }
}
