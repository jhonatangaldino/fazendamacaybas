# FASES 3 + 4 + 5 · Arquitetura Final, Modelagem e UX

> **Complementa** [FASE-1-DIAGNOSTICO-SAAS.md](./FASE-1-DIAGNOSTICO-SAAS.md) (Fases 1 e 2).
> **Base congelada**: FASE 2 é lei — estruturas centrais (`animal_events`, `stock_movements`, `financial_transactions`, `animal_lots`) são reaproveitadas. Novas tabelas apenas as justificadas.
> **Status**: documento pronto para virar implementação. Aguarda 3 decisões finais (§ pendências) antes de iniciar FASE 6.
> **Data**: 2026-04-22

---

## Índice

- [FASE 3 — Arquitetura Final](#fase-3--arquitetura-final)
- [FASE 4 — Modelagem Final](#fase-4--modelagem-final)
- [FASE 5 — UX Detalhado tela a tela](#fase-5--ux-detalhado-tela-a-tela)
- [Validação final](#validação-final-das-fases-3--4--5)
- [Itens pendentes antes da FASE 6](#itens-pendentes-antes-da-fase-6-implementação)

---

# FASE 3 · Arquitetura Final

## 3.1 Camadas e responsabilidades

```
┌─────────────────────────────────────────────────────────────────┐
│ [1] FRONTEND Vue 3 + Inertia                                    │
│     Pages / Components / Composables / Utils                    │
│     UI kit: DataTable, ActionIcon, KpiDrawer, InputDate,        │
│     BarcodeScanner, AvatarUpload, ConfirmDialog, ToastContainer │
└───────────────────────────┬─────────────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────────────┐
│ [2] MIDDLEWARES (ordem em /admin/*)                             │
│     auth → ResolveTenant → EnforceFarm → EnforceSubscription    │
└───────────────────────────┬─────────────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────────────┐
│ [3] CONTROLLERS (magros — só validam + delegam + respondem)     │
└───────────────────────────┬─────────────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────────────┐
│ [4] SERVICES (regra de negócio por domínio — 10 serviços)       │
│     Livestock · Poultry · Aquaculture · Agriculture · Stock     │
│     Finance · Tenancy · Billing                                 │
└───────────────────────────┬─────────────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────────────┐
│ [5] MODELS + OBSERVERS                                          │
│     Trait BelongsToTenant (global scope) em todos os models     │
│     5 observers idempotentes via (source_type, source_id)       │
└───────────────────────────┬─────────────────────────────────────┘
                            │
┌───────────────────────────▼─────────────────────────────────────┐
│ [6] INFRAESTRUTURA                                              │
│     MySQL shared · storage/tenants/{id} · cache tenant:{id}:*   │
│     queue com tenant_id no payload · schedule por tenant        │
└─────────────────────────────────────────────────────────────────┘
```

## 3.2 Estrutura de diretórios (definitiva)

```
app/
├── Domain/
│   ├── Tenancy/
│   │   ├── Models/         Tenant.php, Farm.php (mantido em App\Models)
│   │   ├── Services/       TenantResolver.php, FarmSwitcher.php
│   │   ├── Middleware/     ResolveTenant.php, EnforceFarm.php, EnforceSubscription.php
│   │   ├── Scopes/         BelongsToTenantScope.php
│   │   └── Traits/         BelongsToTenant.php, InteractsWithTenant.php
│   ├── Livestock/
│   │   └── Services/       AnimalEventRegistrar.php, SellBatchService.php,
│   │                       ReproductiveStatusService.php
│   ├── Poultry/
│   │   └── Services/       EggCollectionService.php
│   ├── Aquaculture/
│   │   └── Services/       TankPanelService.php
│   ├── Agriculture/
│   │   └── Services/       PlantingCostAggregator.php, HarvestSaleService.php
│   ├── Stock/
│   │   └── Services/       ConsumeStockService.php
│   ├── Finance/
│   │   └── Services/       ReflectFinancialService.php
│   ├── Billing/
│   │   ├── Models/         Plan.php, Subscription.php, Invoice.php
│   │   └── Services/       BillingService.php, PixService.php
│   └── Observers/
│       AnimalEventObserver.php
│       StockMovementObserver.php
│       MaintenanceOrderObserver.php
│       VehicleEventObserver.php
│       HarvestSaleObserver.php
├── Http/Controllers/Admin/*   (existentes — refatoração magra)
├── Http/Controllers/Master/*  (novo namespace — master global)
├── Models/*                    (existentes + tenant_id via trait)
└── Services/BarcodeLookup/     (mantém como está)

config/
├── tenancy.php          (feature flags, paths)
└── billing.php          (planos, PIX)

resources/js/
├── Pages/
│   ├── Admin/*          (existente)
│   ├── Master/*         (novo)
│   └── Tenant/SelectFarm.vue, Subscription/Overdue.vue
└── Layouts/
    ├── AdminLayout.vue        (existente, estendido com seletor de fazenda)
    └── MasterLayout.vue       (novo)
```

## 3.3 Services (10 — fechados)

| # | Service | Responsabilidade única | Entrada | Saída |
|---|---|---|---|---|
| 1 | `TenantResolver` | Lê user+sessão, seta `tenant_id` e `current_farm_id` no container | Request | void |
| 2 | `FarmSwitcher` | Valida acesso e troca fazenda ativa na sessão | user, farm_id | bool |
| 3 | `AnimalEventRegistrar` | Entrada única para qualquer evento de `animal_events` | array payload | AnimalEvent |
| 4 | `SellBatchService` | Venda multi-animal contextual por profile (arroba/kg/un) | ids + cfg | Event + FinancialTransaction |
| 5 | `ReproductiveStatusService` | Atualiza `animals.status_reprodutivo` a partir do ciclo de eventos | animal, event | void |
| 6 | `EggCollectionService` | Registra postura + entrada automática em `stock_items` | lote, data, qtd, turno, classificação | AnimalEvent + StockMovement |
| 7 | `TankPanelService` | Retorna estado atual do tanque (peso médio, qtd, FCA, últimas leituras) | lot_id | array painel |
| 8 | `PlantingCostAggregator` | Soma custos acumulados por plantio | planting_id | array breakdown |
| 9 | `ReflectFinancialService` | Cria `FinancialTransaction` polimórfica idempotente | source, tipo, valor, partner | FinancialTransaction \| null |
| 10 | `ConsumeStockService` | Cria `StockMovement` saída polimórfica idempotente | source, item, qtd | StockMovement \| null |

**Billing** (isolado no namespace próprio):

| Service | Responsabilidade |
|---|---|
| `BillingService` | Emite fatura mensal, consulta status da assinatura |
| `PixService` | Gera QR + copia-cola, consulta pagamento via webhook |

## 3.4 Observers (5 — padrão único)

**Contrato comum a todos**:

```
- Dispara APENAS em `created` (nunca update — evita duplicação)
- Feature flag via settings: OBSERVER_FINANCE_REFLECT, OBSERVER_STOCK_CONSUME
- Idempotência: constraint unique em (reference_type, reference_id) nas tabelas destino
- Assinatura explícita: só age se valor > 0 e/ou stock_item_id != null
- Grava em activity_log com caused_by = "observer:<name>"
- Modo dry-run disponível para comando de backfill histórico
```

| Observer | Gatilho | Efeito |
|---|---|---|
| **AnimalEventObserver** | AnimalEvent::created | Se `valor > 0` → ReflectFinancial (despesa vacina/med; receita venda). Se `stock_item_id != null` → ConsumeStock (baixa vacina/ração/medicamento) |
| **StockMovementObserver** | StockMovement::created com `motivo ∈ {compra, venda}` e `valor > 0` | ReflectFinancial (despesa se compra, receita se venda) |
| **MaintenanceOrderObserver** | MaintenanceOrder::created com `valor_total > 0` | ReflectFinancial (despesa) |
| **VehicleEventObserver** | VehicleEvent::created tipo=abastecimento | ReflectFinancial (despesa) + ConsumeStock (combustível se stock_item_id setado) |
| **HarvestSaleObserver** | Venda de colheita criada (campo `partner_id` + `valor_venda` em `harvests`) | ReflectFinancial (receita) |

## 3.5 Middlewares (5 novos)

Aplicados como grupo em `/admin/*`:

```
'auth',
'resolve.tenant',
'enforce.farm',
'enforce.subscription',
(permission/role específicas da rota)
```

| Middleware | Função | Falha → |
|---|---|---|
| `ResolveTenant` | Lê `Auth::user()->tenant_id` + sessão `current_farm_id`, seta em `app()->instance()` | Se user não tem tenant e não é master → 403 |
| `EnforceFarm` | Se tenant tem >1 farm e sessão sem `current_farm_id` | Redirect `/admin/selecionar-fazenda` |
| `EnforceSubscription` | Checa status da subscription (`active`, `trial`, `past_due <3d`) | Redirect `/admin/assinatura/vencida` (exceção: rotas de billing e logout) |
| `EnforceTenantOnWrite` | Global — no `saving` de model com `BelongsToTenant`, valida `tenant_id === app('tenant_id')` | Exception + log (última linha de defesa) |
| `RequireGlobalMaster` | Aplicado em `/master/*` | 403 se user tem tenant_id |

## 3.6 Scopes e Traits

### Trait `BelongsToTenant`

Aplicada em **todos** os models de dados. Funcionamento:

- `boot`: registra global scope `BelongsToTenantScope` que injeta `WHERE tenant_id = app('tenant_id')`
- `creating`: preenche `tenant_id = app('tenant_id')` se null
- Bypass: `Model::withoutGlobalScope(BelongsToTenantScope::class)` — usado apenas em seeders, backfill e comandos explicitamente tenant-agnósticos

### Trait `BelongsToFarm`

Aplicada onde a entidade também pertence a uma farm específica (animais, plantios, transações). Funciona como o anterior mas escopa por `current_farm_id` quando setado. Seletor de fazenda pode ser null = "todas as fazendas do tenant" (dashboard consolidado).

### Trait `InteractsWithTenant`

Aplicada em Jobs e Commands:

- Job recebe `tenant_id` no construtor, serializa no payload
- `handle()` chama `app()->instance('tenant_id', $this->tenantId)` antes de qualquer query

## 3.7 Infraestrutura multi-tenant

| Camada | Estratégia |
|---|---|
| **Storage** | Wrapper `TenantStorage::disk('public')->put('animals/xxx.jpg')` internamente vira `storage/app/public/tenants/{tenant_id}/animals/xxx.jpg`. Paths em DB salvos com prefixo. |
| **Cache** | Facade `TenantCache` com auto-prefix `tenant:{id}:`. Nunca usar `Cache::` direto em domain. |
| **Queue** | Driver `database` (mantido). Jobs herdam `InteractsWithTenant`. Worker não faz nada tenant-específico — o próprio job injeta contexto no `handle`. |
| **Schedule** | Em `routes/console.php`: commands que tocam dados iteram `Tenant::active()->each(fn($t) => ...)`. Commands neutros (`menu:snapshot`, `backup-db`) ficam como estão. |
| **Session** | `tenant_id` gravado ao logar; **imutável** na sessão (FarmSwitcher só troca `current_farm_id`). |
| **Logs** | `Log::info($msg, ['tenant_id' => app('tenant_id')])` via macro. Facilita filtrar log por tenant. |

## 3.8 Configuração via `config/tenancy.php`

```
- feature_flags:
    OBSERVER_FINANCE_REFLECT  (default true)
    OBSERVER_STOCK_CONSUME    (default true)
    BILLING_ENFORCEMENT       (default false no início, vira true pós-lançamento)

- storage:
    base_path: 'tenants/{tenant_id}'

- cache:
    key_prefix: 'tenant:{tenant_id}:'

- master:
    global_role: 'platform_master'
    impersonation_mode: 'read_only_by_default'
```

## 3.9 Master Global

- `users.tenant_id` **nullable** permanente (é como identificamos o master)
- Role `platform_master` criada no seeder, separada dos roles de tenant
- Rotas em `/master/*` com layout próprio, sem `ResolveTenant`
- Impersonação:
  - Entra em modo **read-only** no tenant
  - Botão "Entrar em modo suporte" ativa escrita por 30 min
  - Toda ação loga no `activity_log` com `impersonated_by`
  - Banner vermelho permanente na UI durante impersonação

---

# FASE 4 · Modelagem Final

## 4.1 Princípios aplicados

- **Aditivo-only** nesta fase. Nenhum `dropColumn`.
- **7 tabelas novas apenas** — tenants, plans, subscriptions, invoices, corte_packages, milk_deliveries, employee_punches.
- **`metadata` JSON como coringa** — guarda campos específicos por tipo de evento, evita proliferação.
- Toda tabela operacional recebe `tenant_id` + `farm_id`.

## 4.2 Tabelas novas — schema definitivo

### tenants

```
id                  bigint PK
nome                varchar(150)
slug                varchar(80)      UNIQUE
documento           varchar(20)      (CPF 11 ou CNPJ 14 alfanumérico)
email               varchar(150)
telefone            varchar(20)      nullable
plan_id             FK plans         nullable (null = trial não iniciado)
status              enum             ('trial','active','past_due','blocked','canceled')
trial_ends_at       timestamp        nullable
is_active           boolean          default true
created_at / updated_at
INDEX (slug), (status), (documento)
```

### plans

```
id                  bigint PK
slug                varchar(40)      UNIQUE   ('essencial','profissional','empresarial')
nome                varchar(80)
preco_mensal        decimal(10,2)
max_farms           unsigned int     (0 = ilimitado)
max_users           unsigned int     (0 = ilimitado)
features            JSON             lista de slugs de features
is_active           boolean          default true
sort_order          unsigned int
```

### subscriptions

```
id                  bigint PK
tenant_id           FK tenants       UNIQUE (1 sub ativa por tenant)
plan_id             FK plans
status              enum             ('trial','active','past_due','canceled')
started_at
trial_ends_at       nullable
current_period_start
current_period_end
canceled_at         nullable
meta                JSON             (histórico de upgrades/downgrades)
```

### invoices

```
id                  bigint PK
tenant_id           FK tenants
subscription_id     FK subscriptions
numero              varchar(20)      UNIQUE  (formato INV-202604-0001)
valor               decimal(10,2)
status              enum             ('pendente','paga','vencida','cancelada')
data_emissao        date
data_vencimento     date
data_pagamento      date nullable
pix_txid            varchar(35)      nullable
pix_payload         text             nullable
pix_qrcode_base64   longtext         nullable
meta                JSON
INDEX (tenant_id, status), (data_vencimento)
```

### corte_packages

```
id                  bigint PK
tenant_id + farm_id FK
codigo              varchar(20)      UNIQUE por tenant
data_venda          date
partner_id          FK partners      (frigorífico)
valor_total         decimal(12,2)
custo_acumulado_cached  decimal(12,2)  nullable  (preenchido por service)
margem_cached       decimal(12,2)    nullable
peso_total_kg       decimal(12,2)    nullable
total_arrobas       decimal(10,3)    nullable
preco_arroba        decimal(8,2)     nullable
observacoes         text
```

**FK em `animal_events.package_id`** nullable (relaciona venda ao pacote).

### milk_deliveries *(venda ao laticínio é fluxo específico — receita a receber)*

```
id                  bigint PK
tenant_id + farm_id FK
data_coleta         date
volume_litros       decimal(10,2)
partner_id          FK partners      (laticínio)
preco_litro_estimado decimal(6,4)    nullable
preco_litro_final   decimal(6,4)     nullable (preenchido ao faturar)
qualidade           JSON             ({ ccs, cbt, gordura, proteina })
status              enum             ('coletado','faturado','pago')
financial_transaction_id  FK nullable  (receita a receber gerada)
INDEX (tenant_id, data_coleta), (status)
```

### employee_punches *(ponto — frequência alta e domínio próprio)*

```
id                  bigint PK
tenant_id + farm_id
employee_id         FK employees
tipo                enum             ('entrada','saida','refeicao_inicio','refeicao_fim')
timestamp           timestamp
lat / lng           decimal(10,7)    nullable (geofence futuro)
device_info         varchar(255)     nullable
observacao          varchar(255)     nullable
INDEX (tenant_id, employee_id, timestamp)
```

## 4.3 Colunas aditivas em tabelas existentes

Agrupadas por tabela. Todas nullable na release 1; backfill; `NOT NULL` na release 2.

### Multi-tenant (universal)

Todas as tabelas operacionais abaixo ganham **`tenant_id`** + **`farm_id`**:

```
users, farms, animals, animal_species, animal_breeds, animal_lots, animal_events,
financial_accounts, financial_transactions, financial_recurrences,
stock_items, stock_movements, warehouses, categories,
fields, crops, seasons, plantings, harvests, applications,
vehicles, maintenance_orders, vehicle_events,
employees, tasks, checklists, documents, document_categories,
partners, menus, menu_items, barcode_lookups, menu_usage
```

Exceção: `users.tenant_id` é nullable permanente (master global).

### animals

```
status_reprodutivo  enum nullable
                    ('vazia','cio','coberta','prenha','lactando','seca','descarte')
                    — atualizado por ReproductiveStatusService
```

### animal_lots

```
finalidade              varchar(30)   nullable  ('corte','leite','postura','engorda_ave','aquicultura','reproducao')
quantidade_inicial      unsigned int  nullable  (para aves/peixes)
data_alojamento         date          nullable
galpao                  varchar(60)   nullable
volume_m3               decimal(10,2) nullable  (tanque de peixe)
tipo_cultivo            varchar(30)   nullable  ('intensivo','semi_intensivo','extensivo')
profundidade_m          decimal(5,2)  nullable
```

### animal_events

```
turno               enum nullable        ('manha','tarde','unico','noite')
stock_item_id       FK stock_items nullable
                    — link opcional: se informado, observer baixa estoque
quantidade          decimal(12,3) nullable
                    — para postura (qtd ovos), biometria (qtd amostrada),
                      alimentação (kg ração), mortalidade (qtd)
metadata            JSON nullable
                    — conforme dicionário em §4.4
finance_reflected_at  timestamp nullable   — idempotência observer financeiro
stock_consumed_at     timestamp nullable   — idempotência observer estoque
package_id          FK corte_packages nullable  — ligação com venda em pacote
```

### stock_items

```
validade            date nullable         — vacina, medicamento, semente
lote_fabricante     varchar(50) nullable  — rastreabilidade
```

### stock_movements

```
reference_type      varchar(255) nullable
reference_id        bigint nullable
                    UNIQUE (reference_type, reference_id) — idempotência polimórfica
```

### financial_transactions

```
(já tem reference_type/reference_id)
UNIQUE (reference_type, reference_id)  — adicionar agora
```

### harvests

```
partner_id          FK partners nullable
data_venda          date nullable
valor_venda         decimal(12,2) nullable
forma_pagamento     varchar(30) nullable
```

## 4.4 Dicionário do `animal_events.metadata` (JSON)

Documento vivo. Versão 1.0 na release do multi-tenant.

| tipo | peso | quantidade | turno | stock_item_id | metadata JSON |
|---|---|---|---|---|---|
| `pesagem` | kg | — | — | — | `{}` |
| `vacinacao` | — | ml dose | — | vacina | `{ via_aplicacao, responsavel, lote_vacina }` |
| `medicacao` | — | ml/mg | — | medicamento | `{ via_aplicacao, responsavel }` |
| `vermifugacao` | — | ml/mg | — | vermífugo | `{ responsavel }` |
| `ordenha` | — | litros | manhã/tarde | — | `{ qualidade: {ccs, cbt, gordura, proteina} }` |
| `secagem` | — | — | — | — | `{}` |
| `postura_diaria` | — | total ovos | manhã/tarde | — | `{ classificacao: {extra, grande, medio, pequeno, industrial} }` |
| `biometria_amostral` | kg médio | qtd amostrada | — | — | `{ comprimento_medio_cm }` |
| `qualidade_agua` | — | — | manhã/tarde | — | `{ ph, od_mg_l, temperatura_c, amonia_mg_l }` |
| `alimentacao` | — | kg ração | — | ração | `{ tipo_racao }` |
| `mortalidade` | — | qtd | — | — | `{ causa, destino }` |
| `reproducao` | — | — | — | — | `{ subtipo: 'cio'\|'cobertura'\|'diagnostico'\|'parto'\|'secagem', touro_id?, semen?, resultado? }` |
| `movimentacao` | — | qtd (se lote) | — | — | `{ motivo }` (usa `lot_origem_id`/`lot_destino_id`) |
| `venda` | — | qtd unidade | — | — | `{ unidade_venda, valor_unitario, peso_medio }` |
| `compra` | — | qtd unidade | — | — | `{ unidade_compra, valor_unitario }` |
| `ferrageamento` | — | — | — | — | `{ responsavel }` |
| `tosquia` | kg lã | — | — | — | `{}` |
| `castracao` | — | — | — | — | `{ metodo, responsavel }` |
| `observacao` | — | — | — | — | `{}` livre |

**Regra de evolução**: quando um campo do `metadata` for lido em ≥3 relatórios, migrar para coluna dedicada. Até lá, índice em `(tipo, data)` é suficiente; consultas ad-hoc usam `JSON_EXTRACT`.

## 4.5 Índices (performance garantida multi-tenant)

Universal em toda tabela operacional:

```
INDEX (tenant_id)
INDEX (tenant_id, farm_id)
INDEX (tenant_id, created_at)
```

Específicos:

| Tabela | Índice extra |
|---|---|
| `animal_events` | `(tenant_id, animal_id, tipo, data)` · `(tenant_id, lot_id, tipo, data)` · `(tenant_id, tipo, data)` · `(stock_item_id, data)` |
| `animals` | `(tenant_id, farm_id, status, species_id)` · `(tenant_id, identificacao)` · `(tenant_id, status_reprodutivo)` |
| `animal_lots` | `(tenant_id, farm_id, finalidade)` |
| `stock_movements` | `(tenant_id, item_id, data)` · `UNIQUE(reference_type, reference_id)` |
| `stock_items` | `(tenant_id, codigo_barras)` · `(tenant_id, tipo)` |
| `financial_transactions` | `(tenant_id, data_vencimento, status)` · `(tenant_id, data_pagamento)` · `UNIQUE(reference_type, reference_id)` |
| `plantings` | `(tenant_id, farm_id, status, season_id)` |
| `harvests` | `(tenant_id, planting_id, data_colheita)` |
| `vehicle_events` | `(tenant_id, vehicle_id, tipo, data)` |
| `employee_punches` | `(tenant_id, employee_id, timestamp)` |
| `invoices` | `(tenant_id, status, data_vencimento)` |
| `barcode_lookups` | `(tenant_id, code, created_at)` |

## 4.6 Plano de migração (release-by-release)

### Release R1 — "Tenancy-ready"

1. Criar `tenants`, `plans`, `subscriptions`, `invoices` (billing dormido: flag OFF)
2. Add `tenant_id` + `farm_id` nullable em TODAS as tabelas operacionais
3. Seeder: cria tenant `id=1` (Fazenda Macaybas) + plan Essencial "grátis vitalício" + subscription active
4. Backfill: `UPDATE tabela SET tenant_id=1, farm_id=<id_default> WHERE tenant_id IS NULL`
5. Aplicar trait `BelongsToTenant` em models (em modo detector-apenas: loga warning mas não enforce)
6. Deploy. Sistema continua operando como single-tenant na prática — ninguém percebe.

### Release R2 — "Tenancy enforced"

7. Trait passa a enforce (global scope ativo)
8. Middlewares `ResolveTenant` + `EnforceFarm` ativos em `/admin/*`
9. Migration `NOT NULL` em `tenant_id` (já tem dado em tudo)
10. Master global criado + rotas `/master/*` no ar
11. Seletor de fazenda aparece para usuários multi-farm
12. Storage migrado para `tenants/{id}/*`

### Release R3 — "Integrations ON"

13. Colunas novas em `animal_events` (turno, metadata, quantidade, stock_item_id, finance_reflected_at, stock_consumed_at, package_id)
14. Colunas novas em `animal_lots` (finalidade, galpao, quantidade_inicial, volume_m3, etc.)
15. Colunas novas em `animals` (status_reprodutivo)
16. Colunas novas em `harvests` (venda)
17. Observers registrados (flag ON) — disparando integração financeira e estoque
18. Backfill de observers: dry-run histórico, review, commit

### Release R4 — "Aves + Peixes"

19. `EggCollectionService` + tela de colheita de ovos
20. Categoria "Ovos" em `stock_items` + entrada automática
21. `TankPanelService` + tela de painel de tanque

### Release R5 — "Leite + Corte"

22. `milk_deliveries` + UI de conta do laticínio
23. `ReproductiveStatusService` + eventos reprodutivos encadeados
24. `corte_packages` + UI de venda ao frigorífico

### Release R6 — "Billing ON"

25. `EnforceSubscription` ativo
26. `PixService` integrado
27. Cadastro público de novo tenant (sign-up)
28. Trial 14 ou 30 dias (conforme decisão)

### Release R7 — "Mobile PWA + polish"

29. Service worker, manifest, instalação em home
30. Telas da FASE 5 refinadas ao estado final
31. `employee_punches` + app de ponto

---

# FASE 5 · UX Detalhado tela a tela

Princípios aplicados em TODAS (não repito em cada):

- Mobile-first. 360 × 640 cabe tudo sem scroll horizontal.
- 1 ação principal evidente por tela.
- Botão principal ≥44px. Cor verde cheio, largura total ou flutuante fixo.
- Zero tabela no mobile. Cards.
- Teclado numérico automático em todo input numérico.
- Data default = hoje.
- Toast no topo para feedback (Salvo ✓ / Falhou ✗).
- Offline banner: "Sem conexão. Tentando de novo em 30s."
- Cores semânticas: 🟢 OK, 🟡 atenção, 🔴 problema.

## 5.1 Seleção de Fazenda

**Quando**: login com >1 fazenda · botão de troca na topbar.
**Ação principal**: tocar no card.

```
┌──────────────────────────────────┐
│  Macaybas ERP           🚪 Sair │
├──────────────────────────────────┤
│                                  │
│  Olá, Jhonatan 👋                │
│  Em qual fazenda vai trabalhar?  │
│                                  │
│  ┌──────────────────────────┐   │
│  │ 🖼                       │   │
│  │                          │   │
│  │ FAZENDA MACAYBAS         │   │
│  │ Itabirito — MG           │   │
│  │                          │   │
│  │ 🐄 134 animais           │   │
│  │ 🌾 5 ha plantados        │   │
│  │                          │   │
│  │  ─────────────────────   │   │
│  │  Acessar →               │   │
│  └──────────────────────────┘   │
│                                  │
│  ┌──────────────────────────┐   │
│  │ 🖼                       │   │
│  │                          │   │
│  │ SÍTIO BOA VISTA          │   │
│  │ Ouro Preto — MG          │   │
│  │                          │   │
│  │ 🐄 42 animais            │   │
│  │ 🥚 2 galpões de postura  │   │
│  │                          │   │
│  │  ─────────────────────   │   │
│  │  Acessar →               │   │
│  └──────────────────────────┘   │
└──────────────────────────────────┘
```

- Foto do topo grande (não ícone pequeno)
- Métricas-resumo por fazenda (contextual: se não tem gado, esconde linha de animais)
- Card inteiro clicável
- Se usuário tem 1 fazenda só: essa tela nem aparece. Entra direto.
- Master global tem variação: cards de **tenants** em vez de farms. Mesmo layout.

## 5.2 Dashboard

**Objetivo**: saber em 3 segundos se a fazenda está OK.

```
┌──────────────────────────────────┐
│ ≡  Macaybas               J ▼   │
├──────────────────────────────────┤
│  Bom dia, Jhonatan 👋            │
│  Quinta, 22 de abril             │
│                                  │
│  🔴 ATENÇÃO                       │
│  ┌──────────────────────────┐   │
│  │ 2 contas atrasadas    →  │   │
│  │ Ração acaba em 5 dias →  │   │
│  └──────────────────────────┘   │
│                                  │
│  ESTA SEMANA                     │
│  ┌─────────────┬─────────────┐  │
│  │ 🥛          │ 🥚          │  │
│  │ 2.940 L     │ 5.640       │  │
│  │ leite       │ ovos        │  │
│  │ +8% vs sem. │ +2% vs sem. │  │
│  └─────────────┴─────────────┘  │
│  ┌─────────────┬─────────────┐  │
│  │ 💰          │ 🐄          │  │
│  │ R$ 12.340   │ 134         │  │
│  │ saldo mês   │ animais     │  │
│  └─────────────┴─────────────┘  │
│                                  │
│                         ( + )    │ ← FAB
│                                  │
│  🏠 Início   📋 Tudo   👤 Eu    │
└──────────────────────────────────┘
```

- Header com menu hambúrguer + fazenda ativa (botão para trocar) + avatar
- Bloco de alertas só aparece se tem alerta. Cada linha clicável → drawer.
- 4 KPIs em grid 2×2. Cada card tem ícone + número + label + comparativo vs período anterior. Clicar abre **drawer** com detalhe.
- **FAB "+"** flutuante no canto inferior direito. Ao tocar, abre menu contextual com **ações rápidas da fazenda** (filtradas pelas atividades ativas):

```
  ⚖ Pesar animal
  🥛 Registrar ordenha
  💉 Aplicar vacina
  🥚 Coletar ovos
  📏 Biometria de tanque
  📷 Escanear estoque
  💰 Novo lançamento
```

- **Bottom nav** (só mobile): Início / Tudo / Eu

## 5.3 Menu "Tudo" (drawer)

Abre como drawer full-screen ao tocar em "Tudo" no bottom nav.

```
┌──────────────────────────────────┐
│ ← Tudo                           │
├──────────────────────────────────┤
│  OPERAÇÃO (mais usado primeiro)  │
│  🐄 Rebanho                      │
│  🥚 Aves                         │
│  🐟 Peixes                       │
│  🌾 Lavoura                      │
│  📦 Estoque                      │
│  🚜 Máquinas                     │
│  👷 Funcionários                 │
│  ✅ Tarefas                      │
│                                  │
│  DECISÃO                         │
│  💰 Financeiro                   │
│  📊 Relatórios                   │
│                                  │
│  FAZENDA                         │
│  🏡 Trocar fazenda               │
│  ⚙ Configurações                 │
│  🌐 Site público (somente master)│
│                                  │
│  CONTA                           │
│  💳 Assinatura                   │
│  🚪 Sair                         │
└──────────────────────────────────┘
```

Ordem dentro de "OPERAÇÃO" segue o snapshot diário de uso (atual).

## 5.4 Rebanho — Lista

```
┌──────────────────────────────────┐
│ ← Rebanho                   🔍  │
├──────────────────────────────────┤
│  [Todos] [Leite] [Corte] [Pet]  │ ← chips
│                                  │
│  ┌──────────────────────────┐   │
│  │  🖼  32032 marronzinha  │   │
│  │      🐄 Bovino · ♀      │   │
│  │      420 kg              │   │
│  │                          │   │
│  │  [⚖ Pesar] [💉 Vacina]  │   │
│  └──────────────────────────┘   │
│  ┌──────────────────────────┐   │
│  │  📦 GALPÃO A            │   │
│  │      🥚 1.200 aves       │   │
│  │      Postura · 32 sem.   │   │
│  │                          │   │
│  │  [🥚 Colher] [🍽 Alim.] │   │
│  └──────────────────────────┘   │
│                                  │
│  [ + Novo ▾ ]                    │
└──────────────────────────────────┘
```

- **Chips de filtro** por categoria (chip ativo em verde)
- **Cards** por animal individual E por lote (aves/peixes) — mesma lista
- 2 botões de ação imediata por card, contextuais por profile
- Toque no card (fora dos botões) → página de detalhe
- Toque longo → entra modo seleção múltipla (para venda)
- Botão flutuante "+ Novo ▾" com menu: Novo animal / Novo lote

## 5.5 Registrar Pesagem

```
┌──────────────────────────────────┐
│ ← Pesar 32032 marronzinha        │
├──────────────────────────────────┤
│                                  │
│     🖼 foto 120×120              │
│                                  │
│  Peso anterior: 415 kg           │
│  Em 15/04/2026                   │
│                                  │
│  ━━━━━━━━━━━━━━━━━━━━━━           │
│                                  │
│  PESO AGORA                      │
│  ┌──────────────────────────┐   │
│  │         420              │ kg│  ← foco auto + tecl. numérico
│  └──────────────────────────┘   │
│                                  │
│  Data: Hoje (22/04) [alterar]    │
│                                  │
│  ▸ Observações (opcional)        │ ← colapsado
│                                  │
│  ┌──────────────────────────┐   │
│  │        SALVAR            │   │
│  └──────────────────────────┘   │
└──────────────────────────────────┘
```

- Peso anterior visível para referência
- Campo número grande, teclado numérico
- Data default hoje; picker só se tocar "alterar"
- Observações colapsadas (raramente usado)
- Após salvar: toast "Pesagem registrada ✓" e **volta pra lista** com animal destacado 2s

## 5.6 Registrar Ordenha (fluxo 5 segundos)

```
┌──────────────────────────────────┐
│ ← Ordenha 32032                  │
├──────────────────────────────────┤
│                                  │
│  🥛 Última ordenha: 14 L ontem   │
│                                  │
│  QUANTOS LITROS AGORA?           │
│  ┌──────────────────────────┐   │
│  │         15,5             │ L │
│  └──────────────────────────┘   │
│                                  │
│  Turno                           │
│  ┌───────┬───────┐               │
│  │🌅 Manhã│🌇 Tarde│              │
│  └───────┴───────┘               │
│                                  │
│  ▸ Qualidade (CCS, CBT, gordura) │ ← para quem tem laboratório
│                                  │
│  [        SALVAR         ]       │
│  [ + Registrar outra vaca ]      │
└──────────────────────────────────┘
```

- Hora automática detecta se é manhã/tarde pela hora atual; usuário confirma
- Qualidade colapsada (preenchida só quando chega análise do laticínio, dias depois)
- Botão extra "Registrar outra vaca" permite loop rápido no curral

## 5.7 Aves — Colheita de Ovos

```
┌──────────────────────────────────┐
│ ← Coleta de ovos                 │
├──────────────────────────────────┤
│                                  │
│  Galpão A · 1.200 aves · 32 sem │ ← contexto automático
│                                  │
│  🌅 MANHÃ    🌇 TARDE             │ ← toggle
│                                  │
│  QUANTOS OVOS?                   │
│  ┌──────────────────────────┐   │
│  │         840              │   │
│  └──────────────────────────┘   │
│                                  │
│  ▸ Separar por tamanho           │ ← colapsado, 90% pula
│                                  │
│  ┌──────────────────────────┐   │
│  │       REGISTRAR          │   │
│  └──────────────────────────┘   │
│                                  │
│  ━━━━━━━━━━━━━━━━━━━━━━           │
│  ESTA SEMANA                     │
│  Seg 620 · Ter 810 · Qua 790 ... │
│  Total: 4.810 ovos               │
└──────────────────────────────────┘
```

- Se usuário expande "Separar por tamanho":

```
  Extra    [ 120 ]
  Grande   [ 420 ]
  Médio    [ 200 ]
  Pequeno  [ 80  ]
  Industrial [ 20 ]
  = 840 (bate)
```

- Rodapé mostra progresso da semana (motivacional)
- **Invisível pro usuário**: sistema já dá entrada no estoque de ovos e está pronto pra venda

## 5.8 Peixes — Painel do Tanque

```
┌──────────────────────────────────┐
│ ← Tanque 1 · Tilápia             │
├──────────────────────────────────┤
│  Povoado 15/10/2025 (6 meses)    │
│  Peso médio: 480 g               │
│  Estimado: 4.200 peixes          │
│  Mortalidade no ciclo: 4,2%      │
│                                  │
│  ┌───────┬───────┐               │
│  │ 📏    │ 💧    │               │
│  │Biomet.│Água   │               │
│  └───────┴───────┘               │
│  ┌───────┬───────┐               │
│  │ 🍽    │ ⚰     │               │
│  │Alimen.│Mortal.│               │
│  └───────┴───────┘               │
│                                  │
│  EVOLUÇÃO DO PESO                │
│  ┌──────────────────────────┐   │
│  │   📈 gráfico linha        │   │
│  │   300g → 480g (6 meses)  │   │
│  └──────────────────────────┘   │
│                                  │
│  [ 💰 Despescar e vender ]       │ ← só ativa quando peso >= 700g
└──────────────────────────────────┘
```

- Painel, não lista (não existe "peixe individual")
- 4 ações em botões grandes
- Gráfico de evolução sempre visível
- Botão de despesca/venda só habilita ao atingir peso-padrão

## 5.9 Venda em Lote (rebanho)

Fluxo toque-longo-em-card → seleção múltipla → botão grande.

```
PASSO 1: dentro da lista, toque longo num card
↓
PASSO 2: modo seleção aparece com bar no topo

┌──────────────────────────────────┐
│ ← 3 selecionados     [Limpar]   │
├──────────────────────────────────┤
│  ☑ 32032  420 kg                 │
│  ☑ 32033  385 kg                 │
│  ☑ 32034  410 kg                 │
│  ☐ 32035  380 kg                 │
│                                  │
│  Peso médio: 405 kg              │
│                                  │
│  [      VENDER 3 BOVINOS      ]  │ ← sticky bottom
└──────────────────────────────────┘

PASSO 3: modal de venda (bottom sheet mobile)

┌──────────────────────────────────┐
│  ▬▬                              │ ← handle
│  Venda de 3 bovinos              │
│                                  │
│  Vender em                       │
│  ┌──────────────────────────┐   │
│  │ Arroba (@) ▾            │   │
│  └──────────────────────────┘   │
│                                  │
│  Peso médio por cabeça           │
│  [ 405 ] kg                      │
│                                  │
│  Valor por arroba                │
│  [ R$ 325,00 ]                   │
│                                  │
│  ──────────────────────           │
│  Total em arrobas: 81 @          │
│  Por cabeça: R$ 8.775,00         │
│  TOTAL: R$ 26.325,00             │
│  ──────────────────────           │
│                                  │
│  Comprador (opcional)            │
│  [ Frigorífico Silva ▾ ]         │
│                                  │
│  [    CONFIRMAR VENDA    ]       │
└──────────────────────────────────┘
```

- Unidade contextual (arroba/kg/un/cabeça) conforme espécie
- Resumo calcula ao vivo
- Botão tem o valor dentro ("Confirmar venda de R$ 26.325,00")
- Após confirmar:
  - Animais somem da lista ativa
  - Receita já aparece em Financeiro (automático)
  - Toast: "Venda registrada ✓ Receita em Financeiro"

## 5.10 Estoque — Consulta + Ações Rápidas

```
┌──────────────────────────────────┐
│ ← Estoque              🔍  📷   │
├──────────────────────────────────┤
│  🔴 2 itens abaixo do mínimo      │
│                                  │
│  [ Tudo ] [ Ração ] [ Vacina ] ▸│ ← chips scroll-x
│                                  │
│  ┌──────────────────────────┐   │
│  │ Ração Bovina 20kg        │   │
│  │ 🔴 5 sacos · mín. 10     │   │
│  │ Validade 15/08/2026      │   │
│  │                          │   │
│  │ [ + Entrada ] [ − Saída ]│   │
│  └──────────────────────────┘   │
│  ┌──────────────────────────┐   │
│  │ Vacina Aftosa 250ml      │   │
│  │ 🟢 3 frascos             │   │
│  │ 🟡 Vence em 60 dias      │   │
│  │                          │   │
│  │ [ + Entrada ] [ − Saída ]│   │
│  └──────────────────────────┘   │
│                                  │
│                         ( + )    │ ← FAB (novo item OU scanner)
└──────────────────────────────────┘
```

### Modal de Entrada (bottom sheet)

```
│  ▬▬                              │
│  Entrada: Ração Bovina 20kg      │
│                                  │
│  Quantos sacos chegaram?         │
│  [ 20 ]                          │
│                                  │
│  Valor total (opcional)          │
│  [ R$ 1.200,00 ]                 │
│                                  │
│  Fornecedor (opcional)           │
│  [ Agropecuária XYZ ▾ ]          │
│                                  │
│  [   CONFIRMAR ENTRADA   ]       │
```

- Se valor preenchido: observer cria despesa automaticamente no financeiro
- Após confirmar: saldo atualiza, toast, fecha sheet

## 5.11 Financeiro — Lista mobile

```
┌──────────────────────────────────┐
│ ← Financeiro               🔍  │
├──────────────────────────────────┤
│  ABRIL/2026                      │
│  ┌────────┬────────┬────────┐   │
│  │ 🟢     │ 🔴     │ 💰     │   │
│  │+12,3k  │-4,8k   │ 7,5k   │   │
│  │Receita │Despesa │ Saldo  │   │
│  └────────┴────────┴────────┘   │
│                                  │
│  [Todos] [Pendente] [Pago]      │
│                                  │
│  ┌──────────────────────────┐   │
│  │ 🟡 Energia elétrica       │   │
│  │    Vence em 25/04        │   │
│  │    R$ 480,00      [Pagar]│   │
│  └──────────────────────────┘   │
│  ┌──────────────────────────┐   │
│  │ 🟢 Laticínio Nestlé (leite)│ │
│  │    Recebido em 18/04      │  │
│  │    R$ 3.840,00           │   │
│  └──────────────────────────┘   │
│                                  │
│                         ( + )    │ ← novo lançamento
└──────────────────────────────────┘
```

- KPIs do mês na topo (tocáveis → drawer de detalhamento)
- Chips de status
- Cards por lançamento com ação rápida (Pagar) embutida se pendente
- Ícone ao lado do lançamento indica origem: 🥛 leite, 🐄 venda animal, 💊 vacina, ⛽ combustível — usuário **vê imediatamente** de onde veio (integração polimórfica visível)

## 5.12 Funcionários — Ponto Mobile

Tela dedicada para o funcionário bater ponto (app mode).

```
┌──────────────────────────────────┐
│  MEU PONTO             João ▾   │
├──────────────────────────────────┤
│                                  │
│        📅 22/04/2026             │
│        🕐 14:32                  │
│                                  │
│  ━━━━━━━━━━━━━━━━━━━━━━           │
│                                  │
│  Entrada hoje:   06:02           │
│  Almoço:   11:45 → 12:40         │
│  Saída:    — ainda aberto        │
│                                  │
│  ━━━━━━━━━━━━━━━━━━━━━━           │
│                                  │
│  ┌──────────────────────────┐   │
│  │    🔴 BATER SAÍDA        │   │ ← botão enorme
│  └──────────────────────────┘   │
│                                  │
│  📍 GPS: 20.23°S 43.80°W (OK)   │
└──────────────────────────────────┘
```

- 1 ação por tela
- Botão muda conforme próximo estado esperado (Entrada, Saída almoço, Volta almoço, Saída)
- GPS opcional (geofence futuro)
- Dono/gerente vê relatório agregado em tela separada

## 5.13 Relatórios — Mobile

```
┌──────────────────────────────────┐
│ ← Relatórios                     │
├──────────────────────────────────┤
│  PERÍODO                         │
│  [ Mês ] [ Trim. ] [ Semestre ]  │
│  [ Ano ] [ Personalizado ]       │
│                                  │
│  🟢 Mais lucrativo: Leite         │
│      +R$ 8.340 de margem         │
│                                  │
│  🔴 Menos lucrativo: Corte        │
│      -R$ 1.200 prejuízo          │
│                                  │
│  MARGEM POR ATIVIDADE            │
│  🥛 Leite        ██████ +8,3k   │
│  🥚 Ovos         ████   +3,1k   │
│  🌾 Milho        ██     +0,9k   │
│  🐄 Corte        ▏     -1,2k    │
│                                  │
│  [ 📊 Ver comparativo ]           │ ← drawer com mês anterior
│                                  │
│  ITENS MAIS CONSUMIDOS           │
│  🍽 Ração Bovina      430 sc    │
│  💊 Ivermectina       12 frasc. │
│  ⛽ Diesel            280 L     │
└──────────────────────────────────┘
```

- Pergunta-chave respondida: **"qual atividade dá mais lucro?"** já no topo
- Barras horizontais por atividade (fácil comparar)
- Drawer de comparativo mostra mês/trimestre/ano anterior lado a lado

## 5.14 Cobrança SaaS — Assinatura Vencida

```
┌──────────────────────────────────┐
│         Macaybas ERP             │
├──────────────────────────────────┤
│                                  │
│     ⚠ Assinatura em atraso       │
│                                  │
│  Para continuar, por favor       │
│  regularize seu pagamento.       │
│                                  │
│  ┌──────────────────────────┐   │
│  │ Fatura Abril/2026        │   │
│  │ Venceu: 15/04            │   │
│  │ Valor: R$ 349,00         │   │
│  └──────────────────────────┘   │
│                                  │
│  💠 PAGUE COM PIX                │
│  ┌──────────────────────────┐   │
│  │   [ QR code 200×200 ]    │   │
│  └──────────────────────────┘   │
│                                  │
│  [ 📋 Copiar código PIX ]        │
│                                  │
│  Após pagar, o acesso libera     │
│  em até 2 minutos automático.    │
│                                  │
│  ──────────────────────           │
│  Sair · Suporte · Trocar plano   │
└──────────────────────────────────┘
```

- Mensagem humanizada
- QR grande
- Copia-cola PIX em 1 toque
- Auto-unlock após webhook confirmar
- Rodapé com saídas: sair, suporte, downgrade

## 5.15 Cadastro via Scan

Quando scanner não encontra em nenhuma base:

```
Modal (overlay):

┌──────────────────────────────────┐
│  📦 Produto novo                 │
├──────────────────────────────────┤
│                                  │
│  Código 7898745991188            │
│  ainda não está na sua base.     │
│                                  │
│  Vamos cadastrar?                │
│  Das próximas vezes será         │
│  reconhecido automaticamente.    │
│                                  │
│  [ Cancelar ] [ Cadastrar →]     │
└──────────────────────────────────┘
```

Após tocar Cadastrar, abre form de novo item com:

- Código de barras **pré-preenchido**
- Selo verde ✓ "Código verificado via scanner"
- Campo nome em foco + teclado aberto

## 5.16 Master Global — Lista de Tenants

Layout próprio, sem seletor de fazenda.

```
┌──────────────────────────────────┐
│ Macaybas ERP — Master         J ▼│
├──────────────────────────────────┤
│  42 tenants ativos · MRR R$ 12k  │
│                                  │
│  [ Ativos ] [ Atrasados ] [ Todos]│
│                                  │
│  ┌──────────────────────────┐   │
│  │ 🖼 FAZENDA MACAYBAS      │   │
│  │    @macaybas             │   │
│  │    🟢 active · Essencial │   │
│  │    R$ 149 mensal         │   │
│  │  ──────────────────────  │   │
│  │  Acessar · Suporte →     │   │
│  └──────────────────────────┘   │
│  ┌──────────────────────────┐   │
│  │ 🖼 SITIO BOA VISTA       │   │
│  │    @boavista             │   │
│  │    🔴 past_due (12 dias) │   │
│  │    R$ 349 mensal         │   │
│  │  ──────────────────────  │   │
│  │  Cobrar · Suporte →      │   │
│  └──────────────────────────┘   │
└──────────────────────────────────┘
```

Ao clicar "Acessar" → impersonação read-only com banner vermelho no topo:

```
⚠ Você está visualizando FAZENDA MACAYBAS como suporte · [Encerrar]
```

Com botão "Entrar em modo suporte (30 min)" para ganhar escrita.

---

# Validação Final das Fases 3 + 4 + 5

| Pergunta | Resposta |
|---|---|
| Estou criando algo fora da FASE 2? | **Não**. 7 tabelas novas todas justificadas: tenancy (4), corte_packages (negócio específico), milk_deliveries (fluxo único de laticínio), employee_punches (volume/frequência distinta) |
| Estou duplicando estrutura? | **Não**. Aves, peixes, ovos, ordenha reusam `animal_lots`/`animal_events`/`stock_items`/`financial_transactions` + `metadata` JSON |
| Estou complicando UX? | **Não**. Todas as telas têm 1 ação principal, botão grande, máx 4-5 campos, cards no mobile, linguagem humana |
| Estou quebrando algo existente? | **Não**. Toda mudança é aditiva. Fluxos atuais continuam. Tenancy entra com `tenant_id=1` default; single-tenant continua válido até virar a chave |
| Isso funciona para fazendeiro leigo? | **Sim**. "Quantos ovos coletou hoje?" em vez de "Registrar evento de postura diária". Botões grandes, 1 ação por tela. Scanner substitui digitação |
| Isso funciona no celular? | **Sim**. Mobile-first por design. Bottom nav + cards + bottom sheets. Sem scroll horizontal. Teclado numérico auto |

---

# Itens pendentes antes da FASE 6 (implementação)

Só **3** itens bloqueantes:

1. **Preços finais dos planos** — qual valor para Essencial / Profissional / Empresarial?
2. **Gateway PIX** — Banco do Brasil, Inter, Efi, Asaas ou outro?
3. **Ordem das releases R1–R7** — confirmar sequência ou ajustar.

Também úteis mas não bloqueantes:

- Duração do trial: 14 ou 30 dias
- Marca do SaaS: mantém "Fazenda Macaybas" ou cria marca da plataforma
- Decisão sobre impersonação: read-only default está OK?

---

**Fim das FASES 3 + 4 + 5.**

Ao receber as 3 respostas bloqueantes, inicia-se a FASE 6 (implementação) pela Release R1 (Tenancy-ready), testável sem afetar o sistema atual.
