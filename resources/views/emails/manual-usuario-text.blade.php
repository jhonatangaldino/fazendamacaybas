{{ $manualTitulo }} · Fazenda Macaybas
========================================

Olá, {{ $nome }}!

@if ($remetenteNome)
{{ $remetenteNome }} da equipe Fazenda Macaybas está te enviando o
@else
Segue o
@endif
{{ $manualTitulo }}.
@if ($modoAnexo)
Está em anexo neste e-mail (formato HTML — abre em qualquer navegador,
no computador ou celular).
@else
Como o arquivo é grande, em vez de anexar, deixei um link de download abaixo.
É só clicar e salvar no seu dispositivo.
@endif

@if ($mensagemPersonalizada)
---
MENSAGEM:
{{ $mensagemPersonalizada }}
---
@endif

@if ($downloadUrl)
BAIXAR O MANUAL:
{{ $downloadUrl }}
(link válido por 30 dias)

@endif
COMO USAR O MANUAL
------------------
@if ($modoAnexo)
1. Salve o arquivo anexado em uma pasta do seu computador ou celular.
2. Clique duas vezes nele - abre no navegador.
@else
1. Clique no link "Baixar o manual" acima.
2. Salve o arquivo. Clique duas vezes pra abrir no navegador.
@endif
3. Use o sumário pra ir direto pra seção que precisa.
4. Pra imprimir: Ctrl+P (Windows) ou Cmd+P (Mac).

Comece pela seção "Primeiros passos · 5 coisas pra fazer no Dia 1" se
ainda não conhece o sistema. Se preferir um cenário concreto, vai direto
em "Cenários reais" - fluxos completos amarrados.

Qualquer dúvida ou sugestão, é só responder este e-mail.

Bom trabalho!
Equipe Fazenda Macaybas

--
Este e-mail foi enviado automaticamente.
© {{ now()->year }} Fazenda Macaybas — Itabirito/MG
