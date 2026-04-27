{{--
    Layout base de emails — identidade Macaybas (verde sage + bege).
    Inline CSS porque clientes de email (Gmail, Outlook) não suportam <style> externo.
    Use @yield('content') no email derivado.
--}}<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tituloEmail ?? 'Fazenda Macaybas' }}</title>
</head>
<body style="margin:0; padding:0; background:#f5f5f0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; color:#1a2e1f;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f5f0; padding:40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.06);">
                    {{-- Header com brand --}}
                    <tr>
                        <td style="background:#2d4a32; padding:28px 32px; text-align:left;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <div style="display:inline-block; width:40px; height:40px; background:#ffffff; border-radius:50%; text-align:center; line-height:40px; color:#2d4a32; font-weight:700; font-size:18px; font-family:Georgia,serif;">M</div>
                                    </td>
                                    <td style="padding-left:14px; vertical-align:middle;">
                                        <div style="color:#ffffff; font-family:Georgia,serif; font-size:18px; font-weight:700; line-height:1;">Fazenda Macaybas</div>
                                        <div style="color:#d4a045; font-size:11px; letter-spacing:2px; text-transform:uppercase; margin-top:4px;">Sistema de Gestão</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Conteúdo --}}
                    <tr>
                        <td style="padding:36px 32px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background:#fafaf5; padding:20px 32px; border-top:1px solid #ececea;">
                            <p style="margin:0; color:#7a8071; font-size:12px; line-height:1.5;">
                                Este e-mail foi enviado automaticamente — não responda esta mensagem.<br>
                                Em caso de dúvidas, fale com o administrador da sua conta.
                            </p>
                            <p style="margin:8px 0 0; color:#9a9a90; font-size:11px;">
                                © {{ now()->year }} Fazenda Macaybas — Itabirito/MG
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
