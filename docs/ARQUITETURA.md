# Arquitetura — Fazenda Macaybas

## Visão geral

Monolito Laravel 11 servindo três superfícies distintas em um único domínio:

```
┌─────────────────────────────────────────────────────────┐
│         fazendamacaybas.com.br (único domínio)          │
├─────────────────────────────────────────────────────────┤
│  /                → Landing pública (Blade + Tailwind)  │
│  /login           → Autenticação (Inertia + Vue)        │
│  /admin/*         → ERP + CMS (Inertia + Vue)           │
│  /storage/*       → Uploads (symlink para shared/)      │
│  /build/*         → Assets compilados pelo Vite         │
└─────────────────────────────────────────────────────────┘
```

## Decisões arquiteturais

### Por que Laravel 11 + Inertia + Vue?
- **PHP é nativo no Hostinger Business** — máxima compatibilidade, zero processos extras.
- **Inertia.js** elimina a necessidade de API REST separada: controllers Laravel retornam páginas Vue com props tipadas.
- **Blade para a landing** dá SEO perfeito (SSR nativo) e cache fácil; Inertia/Vue só nas telas autenticadas (onde interatividade importa).
- **Um único deploy** — sem dois artefatos, sem duas URLs.

### Por que releases atômicas com symlink?
- Evita janela de downtime onde o site fica "meio atualizado".
- Rollback é instantâneo: basta reapontar o symlink.
- O usuário nunca vê erros de classe não encontrada durante o deploy.

### Por que `queue:database` e `cache:file` em vez de Redis?
- Hostinger Business compartilhado **não tem Redis persistente**.
- Para o volume esperado (fazenda de pequeno/médio porte, < 100 usuários), `database` e `file` são mais que suficientes.
- Facilmente migrável para Redis no futuro (basta trocar `.env`).

## Fluxo de uma requisição

### Landing pública
```
Browser → Apache → public/index.php → Laravel → SiteController::home()
       → Page + Sections (query MySQL cacheada) → Blade render → resposta
```

### Área admin
```
Browser → /admin/dashboard → auth middleware → permission middleware
       → DashboardController → Inertia::render('Admin/Dashboard', $data)
       → app.blade.php (shell) + Vue 3 hidrata → resposta
```

### Deploy
```
git push main → GitHub Actions → composer install + npm build
             → tar.gz → rsync SSH → activate.sh remoto
             → php artisan migrate/cache → symlink swap → health check
```

## Modelagem do banco

~40 tabelas organizadas em 9 grupos (ver migrations em `database/migrations/`):

1. **Core auth** — users, password_reset_tokens, sessions, cache, jobs
2. **RBAC** — permissions, roles, model_has_permissions, model_has_roles, role_has_permissions
3. **CMS** — cms_pages, cms_sections, cms_menus, cms_menu_items, settings, media
4. **Auditoria** — activity_log
5. **Fazenda base** — farms, partners, employees, categories, cost_centers
6. **Financeiro** — financial_accounts, financial_transactions, financial_transaction_attachments, financial_recurrences
7. **Rebanho** — animal_species, animal_breeds, animal_lots, animals, animal_events
8. **Agrícola** — fields, crops, seasons, plantings, harvests, field_applications
9. **Estoque / Máquinas / Tarefas / Documentos** — warehouses, stock_items, stock_movements, vehicles, maintenance_orders, tasks, task_assignments, checklists, checklist_items, document_categories, documents

Estratégia: `softDeletes` em entidades principais (users, animals, transactions, etc.), FKs com `restrictOnDelete` em cascatas críticas (contas → transações) e `nullOnDelete` onde a relação é opcional (partner, category).

## Autorização

Usamos `spatie/laravel-permission` com guard `web`. Permissões são nomeadas no padrão `<módulo>.<recurso>.<ação>`:

```
dashboard.view
users.create / users.update / users.delete / users.reset_password
financeiro.transacoes.view / create / update / delete
cms.publish
```

Cada rota tem `->middleware('permission:...')`. Na UI, `auth.user.permissions` é compartilhado via Inertia para esconder/mostrar botões conforme o perfil.

## Padrão pt-BR

Implementado em 4 camadas:

1. **Config** — `timezone`, `locale`, `fallback_locale` em `config/app.php` e `.env`.
2. **MySQL** — conexão força offset `-03:00` (`config/database.php`).
3. **Middleware `SetLocaleTimezone`** — redundância defensiva em toda request.
4. **Helpers globais** — `brl()`, `dataBR()`, `dataHoraBR()`, `cpfMask()`, `cnpjMask()`, `telefoneMask()`, `cepMask()`, `placaMask()`.
5. **Regras de validação** — `App\Rules\Cpf`, `Cnpj`, `TelefoneBr`, `Cep`.
6. **Mensagens pt-BR** — `lang/pt_BR/validation.php`, `auth.php`, `passwords.php`, `pagination.php`, `messages.php`.
7. **Front-end** — `maska` (máscaras), `dayjs` com locale pt-br, helpers em `resources/js/utils/format.js`.

## Observabilidade

- **Logs** — `storage/logs/laravel.log` (rotação diária em produção).
- **Auditoria** — `activity_log` via Spatie (90 dias de retenção, purge via scheduler).
- **Health check** — `/up` (Laravel) + `/health` (versão + timezone + locale).

## Expansão futura (sprints 2+)

Módulos com tabelas e models prontos, apenas UI pendente:

- Agrícola — talhões, plantios, colheitas, aplicações
- Estoque — itens, movimentos, transferências, alertas
- Máquinas — veículos, manutenções
- Funcionários — cadastro detalhado, contratos
- Tarefas — agenda, atribuições, checklists
- Documentos — categorização, upload, tags, vencimento
- Relatórios — exports PDF/Excel por período
