# 🚨 Recuperação de Desastre · Fazenda Macaybas

Documento operacional · O que fazer se o servidor for apagado, banco corrompido, ou sistema quebrar.

---

## Índice

1. [Estratégia de backup](#estratégia-de-backup)
2. [Onde estão os backups](#onde-estão-os-backups)
3. [Cenários de recuperação](#cenários-de-recuperação)
4. [Restore passo a passo](#restore-passo-a-passo)
5. [Configuração do backup automático](#configuração-do-backup-automático)
6. [Backup off-server (recomendado)](#backup-off-server-recomendado)

---

## Estratégia de backup

### O que está backado e onde

| Item | Onde | Atualizado | Responsável |
|---|---|---|---|
| **Código** (PHP, Vue, manuais, scripts) | GitHub `jhonatangaldino/fazendamacaybas` | A cada `git push` | Desenvolvedor |
| **Banco MySQL** | `shared/backups/db-current.sql.gz` (servidor) | A cada 10 dias automático | Cron + script |
| **Storage** (uploads, fotos, comprovantes) | `shared/backups/storage-current.tar.gz` (servidor) | A cada 10 dias automático | Cron + script |
| **`.env`** (DB pass, APP_KEY, SMTP) | `shared/backups/env-current.txt` (servidor) + cofre pessoal | A cada 10 dias + manual | Cron + você (cofre) |
| **Hostinger** automatic backup | Painel Hostinger (interno) | Diário (retenção 7d) | Hostinger |

### Premissa central

**3 backups distintos**, com mudança nunca chega a destruir mais de um simultaneamente:

```
GitHub (código)            ← off-server, multi-region (GitHub infra)
   +
Hostinger backup automático ← off-server (retenção 7 dias)
   +
shared/backups/ (servidor) ← on-server, rotativo a cada 10 dias
```

Se um falhar, os outros 2 ainda existem.

---

## Onde estão os backups

### No servidor (shared persistente entre deploys)

```
/home/u931382046/domains/fazendamacaybas.com.br/shared/backups/
├── db-current.sql.gz       ← mysqldump comprimido (~2-5 MB)
├── storage-current.tar.gz  ← uploads (~20-30 MB)
├── env-current.txt         ← cópia do .env (perm 600)
├── last-backup.txt         ← timestamp do último backup OK
└── backup.log              ← log de execuções
```

**Tamanho total**: ~30-50 MB. Não cresce porque é rotativo (substitui).

### No GitHub

Tudo em `github.com/jhonatangaldino/fazendamacaybas`. Inclui:
- Código completo
- Migrations e seeders (estrutura do banco)
- Scripts de deploy e backup
- Manuais HTML + screenshots
- Este documento

### Local (você)

Recomendado manter no seu PC ou cofre (Bitwarden/1Password):
- Cópia atual do `.env` (atualiza quando mudar APP_KEY ou DB_PASSWORD)
- Chave SSH `~/.ssh/macaybas_deploy` + `.pub`
- Senha master da plataforma

---

## Cenários de recuperação

### 🟢 Cenário 1 · Bug no código (deploy ruim)
Sintomas: site fora do ar OU funcionalidade quebrada após deploy.

**Solução**: rollback do symlink no servidor.
```bash
ssh -i ~/.ssh/macaybas_deploy -p 65002 u931382046@147.93.14.208 \
    'cd domains/fazendamacaybas.com.br/releases && \
     ln -sfn $(ls -t | grep -v current | head -2 | tail -1) current'
```
Volta pra release anterior em < 1 segundo. Banco e storage **não são afetados**.

### 🟡 Cenário 2 · Dados corrompidos (alguém apagou algo crítico)
Sintomas: registros desapareceram, banco voltou pra estado errado.

**Solução**: restore do banco a partir do `db-current.sql.gz`.
```bash
ssh -i ~/.ssh/macaybas_deploy -p 65002 u931382046@147.93.14.208 \
    'bash domains/fazendamacaybas.com.br/releases/current/scripts/restore-do-zero.sh'
```
**Atenção**: sobrescreve banco atual. Você perde mudanças entre o último backup e agora (até 10 dias).

### 🟠 Cenário 3 · Servidor apagado / hackeado
Sintomas: site fora do ar, arquivos sumiram, ou Hostinger reportou intrusão.

**Solução**: restore completo (passo a passo abaixo). Tempo estimado: 30-45 min.

### 🔴 Cenário 4 · Hostinger inteiro fora do ar (raro)
Sintomas: nenhum SSH responde, painel Hostinger inacessível por horas.

**Solução**: contratar nova hospedagem (qualquer com PHP 8.2 + MySQL + SSH), seguir restore passo a passo, atualizar DNS.

---

## Restore passo a passo

### Pré-requisitos
- Você tem acesso SSH ao servidor (chave + IP + porta)
- Você tem o `.env` no seu cofre OU `shared/backups/env-current.txt` ainda existe
- Banco MySQL Hostinger acessível (mesmo se vazio)

### Passo 1 · Confirma os 3 backups
```bash
ssh -i ~/.ssh/macaybas_deploy -p 65002 u931382046@147.93.14.208 \
    'ls -la domains/fazendamacaybas.com.br/shared/backups/'
```
Você deve ver: `db-current.sql.gz`, `storage-current.tar.gz`, `env-current.txt`.

### Passo 2 · Re-deploya código do GitHub
No seu PC:
```bash
cd "D:/Sites/Fazenda Macaybas/Sistema"
git pull origin main           # garante código atualizado
bash scripts/deploy-local.sh   # build + ship
```

### Passo 3 · Roda o restore script
```bash
ssh -i ~/.ssh/macaybas_deploy -p 65002 u931382046@147.93.14.208 \
    'bash domains/fazendamacaybas.com.br/releases/current/scripts/restore-do-zero.sh'
```
O script:
1. Valida os 3 backups são íntegros
2. Pede confirmação ("SIM RESTAURAR")
3. Restaura `.env`
4. DERRUBA o banco e RECRIA com mysqldump
5. Restaura `storage/app/public/`
6. Recria symlinks e permissões
7. Limpa caches Laravel
8. Smoke test HTTP

### Passo 4 · Validação manual
Acesse https://app.fazendamacaybas.com.br/login e:
- Faça login com user master
- Vá em /master/dashboard → confira KPIs
- Vá em /admin/rebanho → ver animais
- Vá em /admin/financeiro → ver transações

Se tudo OK ✅. Se algo errado, ver `storage/logs/laravel.log` no servidor.

### Passo 5 (opcional) · Rotaciona APP_KEY se houve invasão
Se o restore foi por suspeita de invasão:
```bash
ssh -i ~/.ssh/macaybas_deploy -p 65002 u931382046@147.93.14.208 \
    'cd domains/fazendamacaybas.com.br/releases/current && \
     php artisan key:generate --force'
```
Atenção: invalida sessões ativas e tokens encriptados antigos (recoveries de senha temp ficam ilegíveis — usuários precisam pedir nova senha).

---

## Configuração do backup automático

### No servidor (uma única vez)

Sincronize o script:
```bash
scp -i ~/.ssh/macaybas_deploy -P 65002 \
    scripts/backup-rotativo.sh \
    u931382046@147.93.14.208:domains/fazendamacaybas.com.br/shared/scripts/
ssh -i ~/.ssh/macaybas_deploy -p 65002 u931382046@147.93.14.208 \
    'chmod +x domains/fazendamacaybas.com.br/shared/scripts/backup-rotativo.sh'
```

### Cron do Hostinger (uma única vez)

Painel Hostinger → **Cron Jobs** → "Adicionar novo":

| Campo | Valor |
|---|---|
| Comando | `bash /home/u931382046/domains/fazendamacaybas.com.br/shared/scripts/backup-rotativo.sh` |
| Tipo | Avançado (cron) |
| Quando | `0 3 * * *` (todo dia às 3h da manhã) |

**O cron roda diariamente, mas o script só executa de fato a cada 10 dias** (auto-skip via `last-backup.txt`).

Por que diário? Caso a janela de 3h falhe (servidor offline), o próximo dia tenta de novo.

### Validação · primeira execução manual

Após configurar cron, rode 1× manualmente pra confirmar:
```bash
ssh -i ~/.ssh/macaybas_deploy -p 65002 u931382046@147.93.14.208 \
    'bash domains/fazendamacaybas.com.br/shared/scripts/backup-rotativo.sh'
```

Espere ver `✅ Backup OK` no fim. Confira:
```bash
ssh -i ~/.ssh/macaybas_deploy -p 65002 u931382046@147.93.14.208 \
    'ls -lh domains/fazendamacaybas.com.br/shared/backups/'
```

---

## Backup off-server (recomendado)

O backup no servidor protege contra **corrupção**, mas não contra **wipe total** (servidor inacessível).

### Solução 1 · Sync mensal pro PC

No seu PC, rode 1× por mês:
```bash
mkdir -p "$HOME/backups-macaybas/$(date +%Y-%m)"
scp -i ~/.ssh/macaybas_deploy -P 65002 \
    "u931382046@147.93.14.208:domains/fazendamacaybas.com.br/shared/backups/*" \
    "$HOME/backups-macaybas/$(date +%Y-%m)/"
```
Mantém os últimos 3-6 meses. ~150 MB total.

### Solução 2 · Sync pra Bitwarden/1Password

Pro `.env` (mais sensível):
1. Crie uma nota segura no Bitwarden: "Fazenda Macaybas · .env produção"
2. Cole o conteúdo do `.env` atualizado lá
3. Atualize sempre que mudar (APP_KEY, DB_PASSWORD, SMTP)

### Solução 3 · Backblaze B2 / S3 (~$0.50/mês)

Pra automatizar 100%:
1. Crie bucket Backblaze B2 ($6/TB/mês — ~$0.50 pra ~50 GB)
2. Adiciona ao `backup-rotativo.sh` upload via `b2-cli` ou `rclone`
3. Mantém versionamento automático de 6 meses

(Implementação opcional — só vale se o sistema crescer pra ter dados críticos de muitos clientes.)

---

## FAQ

**Q: E se o backup novo der erro?**
A: O script só substitui current depois de validar. Se falhar (mysqldump quebrar, tar zerar), o backup atual fica intacto e o erro é logado em `backup.log`.

**Q: Posso forçar um backup agora?**
A: Sim. SSH no servidor e rode `bash shared/scripts/backup-rotativo.sh`. O auto-skip respeita 10 dias, mas você pode forçar com `INTERVALO_DIAS=0 bash ...`.

**Q: Quanto espaço gasta?**
A: ~30-50 MB por ciclo. Não acumula porque substitui o anterior.

**Q: Por que 10 dias e não diário?**
A: Custo/benefício. Mudanças num sistema rural são gradativas — perder até 10 dias de cadastros é recuperável manualmente. Diário seria overkill.

**Q: Se eu mudar o APP_KEY ou DB_PASSWORD, o backup do `.env` continua válido?**
A: O `env-current.txt` no servidor é atualizado a cada 10 dias automaticamente. Mas pra urgência (servidor offline), mantenha cópia atualizada no Bitwarden cada vez que mudar.

---

**Última atualização**: 2026-04-29
