@extends('emails.partials.layout', ['tituloEmail' => $manualTitulo.' · Fazenda Macaybas'])

@section('content')
    <h1 style="margin:0 0 16px; color:#1a2e1f; font-family:Georgia,serif; font-size:24px; font-weight:700; line-height:1.3;">
        Olá, {{ $nome }}!
    </h1>

    <p style="margin:0 0 16px; color:#3a4a3f; font-size:15px; line-height:1.6;">
        @if ($remetenteNome)
            <strong>{{ $remetenteNome }}</strong> da equipe Fazenda Macaybas está te enviando o
        @else
            Segue o
        @endif
        <strong>{{ $manualTitulo }}</strong>.
        @if ($modoAnexo)
            Está em anexo neste e-mail (formato HTML — abre em qualquer navegador,
            no computador ou celular).
        @else
            Como o arquivo é grande, em vez de anexar, deixei um link de download abaixo.
            É só clicar e salvar no seu dispositivo.
        @endif
    </p>

    @if ($mensagemPersonalizada)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="margin:20px 0; background:#f0f4ee; border-left:4px solid #2d4a32; border-radius:8px;">
            <tr>
                <td style="padding:18px 22px;">
                    <p style="margin:0 0 6px; color:#5a6a5f; font-size:12px; text-transform:uppercase; letter-spacing:1px; font-weight:600;">
                        Mensagem
                    </p>
                    <div style="color:#1a2e1f; font-size:14.5px; line-height:1.6; white-space:pre-wrap;">{{ $mensagemPersonalizada }}</div>
                </td>
            </tr>
        </table>
    @endif

    {{-- CTA de download (modo link) --}}
    @if ($downloadUrl)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;">
            <tr>
                <td style="border-radius:8px; background:#2d4a32;">
                    <a href="{{ $downloadUrl }}"
                       style="display:inline-block; padding:14px 32px; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; border-radius:8px;">
                        📥 Baixar o manual
                    </a>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 18px; color:#7a8071; font-size:12px; line-height:1.5;">
            Se o botão não funcionar, copie e cole este link no navegador:<br>
            <a href="{{ $downloadUrl }}" style="color:#2d4a32; word-break:break-all;">{{ $downloadUrl }}</a><br>
            <em>(link válido por 30 dias)</em>
        </p>
    @endif

    {{-- Como abrir / dicas de uso --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:24px 0; background:#fdf6e3; border-radius:8px;">
        <tr>
            <td style="padding:16px 20px;">
                <p style="margin:0 0 8px; color:#7a5e15; font-size:13px; font-weight:700; line-height:1.5;">
                    📖 Como usar o manual
                </p>
                <ul style="margin:6px 0 0; padding-left:20px; color:#7a5e15; font-size:13px; line-height:1.6;">
                    @if ($modoAnexo)
                        <li>Salve o arquivo anexado em uma pasta do seu computador ou celular.</li>
                        <li>Clique duas vezes nele → abre no navegador.</li>
                    @else
                        <li>Clique no botão "Baixar o manual" acima.</li>
                        <li>Salve o arquivo. Clique duas vezes pra abrir no navegador.</li>
                    @endif
                    <li>Use o sumário pra ir direto pra seção que precisa.</li>
                    <li>Pra imprimir: Ctrl+P (Windows) ou Cmd+P (Mac) — já formatado pra impressão.</li>
                </ul>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0; color:#3a4a3f; font-size:14px; line-height:1.6;">
        Comece pela seção <strong>"★ Primeiros passos · 5 coisas pra fazer no Dia 1"</strong> se
        ainda não conhece o sistema. Se preferir um cenário concreto, vai direto em
        <strong>"★ Cenários reais"</strong> — fluxos completos amarrados.
    </p>

    <p style="margin:18px 0 0; color:#3a4a3f; font-size:14px; line-height:1.6;">
        Qualquer dúvida ou sugestão, é só responder este e-mail.
    </p>

    <p style="margin:24px 0 0; color:#7a8071; font-size:13px; line-height:1.6;">
        Bom trabalho!<br>
        <strong style="color:#1a2e1f;">Equipe Fazenda Macaybas</strong>
    </p>
@endsection
