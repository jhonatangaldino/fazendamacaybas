# Fazenda Macaybas — FASE 1: Diagnóstico, Arquitetura, Deploy e Autorizações

> Documento operacional. Nenhuma linha de código é escrita antes de encerrar a FASE 1.
> Padrão obrigatório: **pt-BR, timezone `America/Sao_Paulo` (UTC−3), moeda BRL, máscaras de CPF/CNPJ/telefone/CEP/placa/valor/data em todos os inputs.**

---

## 1. RESUMO EXECUTIVO

### Entendimento do projeto
Transformar `fazendamacaybas.com.br` — hoje uma landing template incompleta — em um **ecossistema unificado** com:

- **Site público** (landing page institucional)
- **Área logada / ERP da fazenda** (financeiro, rebanho, agrícola, estoque, máquinas, funcionários/tarefas, documentos, relatórios)
- **CMS administrativo** que permite editar textos, imagens, banners, seções, galeria, menus e rodapé da landing sem mexer em código

Tudo em **um único repositório, um único deploy, um único domínio**, hospedado no plano **Hostinger Business** já existente, consumindo o **MySQL já criado**, com **deploy automático a cada `git push` na `main`**.

### Objetivo real
Entregar um sistema **em produção**, estável, mantível e expansível, que preserve o esqueleto visual da landing atual e atenda os perfis Admin Master (você), Dono da Fazenda (seu pai), Funcionários e perfis futuros parametrizáveis (Gerente, Veterinário, Agrônomo, Financeiro, Administrativo, Auditor, Visitante).

### Resultado esperado
- `fazendamacaybas.com.br` → landing pública editável via CMS
- `fazendamacaybas.com.br/admin` → área logada (ERP + CMS)
- Push na `main` → site atualizado em 2–3 minutos, sem downtime
- 100% pt-BR: moeda, data, máscaras, validações, mensagens de erro

### Visão geral da solução
**Monolito Laravel 11 (PHP 8.2) + Inertia.js + Vue 3 + TailwindCSS + MySQL**, com deploy via **GitHub Actions → SSH/rsync → Hostinger Business** usando estratégia de **releases atômicas com symlink** (zero downtime).

---

## 2. PREMISSAS ASSUMIDAS

| # | Premissa | Status | Como validar |
|---|---|---|---|
| 1 | Plano é **Hostinger Business** (inclui SSH, Git, cron, Composer) | ⚠️ A confirmar | hPanel → Home (topo mostra o plano) |
| 2 | MySQL **já criado** com credenciais conhecidas ou recuperáveis | ⚠️ Pendente | hPanel → Databases |
| 3 | Domínio apontado para o Hostinger | ✅ Aparente (site responde) | `dig fazendamacaybas.com.br` |
| 4 | Repositório `github.com/jhonatangaldino/fazendamacaybas` está vazio ou só com landing-template | ⚠️ A confirmar | abrir URL |
| 5 | Você é o único admin do GitHub e do Hostinger | ✅ Assumido | — |
| 6 | PHP ≥ 8.2 disponível | ⚠️ A confirmar | hPanel → PHP Configuration |
| 7 | Locale pt-BR, timezone `America/Sao_Paulo`, moeda BRL, máscaras obrigatórias | ✅ Requisito firme | — |
| 8 | Primeiro deploy pode ser manual (para calibrar o pipeline) | ✅ OK | — |
| 9 | E-mails transacionais usarão SMTP do Hostinger inicialmente | ✅ OK | — |
| 10 | Uploads vão para filesystem do Hostinger (`storage/`), sem S3 | ✅ OK | — |
| 11 | Sem necessidade de Websocket real-time na v1 | ✅ OK | — |

Qualquer premissa marcada ⚠️ precisa ser validada antes da FASE 2. O **Passo 7** desta fase (checklist) cobre isso.

---

## 3. DIAGNÓSTICO DO AMBIENTE

### 3.1 — Hostinger Business
**Capacidades confirmadas pelo plano Business (documentação oficial do Hostinger):**
- PHP 7.4 → 8.3 selecionável por domínio, com OPcache.
- MySQL com phpMyAdmin, backups diários automáticos.
- **Acesso SSH** (porta custom, normalmente `65002`), com Composer e `artisan` rodando.
- **Cron jobs** via hPanel (indispensável para o scheduler do Laravel).
- SSL Let's Encrypt gratuito, renovação automática.
- Node.js disponível via "Gerenciador Node.js" — **útil apenas para build local no servidor, não para servir app**. Preferimos buildar no GitHub Actions.
- 100 GB NVMe, 100k visitas/mês — cabe com folga.

**Limitações confirmadas:**
- Sem acesso root, sem Docker, sem apt/yum.
- Timeout de PHP em requisições web: ~60s (ajustável até ~300s, mas não além).
- Memória por processo PHP: 256 MB padrão.
- Sem Redis/Memcached persistente (Laravel cache vai em `file` ou `database`).
- Apps Node persistentes (SSR) funcionam, mas são instáveis em shared — **não vamos usar**.
- Websockets não suportados nativamente (se precisar, via SaaS externo no futuro).

### 3.2 — GitHub privado
- Repo `jhonatangaldino/fazendamacaybas` já criado.
- Contas gratuitas têm **2.000 min/mês de GitHub Actions em repos privados**. Nosso pipeline consome ~3 min/deploy → suficiente para ~600 deploys/mês.
- Secrets: até 100 por repo, suficiente.
- Branch `main` será a branch de produção; deploy dispara nela.

### 3.3 — MySQL
- Já existe um banco criado. Precisamos confirmar: nome, usuário, senha, host, charset (padrão Hostinger é `utf8mb4_unicode_ci` — ideal).
- Se o banco estiver vazio → migrations do Laravel criam tudo.
- Se já tiver tabelas → precisamos decidir: migrar dados ou recriar (dump primeiro, análise depois).

### 3.4 — Viabilidade de automação de deploy
**Altíssima**, desde que o plano confirme SSH. Pipeline proposto roda em 2–3 min, com rollback instantâneo (basta re-apontar o symlink). Se SSH não estiver disponível, temos **Plano B** (seção 9).

### 3.5 — Riscos iniciais mapeados
Ver seção 9 (Riscos e Plano B) para o tratamento.

---

## 4. ARQUITETURA MAIS REALISTA PARA ESTE CENÁRIO

### 4.1 — Stack escolhida
| Camada | Tecnologia | Justificativa |
|---|---|---|
| **Backend** | Laravel 11 (PHP 8.2+) | Hostinger Business roda PHP estaticamente estável; Laravel cobre auth, RBAC, migrations, queue, storage, locale pt-BR, tudo nativo |
| **Frontend admin** | Inertia.js + Vue 3 + TypeScript | SPA-like sem SPA duplo; single repo; manutenção simples |
| **Frontend landing** | Blade + Tailwind (SSR puro) | SEO nativo, carregamento rápido, fácil de editar via CMS |
| **Estilo** | TailwindCSS + Vite | Build estático, só CSS/JS compilado vai pro servidor |
| **Banco** | MySQL 8 (existente) | Requisito firme do cenário |
| **Auth/RBAC** | laravel/breeze + spatie/laravel-permission | Padrão maduro, RBAC granular por perfil |
| **CMS** | Módulo próprio + tiptap (editor WYSIWYG) + spatie/laravel-medialibrary | Edição de blocos/seções/galeria/menus com rascunho→publicado |
| **Upload/imagens** | Intervention/Image + GD | Redimensionamento, thumbs, webp |
| **Filas** | driver `database` | Sem Redis no Hostinger; fila em tabela MySQL é suficiente |
| **Logs/Auditoria** | spatie/laravel-activitylog | Audita quem mexeu em quê |
| **i18n/pt-BR** | laravel-lang/common + helpers próprios | Traduções + helpers de data/moeda/máscara |
| **Testes** | Pest | Sintaxe limpa, mais produtiva |

### 4.2 — Por que essa stack é adequada
- **100% compatível com Hostinger Business** (PHP + MySQL + static assets).
- **Um único deploy** (sem dois processos, sem API separada).
- **Build once, run cheap**: Vite compila no Actions; servidor só entrega HTML/CSS/JS.
- **Auth, RBAC, upload, validação, filas, scheduler** — Laravel entrega tudo sem reinventar.
- **Manutenção futura**: a comunidade Laravel é enorme; qualquer dev PHP consegue evoluir.

### 4.3 — O que evitar
- ❌ Next.js/Nuxt em SSR (Node persistente instável no Business)
- ❌ Microserviços, gRPC, filas externas (Redis/RabbitMQ)
- ❌ Docker no servidor
- ❌ Monorepo com múltiplos workspaces — overkill
- ❌ Headless CMS externo (Strapi/Directus) — mais uma peça para manter

### 4.4 — Como manter o esqueleto visual da landing
A landing atual tem: header → hero → sobre → banner → galeria → depoimentos → newsletter → rodapé.

No novo sistema, cada um desses blocos vira uma **Section** no CMS, com campos editáveis (título, subtítulo, imagens, CTAs, ordem, visibilidade). A primeira versão do template reproduz o layout atual pixel-próximo, apenas com marcações Blade de componente:

```
resources/views/site/
├── layouts/public.blade.php
├── sections/
│   ├── header.blade.php
│   ├── hero.blade.php
│   ├── about.blade.php
│   ├── gallery.blade.php
│   ├── testimonials.blade.php
│   ├── newsletter.blade.php
│   └── footer.blade.php
└── home.blade.php
```

O CMS permite: editar conteúdo de cada seção, ativar/desativar, reordenar via drag-and-drop, salvar rascunho, publicar.

### 4.5 — Padrão pt-BR (obrigatório em tudo)
- `config/app.php`: `'timezone' => 'America/Sao_Paulo'`, `'locale' => 'pt_BR'`, `'fallback_locale' => 'pt_BR'`.
- `.env`: `APP_TIMEZONE=America/Sao_Paulo`, `APP_LOCALE=pt_BR`.
- MySQL: `timezone '-03:00'` forçado na conexão via `options` do config/database.
- Helpers globais:
  - `brl($valor)` → `R$ 1.234,56`
  - `dataBR($carbon)` → `22/04/2026`
  - `dataHoraBR($carbon)` → `22/04/2026 14:35`
  - `cpfMask`, `cnpjMask`, `telefoneMask`, `cepMask`, `placaMask`
- Front-end: componente Vue `<InputMasked>` reutilizável, usando `maska` (lib pura, 3KB, sem jQuery).
- Validação: regras customizadas `cpf`, `cnpj`, `telefone_br`, `cep`.
- Mensagens de erro: arquivo `lang/pt_BR/validation.php` completo.
- Moment/Day.js configurado para `pt-br` no front-end.

---

## 5. ESTRATÉGIA DE DEPLOY (DETALHADA)

### 5.1 — Fluxo escolhido
**GitHub Actions → SSH/rsync → Hostinger Business** com release atômica.

```
┌──────────────┐  git push main   ┌─────────────────────┐  rsync + ssh   ┌─────────────────────┐
│  Dev local   │ ───────────────▶ │   GitHub Actions    │ ─────────────▶ │  Hostinger Business  │
│  (seu PC)    │                  │  • PHP 8.2 setup    │                │                      │
└──────────────┘                  │  • composer install │                │  /domains/           │
                                  │  • npm ci + build   │                │   fazendamacaybas.   │
                                  │  • testes (opcional)│                │   com.br/            │
                                  │  • artefato tar.gz  │                │   ├── public_html    │
                                  │  • rsync ao servidor│                │   │    → symlink     │
                                  │  • ssh: migrate     │                │   │      releases/   │
                                  │  • ssh: cache warm  │                │   │      current/    │
                                  │  • ssh: swap symlink│                │   │      public      │
                                  └─────────────────────┘                │   ├── releases/      │
                                                                         │   │   └── <timestamp>│
                                                                         │   └── shared/        │
                                                                         │       ├── .env       │
                                                                         │       └── storage/   │
                                                                         └─────────────────────┘
```

### 5.2 — Por que este fluxo e não outros
| Abordagem | Prós | Contras | Decisão |
|---|---|---|---|
| **GitHub Actions + SSH/rsync** | Build em ambiente limpo, cache de deps, migrations automáticas, release atômica, rollback trivial | Exige SSH no plano | **✅ Escolhida** |
| Git Integration nativa do hPanel | 1 clique | **Não roda `composer install` nem `npm run build`** — só puxa o repo cru. Laravel/Vite inútil assim. | ❌ |
| FTP manual | Zero setup | Zero automação — contraria o objetivo | ❌ |
| Webhook + pull no servidor | Simples | Não roda build; precisaria commitar `vendor/` e `public/build/` (anti-padrão) | ❌ |
| Deployer PHP | Robusto | Mesma coisa que Actions + SSH, só que com outra sintaxe; preferimos Actions (dashboard visual, logs, retries) | ❌ |

### 5.3 — Estrutura de release atômica
```
/home/u<ID>/domains/fazendamacaybas.com.br/
├── public_html  →  releases/current/public    (symlink)
├── releases/
│   ├── 20260422143500/
│   ├── 20260422151200/
│   └── current  →  20260422151200             (symlink trocado atomicamente)
└── shared/
    ├── .env                                    (nunca versionado)
    └── storage/                                (uploads do CMS persistentes)
```

**Troca atômica**: o `mv -T` do symlink é instantâneo no Linux. Se o deploy novo falhar, `current` continua apontando para a release anterior. Site nunca fica "meio publicado".

### 5.4 — O que o workflow fará (resumo; código real na FASE 2)
1. Checkout
2. Setup PHP 8.2 + Composer (com cache)
3. `composer install --no-dev --optimize-autoloader --prefer-dist`
4. Setup Node 20 (com cache)
5. `npm ci && npm run build`
6. Empacotar artefato de produção (`tar.gz`)
7. Abrir túnel SSH com chave privada (secret)
8. Criar `releases/<timestamp>` no servidor
9. Subir artefato via `rsync`, extrair
10. Symlinkar `.env` → `shared/.env`, `storage/` → `shared/storage/`
11. Rodar `php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache`, `event:cache`
12. Trocar symlink `releases/current`
13. Reiniciar PHP-FPM (`touch` em um arquivo que o Hostinger vigia) ou aguardar OPcache invalidar
14. Manter últimas 5 releases; remover antigas
15. Notificar status (Actions UI)

### 5.5 — Proteções
- Secrets: `SSH_PRIVATE_KEY`, `SSH_HOST`, `SSH_USER`, `SSH_PORT` ficam em Repo Settings → Secrets.
- Workflow dispara **apenas em `main`**. Outras branches rodam só CI (lint + testes).
- Proteção de branch recomendada: PR obrigatório + status check verde antes de merge.
- Backup automático do banco via cron (`mysqldump`) antes de cada migration em produção (safety net extra).

---

## 6. LISTA EXATA DE AUTORIZAÇÕES / ACESSOS / CREDENCIAIS

> Organizado por sistema. Cada item indica **o que preciso receber de você**.

### 🔑 Bloco A — GitHub
- [ ] **A1.** Confirmar acesso admin ao repo `github.com/jhonatangaldino/fazendamacaybas`.
- [ ] **A2.** Permissão para criar Secrets no repositório (você mesmo faz; só preciso saber que consegue):
  - `SSH_PRIVATE_KEY`
  - `SSH_HOST`
  - `SSH_USER`
  - `SSH_PORT`
  - `DEPLOY_PATH` (caminho base no servidor)
- [ ] **A3.** Status do repositório: **vazio** ou com algum conteúdo? Se tiver, me mande print.
- [ ] **A4.** (Opcional mas recomendado) Branch protection na `main`: PR obrigatório, revisão, status checks.

### 🏠 Bloco B — Hostinger (hPanel)
- [ ] **B1.** Confirmação do **plano exato** (Business? Premium? Cloud Startup?).
- [ ] **B2.** **Ativar SSH Access** em *Advanced → SSH Access* e coletar:
  - SSH IP/Host
  - SSH Port (normalmente `65002`)
  - SSH Username (ex.: `u123456789`)
- [ ] **B3.** Versão de PHP ativa no domínio em *Advanced → PHP Configuration* — precisamos **≥ 8.2**.
- [ ] **B4.** Extensões PHP necessárias habilitadas: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `gd` **ou** `imagick`, `curl`, `zip`, `bcmath`, `intl`.
- [ ] **B5.** **Composer** disponível via SSH — vou te passar o comando para validar.
- [ ] **B6.** **Cron jobs** habilitados em *Advanced → Cron Jobs*.
- [ ] **B7.** Confirmar que tem permissão para criar symlinks via SSH (padrão no Hostinger Business).

### 💾 Bloco C — Banco MySQL
- [ ] **C1.** Enviar as 4 credenciais do banco criado:
  - Host (normalmente `localhost` ou `127.0.0.1`)
  - Nome do banco (ex.: `u123456789_macaybas`)
  - Usuário (ex.: `u123456789_app`)
  - Senha
- [ ] **C2.** Estado atual do banco: vazio ou com tabelas? Se tiver, mande dump.
- [ ] **C3.** Charset/collation (deve ser `utf8mb4` / `utf8mb4_unicode_ci`).
- [ ] **C4.** Confirmar que o usuário do banco tem permissões DDL (CREATE, ALTER, DROP) — essencial para migrations.

### 🌐 Bloco D — Domínio / DNS / SSL
- [ ] **D1.** Confirmar que `fazendamacaybas.com.br` aponta para o Hostinger (aparentemente OK).
- [ ] **D2.** Verificar cadeado SSL. Se não tiver, ativar Let's Encrypt em *Security → SSL*.
- [ ] **D3.** Decidir URL do admin:
  - (A) **`fazendamacaybas.com.br/admin`** — recomendado, sem config extra ✅
  - (B) `admin.fazendamacaybas.com.br` — precisa criar subdomínio
- [ ] **D4.** Forçar HTTPS no hPanel (*Security → Force HTTPS*).

### 📧 Bloco E — E-mail transacional
- [ ] **E1.** Criar/confirmar conta `sistema@fazendamacaybas.com.br` em *Emails → Email Accounts*.
- [ ] **E2.** Coletar credenciais SMTP:
  - Host SMTP (normalmente `smtp.hostinger.com`)
  - Porta (`465` SSL **ou** `587` TLS)
  - Usuário (o e-mail completo)
  - Senha

### 🔐 Bloco F — Chave SSH dedicada ao deploy
Vou te guiar passo a passo na FASE 2. Resumo:
- [ ] **F1.** Gerar par `ed25519` (comando que te passo).
- [ ] **F2.** Colar **chave pública** no Hostinger (*SSH Access → SSH Keys → Add new*).
- [ ] **F3.** Colar **chave privada** no GitHub (*Settings → Secrets → New repository secret → `SSH_PRIVATE_KEY`*).
- [ ] **F4.** Testar conexão: `ssh -i ~/.ssh/macaybas_deploy -p <porta> <user>@<host>`.

### 🧩 Bloco G — Variáveis de ambiente (`.env` no servidor)
Vão uma única vez em `/home/u<ID>/domains/.../shared/.env`. **Nunca** no Git. Na FASE 2 eu te entrego o template preenchido com placeholders; você só substitui os segredos:
- `APP_KEY` (gero via artisan)
- `APP_URL=https://fazendamacaybas.com.br`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_TIMEZONE=America/Sao_Paulo`
- `APP_LOCALE=pt_BR`
- `DB_CONNECTION=mysql` + `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD` (Bloco C)
- `MAIL_MAILER=smtp` + `MAIL_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION` (Bloco E)
- `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=file`
- `FILESYSTEM_DISK=public`

### 💻 Bloco H — Ambiente local (opcional)
Só se quiser rodar o sistema no seu PC antes de publicar. Não bloqueia o deploy.
- PHP 8.2+ (recomendo **Laragon** no Windows — vem com PHP, Apache, MySQL, Composer, Node prontos)
- Git (já tem ✅)
- Node 20+ (já tem v22 ✅)

---

## 7. PASSO A PASSO EXATO PARA VOCÊ

Execute na ordem. Cada passo tem: **onde clicar**, **o que coletar**, **como validar**, **o que me devolver**.

### PASSO 1 — Plano do Hostinger + PHP
1. `hpanel.hostinger.com` → selecione `fazendamacaybas.com.br`.
2. Veja o nome do plano no topo da Home.
3. Menu lateral → **Advanced → PHP Configuration**.
4. Se a versão for **< 8.2**, mude para **8.2** ou **8.3**. Salve.
5. **Validar**: a tela mostra "PHP version: 8.2.x".
6. **Me devolver**: nome do plano + versão do PHP.

### PASSO 2 — SSH ativado
1. hPanel → **Advanced → SSH Access**.
2. Clique em **Enable SSH Access**.
3. Copie os 3 valores que aparecem:
   - SSH IP/Host
   - SSH Port
   - SSH Username
4. Abra o **Git Bash** no Windows e teste: `ssh -p <porta> <user>@<host>` (use a senha do hPanel por agora).
5. Se logar, digite `pwd && php -v && composer --version` — guarde a saída.
6. Digite `exit`.
7. **Me devolver**: host, porta, user e o resultado dos 3 comandos. **Não me mande a senha** — vamos trocar por chave.

### PASSO 3 — MySQL
1. hPanel → **Databases → Management**.
2. Identifique o banco existente. Clique em **Enter phpMyAdmin** para confirmar que abre.
3. Anote: nome do banco, usuário, host (geralmente `localhost`).
4. Se não lembrar a senha, **Change password** e defina nova.
5. Em phpMyAdmin, verifique se o banco está vazio (sidebar esquerda, expandir o banco). Se tiver tabelas, use **Export → SQL** e me mande o dump.
6. **Me devolver**: nome, usuário, host, senha, estado (vazio ou com tabelas).

### PASSO 4 — SSL + HTTPS forçado
1. hPanel → **Security → SSL** → confirmar "Active".
2. hPanel → **Security → Force HTTPS** → ligar.
3. **Validar**: abra `http://fazendamacaybas.com.br` — deve redirecionar para `https://`.
4. **Me devolver**: status (OK ou erro).

### PASSO 5 — E-mail transacional
1. hPanel → **Emails → Email Accounts** → criar `sistema@fazendamacaybas.com.br` (ou use alguma existente).
2. Em **Configuration Settings** (ícone de engrenagem), copie host, porta, usuário, senha SMTP.
3. **Me devolver**: os 4 valores SMTP.

### PASSO 6 — Status do GitHub
1. Abra `github.com/jhonatangaldino/fazendamacaybas`.
2. Tire print da aba "Code" (raiz do repo).
3. **Me devolver**: o print + se você vê "This repository is empty" ou arquivos existentes.

### PASSO 7 — URL do admin
Escolha uma opção:
- (A) **`/admin`** (recomendado, zero DNS extra)
- (B) `admin.fazendamacaybas.com.br` (precisa criar subdomínio em *Domains → Subdomains*)

**Me devolver**: a escolha.

### ✅ Checklist final — copie, preencha e envie

```
1) PLANO HOSTINGER:   _____________________________
2) PHP VERSION:       _____________________________
3) SSH HOST:          _____________________________
4) SSH PORT:          _____________________________
5) SSH USER:          _____________________________
6) SAÍDA `php -v`:    _____________________________
7) SAÍDA `composer --version`: ____________________
8) DB HOST:           _____________________________
9) DB NAME:           _____________________________
10) DB USER:          _____________________________
11) DB PASS:          _____________________________
12) DB VAZIO?:        (sim / não — se não, dump anexado)
13) SSL + FORCE HTTPS: _____________________________
14) SMTP HOST:        _____________________________
15) SMTP PORT:        _____________________________
16) SMTP USER:        _____________________________
17) SMTP PASS:        _____________________________
18) STATUS DO REPO:   (vazio / com conteúdo — print anexado)
19) URL DO ADMIN:     (A: /admin   ou   B: subdomínio)
```

Após receber esse checklist preenchido, passo imediatamente para a **FASE 2**.

---

## 8. O QUE EU FAREI APÓS VOCÊ LIBERAR TUDO (FASES 2 → 7)

### FASE 2 — Bootstrap do projeto
1. `composer create-project laravel/laravel .` em `D:/Sites/Fazenda Macaybas/Sistema/`.
2. Configurar `.env.example` e `config/app.php` com timezone/locale pt-BR.
3. Instalar Inertia + Vue 3 + Breeze (starter kit Inertia-Vue).
4. Configurar Tailwind + PostCSS + Vite.
5. Instalar pacotes:
   - `spatie/laravel-permission` (RBAC)
   - `spatie/laravel-medialibrary` (uploads)
   - `spatie/laravel-activitylog` (auditoria)
   - `intervention/image` (thumbs)
   - `laravel-lang/common` (traduções pt-BR)
   - `maska` (máscaras Vue) no npm
   - `pestphp/pest` (testes)
6. Criar estrutura de pastas modular: `app/Modules/{Auth,Cms,Financeiro,Rebanho,Agricola,Estoque,Maquinas,Funcionarios,Documentos,Relatorios}`.
7. Primeiro commit na `main`.

### FASE 3 — Banco de dados (migrations + seeds)
Migrations em ordem:
1. **Acesso** — `users`, `password_resets`, `sessions`, `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`
2. **Configurações** — `settings` (chave-valor global)
3. **CMS** — `pages`, `sections`, `blocks`, `media`, `menus`, `menu_items`, `section_drafts`
4. **Fazenda base** — `farms`, `partners`, `employees`, `categories`, `cost_centers`
5. **Financeiro** — `financial_accounts`, `financial_transactions`, `financial_transaction_attachments`, `financial_recurrences`, `financial_budgets`
6. **Rebanho** — `animal_species`, `animal_breeds`, `animal_lots`, `animals`, `animal_events` (polimórfica: pesagem, vacina, medicação, reprodução, nascimento, morte, compra, venda, movimentação)
7. **Agrícola** — `fields` (talhões), `crops`, `seasons` (safras), `plantings`, `harvests`, `field_applications`
8. **Estoque** — `warehouses`, `stock_categories`, `stock_items`, `stock_movements`
9. **Máquinas** — `vehicles`, `maintenance_types`, `maintenance_orders`
10. **Tarefas** — `tasks`, `task_assignments`, `checklists`, `checklist_items`
11. **Documentos** — `document_categories`, `documents`
12. **Filas / Jobs** — `jobs`, `job_batches`, `failed_jobs`

Seeders:
- Usuário **Admin Master** (você) com senha temporária (troca no 1º login, 2FA opcional).
- Perfis iniciais: admin_master, dono_fazenda, funcionario, gerente, veterinario, agronomo, financeiro, administrativo, auditor, visitante.
- Permissões mapeadas por módulo (view, create, edit, delete, approve).
- Dados de exemplo da landing (copy atual do site para ficar idêntico no 1º deploy).

### FASE 4 — Autenticação, RBAC e layouts
- Login, logout, "esqueci senha" (envia SMTP), troca de senha obrigatória no 1º acesso do Admin Master.
- Middleware `role:admin_master|dono_fazenda`, `permission:financeiro.view`, etc.
- Layout público (Blade) reproduzindo o esqueleto da landing atual.
- Layout admin (Inertia/Vue) com: sidebar, topbar com avatar, breadcrumbs, notificações.
- Componentes Vue reutilizáveis: `<InputMasked>`, `<InputMoney>`, `<InputDate>`, `<DataTable>`, `<ConfirmModal>`, `<FileUpload>`.
- Toda validação com mensagens pt-BR; toda moeda exibida como `R$ 1.234,56`; toda data `dd/mm/aaaa` e `dd/mm/aaaa HH:MM`.

### FASE 5 — Módulos iniciais funcionais
- **Dashboard** — widgets: saldo financeiro do mês, vencimentos próximos, rebanho total, tarefas pendentes, alertas de estoque baixo, últimos lançamentos.
- **Usuários** — CRUD, atribuição de perfis, ativar/desativar.
- **CMS da Landing** — editar seções Hero, Sobre, Galeria, Depoimentos, Newsletter, Rodapé, Menu; fluxo rascunho → publicar; preview antes de publicar; reordenar via drag-drop.
- **Financeiro** — contas a pagar/receber com recorrência, fluxo de caixa, categorias, centro de custo, anexos.
- **Rebanho** — cadastro individual e por lote, eventos (pesagem/vacinação/medicação/reprodução), movimentações.
- **Demais módulos** — estrutura (migrations + models + rotas vazias + telas placeholder) pronta para evoluir em sprints seguintes.

### FASE 6 — CI/CD + Deploy
- `.github/workflows/deploy.yml` completo (fluxo da seção 5.4).
- `.github/workflows/ci.yml` para lint + testes Pest em PRs.
- Primeiro deploy: conduzo via terminal comigo acompanhando.
- Cron jobs no hPanel:
  - `* * * * * cd .../current && php artisan schedule:run`
  - `0 3 * * * cd .../current && mysqldump ... > backups/$(date +\%F).sql`

### FASE 7 — Validação final
- Smoke test em produção: site carrega, login funciona, admin master entra, CMS salva e publica, landing reflete a mudança, seed-user troca senha, logout, recuperação de senha envia e-mail.
- Performance: Lighthouse ≥ 85 em Performance/SEO/Best Practices na landing.
- Segurança: `https` forçado, cookies `Secure + HttpOnly + SameSite`, headers básicos (CSP, X-Frame-Options), upload com validação de mime e tamanho.
- Checklist final: sistema sobe, landing ok, login ok, CMS ok, banco ok, deploy ok, evolução futura documentada.

---

## 9. RISCOS E PLANO B

| # | Risco | Probabilidade | Impacto | Mitigação / Plano B |
|---|---|---|---|---|
| 1 | Plano for Premium (sem SSH) em vez de Business | Baixa | Alto | **Plano B1**: deploy via **Git Integration nativa do hPanel** + branch `build` no Actions que contém `vendor/` e `public/build/` pré-compilados. Migrations rodadas via phpMyAdmin importando SQL gerado localmente. Perda: zero-downtime. |
| 2 | PHP < 8.2 sem possibilidade de upgrade | Muito baixa | Alto | Descer para **Laravel 10** (PHP 8.1). Nada muda na arquitetura. |
| 3 | Extensão `gd`/`imagick` ausente | Baixa | Médio | Pedir ativação ao suporte (normalmente ativam em 1h) OU usar fallback sem resize no 1º release. |
| 4 | Banco já tem tabelas conflitantes | Média | Médio | Dump → análise → decidir migrar dados ou recriar (criar banco novo e apontar `.env`). |
| 5 | SMTP do Hostinger com limite baixo de envios | Média | Baixo | Migrar para **Resend** (3.000 grátis/mês) ou **Brevo** (9.000/mês grátis). Troca só em `.env`. |
| 6 | Timeout de 60s em relatórios pesados | Alta | Médio | Queue `database` + cron `schedule:run`. Relatório gera em background e notifica por e-mail quando pronto. |
| 7 | Erro no primeiro deploy tira o site do ar | Média (só 1º deploy) | Alto | Release atômica: se falhar, symlink `current` não troca. Backup manual da landing atual antes do 1º deploy (já há backup automático do Hostinger). |
| 8 | Secret (`SSH_PRIVATE_KEY`, `.env`) vazar no Git | Baixa | Crítico | `.gitignore` bloqueia `.env`, chaves, `storage/`. Chave SSH é **dedicada ao deploy**, não a sua pessoal. Pre-commit hook `gitleaks` opcional. |
| 9 | Você esquecer senha do Admin Master | Média | Médio | Comando artisan `php artisan macaybas:reset-admin` documentado, que recria o usuário com senha temporária via SSH. |
| 10 | Memória 256MB estoura em import massivo | Média | Médio | Chunking no `LazyCollection` + `DB::disableQueryLog()` em imports. |
| 11 | GitHub Actions exceder 2.000 min/mês grátis | Muito baixa | Baixo | Pipeline custa ~3 min/deploy → 600 deploys/mês. Se estourar: mover para `act` + runner self-hosted no Hostinger via SSH (complexo, não vale). |
| 12 | Perda de dados de upload em troca de release | Média se mal configurado | Alto | Symlink `storage/` → `shared/storage/` persiste entre releases. Coberto pela arquitetura. |
| 13 | Composer install no Actions lento | Média | Baixo | Cache de `vendor/` em `~/.cache/composer` via `actions/cache@v4`. |
| 14 | Rollback necessário | Baixa | Alto | Comando artisan `php artisan macaybas:rollback` que troca symlink `current` para release anterior em 1s. |

---

## 10. CRITÉRIOS PARA ENCERRAR A FASE 1

- [x] Diagnóstico do ambiente completo (seção 3)
- [x] Arquitetura definida e justificada (seção 4)
- [x] Estratégia de deploy detalhada com fluxo visual (seção 5)
- [x] Lista de autorizações por bloco (seção 6)
- [x] Passo a passo executável com validação (seção 7)
- [x] Plano das fases 2–7 (seção 8)
- [x] Riscos mapeados com plano B (seção 9)
- [x] Requisitos pt-BR (timezone UTC−3, moeda BRL, máscaras) incorporados em toda a stack (seção 4.5)

**Próxima ação sua**: preencher o checklist do passo 7 e me devolver. Assim eu inicio a FASE 2 sem retrabalho.
