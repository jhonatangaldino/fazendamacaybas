# QA CHECKLIST VISUAL — Fazenda Macaybas

> Checklist obrigatória para aprovar uma feature como "pronta".
> Cobrir TODOS os itens em DESKTOP e MOBILE (celular real, não só DevTools).

---

## REGRA DE OURO

> **Uma funcionalidade só está aprovada se um humano consegue usá-la
> pela interface sem orientação extra, tanto no desktop quanto no mobile.**

Rota 200 não é aprovação. Controller carregável não é aprovação. Backend que grava não é aprovação. Só "**o dono da fazenda clicou e funcionou**" aprova.

---

## AMBIENTES DE TESTE

| Ambiente | Resolução | Navegador |
|---|---|---|
| Desktop grande | 1440×900 | Chrome + Firefox |
| Laptop | 1280×800 | Chrome |
| Tablet | 768×1024 | Safari iOS + Chrome Android |
| **Mobile (obrigatório)** | **375×812** (iPhone SE/12 mini) | Safari iOS + Chrome Android |
| **Mobile pequeno** | **360×640** (Android entrada) | Chrome Android |

---

## CREDENCIAL DE TESTE

Um usuário operacional (role `dono_fazenda`) no tenant de demonstração. **Nunca use o admin master** (ele cai no painel SaaS Master, não opera a fazenda).

Se não houver usuário de teste, criar um via tinker:
```php
User::create(['email' => 'qa@teste.local', 'name' => 'QA', 'password' => Hash::make('senha'), 'tenant_id' => 1])
    ->syncRoles(['dono_fazenda']);
```

---

## CHECKLIST POR MÓDULO

Legenda de cada item: `[ ]` = não testado · `[D]` = OK desktop · `[M]` = OK mobile · `[DM]` = OK ambos

### 🔐 AUTENTICAÇÃO

- [ ] Abrir `/login` — form aparece limpo
- [ ] Digitar email/senha errados — mensagem de erro visível
- [ ] Login correto redireciona para `/admin`
- [ ] Menu sidebar aparece com itens legíveis
- [ ] Logout fecha sessão (tentar `/admin` depois → volta para login)

### 🏠 DASHBOARD

- [ ] Cards de KPIs aparecem com números
- [ ] Links clicáveis para módulos funcionam
- [ ] **Mobile:** cards empilham verticalmente, não quebram

### 🐄 REBANHO

**Listagem:**
- [ ] Lista de animais aparece (paginada)
- [ ] Busca por brinco retorna só o animal procurado
- [ ] Filtro de status/espécie/lote funciona
- [ ] **Mobile:** vira card com foto/brinco/raça; ações visíveis no rodapé
- [ ] **Mobile:** botão "💰 Vender animal" no header é tocável (≥44px)

**CRUD:**
- [ ] Criar animal · formulário carrega com campos por espécie
- [ ] Ao selecionar "Ave" — campos de placa/identificação individual somem
- [ ] Ao selecionar "Cão" — nome vira obrigatório
- [ ] Salvar cria o animal e aparece na lista
- [ ] Editar altera dados e reflete na lista
- [ ] Foto · upload funciona após criação

**Eventos / histórico:**
- [ ] Abrir detalhe de um animal mostra timeline de eventos
- [ ] Registrar pesagem — peso atual atualiza
- [ ] Gráfico de peso aparece (mesmo com 1 ponto)

### 💰 WIZARD DE VENDA (F4.1)

**Desktop e mobile:**
- [ ] Passo 1 · lista de animais ativos com foto, busca funciona
- [ ] Clicar num animal destaca com borda verde
- [ ] "Continuar" só habilita após selecionar
- [ ] Passo 2 · lista de compradores (clientes); lembrete do animal no topo
- [ ] Passo 3 · campos de valor (InputMoney) e data
- [ ] Passo 4 · resumo em frase clara "Você está vendendo X para Y por Z"
- [ ] "O que vai acontecer" lista os 3 efeitos em linguagem humana
- [ ] Confirmar dispara animal → vendido + FT receita
- [ ] Passo 5 mostra "Venda registrada com sucesso!" + impacto financeiro

### 📦 ESTOQUE · ITENS

- [ ] Lista com saldo, tipo, unidade
- [ ] Abaixo do mínimo fica destacado
- [ ] Cadastrar medicamento — campo "Registro MS" aparece e é obrigatório
- [ ] Cadastrar ração — descrição exige 10 caracteres contextuais
- [ ] Cadastrar combustível — campos de registro/validade não aparecem
- [ ] Editar alterando tipo limpa campos incompatíveis
- [ ] **Mobile:** scanner de código de barras abre câmera (permissão explícita)

### 📦 ESTOQUE · MOVIMENTAÇÕES

- [ ] Listagem exibe entradas/saídas com valor e saldo
- [ ] Criar entrada atualiza saldo do item na listagem anterior
- [ ] Filtros por tipo/warehouse funcionam

### 👥 PARCEIROS

- [ ] Lista com pessoa PF/PJ identificada
- [ ] Criar PF — CPF com máscara, DV validado live
- [ ] Trocar para PJ — campo vira CNPJ, máscara muda, nome fantasia aparece
- [ ] Salvar com DV inválido mostra erro amigável
- [ ] Editar e salvar reflete na lista

### 💰 FINANCEIRO · LANÇAMENTOS

- [ ] Lista com receitas em verde, despesas em vermelho
- [ ] Totais no topo (receitas/despesas/saldo)
- [ ] Criar despesa · só categorias `financeiro_despesa` no dropdown
- [ ] Trocar tipo para receita — categoria anterior é limpa
- [ ] Status "pago" pede data de pagamento
- [ ] Lançamentos criados por F2.x (colheita, venda, manutenção) mostram banner de origem
- [ ] Banner protege `numero_documento` como readonly

### 🚜 VEÍCULOS/MÁQUINAS

- [ ] Lista mostra placa, tipo, medidor
- [ ] Criar caminhão · placa+RENAVAM obrigatórios, aviso DETRAN
- [ ] Criar implemento · placa/RENAVAM somem
- [ ] Criar trator · medidor (horímetro/km) obrigatório, recomendação visual
- [ ] Manutenções · registrar com status "concluída" + valor gera FT despesa automaticamente
- [ ] Verificar no financeiro: FT com prefixo `MAINTENANCE:<id>`

### 🌾 AGRÍCOLA

- [ ] Listar talhões/culturas/safras/plantios
- [ ] Registrar plantio vincula cultura × talhão × safra
- [ ] Aplicação de adubação dá baixa automática no item de estoque correspondente
- [ ] Colheita com valor gera FT receita automaticamente (`HARVEST:<id>`)
- [ ] Plantio muda para status "colhido"

### 📄 DOCUMENTOS

- [ ] Upload arquivo (PDF, JPG) aparece no grid
- [ ] Categoria com "contrato" no nome força vínculo com parceiro
- [ ] Categoria "nota fiscal" permite vincular parceiro OU lançamento
- [ ] Trocar categoria limpa vínculo incompatível
- [ ] Download do arquivo funciona
- [ ] Excluir remove arquivo do servidor

### 👷 FUNCIONÁRIOS

- [ ] Listagem com CPF mascarado, cargo, salário
- [ ] Cadastrar CLT — CPF obrigatório com DV válido, admissão obrigatória
- [ ] Cadastrar PJ — campo vira CNPJ, sem data obrigatória
- [ ] Cadastrar safrista — início E fim obrigatórios
- [ ] Trocar tipo limpa documento incompatível
- [ ] Desligar — modal pede data com min=admissão
- [ ] Reativar limpa data_demissao

### ✅ TAREFAS

- [ ] Lista com prioridade, status, vínculo como badge 🔗
- [ ] Criar tarefa — módulo filtra tipos de vínculo
- [ ] Módulo "rebanho" só permite vincular a animal
- [ ] Módulo "maquinas" só permite veículos
- [ ] Sem responsável, botão Salvar fica travado
- [ ] Concluir/Reabrir/Editar/Excluir · **todos com ActionIcon ≥44×44 em mobile**
- [ ] Toggle de item de checklist funciona

### 🔗 INTEGRAÇÕES VISÍVEIS AO USUÁRIO

- [ ] Vender animal → lançamento de receita no financeiro (prefixo `ANIMAL_EVENT:`)
- [ ] Compra (entrada de estoque com valor) → lançamento de despesa (`STOCK_MOVEMENT:`)
- [ ] Aplicação agrícola (adubo/cal) → baixa no estoque (`FIELD_APP:`)
- [ ] Manutenção concluída com valor → lançamento de despesa (`MAINTENANCE:`)
- [ ] Colheita com valor → lançamento de receita (`HARVEST:`)

---

## CHECKLIST DE COMPORTAMENTO MOBILE

Executar em celular REAL (não Chrome DevTools).

### Navegação
- [ ] Hamburger no topo abre sidebar (animação suave)
- [ ] Clicar fora da sidebar fecha
- [ ] Clicar num item de menu navega E fecha a sidebar
- [ ] Topbar fica fixa (sticky) durante scroll
- [ ] Pull-to-refresh não interfere no conteúdo

### Toque
- [ ] Nenhum botão < 44×44
- [ ] Nenhum ícone de ação (editar/excluir) < 44×44
- [ ] Cliques em ícones não abrem ambiguidade (editar vs excluir)
- [ ] Botão Salvar fixo no fim do form, alcançável com polegar

### Teclado virtual
- [ ] Campo CPF/CNPJ abre teclado NUMÉRICO
- [ ] Campo telefone/CEP abre teclado numérico
- [ ] Campo valor (R$) abre teclado numérico
- [ ] Campo email abre teclado com `@` visível
- [ ] Campo de data abre seletor visual (flatpickr ou nativo)

### Listagens
- [ ] Cards não têm overflow horizontal
- [ ] Valores longos quebram em linha (não cortam)
- [ ] Filtros escondidos atrás de "Mostrar filtros" em páginas com 3+ filtros
- [ ] Paginação visível e tocável

### Formulários
- [ ] Labels legíveis (≥ 14px)
- [ ] Inputs text-base (16px) para evitar zoom do iOS
- [ ] Erros aparecem sob o campo em vermelho visível
- [ ] Flash de sucesso aparece claro no topo
- [ ] Scroll não "pula" ao abrir teclado

### Fluxos guiados
- [ ] Wizard de venda · botões Continuar/Voltar grandes e tocáveis
- [ ] Stepper do wizard visível em todos os tamanhos

---

## COMPORTAMENTO DE ERRO

- [ ] Sem internet: mensagem "Sem conexão" ou retry
- [ ] Backend 500: tela amigável, não stack trace
- [ ] Permissão negada: mensagem clara (não "403" cru)
- [ ] Sessão expirada: volta para login com mensagem explicativa

---

## APROVAÇÃO FINAL

Só considerar "pronto para comercialização" se:

- ✅ 100% dos itens da checklist visual passam em desktop
- ✅ 100% dos itens da checklist visual passam em mobile (celular real)
- ✅ Teste funcional HTTP (test-qa-real-user.php) → 39/39
- ✅ Nenhum log de erro 500 nos últimos 50 acessos

---

## HISTÓRICO DE EXECUÇÕES

| Data | Executor | Resultado | Notas |
|---|---|---|---|
| 2026-04-24 | Claude (automatizado) | **39/39 ✅ HTTP** | Login + navegação + CRUD + props + filtros |
| _pendente_ | Jhonatan | _a executar_ | Revisão visual desktop |
| _pendente_ | Jhonatan (celular) | _a executar_ | Revisão visual mobile |
