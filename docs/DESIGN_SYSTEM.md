# DESIGN SYSTEM — Fazenda Macaybas

> Padrão visual e de interação de todo o sistema ERP.
> Versão consolidada após **F4.2 (Mobile-first)**.

---

## PRINCÍPIOS

1. **Mobile-first**. O usuário real acessa no celular, no campo. Desktop é bônus.
2. **Toque humano ≥ 44×44 px**. Padrão WCAG 2.5.5. Qualquer botão/ícone clicável.
3. **Texto base 16 px em mobile**. Evita zoom automático do iOS em inputs.
4. **Linguagem conversacional** em telas principais. Sem jargão técnico quando evitável.
5. **Densidade progressiva**. Mobile respira; desktop adensa (`md:` reduz paddings).
6. **Zero dependência de hover**. Tudo clicável tem rótulo/ícone visível.

---

## TOKENS BÁSICOS

### Cores principais

| Token | Cor | Uso |
|---|---|---|
| `macaybas-primary` | verde escuro #166534 | botões principais, links, destaques |
| `macaybas-secondary` | amarelo | botões secundários, badges de atenção |
| `emerald-*` | verde claro | confirmações, sucesso, receita |
| `rose-*` | rosa/vermelho | despesa, erro suave |
| `red-*` | vermelho puro | exclusão, erro duro |
| `amber-*` | âmbar | alertas moderados, "faltando algo" |
| `indigo-*` | índigo | eventos automáticos, integrações |
| `slate-*` | cinza | corpo, rótulos, bordas |

### Escala tipográfica

| Uso | Mobile | Desktop |
|---|---|---|
| h1 (PageHeader title) | `text-2xl` (24px) | `text-2xl` (24px) |
| h2 (seção) | `text-xl/2xl` | `text-lg/xl` |
| Corpo principal | `text-base` (16px) | `text-sm` (14px) |
| Helper/label | `text-sm` | `text-xs/sm` |
| Badges/chips | `text-xs` | `text-xs` |

### Espaçamentos padrão

- Gap entre cards da página: `space-y-4` (mobile), `space-y-6` (desktop)
- Padding de card body: `.card-body` → `px-5 py-4`
- Gap em grid de formulários: `gap-4` (mobile), `gap-6` (desktop)
- Margin-bottom do PageHeader: `mb-6`

---

## COMPONENTES FUNDAMENTAIS

### Botões (`.btn`)

```
Mobile:   px-5 py-3 text-base   →  ~48 px altura
Desktop:  md:px-4 md:py-2 md:text-sm  →  ~36 px altura
```

**Variantes:**

- `.btn-primary` — ação dominante (verde escuro)
- `.btn-outline` — ação secundária (contorno slate)
- `.btn-secondary` — ação alternativa com destaque (amarelo)
- `.btn-danger` — exclusão/destrutiva (vermelho)
- `.btn-ghost` — ação sutil (só hover)

**Modificadores:**

- `.btn-sm` — mantém 48px mobile, compacta para 28px só em desktop
- `.btn-lg` — altura maior, usar em CTAs centrais

**NUNCA:** usar `btn-sm` sem `md:` explícito assumindo que fica pequeno sempre.

### Inputs (`.form-input`, `.form-select`, `.form-textarea`)

```
Mobile:   px-3 py-3 text-base   →  ~48 px + não dispara zoom iOS
Desktop:  md:py-2 md:text-sm    →  ~36 px para densidade
```

Acompanhar com `inputmode` adequado quando aplicável:
- `numeric` — dígitos sem separador (CEP, dígitos puros)
- `decimal` — valores com vírgula (ainda não padronizado, usar em valores livres)
- `tel` — telefones
- `email` — e-mail

`InputMoney` e `InputDate` já definem `inputmode`. `InputMasked` detecta automaticamente via máscara.

### ActionIcon (ícones em listas)

```
Default:  h-11 w-11 mobile  ·  md:h-9 md:w-9 desktop   (44/36)
size=sm:  h-10 w-10 mobile  ·  md:h-7 md:w-7 desktop   (40/28)
```

Uso:
```vue
<ActionIcon type="edit"   title="Editar"   @click="..." />
<ActionIcon type="delete" title="Excluir" @click="..." />
<ActionIcon type="power-off" variant="danger" title="Desligar" @click="..." />
```

Tipos disponíveis: `edit`, `delete`, `power-off`, `reactivate`, `pay`, `view`, `download`, `upload`, `toggle-on/off`, `link`, `pdf`, `add`, `publish`, `copy`, `drag`, `scale`, `syringe`, `heart`, `history`, `barcode`, `camera`, `reset-password`.

### DataTable

Mesmo componente, dois layouts automáticos:

- **Desktop (≥ lg)** — tabela tradicional
- **Mobile (< lg)** — cards com label/valor empilhados, ações no rodapé

Labels das colunas viram "título" dos pares label/valor em mobile.

Use:
```vue
<DataTable :columns="[...]" :rows="dados">
  <template #cell-acoes="{ row }">
    <ActionIcon type="edit" title="Editar" @click="editar(row)" />
    <ActionIcon type="delete" title="Excluir" @click="confirmDelete = row" />
  </template>
</DataTable>
```

Coluna especial `acoes` automaticamente vai pro rodapé do card em mobile.

**Colunas com hints responsivos:**

- `hideOnMobile: true` — some em telas < lg
- `hideOnDesktop: true` — só aparece em card mobile
- `primary: true` — força coluna a ser o título do card (default: 1ª)

### MobileFilters

Componente novo para páginas de listagem com 3+ filtros:

```vue
<MobileFilters cols="sm:grid-cols-4">
  <template #always>
    <input v-model="filtros.search" class="form-input" />
  </template>
  <select v-model="filtros.tipo" class="form-select">...</select>
  <select v-model="filtros.status" class="form-select">...</select>
</MobileFilters>
```

- Em mobile, só o slot `always` aparece; resto fica atrás de "Mostrar filtros".
- Em desktop, tudo junto em grid normal.

Aplicado em: Animals, Tasks, Financial Transactions, Stock Items.

### PageHeader

```vue
<PageHeader title="Animais" subtitle="Cadastro individual com histórico">
  <template #actions>
    <Link :href="..." class="btn-primary">+ Novo animal</Link>
  </template>
</PageHeader>
```

Empilha verticalmente em mobile (`flex-col sm:flex-row`), botões ficam abaixo do título.

### Cards (`.card`, `.card-body`, `.card-header`)

Container padrão de seções. Bordas arredondadas `rounded-xl`, shadow sutil, ring slate-200.

```html
<div class="card">
  <div class="card-header"><h2 class="card-title">Título</h2></div>
  <div class="card-body">conteúdo...</div>
</div>
```

---

## LAYOUT DAS TELAS

### Estrutura padrão

```
AdminLayout
├── Sidebar fixa (drawer em mobile, estática em desktop)
├── Topbar sticky (hamburger + avatar + fazenda + alertas)
└── Main
    └── <Página>
        ├── PageHeader (título + ações)
        ├── MobileFilters (se houver filtros)
        ├── Cards/Seções
        └── DataTable ou grid de cards
```

### Grids de formulário

Padrão: `grid gap-4 sm:grid-cols-2` ou `sm:grid-cols-3`.

- Em mobile vira 1 coluna naturalmente.
- Em desktop, 2-3 colunas para densidade.

**Exceções:** formulários com muitos campos (>15) devem ser:
1. Divididos em seções de `<div class="card">` separados
2. Ou convertidos em wizard (ex.: `SaleWizard`) quando o fluxo tem estado

### Botões de ação em telas com scope de mobile

Ordem de prioridade:
1. **CTA principal** à direita no PageHeader: `btn-primary`
2. **Ação secundária** ao lado dela: `btn-outline`
3. Em cards de item: rodapé com `flex-row sm:flex-col` de `ActionIcon`

---

## WIZARDS (PADRÃO ESTABELECIDO POR F4.1)

Fluxos de 3-5 passos que substituem formulários longos.

### Componentes obrigatórios

1. **Stepper no topo** — bolinhas numeradas, atual em primary com ring-4, concluídos em emerald
2. **Labels conversacionais** — "Qual animal?", "Para quem?", "Quanto?"
3. **Contexto contínuo** — cada passo mostra o que já foi escolhido
4. **Botões grandes** — `px-6 py-2.5 text-base` no mínimo
5. **Cards clicáveis** em vez de dropdowns para seleção de entidades
6. **Revisão explícita** antes de confirmar, com frase completa
7. **Feedback final celebra** — ícone grande, texto claro, próximos passos

Ver `resources/js/Pages/Admin/SaleWizard/Index.vue` como gabarito.

---

## CHECKLIST DE REVISÃO MOBILE

Ao tocar em qualquer tela:

- [ ] Abri em 375px (iPhone SE) e 414px (iPhone Pro)?
- [ ] Todos os botões e ícones são clicáveis com polegar (>= 44px)?
- [ ] Nenhum texto está abaixo de 14px (ideal: 16px para inputs)?
- [ ] Nenhum overflow horizontal?
- [ ] Forms não exigem scroll de mais de 3 "telas"?
- [ ] Listas usam DataTable responsivo (cards em mobile)?
- [ ] Filtros escondidos atrás de MobileFilters se forem 3+?
- [ ] Avisos (âmbar/vermelho) têm cor contrastante suficiente?
- [ ] Teclado numérico aparece em campos numéricos (inputmode)?

---

## RETROCOMPATIBILIDADE

Todas as alterações desta fase são **aditivas** ou **ampliações progressivas**:

- `.btn` ganhou tamanhos maiores em mobile; desktop mantém densidade via `md:`
- `.form-input` idem
- `ActionIcon` expande em mobile, mantém tamanho desktop
- `DataTable` já era responsivo, só o label ganhou `sm:w-24` em vez de `w-20`
- `MobileFilters` é opt-in; páginas antigas sem ele continuam funcionando

**Nenhum breaking change.**

---

## PROBLEMAS CONHECIDOS — TO DO

Da auditoria anterior (F4.2 não resolve todos):

1. **Formulários inline gigantes** (Employees/Vehicles/Partners) — ainda precisam virar wizards
2. **Modals em telas altas** — auditar `ConfirmModal` em mobile
3. **Flatpickr em mobile** — avaliar troca por `<input type="date">` nativo
4. **Tooltip só em hover** — em mobile não aparece; ActionIcon ainda depende de `title`

Essas ficam para **F4.3 (Wizards de CRUD)** e **F4.4 (Polimento final)**.

---

## VERSÕES

- **v1.0 (F4.2 · 2026-04-23)** — mobile-first fundamentos, MobileFilters, ActionIcon 44×44
- **v0.1 (F3 · 2026-04-23)** — UX anti-erro dos formulários (Rebanho/Estoque/etc.)
- **v0.0 (F1-F2)** — estrutura inicial, integrações cross-módulo
