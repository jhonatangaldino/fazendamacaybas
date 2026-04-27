@extends('emails.partials.layout', ['tituloEmail' => 'Bem-vindo(a) ao Fazenda Macaybas'])

@section('content')
    <h1 style="margin:0 0 16px; color:#1a2e1f; font-family:Georgia,serif; font-size:24px; font-weight:700; line-height:1.3;">
        Bem-vindo(a), {{ $nome }}!
    </h1>

    <p style="margin:0 0 16px; color:#3a4a3f; font-size:15px; line-height:1.6;">
        Sua conta foi criada no sistema da Fazenda Macaybas. Use as credenciais
        abaixo para acessar pela primeira vez. <strong>No primeiro login, você
        será solicitado a definir uma nova senha.</strong>
    </p>

    {{-- Bloco de credenciais --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:24px 0; background:#f0f4ee; border-left:4px solid #2d4a32; border-radius:8px;">
        <tr>
            <td style="padding:20px 24px;">
                <p style="margin:0 0 6px; color:#5a6a5f; font-size:12px; text-transform:uppercase; letter-spacing:1px; font-weight:600;">
                    Seu acesso
                </p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td style="padding:6px 0;">
                            <div style="color:#7a8071; font-size:12px;">E-mail</div>
                            <div style="color:#1a2e1f; font-size:15px; font-weight:600; font-family:'SF Mono',Consolas,Menlo,monospace;">
                                {{ $email }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0 4px;">
                            <div style="color:#7a8071; font-size:12px;">Senha temporária</div>
                            <div style="color:#1a2e1f; font-size:22px; font-weight:700; font-family:'SF Mono',Consolas,Menlo,monospace; letter-spacing:3px; padding:6px 0;">
                                {{ $senha }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Aviso de expiração --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 28px; background:#fdf6e3; border-radius:8px;">
        <tr>
            <td style="padding:14px 18px;">
                <p style="margin:0; color:#7a5e15; font-size:13px; line-height:1.5;">
                    <strong>⏰ Esta senha expira em {{ $expira_em }}</strong> ({{ \App\Domain\Auth\Services\TemporaryPasswordService::VALIDITY_HOURS }} horas após o cadastro).<br>
                    Se não for usada a tempo, o sistema gerará uma nova e enviará novo email.
                </p>
            </td>
        </tr>
    </table>

    {{-- CTA --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
        <tr>
            <td style="border-radius:8px; background:#2d4a32;">
                <a href="{{ $urlLogin }}"
                   style="display:inline-block; padding:14px 32px; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; border-radius:8px;">
                    Acessar o sistema →
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0; color:#7a8071; font-size:13px; line-height:1.5;">
        Se o botão não funcionar, copie e cole este link no navegador:<br>
        <a href="{{ $urlLogin }}" style="color:#2d4a32; word-break:break-all;">{{ $urlLogin }}</a>
    </p>
@endsection
