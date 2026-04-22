# Checklist do Primeiro Deploy

Acompanhamento passo-a-passo. Marque [x] à medida que concluir.

## ☁️ Pré-deploy

- [ ] **Chave SSH gerada** (`ssh-keygen -t ed25519 -C "deploy-macaybas" -f ~/.ssh/macaybas_deploy -N ""`)
- [ ] **Chave pública no Hostinger** (hPanel → SSH Access → SSH Keys)
- [ ] **Teste de conexão OK** (`ssh -i ~/.ssh/macaybas_deploy -p 65002 u931382046@147.93.14.208 'echo ok'`)

## 🔐 GitHub Secrets

- [ ] `SSH_PRIVATE_KEY` (conteúdo completo de `~/.ssh/macaybas_deploy`)
- [ ] `SSH_HOST` = `147.93.14.208`
- [ ] `SSH_PORT` = `65002`
- [ ] `SSH_USER` = `u931382046`

## 🖥️ Servidor — estrutura inicial

- [ ] `first-deploy.sh` executado (criou `releases/`, `shared/`, `artifacts/`, `backups/`)
- [ ] `.env.production` enviado para `shared/.env` (chmod 600)
- [ ] `APP_KEY` gerada e colocada no `.env` do servidor
- [ ] `activate.sh`, `rollback.sh`, `backup-db.sh` enviados para `shared/scripts/` (chmod +x)

## 🚀 Primeiro deploy

- [ ] `git init` no projeto local
- [ ] `git remote add origin git@github.com:jhonatangaldino/fazendamacaybas.git`
- [ ] `git add . && git commit -m "feat: bootstrap"`
- [ ] `git push -u origin main`
- [ ] Workflow **Deploy to Hostinger** passou verde em Actions
- [ ] Health check `https://fazendamacaybas.com.br/up` retornou 200
- [ ] Landing carrega corretamente em `https://fazendamacaybas.com.br`

## ⏱️ Cron jobs

- [ ] Scheduler Laravel (`* * * * * ... schedule:run`)
- [ ] Backup MySQL diário (`0 3 * * * ... backup-db.sh`)

## ✅ Testes funcionais

- [ ] Login como **Admin Master** (Jhonatan) → entra no Dashboard
- [ ] Logout
- [ ] Login como **Dono da Fazenda** (Antonio) → força troca de senha → nova senha pessoal → Dashboard
- [ ] CMS: editar seção Hero → salvar rascunho → publicar → ver mudança no site público
- [ ] Usuários: criar um terceiro usuário com perfil "funcionario" → login
- [ ] Financeiro: criar uma receita e uma despesa → ver no fluxo de caixa
- [ ] Rebanho: cadastrar um animal → aparece na lista
- [ ] Parceiros: cadastrar um fornecedor PJ
- [ ] Newsletter: inscrever um e-mail no site → chega e-mail para a fazenda
- [ ] Contato: enviar mensagem do site → chega e-mail

## 🔒 Segurança

- [ ] HTTPS forçado (abrir `http://fazendamacaybas.com.br` redireciona para `https://`)
- [ ] Cookies `Secure + HttpOnly` (verificar em DevTools → Application → Cookies)
- [ ] Rate limit no login (6ª tentativa bloqueia por 60s)
- [ ] `.env` **não** aparece em `https://fazendamacaybas.com.br/.env` (deve dar 404)
- [ ] Área `/admin` redireciona para `/login` quando deslogado

## 📈 Performance

- [ ] Lighthouse na landing: Performance ≥ 85, SEO ≥ 90, Best Practices ≥ 90
- [ ] Tempo de primeira resposta (TTFB) < 1s

## 📝 Documentação repassada

- [ ] [README.md](../README.md) lido
- [ ] [GUIA-DEPLOY.md](GUIA-DEPLOY.md) lido
- [ ] Credenciais de produção guardadas em local seguro (ex: 1Password / Bitwarden)
- [ ] `.env.production` local está no `.gitignore` (confirmado: `git status` não lista)

## 🎯 Pronto para operar

Quando todos os itens acima estiverem marcados, o sistema está em produção, funcional e pronto para uso diário do Jhonatan e do Antonio.
