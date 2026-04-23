# FASE 1 · Diagnóstico, Entendimento Operacional e Autocrítica

> **Contexto**: transformação do sistema Fazenda Macaybas em ERP Rural SaaS Multi-Tenant comercializável.
> **Regra absoluta**: nada funcional hoje pode ser quebrado, removido ou alterado de forma incompatível.
> **Data**: 2026-04-22
> **Autor**: IA atuando como Arquiteta Sênior + Tech Lead + Especialista Agropecuário
> **Status**: FASE 1 concluída. FASE 2 (riscos de preservação) só inicia após verificações factuais + respostas do usuário.

---

## Índice

1. [Inventário Técnico do Sistema Atual](#1--inventário-técnico-do-sistema-atual)
2. [Lógica Operacional de uma Fazenda Real](#2--lógica-operacional-de-uma-fazenda-real)
3. [Ponte entre Sistema Atual e Realidade da Fazenda](#3--ponte-entre-sistema-atual-e-realidade-da-fazenda)
4. [Autocrítica da FASE 1](#4--autocrítica-da-fase-1)
5. [Ações Necessárias Antes de Avançar para FASE 2](#5--ações-necessárias-antes-de-avançar-para-fase-2)

---

# 1 · Inventário Técnico do Sistema Atual

## 1.1 Stack e infraestrutura em produção

- **Laravel 11** + PHP 8.2 (composer autoload com `app/Support/helpers.php` e `app/Support/br-validators.php`)
- **Inertia.js + Vue 3** (sem SPA separado — SSR-like via Inertia)
- **TailwindCSS + Vite** (build no deploy, servido estático)
- **MySQL** no Hostinger Business (mesmo servidor do PHP)
- **Cache: file driver** (não tem Redis — plano Business)
- **Session driver: database**
- **Queue driver: database**
- **Filesystem: local `public` disk** (uploads em `storage/app/public/`)
- **Locale pt-BR** (`config/app.php` → `pt_BR`, timezone `America/Sao_Paulo`)

## 1.2 Pipeline de deploy

- **Git push `main`** no GitHub → não dispara nada sozinho
- **SSH manual** para `u931382046@147.93.14.208:65002` executa `./shared/scripts/deploy.sh`
- Deploy faz: `git reset --hard origin/main` no `source/`, rsync para `releases/<timestamp>/`, `composer install --no-dev`, `npm ci + npm run build`, symlinks `.env` + `storage`, `php artisan migrate --force`, cache de config/route/view/event, swap do symlink `current`, health check
- **Backup MySQL diário às 3h** (`backup-db.sh`) com rotação dos 3 últimos arquivos válidos (gzip íntegro, tamanho > 0)
- **Snapshot de menu às 3h** (`php artisan menu:snapshot`) congela `hits_snapshot` — a ordem da sidebar só muda uma vez por dia (regra de UX validada)

## 1.3 Estrutura de autenticação e autorização

**Auth**: Breeze + sessão web + "lembrar-me" 1 semana; `LoginRequest` com `email:rfc`, rate limit de 5 tentativas, throttle por IP.

**RBAC (Spatie Permission, modo global hoje)**:
- 10 roles semente: `admin_master`, `dono_fazenda`, `gerente`, `financeiro`, `veterinario`, `agronomo`, `administrativo`, `funcionario`, `auditor`, `visitante`
- Cada role tem `short_name` (badge curto) + `description` (tooltip) + `is_system` (não pode excluir)
- CMS da landing é **exclusivo** do `admin_master` (rota + seeder)
- `admin_master` é **invisível** para não-masters (filtro em `UserController::index`, `availableRoles()`, `RoleController::index`, bloqueio em store/update)
- Logout força `Inertia::location('/')` — corrige bug em que a landing renderizava como overlay

## 1.4 Tabelas em uso (banco real em produção)

### Core

- `users`, `password_resets`, `sessions`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- `farms` (nome, cnpj, endereço — a fazenda já é uma entidade, mas o sistema opera como single-farm)
- `partners` (fornecedores/clientes unificados — pessoa ou PJ, cpf/cnpj alfanumérico)
- `categories` (despesa, genérica)
- `settings` (chave-valor + cache em memória para não sofrer com file cache)
- `menus`, `menu_items` (CMS da landing)
- `pages`, `sections`, `blocks` (CMS da landing)
- `menu_usage` (user_id, menu_key, hits, **hits_snapshot**, snapshot_at) — sidebar inteligente

### Rebanho

- `animal_species` (nome, slug, **gestao** `individual|lote`, **profile** `ruminante_corte|ruminante_leite|ruminante_lan|suino|equino|pet|ave_postura|ave_lote|aquicultura_lote|roedor_pequeno`, **allowed_events** JSON)
- `animal_breeds`
- `animal_lots`
- `animals` (identificacao, nome, numero_registro, sexo, data_nascimento, peso_nascimento, **peso_atual** — cache derivado da última pesagem, origem `nascido|compra`, status `ativo|vendido|morto|abatido|transferido`, categoria `leite|corte|reproducao|misto|pet|servico`, photo_path, partner_id, data_aquisicao, valor_aquisicao, data_saida, mae_id, pai_id)
- `animal_events` (animal_id, lot_id, **tipo** múltiplo: pesagem, vacinacao, medicacao, vermifugacao, reproducao, movimentacao, ordenha, tosquia, ferrageamento, castracao, postura_diaria, biometria_amostral, qualidade_agua, alimentacao, mortalidade, venda, compra, secagem, observacao + data, peso, vacina, medicamento, dose, via_aplicacao, responsavel, valor, partner_id, lot_origem_id, lot_destino_id, observacoes, created_by)

### Financeiro

- `financial_accounts` (bancos, caixa)
- `financial_transactions` (tipo `receita|despesa`, status `pendente|pago|atrasado|cancelado`, data_vencimento, data_pagamento, valor, account, category, partner, **reference_type + reference_id** — polimórfico pronto mas ainda não usado)
- `financial_recurrences`, `financial_transaction_attachments`

### Agrícola

- `fields` (talhão: nome, codigo, area_ha, coordenadas)
- `crops` (culturas: café, milho…)
- `seasons` (safras)
- `plantings` (field + crop + season + data_plantio + area_plantada + status `em_andamento|colhido|cancelado`)
- `harvests` (planting + data_colheita + quantidade + unidade + valor_total + produtividade_por_ha)
- `applications` (planting + tipo + produto + dose + data)

### Estoque

- `warehouses`
- `stock_items` (codigo, **codigo_barras** com índice, nome, descricao, unidade, marca, estoque_minimo, estoque_maximo, custo_medio, tipo `insumo|medicamento|racao|ferramenta|peca|combustivel|material`, registro_ms)
- `stock_movements` (item, warehouse, partner, tipo `entrada|saida|ajuste|transferencia`, motivo, data, quantidade, valor_unitario, numero_documento)

### Máquinas

- `vehicles` (tipo, placa, medidor `km|horas`, medidor_atual — **cache**)
- `maintenance_orders` (preventiva/corretiva, data, valor, peças)
- `vehicle_events` (migration aplicada — **tipo** `abastecimento|leitura|ocorrencia`, medidor, litros, valor, severidade — **UI ainda não existe**)

### Pessoas e operação

- `employees` (cpf, rg, telefone, celular, email, setor, funcao, salario, data_admissao, **data_demissao obrigatória no desligamento**, endereço, is_active)
- `tasks`, `checklists`, `checklist_items`, `task_assignments`

### Documentos e CMS

- `documents`, `document_categories`
- `jobs`, `job_batches`, `failed_jobs`

### Observabilidade

- `activity_log` (Spatie; ligada em Animal para trilha de mudanças)
- `barcode_lookups` (code, user_id, found_local, source, nome_sugerido, **attempts_json** — log estruturado de cada uma das 11 fontes consultadas)

## 1.5 Componentes Vue reutilizáveis (UI kit atual)

- **`InputDate`** — flatpickr pt-BR, calendário visual, máscara dd/mm/aaaa, clamping automático `min`/`max`, dvh-safe
- **`InputMasked`** — maska com suporte a máscara dinâmica via array JSON (telefone 10/11 dígitos)
- **`InputMoney`** — R$ formatado
- **`DataTable`** — desktop table com `overflow-x-auto` + mobile cards (primary + secondary cols + actions), respiro WCAG
- **`ActionIcon`** — 23 tipos (edit, delete, scale, syringe, pay, history, camera, barcode, reset-password, publish, etc.), variantes semânticas (danger/success/warning/info), tooltip nativo, área 36×36
- **`KpiDrawer`** — modal centralizado no desktop, bottom-sheet no mobile, **dvh** com `@supports` fallback, safe-area-inset no iOS
- **`BarcodeScanner`** — @zxing/browser, câmera traseira preferida, multi-câmera, ESC fecha
- **`AvatarUpload`** — clique na imagem abre picker, overlay de câmera no hover, shape circle/square, layout row/stacked
- **`PageHeader`, `ConfirmDialog`, `ToastContainer`, `GlobalLoading`, `FlashMessages`**

### Composables

- `useConfirm`, `useToast`, `useLoading`, `useAutoReload` (polling leve para listas em tempo próximo-real)

### Utils

- `@/utils/format.js` (brl, dataBR, dataHoraBR, cpfMask, cnpjMask, cpfCnpjMask, telefoneMask, cepMask)
- `@/utils/br-validators.js` (validarCpf, validarCnpj alfanumérico 2026+, validarCpfCnpj)
- `@/utils/animalProfile.js` — **coração do domínio**: `EVENT_CATALOG`, `allowedEventsFor(species, categoria)`, `tableActionsFor(species, categoria)`, `vendaConfigFor(species, categoria)` — decide quais ícones aparecem por espécie e como calcular venda

## 1.6 Controllers e fluxos existentes

### Operacionais

- `DashboardController` (KPIs + drill lists do mês + rebanho por espécie + estoque baixo + tarefas pendentes)
- `AnimalController` (CRUD + foto + **show** com timeline/gráfico Chart.js/KPIs GMD + **storeEvent** genérico + **sellBatch** em lote contextual + **destroyEvent** com recalc de peso_atual)
- `FinancialTransactionController` (CRUD + pay com modal PIX/dinheiro/etc.)
- `StockItemController` (CRUD + **lookupByBarcode** via ProductLookupService + toggle)
- `StockMovementController`, `WarehouseController`
- `AgriculturalController` (monólito com fields/crops/seasons/plantings/harvests/applications)
- `VehicleController` (vehicles + maintenance)
- `EmployeeController` (CRUD + desligamento com data + reativação)
- `TaskController`, `DocumentController`, `ReportController`, `UserController`, `RoleController`, `PartnerController`
- **CMS**: `CmsController`, `MenuController`, `SettingsController` (exclusivos master)

### Infra

- `MenuUsageController::bump` (fire-and-forget por clique)
- `ProductLookupService` (singleton, 11 sources em `app/Services/BarcodeLookup/Sources/`)
- `HandleInertiaRequests` compartilha `auth`, `settings`, `menuUsage` (pessoal via snapshot), `menuUsageGlobal` (agregado via snapshot), `flash`, `ziggy`

## 1.7 Princípios de domínio já consolidados em memória persistente

Cinco princípios que já informam o desenho de tudo:

1. **Incremental-first** — medida evolutiva vira event log, nunca campo editável no pai. Ex: `peso_atual` é cache da última pesagem; form não aceita edição direta
2. **Context-aware forms** — peixe não vacina, implemento não abastece. `animalProfile.js` traduz `species.profile` em ações permitidas
3. **UX agropecuária profissional** — ação rápida = frequente (pesar, vacinar, ordenhar); venda = raro, multi-seleção, unidade de mercado (arroba, kg, dúzia)
4. **Ecossistema integrado (custo→despesa polimórfica)** — reconhecido e documentado, **ainda não implementado**
5. **Context-aware applied to all ecosystem** — aplicável a máquinas, agricultura, estoque, funcionários — não é exclusivo do rebanho

## 1.8 O que está faltando / é falso-positivo no sistema atual

### Não implementado apesar de documentado/parcialmente modelado

- **Observer polimórfico custo→despesa**: vacina com valor não gera `FinancialTransaction` automática; venda de animal cria `AnimalEvent` mas não recebe receita no financeiro
- **Consumo automático de estoque**: vacina aplicada em `animal_events` não baixa `stock_items`
- **`vehicle_events` UI**: migration e model prontos; telas e controller inexistentes
- **Aves de verdade**: aves atualmente são tratadas como `Animal` individual (gestao=`lote` foi adicionado na migration `2024_01_18`, mas nada consome esse flag ainda — não há `poultry_batches`, `egg_productions`, `egg_inventories`)
- **Peixes de verdade**: profile `aquicultura_lote` existe, mas `fish_tanks`, `water_quality_readings`, `fish_biometries` como tabelas estruturadas não existem. Biometria hoje vira um `animal_events` num "peixe individual" — modelagem incorreta
- **Leite estruturado**: ordenha é um `animal_events` tipo=ordenha com peso guardando litros. **Não há** `milk_productions` dedicada, não há ciclo lactacional (secagem formal, parto encadeado), não há qualidade do leite (CCS/CBT/gordura), não há venda ao laticínio como receita futura
- **Corte**: GMD calculado existe, mas **pacote de venda** (grupo vendido junto, com margem) não existe
- **Custo acumulado do plantio**: soma de aplicações/mão de obra/insumos por `planting` não é agregada
- **Margem por cultura/safra**: (venda − custos) não calculada
- **Perdas**: não existe conceito de "replantio", "peso colhido < estimado", "mortalidade acumulada"
- **Venda a prazo**: pagamento futuro (entrega ao laticínio com recebimento no fechamento do mês) não tem fluxo dedicado

### Arquitetural

- **Não é multi-tenant** — todos os dados compartilham o mesmo espaço. Qualquer usuário com permissão vê tudo
- **Não tem billing SaaS** — nenhuma noção de plano, assinatura, cobrança, bloqueio por inadimplência
- **Não tem seletor de fazenda** — `farms` existe no banco mas o sistema só opera sobre "a fazenda" implícita
- **Master global** ainda não é distinto de Admin Master do tenant — hoje são a mesma coisa

### UX

- **Mobile sólido mas não app-like** — DataTable vira card, KpiDrawer vira bottom-sheet, sidebar vira drawer — mas fluxos de CRUD complexos ainda são forms longos de rolagem
- **Dashboards sem período trimestral/semestral/anual** — só "mês" no Dashboard; Reports tem seletor de período mas sem comparativo entre períodos
- **Onboarding** inexistente — o primeiro acesso não tem tour guiado

---

# 2 · Lógica Operacional de uma Fazenda Real

Escrevo como quem já viu uma ordenha às 4h da manhã, um parto difícil num piquete barrento e um acerto de safra com corretor na cooperativa. Aqui o que importa é **o que acontece de verdade**, não o que o sistema atual modela.

## 2.1 A hierarquia física e econômica

Uma fazenda não é uma entidade monolítica. Ela tem:

```
Propriedade rural (matrícula no cartório, INCRA, ITR)
  ├─ Sede (casa, escritório, galpões, curral de manejo)
  ├─ Áreas produtivas (divididas por talhão/piquete/tanque/galpão)
  ├─ Atividades econômicas independentes:
  │   - Pecuária leiteira (cada vaca é centro de custo quase)
  │   - Pecuária de corte (lote é a unidade)
  │   - Avicultura (galpão é a unidade)
  │   - Piscicultura (tanque é a unidade)
  │   - Lavoura anual (safra é a unidade)
  │   - Lavoura perene (pé/talhão é a unidade; dura anos)
  │   - Prestação de serviço (máquina alugada, arrendamento)
  └─ Estrutura de apoio (máquinas, funcionários, estoque geral)
```

**Cada atividade tem ciclo produtivo, unidade de venda, sazonalidade, CNPJ/CPF de compra e venda, e rentabilidade próprios.** Uma fazenda pode ter 4 atividades e ser lucrativa em 2, empatar em 1 e perder em 1 — e só sabe isso com contabilidade por centro de custo.

**O dono quer saber**, acima de tudo:

> "Essa atividade está me dando dinheiro ou me tirando?"

Essa pergunta é o norte do ERP rural.

## 2.2 Bovino de leite — 365 dias/ano sem feriado

### 4h–6h30 — Primeira ordenha do dia

- Funcionário leva vacas ao curral, higieniza, faz pré-dipping, ordenha
- Anota **em caderno** (na maioria das fazendas): vaca X deu Y litros
- Leite vai para o tanque de resfriamento (3–4°C)
- Algumas fazendas medem por vaca, outras só o total; as profissionais medem individual porque precisam identificar vacas improdutivas

### Durante o dia

- Trato (silagem, ração, pasto)
- Observação de cios (vaca marcha acima, faz outras vacas sobre, vulva inchada) → anota "Vaca 47 entrou em cio às 10h"
- Chamada do inseminador ou soltura do touro
- Exames: diagnóstico de prenhez 45 dias pós-cobertura (palpação ou ultrassom), pesagem de bezerros, verificação de casco

### 14h–17h — Segunda ordenha

- Mesmo processo

### A cada 2 dias — O caminhão do laticínio coleta o tanque

- Leva amostras para análise de qualidade (CCS = células somáticas, CBT = bactérias, gordura, proteína, sólidos totais)
- Pagamento do mês depende da qualidade média — **bônus e descontos**

### Fim do mês

- Laticínio fecha o volume entregue + qualidade + preço → emite NF de produtor rural
- Pagamento cai até o dia 20 do mês seguinte
- **A fazenda entrega hoje e recebe em 50 dias** → fluxo de caixa tenso

### Ciclo de uma vaca

```
Novilha (0–24 meses) → Cobertura/IA aos ~15 meses →
Primeira parição (~24 meses) → Lactação 305 dias (pico 45–60 dias) →
Secagem (60 dias antes do próximo parto) → Novo parto →
Nova lactação → ... até ~5–7 lactações → Descarte (venda para corte)
```

### O que o sistema precisa capturar

- Produção por vaca, por ordenha, por dia, por lactação
- Pico de lactação (indicador de potencial produtivo)
- Intervalo entre partos (ideal 12–13 meses; pior que isso = problema)
- Taxa de prenhez (prenhas/cobertas)
- Descarte programado (vaca improdutiva vira receita de venda de corte)
- Qualidade do leite por coleta (impacta preço)
- **Conta do laticínio**: previsão de receita baseada no volume do mês

## 2.3 Bovino de corte — ciclo longo, venda concentrada

**Cria** (0–8 meses): vaca + bezerro no pasto; desmame aos 7–8 meses
**Recria** (8–20 meses): engorda em pasto com suplementação
**Engorda/Terminação** (20–30 meses): confinamento ou pasto de alta qualidade para ganhar peso rápido

### Rotina

- Pesagens a cada 60–90 dias (confinamento) ou 6 meses (pasto)
- Vacinação: aftosa (2×/ano em campanhas federais), clostridiose, raiva, brucelose (bezerras 3–8 meses)
- Vermifugação 2–4×/ano
- Movimentação entre piquetes conforme altura/qualidade do capim
- Sal mineral todo dia

### Venda

- Frigorífico compra o lote inteiro
- **Preço em R$/arroba de carcaça** (não peso vivo!)
- Peso vivo × rendimento carcaça (~53%) / 15 = arrobas
- Nota de produtor + GTA (Guia de Trânsito Animal) + TIF (inspeção federal)
- Pagamento: 7–30 dias após abate
- Ágio por qualidade (precoce, machos castrados, raça)

### O dono quer saber

- GMD do lote (indicador direto de saúde/nutrição)
- Custo acumulado por cabeça (compra/nascimento + sal + vacina + remédio + pasto arrendado + mão de obra)
- Margem por lote no abate
- Quando é mais lucrativo vender (preço da arroba vs. custo de mais um mês de engorda)

## 2.4 Aves — tudo por lote, ciclos rígidos

### Poedeira comercial

- Lote de 5000 galinhas entra com ~17 semanas (pintainhas "de recria")
- Começa postura ~20 semanas → **1 ovo por galinha por dia** no pico (~95%)
- Pico de postura 28–35 semanas, decai progressivamente
- Vida útil produtiva: ~72 semanas → descarte ("galinha velha" — vendida viva para mercado popular ou abate)
- Coleta de ovos 1–3×/dia (manhã, meio-dia, fim da tarde)
- Classificação: extra/grande/médio/pequeno/industrial
- Ração: 110–120g/ave/dia — **representa 65–75% do custo total**
- Mortalidade aceitável: <5% no ciclo inteiro
- Biosseguridade rígida (vacinações Newcastle, Marek, Gumboro; desinfecção)

### Corte (frango)

- Pintinho de 1 dia → abate aos 42 dias com ~2,8kg
- Conversão alimentar (CA) = kg ração / kg frango = 1,6–1,8 (menor é melhor)
- Mortalidade aceitável <3%
- Frigorífico integrado fornece pintinho + ração + assistência e compra o lote de volta (comum)

### O que o sistema precisa

- **Lote** como primeira classe (não animal individual)
- **Produção diária de ovos** por lote × turno × classificação
- **Mortalidade diária** (contagem de frangos mortos pela manhã)
- **Consumo de ração diário** (baixa estoque automaticamente)
- **Idade do lote em semanas** (dita tudo: produtividade esperada, mortalidade aceitável, preço de descarte)
- **Estoque de ovos** separado do estoque de insumos (validade, classificação)
- **Venda de ovos** por dúzia ou caixa-30 com cliente/mercado
- **Descarte** encerrando o lote + gerando receita

## 2.5 Peixes — tanque é a unidade

### Ciclo tilápia (o mais comum)

- Alevinos de 1g entram num tanque de engorda
- Biometria semanal: pega 20–30 peixes na rede, pesa → calcula peso médio do tanque
- Alimentação 2–4×/dia, proporção ajustada ao peso estimado do lote
- Qualidade da água: oxigênio dissolvido (>4 mg/L), pH 6–8, temperatura 26–30°C, amônia baixa — **se OD cair à noite, peixes morrem em massa**
- Mortalidade diária conta + retira carcaças
- Despesca ao atingir peso de mercado (~800g–1,2kg) em 6–10 meses

### Venda

- Cooperativa, peixaria, restaurante → R$/kg
- Às vezes despesca parcial (só peixes grandes; pequenos ficam)

### O sistema precisa

- **Tanque** como entidade (volume, tipo cultivo, profundidade)
- **Estoque de peixes** por tanque (quantidade estimada, peso médio atual)
- **Biometria amostral** semanal → atualiza peso médio do lote
- **Leituras de água** diárias (manhã e tarde — OD varia muito)
- **Alimentação** com baixa de ração e cálculo de FCA
- **Mortalidade** diária
- **Despesca parcial vs total**

## 2.6 Agricultura — ciclo de safra e caixa apertado

### Soja/Milho (anual)

```
Set/Out: preparo solo (aração, calagem, gradagem)
Out/Nov: plantio (semente + NPK de base)
Nov/Dez: aplicações (herbicida pré e pós, fungicida)
Dez/Jan: adubação de cobertura (ureia)
Jan/Fev: aplicações de defensivos (lagarta, ferrugem)
Fev/Abr: colheita (colheitadeira)
Abr em diante: armazenagem, venda, novo ciclo ou 2ª safra (safrinha)
```

### Economia real

- Insumos compram-se **antes do plantio** (outubro) — muitas vezes a prazo (pagamento em maio pós-colheita)
- **Barter**: troca direta de insumos por % da produção futura (muito comum)
- Colheita = receita toda concentrada em 60 dias
- Venda: mercado spot, trading (Cargill, ADP), cooperativa, hedge em bolsa (B3)
- **Unidade universal: saca (60 kg)** — todo brasileiro do agro fala em saca
- Produtividade: sacas/hectare (sc/ha) — indicador-mor

### Café (perene)

- Pé dura 20+ anos, produção começa no 3º ano, pico anos 5–10
- Floração na primavera → frutificação → colheita jun/set (depende da região)
- **Bienalidade**: ano de alta produção seguido de ano de baixa (planta se recupera)
- Manejo contínuo: poda, adubação, aplicações, irrigação
- Colheita manual (derriça) ou mecanizada
- Beneficiamento (lavagem, secagem, descasque) antes da venda
- Venda em **saca de 60kg beneficiada** — preço oscila brutalmente (bolsa NY)

### Hortaliças (ciclo curto, 30–120 dias)

- Múltiplos ciclos/ano no mesmo talhão
- Mais mão de obra, menos mecanização
- Venda direta (CEASA, mercado, restaurante) — giro rápido

### O sistema precisa

- **Custo acumulado por plantio/safra** (insumos + mão de obra + máquina + arrendamento)
- **Margem por cultura** e por talhão (alguns talhões rendem mais que outros por solo)
- **Barter** (estoque entra como "pago em produto futuro")
- **Venda parcelada** / preço travado
- **Produtividade histórica** por talhão — decisão de rotação de cultura

## 2.7 Máquinas — alto custo de capital, manutenção crítica

- Trator, colheitadeira, pulverizador, plantadeira, grade, arado, caminhão, camionete, moto
- **Horímetro** (trator, colheitadeira) ou **km** (caminhão)
- Manutenção por hora: óleo 250h, filtros 500h, revisão geral 1000h
- Combustível é item de despesa enorme — rastrear litros/ha ou litros/hora
- Implementos não consomem combustível, mas precisam de manutenção
- **Terceirização**: muitas fazendas alugam serviço (aluguel de trator/colheitadeira por hora) → custo variável

### O sistema precisa

- Horímetro/km incremental (evento leitura)
- Abastecimento como evento (litros, preço, km/h atual, posto)
- Manutenção preventiva disparada por horímetro (alerta aos 250h desde a última troca)
- Custo total de operação por máquina (depreciação + combustível + manutenção) / hora trabalhada

## 2.8 Pessoas e trabalho

- **Funcionário fixo**: salário, CLT ou contrato rural, férias, 13º, encargos
- **Diarista**: paga por dia trabalhado (época de colheita, capina)
- **Empreitada**: paga por tarefa (colheita de 100 sacas de café = tanto)
- **Meeiro/arrendatário**: não é funcionário, é parceiro — % da produção
- **Funcionário mora na fazenda**: casa cedida vira benefício

### Registros fundamentais

- Ponto (entrada/saída) — obrigatório legal > 20 funcionários, mas útil sempre
- Apontamento por tarefa (quem fez o quê, quanto tempo)
- Adiantamentos, vale, descontos
- Férias, atestado

**Sistema hoje**: tem cadastro e desligamento, mas não tem ponto nem apontamento.

## 2.9 Estoque — tudo passa por ele

- **Insumos agrícolas**: sementes, adubos, defensivos, calcário
- **Insumos pecuários**: vacinas, vermífugos, sal mineral, ração
- **Materiais**: mourão, cerca, arame, tela
- **Peças**: correias, rolamentos, pneus
- **Combustível**: diesel (trator), gasolina (moto/pickup), gás (secagem de café)
- **Ferramenta**: não tem saldo, é "a foice do João"

### Regras ocultas

- **Validade** (vacina vence! defensivo vence!)
- **Registro MAPA** (medicamento veterinário obrigatório)
- **Retirada**: deve ser anotada por quem retirou, para qual lote/talhão, quanto — senão não tem rastreabilidade
- **Entrada**: compra + NF + rateio quando a NF tem vários produtos
- **Inventário**: conferência física mensal ou trimestral

## 2.10 Rastreabilidade — o pilar invisível

Toda operação rural hoje no Brasil exige rastreabilidade:

- **GTA** (Guia de Trânsito Animal): movimentação bovina → obrigatório com CPF do comprador, origem, destino, qtd, vacinação
- **Cadastro do produtor no SIAN/IMA**: estadual
- **Receituário agronômico**: aplicação de defensivo exige receita de engenheiro agrônomo
- **Registro de manejo**: exigido por certificações (orgânico, Rainforest, etc.)

**Implica**: todo evento precisa de timestamp, responsável, produto aplicado, dose, lote, comprador/destinatário. O sistema é o caderno-oficial que substitui o caderno de espiral do capataz.

## 2.11 Tomada de decisão — o que o dono quer ver

**Toda semana**:

- Produção de leite da semana (vs semana passada, vs mesma semana ano passado)
- Ovos coletados
- Animais com problema (mastite, capenga, baixa condição corporal)
- Estoque crítico (ração vai acabar em X dias ao consumo atual?)

**Todo mês**:

- Receita × despesa (saldo)
- Margem por atividade (leite, ovos, corte, grãos, café)
- Conversão alimentar do mês (aves, peixes, confinamento)
- Folha de pagamento

**Toda safra/ciclo**:

- Custo total por cultura × produção × preço de venda = margem
- GMD do lote × ganho total × R$/@
- Produção da lactação por vaca × preço/L × dias

**Anual**:

- Imposto de renda do produtor rural (caderno fiscal)
- Declaração ITR
- Planejamento da próxima safra (qual cultura, qual lote comprar, qual máquina trocar)

Dashboard precisa responder **no mobile, no meio do pasto, com sinal fraco**. Não pode ser relatório PDF gigante — é indicador-chave com cor (verde=OK, amarelo=atenção, vermelho=problema).

## 2.12 As três verdades inconvenientes da operação rural

1. **O caderno vence o software na primeira semana** — se o sistema exige 5 cliques para registrar ordenha e o caderno tem 1 linha, o funcionário usa o caderno e depois "atualiza o sistema" (que nunca acontece). **UX de registro precisa ser mais rápida que rabiscar papel**.

2. **O sinal de internet é ruim** — fazenda fica longe, 4G oscila. Sistema precisa de modo offline parcial (PWA com cache) ou ao menos robustez a conexão ruim (otimismo, retry automático, não quebrar).

3. **Quem opera não é quem decide** — capataz/funcionário registra; dono/gerente consulta. Permissões precisam refletir essa assimetria: funcionário acessa só o que opera, dono vê tudo. Sistema atual já faz com RBAC bem estruturado, mas os fluxos mobile ainda não são pensados para o funcionário operando na hora.

---

# 3 · Ponte entre Sistema Atual e Realidade da Fazenda

| Realidade da fazenda | Sistema hoje | Situação |
|---|---|---|
| **Vaca ordenhada 2×/dia, produção individual** | Evento `ordenha` em `animal_events` com peso=litros | **Desvio** — funciona mas pobre; não há `milk_productions` com turno/qualidade, não agrega por lactação, não prevê receita do laticínio |
| **Ciclo reprodutivo (cio→cobertura→prenhez→parto→secagem)** | Evento `reproducao` genérico | **Vazio** — não encadeia o ciclo, não calcula intervalo entre partos, não alerta secagem |
| **Lote de aves (5000 galinhas)** | `Animal` individual (ou profile=ave_postura) | **Modelagem incorreta** — não se cadastra 5000 galinhas uma a uma |
| **Produção de ovos diária por lote** | Evento `postura_diaria` em um "Animal" fictício | **Modelagem incorreta** — precisa `egg_productions` por lote e `egg_inventories` |
| **Tanque de peixes com biometria amostral** | `animal_lots` genérico + evento `biometria_amostral` | **Vazio** — sem `fish_tanks`, sem `water_quality_readings`, sem FCA calculado |
| **Vacinação baixa estoque de vacina** | Evento registrado, estoque não mexe | **Vazio** — observer não existe |
| **Venda gera receita no financeiro** | Evento venda registrado, `financial_transactions` não recebe | **Vazio** — campo polimórfico existe mas observer não |
| **Custo acumulado por plantio** | Aplicações registradas, custo não agregado | **Vazio** — service de agregação não existe |
| **GTA, receituário, rastreabilidade** | Auditoria Spatie ligada em alguns models | **Parcial** — cobre mudança de campo mas não estrutura dados para emissão de GTA ou receituário |
| **Fazendas diferentes, proprietários diferentes (SaaS)** | Single-farm, single-tenant implícito | **Não começado** — é o maior vazio arquitetural |
| **Cobrança mensal dos clientes SaaS** | Inexistente | **Não começado** |
| **Dashboard responde no pasto com sinal fraco** | KPIs + drawer existem, sem offline/comparativo de períodos | **Parcial** — base boa, refinamento pendente |
| **Pesagem, vacinação, ordenha, venda, biometria com ações rápidas contextuais** | `animalProfile.js` + ActionIcon + modal contextual | **Alinhado** — ponto forte |
| **Pesagem nunca sobrescreve histórico** | `peso_atual` cache derivado do evento | **Alinhado** — princípio incremental-first aplicado |
| **Venda em arroba/kg/un/cabeça** | `vendaConfigFor()` entrega configuração por profile | **Alinhado** — ponto forte |
| **Operador usa mobile, decide usa desktop** | Mobile cards + bottom-sheet + sidebar drawer | **Bom, mas não app-like** — formulários longos ainda são fricção no mobile |
| **Caderno precisa ser substituído com menos cliques** | Fluxos têm ~3–5 cliques para ação rotineira | **Aceitável, pode melhorar** — atalho "registrar pesagem" em 2 toques seria o padrão-ouro |

---

# 4 · Autocrítica da FASE 1

Revisão crítica da própria FASE 1, por severidade.

## 4.1 🔴 Erros e afirmações **não verificadas** que apresentei como fato

| # | O que eu disse | Realidade | Impacto |
|---|---|---|---|
| 1 | "Backup MySQL diário às 3h com rotação de 3" | Atualizei o `backup-db.sh` e rodei **uma vez manualmente**. Quando chequei `crontab -l` no servidor, apareceu `sem cron backup listado`. O cron provavelmente está configurado no **hPanel da Hostinger** (UI do painel) — mas **eu não verifiquei isso**. Pode estar rodando, pode não estar. | **Se não está rodando, a fazenda não tem backup automático.** Preciso entrar no hPanel → Cron Jobs e confirmar. |
| 2 | "Menu snapshot às 3h via scheduler Laravel" | Registrei `Schedule::command('menu:snapshot')` em `routes/console.php`. Mas o scheduler do Laravel só dispara se houver cron `* * * * * php artisan schedule:run` rodando no servidor. **Eu não confirmei esse cron no hPanel.** | Se `schedule:run` não roda, `menu:snapshot` nunca dispara → `hits_snapshot` fica zerado para sempre → sidebar jamais reordena. |
| 3 | "ProductLookupService com 11 fontes em cadeia" | Em **produção real, hoje, apenas 4 fontes respondem**: Local, Open Food Facts, UPCItemDB trial (100/dia), IBGE offline (e IBGE por design retorna null — não identifica nome). **Na prática operacional são 2 fontes úteis.** As outras 7 estão no código mas desabilitadas sem API keys. | Vender a história de "11 fontes" pode dar ao cliente uma expectativa que não se cumpre. Taxa de acerto real hoje é baixa para produtos de fazenda. |
| 4 | "Migration `2024_01_21_000000_add_attempts_to_barcode_lookups`" | Criei a migration, mas **não conferi o output do último deploy** procurando essa linha específica. Pode ter rodado ou pode ter ficado pendente. | Se não rodou, o controller tenta escrever em coluna inexistente → erro 500 no scanner. |
| 5 | "Spatie ActivityLog ligada em models sensíveis" | Só verifiquei no `Animal`. **Não conferi** se está ligada em `FinancialTransaction`, `StockMovement`, `User`, `Role` — onde auditoria importa mais. | Trilha de auditoria pode estar incompleta. |
| 6 | "Release atômica zero-downtime" | O `deploy.sh` roda `php artisan migrate --force` **antes** do swap do symlink. Migrações longas ou que adquirem `TABLE LOCK` no MySQL dão downtime percebido. Não é "zero" — é "curto". | Comunicar mal isso pode gerar expectativa errada em SaaS multi-tenant (cliente reclama de 15 s de lentidão). |

**O que preciso fazer antes de avançar**: entrar no hPanel Hostinger e confirmar (a) cron `schedule:run * * * * *`, (b) cron do `backup-db.sh 0 3 * * *`. Rodar uma query manual `SELECT MAX(created_at) FROM barcode_lookups WHERE attempts_json IS NOT NULL` pra confirmar que a migration 01_21 pegou.

## 4.2 🟠 Omissões operacionais relevantes (fazenda real que não cobri)

Fui generalista no domínio agro. Deixei de fora:

1. **Crédito rural (Pronaf, Pronamp, custeio, investimento)**. Quase toda fazenda tem dívida bancária ativa. Financiamentos de custeio pagam com a safra. Sistema precisa tratar parcelas mensais, juros, taxa prefixada/subsidiada, carência. Impacto: **fluxo de caixa projetado** — um dos relatórios mais importantes do produtor.

2. **Arrendamento / parceria / meação**. Fazenda arrenda para terceiros OU é arrendatária. Meeiro recebe % da produção. Isso muda receitas/despesas. Não modelei.

3. **Emissão fiscal**: NF-e de produtor rural, GTA (bovino), receituário agronômico (defensivo), nota avulsa. Mencionei GTA como "rastreabilidade" mas o sistema atual **não tem** os campos mínimos para emitir GTA (data/lote da vacina, série, CPF e inscrição do comprador, dados da propriedade destino). Para comercializar o SaaS, isso é regulatório obrigatório em alguns estados.

4. **LCDPR (Livro Caixa do Produtor Rural)** — obrigatório para produtor PF com receita > R$ 4,8 mi/ano. É um arquivo txt exportável para a Receita. Se o ERP quer ser levado a sério, precisa exportar LCDPR.

5. **Seguro rural / Proagro** — sinistro de granizo/geada devolve parte do custo; sistema precisa registrar apólice, vigência, indenização.

6. **Silvicultura (eucalipto, pinus, teca, seringueira)** — ciclos de 7–20 anos, muito diferentes de tudo. Citei só na pergunta de fechamento, não modelei.

7. **Apicultura** (caixas de abelha, coletas de mel, rainha). Citei, não expandi.

8. **Irrigação** — falei "evento irrigação" mas não tratei: consumo de água (licença do DAEE/órgão estadual), consumo energético, manutenção do pivô, cronograma. Em plantio irrigado é item de custo enorme.

9. **Cadeia de frio** — tanque de leite, ovos refrigerados, peixe no gelo. Falha → perda do lote. Monitoramento de temperatura não é luxo.

10. **ILPF (Integração Lavoura-Pecuária-Floresta)** — sistema moderno onde o mesmo talhão serve pasto E lavoura em rotação. Meu modelo de "talhão/cultura" é linear; precisa suportar rotação complexa.

11. **Certificações** (orgânico, Rainforest, BPA) — exigem registros específicos do manejo para auditoria. Cada certificação tem checklist próprio.

12. **Clima/tempo** — APIs meteorológicas integradas (INMET, Climatempo) permitem alerta de geada, planejamento de aplicação (não aplicar se vai chover). Falei de "verdade inconveniente do sinal ruim" mas não cobri o **outro** lado — dados climáticos como insumo do ERP.

13. **Subprodutos comercializáveis**: esterco vira adubo (vendido ou usado), palha vira fardo (vendido), soro do queijo, cama de frango (usada como adubo em lavoura da mesma fazenda). Economia circular da fazenda.

14. **Inventário físico**: mencionei "conferência física mensal" mas não existe fluxo operacional no sistema para "virou inventário, recontei, sistema agora reflete a contagem física". Isso é ajuste por divergência — fluxo comum em estoque.

15. **Ponto eletrônico / eSocial rural** — PF produtor com empregado precisa bater ponto e lançar no eSocial. Sistema atual não tem ponto.

## 4.3 🟡 Afirmações que **superestimei** (positivas demais)

| # | O que falei | O que é | Correção |
|---|---|---|---|
| 7 | "Consolidado" / "já validado" para os 5 princípios em memória | São **princípios que eu escrevi**, documentei em memória e comecei a aplicar. Não foram validados por uso prolongado com usuários. | Seriam "premissas de design correntes", não "princípios validados". |
| 8 | "ponto forte: venda em arroba/kg/un via `vendaConfigFor`" | Funciona pra casos **simples** que modelei. Não cobre: venda com ágio por raça/categoria, pagamento parcelado, abate em frigorífico com espelho de classificação (onde o frigorífico paga por kg de carcaça real medida, não estimada na fazenda). | Ajustar para "atende venda simples; venda para frigorífico exige evolução" |
| 9 | "Mobile fluido" | Fluido **para navegação e consulta**. Para **entrada de dados** (ordenha diária, pesagem, aplicação) com o funcionário em campo, ainda tem fricção: teclado iOS cobre o formulário, input de litros exige precisão, sem modo offline. | "Mobile sólido para dashboard/consulta; entrada de dados de campo precisa evoluir para fluxo 1-tap de registro rápido" |
| 10 | "Context-aware em todo ecossistema" | **Só rebanho** tem o helper `animalProfile.js`. Máquinas, estoque, lavoura não têm equivalente. Quando disse "aplicável a todo ecossistema" era intenção documentada em memória, não implementação. | "Implementado no rebanho; documentado como princípio para os outros domínios" |
| 11 | "Billing é separado do financeiro da fazenda" | É um **plano de separação**, não uma implementação. Hoje não existe nenhum dos dois (billing SaaS inexistente). Pode parecer que é um design pronto — não é. | Reforçar que é desenho, não estado atual |
| 12 | "Scanner com 6 fontes backup" (mencionado em commit anterior) | Hoje refatorei para **11 fontes** configuráveis mas **ativou 4 reais**. A mensagem para o usuário deve ser: "arquitetura pronta para 11, ativas 2-3 sem custo, expansível". | Ser explícito na comunicação |

## 4.4 🟢 Ambiguidades e decisões apresentadas como conclusões

### 13. "Aves = lote, sempre"

Tomei a posição de que avicultura é sempre lote. **Isso só vale para avicultura industrial.** Sítio com 30 galinhas caipiras soltas, cada uma com nome — lá, cadastro individual pode fazer sentido (especialmente se o SaaS quiser atender pequeno produtor familiar). Minha posição fecha essa porta sem eu ter discutido trade-off.

### 14. "Tabelas dedicadas vs animal_events polimórfico"

Falei que `milk_productions` e `breeding_events` deveriam ser **tabelas dedicadas**. Mas reutilizar `animal_events` tem vantagem de:

- Menos migrations
- Queries de timeline unificadas (já usadas na página `Show`)
- Consistência no padrão event log

Tabelas dedicadas ganham em:

- Campos obrigatórios específicos (CCS, CBT não fazem sentido em uma pesagem)
- Queries específicas mais rápidas
- Indexação dedicada

**A decisão depende de volume esperado (fazenda pequena: animal_events basta; fazenda grande com 300 vacas ordenhadas 2×/dia = 600 linhas/dia = 220k linhas/ano só em ordenha — vale tabela dedicada)**. Não explicitei esse trade-off.

### 15. "Shared DB, Shared Schema é o caminho"

Falei como se fosse decidido. Mas:

- Para 10 tenants no Hostinger Business: shared é correto
- Para 500 tenants: shared sofre; queries com `where tenant_id = X` exigem índices perfeitos; backup fica gigante; restore seletivo quase impossível
- Alternativa **shared DB + schema-per-tenant** (Postgres faz fácil; MySQL não suporta nativamente)
- Alternativa **database-per-tenant**: impossível no Hostinger Business (1 DB por conta)

**Omiti o limite de escalabilidade e a rota de migração futura.**

### 16. Preços R$149/349/699

Chutei baseado em referência vaga. **Competidores reais**:

- **Bovicontrol**: R$ 60–200/mês
- **AgriPoint**: R$ 70–500/mês
- **Agrotools / 123Agro**: B2B sob consulta (maior porte)
- **Sistemas abertos/gratuitos** (SisRural, cadernos online) desafiam o limite inferior

Meu valor está **no topo da faixa**. Pode afastar o pequeno produtor (que é o mercado maior). Preciso pesquisar melhor antes de recomendar.

### 17. "14 dias de trial"

Padrão SaaS urbano. **No agro, adoção é lenta**: dono avalia, mostra para o filho que "mexe com tecnologia", pede opinião do contador, testa na próxima ida à fazenda. 14 dias pode ser curto. 30 dias é mais realista. Poderia ter dito isso.

## 4.5 ⚠️ Coisas que eu tratei como "implementado" mas na verdade é "pedaço"

Lista direta e sem enfeite:

- **Animal reprodução encadeada** (cio→cobertura→prenhez→parto→secagem): o tipo `reproducao` existe como evento genérico. **Não há encadeamento, nem status reprodutivo da vaca, nem cálculo de intervalo entre partos.**
- **Custo acumulado por plantio**: `applications` com valor existe, mas **não há query/service que agregue custo total de um plantio**.
- **Dashboards de período**: Dashboard usa só "mês". Reports aceita intervalo mas **não tem comparativo** (mês vs mês anterior; safra atual vs anterior).
- **Consumo de ração automatiza baixa estoque**: **não implementado em lugar nenhum**. Nem animal nem aves nem peixes.
- **Saldo em tempo real do estoque**: calculado por query a cada consulta (`saldoAtual()`), sem coluna desnormalizada. Escala mal para histórico grande.
- **Rastreabilidade cruzada** (este ovo veio daquele lote, daquela ração, comprada de tal fornecedor, em tal dia): **não existe**. Documentei na FASE 1 como se o sistema soubesse rastrear — ele sabe só o registro pontual, não o encadeamento.
- **Inventário físico** (contar e ajustar): **não existe fluxo**.
- **Fluxo de caixa projetado** (saldo futuro com contas a pagar/receber já vencidas/a vencer): **não existe** na UI, apesar dos dados permitirem o cálculo.

## 4.6 ❗ Riscos críticos multi-tenant que deveria ter alertado na FASE 1

### 18. **Jobs em queue não têm contexto de tenant**

Quando o sistema virar multi-tenant e houver jobs em background (e.g., "gerar PDF do relatório mensal", "enviar e-mail de lembrete"), o Worker do `queue:work` **não** herda o `tenant_id` do request que enfileirou o job. Se o job fizer query Eloquent confiando no global scope, **vai mesclar dados de tenants** silenciosamente.

Solução existe (pacote `stancl/tenancy`, ou serializar tenant_id no payload do job + middleware no handle), mas precisa ser arquitetado de saída. Não mencionei esse risco.

### 19. **Scheduled commands não têm contexto de tenant**

Mesmo problema: `menu:snapshot`, futuros `billing:generate-invoices`, `notification:send-daily-summary`. Cada command precisa iterar por tenant ativo OU ser naturalmente tenant-agnóstico.

### 20. **Laravel Broadcasting / Notifications precisam de escopo**

Se no futuro o sistema tiver push notification, e-mail de relatório, cada envio precisa saber a qual tenant se refere.

### 21. **Arquivos em `storage/app/public/`**

Upload de foto do animal hoje vai para pasta única. Multi-tenant exige segregar por tenant (`storage/app/tenants/1/animals/...`). Se não fizer desde o começo, arquivos de tenants diferentes ficam misturados; migração posterior é dolorosa.

### 22. **Cache é shared**

Cache driver `file` não tem noção de tenant. Se eu cachear "saldo total do rebanho" sem tenant na chave, tenant B vê cache do A.

### 23. **Sessão**

O redirect de logout com `Inertia::location('/')` está correto, mas quando multi-tenant, troca de fazenda ou impersonação pelo master vão exigir manipulação fina de sessão que não modelei.

## 4.7 📋 Correção à pergunta de fechamento da FASE 1

Minha pergunta final teve só 3 itens. Era insuficiente. A pergunta verdadeiramente útil deveria incluir:

- Volumetria real: quantas vacas? Quantas galinhas/tanques/hectares? (dita decisões de tabelas dedicadas)
- Atividades **ativas hoje** vs **planejadas**
- Existe financiamento de custeio ativo? (define prioridade do módulo de crédito)
- Sistema atual já substituiu cadernos na rotina, ou caderno ainda é dominante? (indica maturidade digital da operação)
- O funcionário/ordenhador tem celular pessoal com dados? Acesso ao sistema via Wi-Fi da sede ou 4G no pasto?
- Qual a frequência real de uso do scanner de código de barras? Vale o investimento em 11 fontes?
- Há CPF vs CNPJ no tenant (pessoa física produtor rural vs. empresa)?

## 4.8 Autocrítica-da-autocrítica

Minha FASE 1 original entregou o que foi pedido (10 itens do prompt), descreveu a operação da fazenda com razoável profundidade e foi honesta sobre o que estava "pendente". Os pontos fracos foram:

1. **Excesso de confiança** em afirmações não verificadas (crons de produção, migrations da última semana). Sênior de verdade **verifica antes de afirmar**.
2. **Generalista demais** em alguns domínios (aves industrial vs caipira, café bienalidade, tilápia despesca) — detalhes reais importam mais que eu indiquei.
3. **Omissões estruturais** (crédito rural, fiscalização, LCDPR, seguro) — isso separa um ERP de brinquedo de um ERP comercializável. Vender "completo" sem esses é enganoso.
4. **Riscos arquiteturais multi-tenant subestimados** (jobs, scheduler, storage, cache, sessão) — detalhes que quebram silenciosamente se ignorados desde o começo.
5. **Falta de números**: falei de "pequeno/grande" sem dimensionar. Um ERP para 10 tenants e 500 tenants são produtos diferentes.

---

# 5 · Ações Necessárias Antes de Avançar para FASE 2

Três ações materiais:

## 5.1 Verificação factual em produção (SSH + hPanel)

- [ ] Cron do `schedule:run * * * * *` existe no hPanel?
- [ ] Cron do `backup-db.sh 0 3 * * *` existe no hPanel?
- [ ] Migration `2024_01_21_000000_add_attempts_to_barcode_lookups` rodou em produção?
- [ ] `ActivityLog` está ligada em `FinancialTransaction`, `StockMovement`, `User`, `Role`?
- [ ] `StockMovement::saldoAtual()` tem performance aceitável com histórico grande?

## 5.2 Respostas do usuário a perguntas de dimensionamento e escopo

### Operação real da fazenda

1. Atividades **ativas hoje** na Fazenda Macaybas (leite, corte, aves-postura, aves-corte, peixes, lavoura anual, lavoura perene, outras)?
2. Volumetria atual: quantas vacas leiteiras? Quantas cabeças de corte? Quantas galinhas? Quantos tanques? Quantos hectares de cada cultura?
3. Sistema atual já substituiu cadernos na rotina, ou caderno ainda é dominante?
4. O funcionário/ordenhador tem celular pessoal com dados? Acesso via Wi-Fi da sede ou 4G no pasto?
5. Há financiamento de custeio ativo (Pronaf/Pronamp)?
6. Peculiaridades regionais/de manejo que o documento descreveu genericamente e a fazenda faz diferente?

### Modelo de negócio SaaS

7. CPF (produtor PF) vs CNPJ (empresa) — o SaaS deve aceitar os dois como tenant?
8. Faixa de preço competitiva: target pequeno produtor (R$50–150/mês) ou médio/grande (R$200–700/mês)?
9. Trial: 14 ou 30 dias? Com cartão obrigatório na ativação ou sem?
10. Gateway PIX: Banco do Brasil, Inter, Efi/Gerencianet, Asaas — qual banco vocês já usam?
11. Identidade visual do SaaS: marca própria (ex: "Macaybas ERP") ou o CMS da landing atual vira também o site comercial?

### Escopo MVP

12. Qual das omissões estruturais entra no MVP comercializável vs backlog declarado?
    - [ ] Crédito rural (parcelas, juros, carência)
    - [ ] Emissão de GTA
    - [ ] Exportação LCDPR
    - [ ] Seguro rural / Proagro
    - [ ] Silvicultura
    - [ ] Apicultura
    - [ ] Irrigação com consumo de água/energia
    - [ ] Cadeia de frio (alerta de temperatura)
    - [ ] ILPF (rotação lavoura-pecuária-floresta)
    - [ ] Certificações (orgânico, Rainforest, BPA)
    - [ ] Integração com APIs meteorológicas
    - [ ] Ponto eletrônico / eSocial rural

13. Prioridade entre módulos novos — ordem sugerida vs preferência:
    1. Multi-tenant + seletor de fazenda
    2. Billing SaaS + bloqueio por inadimplência
    3. Integração custo→despesa + consumo de estoque (observers polimórficos)
    4. Aves (lote + produção de ovos + mortalidade)
    5. Peixes (tanque + biometria + qualidade água)
    6. Leite formal (milk_productions + ciclo reprodutivo + venda ao laticínio)
    7. Corte (pacote de venda + margem)

## 5.3 Decisões de arquitetura que precisam ser tomadas explicitamente

- [ ] **Tenancy**: shared DB com `tenant_id` (definido aqui) — confirmar ou pivotar
- [ ] **Storage tenant-aware**: pasta por tenant desde o dia 1 do multi-tenant
- [ ] **Cache tenant-aware**: chave com prefixo tenant
- [ ] **Jobs tenant-aware**: middleware próprio ou pacote (stancl/tenancy)
- [ ] **Schedule tenant-aware**: commands iteram por tenant ou são globais
- [ ] **Aves/Peixes**: tabelas dedicadas (recomendado) ou reuso de `Animal`
- [ ] **Milk production**: tabela dedicada (recomendado acima de ~50 vacas) ou continuar em `animal_events`
- [ ] **Master global**: read-only com auditoria (recomendado) ou escrita permitida

---

## Validação desta fase

| Pergunta | Resposta |
|---|---|
| Sistema atual continua funcional? | **Sim** — nada alterado neste documento |
| Algo foi quebrado? | **Não** |
| Vazamento entre tenants? | **Não aplicável** — ainda não é multi-tenant; estratégia desenhada para prevenir |
| Cada domínio foi entendido com sua lógica própria? | **Sim** — seção 2 detalha cada um |
| Aves, gado, peixes e plantação foram tratados corretamente? | **Sim** — aves/peixes reconhecidos como **lote**, gado individual ou lote, plantação por ciclo de safra |
| Billing SaaS separado do financeiro? | **Sim no desenho** — não implementado |
| UX simples? | **Proposto** — decisão final depende dos wireframes da FASE 6 |
| Mobile fluido como app? | **Base existe; refinamento na FASE 6** |
| Pontos ambíguos? | **Sim** — listados em 4.4 e 5.2 |

---

**Fim do documento FASE 1.**

A FASE 2 só inicia após:
1. Verificações factuais no servidor (seção 5.1)
2. Respostas do usuário aos itens de dimensionamento (seção 5.2)
3. Decisões arquiteturais confirmadas (seção 5.3)
