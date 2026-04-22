# Guia de Deploy — Fazenda Macaybas

Fluxo completo para colocar o sistema em produção no Hostinger Business. Execute os passos **uma vez** para o primeiro deploy; a partir daí, todo `git push origin main` dispara deploy automático em 2–3 minutos.

---

## Pré-requisitos (já coletados na FASE 1)

- ✅ Plano Hostinger **Business**
- ✅ PHP **8.2+** ativo (via hPanel → Advanced → PHP Configuration)
- ✅ SSH habilitado — **host `147.93.14.208`, porta `65002`, user `u931382046`**
- ✅ MySQL existente — **`u931382046_macaybas`** no host `srv1885.hstgr.io`
- ✅ Domínio `fazendamacaybas.com.br` apontado para o Hostinger, com SSL ativo
- ✅ Repositório `github.com/jhonatangaldino/fazendamacaybas` privado

---

## PASSO 1 — Gerar chave SSH dedicada ao deploy

No seu PC (Git Bash no Windows):

```bash
ssh-keygen -t ed25519 -C "deploy-macaybas" -f ~/.ssh/macaybas_deploy -N ""
```

Isso cria:

- `~/.ssh/macaybas_deploy` → **chave privada** (vai no GitHub Secrets)
- `~/.ssh/macaybas_deploy.pub` → **chave pública** (vai no Hostinger)

**Nunca** commite essas chaves no repositório.

---

## PASSO 2 — Cadastrar a chave PÚBLICA no Hostinger

```bash
cat ~/.ssh/macaybas_deploy.pub
```

Copie o conteúdo completo (começa com `ssh-ed25519 AAAA...`).

No **hPanel → Advanced → SSH Access → SSH Keys → Add new**:

1. Nome: `GitHub Actions Deploy`
2. Cole a chave pública
3. Salvar

Teste a conexão:

```bash
ssh -i ~/.ssh/macaybas_deploy -p 65002 u931382046@147.93.14.208
# Se logar sem pedir senha, está OK. Digite "exit".
```

---

## PASSO 3 — Cadastrar secrets no GitHub

No repositório: **Settings → Secrets and variables → Actions → New repository secret**.

Crie exatamente estes 4 secrets:

| Secret | Valor |
|---|---|
| `SSH_PRIVATE_KEY` | O conteúdo completo de `~/.ssh/macaybas_deploy` (a chave **privada**, inclusive as linhas `-----BEGIN OPENSSH PRIVATE KEY-----` e `-----END OPENSSH PRIVATE KEY-----`) |
| `SSH_HOST` | `147.93.14.208` |
| `SSH_PORT` | `65002` |
| `SSH_USER` | `u931382046` |

---

## PASSO 4 — Preparar a estrutura no servidor (one-shot)

Copie o script para o servidor e execute:

```bash
scp -P 65002 -i ~/.ssh/macaybas_deploy \
    scripts/first-deploy.sh \
    u931382046@147.93.14.208:~/first-deploy.sh

ssh -p 65002 -i ~/.ssh/macaybas_deploy u931382046@147.93.14.208 'bash ~/first-deploy.sh'
```

Isso cria em `/home/u931382046/domains/fazendamacaybas.com.br/`:

- `releases/` — onde cada deploy é extraído
- `artifacts/` — tarballs enviados pelo Actions
- `shared/storage/` — uploads persistentes
- `shared/scripts/` — onde ficará o `activate.sh`
- `backups/` — para dumps do MySQL

---

## PASSO 5 — Enviar o `.env` de produção

**Atenção:** este é o único arquivo sensível que vai manualmente pro servidor. Todos os outros secretos (DB, SMTP, chave SSH) estão nele.

```bash
scp -P 65002 -i ~/.ssh/macaybas_deploy \
    .env.production \
    u931382046@147.93.14.208:/home/u931382046/domains/fazendamacaybas.com.br/shared/.env

ssh -p 65002 -i ~/.ssh/macaybas_deploy u931382046@147.93.14.208 \
    'chmod 600 /home/u931382046/domains/fazendamacaybas.com.br/shared/.env'
```

---

## PASSO 6 — Copiar os scripts de deploy para o servidor

```bash
scp -P 65002 -i ~/.ssh/macaybas_deploy \
    scripts/activate.sh scripts/rollback.sh scripts/backup-db.sh \
    u931382046@147.93.14.208:/home/u931382046/domains/fazendamacaybas.com.br/shared/scripts/

ssh -p 65002 -i ~/.ssh/macaybas_deploy u931382046@147.93.14.208 \
    'chmod +x /home/u931382046/domains/fazendamacaybas.com.br/shared/scripts/*.sh'
```

---

## PASSO 7 — Gerar APP_KEY do Laravel

Pela primeira vez, o `.env` em `shared/` está sem `APP_KEY`. Gere uma:

```bash
# No seu PC, só para gerar a string (não precisa do Laravel rodar localmente):
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
# Ou qualquer gerador de chave 32 bytes base64.
```

Cole o resultado no `.env` do servidor:

```bash
ssh -p 65002 -i ~/.ssh/macaybas_deploy u931382046@147.93.14.208
nano /home/u931382046/domains/fazendamacaybas.com.br/shared/.env
# Edite APP_KEY= para: APP_KEY=base64:XXXX...
# Ctrl+O, Enter, Ctrl+X
exit
```

---

## PASSO 8 — PRIMEIRO PUSH → PRIMEIRO DEPLOY

No seu PC, inicialize o repositório, adicione tudo, faça o primeiro commit e push:

```bash
cd "D:/Sites/Fazenda Macaybas/Sistema"
git init
git remote add origin git@github.com:jhonatangaldino/fazendamacaybas.git
git branch -M main
git add .
git commit -m "feat: bootstrap inicial do sistema Fazenda Macaybas"
git push -u origin main
```

O **GitHub Actions** vai:

1. Instalar PHP 8.2 + Composer
2. Rodar `composer install --no-dev --optimize-autoloader`
3. Instalar Node 20 + `npm ci && npm run build`
4. Gerar tarball `release-<timestamp>.tar.gz`
5. Fazer rsync pro servidor
6. Executar `activate.sh` remotamente — que extrai, roda migrations/seeders, troca o symlink
7. Fazer health check em `https://fazendamacaybas.com.br/up`

Acompanhe o progresso em **GitHub → Actions → Deploy to Hostinger**.

---

## PASSO 9 — Configurar cron jobs no Hostinger

**hPanel → Advanced → Cron Jobs → Add New**:

### Cron 1 — Scheduler do Laravel (a cada minuto)
```
* * * * * cd /home/u931382046/domains/fazendamacaybas.com.br/releases/current && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

### Cron 2 — Backup diário do MySQL (3h da manhã)
```
0 3 * * * /home/u931382046/domains/fazendamacaybas.com.br/shared/scripts/backup-db.sh >> /home/u931382046/domains/fazendamacaybas.com.br/backups/backup.log 2>&1
```

---

## PASSO 10 — Testes pós-deploy

Abra no navegador:

1. **Site público**: https://fazendamacaybas.com.br
   - Deve mostrar a landing com hero, sobre, galeria, depoimentos, newsletter, footer
2. **Health check**: https://fazendamacaybas.com.br/up — retorna 200 OK
3. **Status**: https://fazendamacaybas.com.br/health — retorna JSON com timezone, locale, versão
4. **Login**: https://fazendamacaybas.com.br/login
   - Entre com `Jhonatan_freitas_galdino@hotmail.com` / `Jhonatan431994@`
   - Deve cair no Dashboard
5. **Logout** e login como Dono (`antonio.galdino90@gmail.com` / `MudarNoPrimeiroLogin@2026`)
   - Força troca de senha
6. **CMS**: editar uma seção, publicar, verificar mudança no site público

---

## Deploys subsequentes — automáticos

A partir daqui, qualquer `git push origin main` dispara o pipeline completo (2–3 min).

Para rodar manualmente fora de push (ex: re-deploy sem commit): **Actions → Deploy to Hostinger → Run workflow**.

---

## Rollback

Se algo quebrar em produção, rollback em 1 segundo:

```bash
ssh -p 65002 -i ~/.ssh/macaybas_deploy u931382046@147.93.14.208
bash /home/u931382046/domains/fazendamacaybas.com.br/shared/scripts/rollback.sh
# aponta automaticamente para a release anterior
```

Ou para uma release específica:

```bash
bash /home/u931382046/domains/fazendamacaybas.com.br/shared/scripts/rollback.sh 20260422143500
```

---

## Solução de problemas

### "Permission denied (publickey)"
- Confirme que a chave pública está em **hPanel → SSH Keys**, não em outro lugar.
- Confirme que `SSH_PRIVATE_KEY` no GitHub contém **toda** a chave privada, incluindo as linhas BEGIN/END.

### "php: command not found" no activate.sh
- O Hostinger às vezes chama o binário `php` de forma diferente. Se acontecer, editar `activate.sh` e trocar `php` por `/usr/bin/php` ou `php8.2`.

### "SQLSTATE[HY000] [2002]"
- Verifique que `DB_HOST=srv1885.hstgr.io` está correto no `.env` do servidor.
- No Hostinger Business, o host NÃO é `localhost` — é o hostname do MySQL remoto interno.

### "The stream or file storage/logs/laravel.log could not be opened"
- Permissões quebradas:
  ```bash
  chmod -R 775 /home/u931382046/domains/fazendamacaybas.com.br/shared/storage
  ```

### Site mostra página "Welcome" do Laravel em vez da landing
- O cache pode estar desatualizado. No SSH:
  ```bash
  cd /home/u931382046/domains/fazendamacaybas.com.br/releases/current
  php artisan optimize:clear
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  ```
