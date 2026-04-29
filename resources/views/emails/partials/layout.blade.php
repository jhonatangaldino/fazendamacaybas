{{--
    Layout base de emails — identidade Macaybas (verde sage + bege).
    Otimizado pra mobile: layout simples, single-column, inline CSS,
    plus <style> mediafallback. Evita o "Download full message" no
    Outlook/Hotmail mobile com:
      - Estrutura mínima (1 tabela, sem aninhamento profundo)
      - Cor de fundo simples
      - <style> com max-width responsivo + hide on mobile (decorations)
      - Plain-text alternativo (configurado no Mailable .text())
--}}<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="format-detection" content="telephone=no, address=no, email=no">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $tituloEmail ?? 'Fazenda Macaybas' }}</title>
    <!--[if mso]>
    <style type="text/css">
        table { border-collapse: collapse; }
        body, table, td, p { font-family: Arial, sans-serif !important; }
    </style>
    <![endif]-->
    <style>
        /* Reset e responsivo mínimo */
        body, table, td, p, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; }

        /* Mobile */
        @media only screen and (max-width: 600px) {
            .container { width: 100% !important; max-width: 100% !important; border-radius: 0 !important; }
            .padded { padding-left: 18px !important; padding-right: 18px !important; }
            h1 { font-size: 20px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#f5f5f0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1a2e1f;">
    {{-- Pre-header invisível: melhora preview na caixa de entrada --}}
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">{{ $preheader ?? $tituloEmail ?? 'Fazenda Macaybas — sistema de gestão' }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f5f0;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="container" style="width:600px; max-width:600px; background:#ffffff; border-radius:12px; overflow:hidden;">

                    {{-- Header com brand --}}
                    <tr>
                        <td class="padded" style="background:#2d4a32; padding:24px 32px; color:#ffffff;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="vertical-align:middle; width:48px;">
                                        <div style="width:40px; height:40px; background:#ffffff; border-radius:50%; text-align:center; line-height:40px; color:#2d4a32; font-weight:700; font-size:20px; font-family:Georgia,serif;">M</div>
                                    </td>
                                    <td style="padding-left:14px; vertical-align:middle;">
                                        <div style="color:#ffffff; font-family:Georgia,serif; font-size:18px; font-weight:700; line-height:1.2;">Fazenda Macaybas</div>
                                        <div style="color:#d4a045; font-size:11px; letter-spacing:1.5px; text-transform:uppercase; margin-top:4px;">Sistema de Gestão</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Conteúdo --}}
                    <tr>
                        <td class="padded" style="padding:32px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td class="padded" style="background:#fafaf5; padding:18px 32px; border-top:1px solid #ececea;">
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
