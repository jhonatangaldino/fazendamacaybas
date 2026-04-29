Bem-vindo(a) ao Fazenda Macaybas!
==================================

Olá, {{ $nome }}!

Sua conta foi criada no sistema da Fazenda Macaybas. Use as credenciais
abaixo para acessar pela primeira vez. NO PRIMEIRO LOGIN, VOCÊ SERÁ
SOLICITADO A DEFINIR UMA NOVA SENHA.

SEU ACESSO
----------
E-mail: {{ $email }}
Senha temporária: {{ $senha }}

>> Esta senha expira em {{ $expira_em }}.
   Se não for usada a tempo, o sistema gerará uma nova e enviará novo e-mail.

Acessar o sistema:
{{ $urlLogin }}

--
Este e-mail foi enviado automaticamente.
© {{ now()->year }} Fazenda Macaybas — Itabirito/MG
