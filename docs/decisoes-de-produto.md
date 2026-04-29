# Decisões de Produto · Fazenda Macaybas

Decisões registradas após auditoria QA E2E ponta-a-ponta (29-04-2026).

---

## 1. Subdomínios e roteamento por host

| Host | Quem loga | Layout |
|------|-----------|--------|
| `fazendamacaybas.com.br` | **Apenas master** (admin_master, tenant_id=NULL) | MasterLayout |
| `app.fazendamacaybas.com.br` | **Apenas tenants** (qualquer role com tenant_id preenchido) | AdminLayout |
| `<tenant-slug>.fazendamacaybas.com.br` | Tenant específico (futuro — ainda não implementado) | AdminLayout |

**Implementação:** middleware `RouteByHost` (`app/Http/Middleware/RouteByHost.php`) inspeciona o `Host` header e rejeita login fora do escopo. Tentar logar como tenant na raiz devolve erro silencioso ("E-mail ou senha incorretos") porque `LoginRequest::expected_tenant_id` falha.

**Justificativa:** isolamento de marca + facilita futura whitelabel por tenant.

**Comunicação ao usuário:** o link de login enviado por e-mail ao primeiro acesso de um tenant aponta automaticamente pra `app.fazendamacaybas.com.br/login`. Documentar no e-mail de boas-vindas.

---

## 2. Veterinário pode registrar venda de animal?

**Decisão atual:** SIM. Veterinário tem `operational.rebanho.eventos.create` que permite registrar evento de venda.

**Histórico:** QA E2E (Agente B, BUG-001) flagou como bug porque "intuitivamente vet não vende". Foi reavaliado e mantido como design.

**Justificativa:**
- Em fazendas pequenas, vet acumula função clínica + comercial (avalia, vacina, vende).
- Em fazendas grandes, dono restringe via remoção de role (cria role `vet_clinico` sem `eventos.create` se preferir).
- Botão "💰 Vender animal" aparece pra todos com permissão — explicit é melhor que implícito.

**Reabertura:** se múltiplos donos pedirem, criar `operational.rebanho.vender` separado e remover do role default `veterinario`.

---

## 3. Auditor pode ler e-mails dos usuários do tenant?

**Decisão atual:** SIM. Auditor tem `operational.usuarios.view` e enxerga o `email` na listagem.

**Histórico:** QA E2E (Agente C, BUG-C-02) flagou como possível concern de privacidade.

**Justificativa:**
- Auditor é um papel INTERNO ao tenant (contratado pelo dono pra checar conformidade).
- E-mail é PII de baixo risco (sem CPF/conta bancária na mesma view).
- LGPD permite tratamento por interesse legítimo do controlador (dono da fazenda).

**Reabertura:** se demanda regulatória surgir, mascarar e-mail no frontend (`a***@dom.com`) sem mexer na permissão backend.

---

## 4. Vet seller / Auditor email · ressalvas formalizadas

Para evitar reabertura desnecessária, esses dois itens **não são bugs** e estão registrados aqui como decisão pra serem revisitados apenas se houver pedido explícito do produto.

---

## 5. Soft-delete vs hard-delete

| Entidade | Estratégia | Motivo |
|----------|------------|--------|
| `Animal` | SoftDelete (`deleted_at`) | histórico zootécnico precisa preservar registros |
| `AnimalLot` | Hard delete | lote vazio não tem valor histórico próprio (eventos cascadeOnDelete) |
| `User` | Hard delete | re-cadastro é instantâneo; LGPD pede direito ao esquecimento |
| `Tenant` | Hard delete (manual via master) | extremamente raro |
| `FinancialTransaction` | Hard delete | usuário pode reverter por concept (estorno) |
| `AnimalEvent` | Hard delete | cascade de Animal já cobre |

---

## 6. Tarja de impersonação

- **Posição:** fixed top:0, h:40px, z-70, bg-amber-500.
- **Mobile:** ícone + nome do tenant truncado + botão SAIR. Texto "IMPERSONAÇÃO" e "Operando como" só em sm+ (640px+).
- **Layout offset:** wrapper externo `pt-10` quando impersona (não no body, pra Vue ser fonte de verdade); aside `top-10`; header sticky `top-0` (já recebe offset do wrapper).

---

## 7. Mensagens de erro em forms

- **Backend:** Inertia retorna `errors` via `request->validate()`; `InputError.vue` renderiza inline embaixo de cada campo.
- **Frontend:** HTML5 `required` adiciona validação de browser (tooltip nativo) antes do submit chegar ao backend. **É proposital** — feedback imediato sem round-trip.
- **403 em POST:** `bootstrap/app.php` captura `UnauthorizedException` e redireciona com `flash('error', ...)` → `FlashMessages.vue` dispara toast vermelho.

---

## 8. Métricas / KPIs · fonte única

Todos os números do sistema vêm dos services em `app/Services/Metrics/` (FinancialMetrics, TarefasMetrics, AgricolaMetrics, EstoqueMetrics, MaquinasMetrics, DocumentosMetrics, ParceirosMetrics, PlatformMetrics) e `app/Services/Livestock/LivestockMetricsService`. Cache via `MetricsCache` invalidado por model `booted()` hooks. ZERO query duplicada cross-controller.

Detalhes em `qa-evidence/audit-2026-04-28/metrics-system-wide/METRICS-DESIGN.md`.

---

**Última atualização:** 2026-04-29 (commit pendente)
