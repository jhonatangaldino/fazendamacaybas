# Sistema Fazenda Macaybas

Sistema integrado de **landing page pública + ERP da fazenda + CMS administrativo**, em um único monolito Laravel, hospedado no Hostinger Business.

- **Stack:** Laravel 11 (PHP 8.2+) · Inertia.js · Vue 3 · TailwindCSS · MySQL · Vite
- **Padrão:** 100% pt-BR, timezone `America/Sao_Paulo` (UTC-3), moeda BRL, máscaras brasileiras em todos os inputs
- **Deploy:** GitHub Actions → SSH/rsync → Hostinger (releases atômicas com symlink)

## Estrutura

```
app/
├── Console/Commands/            # php artisan macaybas:reset-admin
├── Http/
│   ├── Controllers/
│   │   ├── Admin/               # área logada (Dashboard, CMS, Financeiro, Rebanho, etc)
│   │   ├── Auth/                # login, logout, forgot/reset password
│   │   └── SiteController.php   # landing pública + newsletter + contato
│   ├── Middleware/              # SetLocaleTimezone, HandleInertiaRequests
│   └── Requests/
├── Models/
│   ├── Agricultural/            # Field, Crop, Planting, Harvest, FieldApplication
│   ├── Cms/                     # Page, Section, Menu, MenuItem
│   ├── Document/                # Document, DocumentCategory
│   ├── Financial/               # FinancialAccount, FinancialTransaction, etc.
│   ├── Livestock/               # Animal, AnimalEvent, AnimalLot, AnimalSpecies
│   ├── Stock/                   # Warehouse, StockItem, StockMovement
│   ├── Task/                    # Task, Checklist
│   ├── Vehicle/                 # Vehicle, MaintenanceOrder
│   ├── User.php · Farm.php · Partner.php · Employee.php · Category.php · Setting.php
├── Rules/                       # Cpf, Cnpj, TelefoneBr, Cep
└── Support/helpers.php          # brl(), dataBR(), cpfMask(), etc.

database/
├── migrations/                  # 0001 users + 2024 módulos
└── seeders/                     # Roles, AdminMaster, DonoFazenda, CmsPage, etc.

resources/
├── css/{app,site}.css
├── js/
│   ├── Components/              # DataTable, InputMasked, InputMoney, InputDate, etc.
│   ├── Layouts/{AuthLayout, AdminLayout}.vue
│   ├── Pages/                   # páginas Inertia (Dashboard, Users, Cms, Financial, Livestock)
│   ├── app.js · site.js         # entrypoints Vite
│   └── utils/format.js
└── views/site/                  # landing Blade (home, layouts, partials, sections)

routes/
├── web.php                      # rotas públicas + admin
└── console.php                  # scheduler

scripts/                         # first-deploy.sh, activate.sh, rollback.sh, backup-db.sh
.github/workflows/               # deploy.yml, ci.yml
```

## Perfis (RBAC)

- **admin_master** — Jhonatan (controle total)
- **dono_fazenda** — Antonio Galdino (operação completa, sem admin)
- **gerente**, **financeiro**, **veterinario**, **agronomo**, **administrativo**, **funcionario**, **auditor**, **visitante**

## Comandos úteis

```bash
# Desenvolvimento local (precisa de PHP 8.2+ e Node 20+)
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve

# Reset admin master (via SSH em produção)
php artisan macaybas:reset-admin --email=Jhonatan_freitas_galdino@hotmail.com

# Build de produção
npm run build
```

## Deploy

Ver **[docs/GUIA-DEPLOY.md](docs/GUIA-DEPLOY.md)** para o fluxo completo do primeiro deploy (geração de chave SSH, secrets no GitHub, execução inicial) e deploys subsequentes automáticos.

## Segurança

- `.env` **nunca** é versionado. O servidor tem um único `.env` em `shared/.env` que persiste entre releases.
- Chave SSH dedicada ao deploy, separada da chave pessoal.
- HTTPS forçado no `.htaccess` + Hostinger.
- Sessões com `Secure + HttpOnly`.
- Rate limiting no login (5 tentativas por minuto).
- CSRF em todos os POSTs.
- Audit log via `spatie/laravel-activitylog`.

## Licença

Proprietário — Fazenda Macaybas.
