<script setup>
/**
 * Hub de ações — "O que você quer fazer?"
 *
 * Porta de entrada do sistema. Substitui o dashboard como tela inicial.
 *
 * Design rules:
 * - Linguagem do fazendeiro, não do sistema ("Pesar um bicho", não "Registrar pesagem").
 * - Agrupamento por FREQUÊNCIA (todo dia / essa semana / safra / ocasional),
 *   NUNCA por módulo. O fazendeiro não pensa "agora vou no módulo financeiro".
 * - Cards grandes (touch-friendly, ≥140px altura), emoji grande pra reconhecimento
 *   imediato sem leitura.
 * - Mobile first: 2 colunas em tela pequena, 3-4 no desktop.
 * - Filtragem por permissão no front: card que o usuário não pode usar não aparece.
 * - Estado vazio amigável se o usuário não tiver nenhuma permissão.
 *
 * Integração com os wizards:
 * - Hoje só "Vender boi" tem wizard real (admin.fluxos.venda-animal). Os outros
 *   linkam pras telas de módulo existentes. Conforme wizards forem sendo criados,
 *   as `rota` + `query` dos cards trocam pelos novos fluxos guiados (1 linha por card).
 * - Cards com fluxo guiado recebem `destaque: true` → badge "Passo a passo".
 */
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const perms = computed(() => page.props.auth?.user?.permissions ?? []);

function can(permission) {
    const p = perms.value;
    if (!Array.isArray(p)) return false;
    return p.includes(permission);
}

// Cumprimento pelo horário — toque humano, não texto de sistema
const cumprimento = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Bom dia';
    if (h < 18) return 'Boa tarde';
    return 'Boa noite';
});

const primeiroNome = computed(() => {
    const nome = user.value?.name || '';
    return nome.split(' ')[0] || '';
});

/**
 * Catálogo das 27 operações reais organizadas em 4 grupos de frequência.
 * Cada item tem:
 *   nome      — fala de fazendeiro (sem jargão técnico)
 *   desc      — uma linha explicando o que acontece
 *   emoji     — reconhecimento visual imediato
 *   rota      — nome da rota Laravel (atualmente aponta pra tela de módulo)
 *   query     — opcional: querystring que pré-seleciona filtro/tipo na tela destino
 *   perm      — permissão necessária (usada pra filtrar no front)
 *   destaque  — true se já existe wizard guiado pronto
 */
const grupos = [
    {
        id: 'hoje',
        titulo: 'Todo dia',
        subtitulo: 'O que você faz com mais frequência',
        emoji: '🌅',
        tom: 'primary',
        itens: [
            { nome: 'Pesar um bicho',                  desc: 'Anotar o peso do animal',          emoji: '⚖️',  rota: 'admin.rebanho.animais.index',    perm: 'operational.rebanho.eventos.create' },
            { nome: 'Anotei uma despesa',              desc: 'Dinheiro que saiu',                emoji: '💸',  rota: 'admin.financeiro.transacoes.create', query: { tipo: 'despesa' }, perm: 'operational.financeiro.transacoes.create' },
            { nome: 'Chegou mercadoria',               desc: 'Entrou produto — lançar nota',     emoji: '📦',  rota: 'admin.estoque.movimentos.index',  perm: 'operational.estoque.movimentos.create' },
            { nome: 'Paguei uma conta',                desc: 'Baixar conta aberta',              emoji: '💳',  rota: 'admin.financeiro.transacoes.index', query: { status: 'pendente', tipo: 'despesa' }, perm: 'operational.financeiro.transacoes.update' },
            { nome: 'Recebi dinheiro',                 desc: 'Entrou dinheiro no caixa',         emoji: '💰',  rota: 'admin.financeiro.transacoes.create', query: { tipo: 'receita' }, perm: 'operational.financeiro.transacoes.create' },
            { nome: 'Mandar alguém fazer serviço',     desc: 'Distribuir tarefa pro peão',       emoji: '📋',  rota: 'admin.tarefas.index',             perm: 'operational.funcionarios.tarefas.create' },
            { nome: 'Terminei o serviço',              desc: 'Marcar tarefa como feita',         emoji: '✅',  rota: 'admin.tarefas.index',             query: { status: 'pendente' }, perm: 'operational.funcionarios.tarefas.update' },
            { nome: 'Usei do estoque',                 desc: 'Saída de material do galpão',      emoji: '📤',  rota: 'admin.estoque.movimentos.index',  perm: 'operational.estoque.movimentos.create' },
        ],
    },
    {
        id: 'semana',
        titulo: 'Essa semana',
        subtitulo: 'Coisas do manejo e da roça',
        emoji: '📅',
        tom: 'sky',
        itens: [
            { nome: 'Vacinar o gado',          desc: 'Registrar vacinação',              emoji: '💉',  rota: 'admin.rebanho.animais.index',    perm: 'operational.rebanho.eventos.create' },
            { nome: 'Dar remédio pro bicho',   desc: 'Registrar medicação',              emoji: '💊',  rota: 'admin.rebanho.animais.index',    perm: 'operational.rebanho.eventos.create' },
            { nome: 'Vermifugar',              desc: 'Aplicar vermífugo no gado',        emoji: '🧴',  rota: 'admin.rebanho.animais.index',    perm: 'operational.rebanho.eventos.create' },
            { nome: 'Passar veneno na roça',   desc: 'Herbicida, fungicida, inseticida', emoji: '🌿',  rota: 'admin.agricola.aplicacoes.index', perm: 'operational.agricola.aplicacoes.create' },
            { nome: 'Adubar a roça',           desc: 'Aplicar adubo no talhão',          emoji: '🌱',  rota: 'admin.agricola.aplicacoes.index', perm: 'operational.agricola.aplicacoes.create' },
            { nome: 'Arrumar máquina',         desc: 'Manutenção de trator, caminhão',   emoji: '🔧',  rota: 'admin.maquinas.manutencoes.index', perm: 'operational.maquinas.manutencoes.create' },
            { nome: 'Mudar bicho de pasto',    desc: 'Trocar animal de lote',            emoji: '🐄',  rota: 'admin.rebanho.animais.index',    perm: 'operational.rebanho.eventos.create' },
            { nome: 'Anotar coisa do bicho',   desc: 'Cio, observação, cobertura',       emoji: '📝',  rota: 'admin.rebanho.animais.index',    perm: 'operational.rebanho.eventos.create' },
        ],
    },
    {
        id: 'safra',
        titulo: 'Época da safra',
        subtitulo: 'Plantar, colher, vender',
        emoji: '🌾',
        tom: 'amber',
        itens: [
            { nome: 'Plantar',           desc: 'Começar um plantio',                emoji: '🌾',  rota: 'admin.agricola.plantios.index',   perm: 'operational.agricola.plantios.create' },
            { nome: 'Colher',            desc: 'Registrar colheita',                emoji: '🌽',  rota: 'admin.agricola.colheitas.index',  perm: 'operational.agricola.colheitas.create' },
            { nome: 'Vender boi',        desc: 'Passo a passo guiado',              emoji: '🐂',  rota: 'admin.fluxos.venda-animal',       perm: 'operational.rebanho.eventos.create', destaque: true },
            { nome: 'Comprar boi',       desc: 'Registrar compra de animal',        emoji: '🛒',  rota: 'admin.rebanho.animais.index',     perm: 'operational.rebanho.eventos.create' },
            { nome: 'Conferir o paiol',  desc: 'Ajuste de estoque',                 emoji: '🔢',  rota: 'admin.estoque.movimentos.index',  perm: 'operational.estoque.movimentos.create' },
        ],
    },
    {
        id: 'ocasional',
        titulo: 'Quando precisar',
        subtitulo: 'Nem sempre, mas acontece',
        emoji: '🏥',
        tom: 'slate',
        itens: [
            { nome: 'Morreu bicho',          desc: 'Registrar mortalidade',        emoji: '⚰️',  rota: 'admin.rebanho.animais.index',  perm: 'operational.rebanho.eventos.create' },
            { nome: 'Nasceu bicho',          desc: 'Cadastrar cria nova',          emoji: '🐣',  rota: 'admin.rebanho.animais.create', perm: 'operational.rebanho.animais.create' },
            { nome: 'Contratei peão',        desc: 'Cadastrar funcionário',        emoji: '👷',  rota: 'admin.funcionarios.index',     perm: 'operational.funcionarios.cadastro.create' },
            { nome: 'Mandei peão embora',    desc: 'Desligar funcionário',         emoji: '👋',  rota: 'admin.funcionarios.index',     perm: 'operational.funcionarios.cadastro.update' },
            { nome: 'Guardar papelada',      desc: 'Arquivar documento',           emoji: '📄',  rota: 'admin.documentos.index',       perm: 'operational.documentos.create' },
            { nome: 'Cadastrar bicho novo',  desc: 'Sem ser compra (pedigree)',    emoji: '🐾',  rota: 'admin.rebanho.animais.create', perm: 'operational.rebanho.animais.create' },
        ],
    },
];

const gruposVisiveis = computed(() =>
    grupos
        .map((g) => ({ ...g, itens: g.itens.filter((i) => !i.perm || can(i.perm)) }))
        .filter((g) => g.itens.length > 0)
);

const semAcoes = computed(() => gruposVisiveis.value.length === 0);

/**
 * Monta URL final. Se o item tiver `query`, anexa como ?chave=valor (escapado).
 * Uso Inertia `<Link>` com href absoluto porque route() não aceita query nativo.
 */
function hrefPara(item) {
    const base = route(item.rota);
    if (!item.query) return base;
    const qs = new URLSearchParams(item.query).toString();
    return base + (base.includes('?') ? '&' : '?') + qs;
}

const temDashboard = computed(() => can('operational.dashboard.view'));
</script>

<template>
    <Head title="Início" />
    <AdminLayout>
        <template #page-title>Início</template>

        <!-- Saudação humana, não "bem-vindo ao sistema" -->
        <div class="mb-8 sm:mb-10">
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900">
                {{ cumprimento }}<template v-if="primeiroNome">, {{ primeiroNome }}</template>!
            </h1>
            <p class="mt-1 text-base sm:text-lg text-slate-600">
                O que você quer fazer agora?
            </p>
        </div>

        <!-- Estado vazio: usuário sem nenhuma permissão operacional -->
        <div v-if="semAcoes" class="card p-8 text-center">
            <div class="text-5xl mb-3" aria-hidden="true">🤔</div>
            <div class="font-semibold text-slate-900 mb-1">
                Seu perfil ainda não tem ações liberadas
            </div>
            <div class="text-sm text-slate-500">
                Peça pro administrador do sistema liberar as funções que você precisa usar.
            </div>
        </div>

        <!-- Grupos por frequência -->
        <section
            v-for="grupo in gruposVisiveis"
            :key="grupo.id"
            class="mb-8 sm:mb-10"
        >
            <div class="flex items-center gap-3 mb-3 sm:mb-4">
                <span class="text-2xl sm:text-3xl" aria-hidden="true">{{ grupo.emoji }}</span>
                <div>
                    <h2 class="text-lg sm:text-xl font-semibold text-slate-900 leading-tight">
                        {{ grupo.titulo }}
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500">{{ grupo.subtitulo }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <Link
                    v-for="item in grupo.itens"
                    :key="item.nome"
                    :href="hrefPara(item)"
                    class="hub-card"
                    :class="[
                        `hub-card--${grupo.tom}`,
                        item.destaque ? 'hub-card--destaque' : '',
                    ]"
                >
                    <span class="hub-card__emoji" aria-hidden="true">{{ item.emoji }}</span>
                    <span class="hub-card__nome">{{ item.nome }}</span>
                    <span class="hub-card__desc">{{ item.desc }}</span>
                    <span v-if="item.destaque" class="hub-card__badge">
                        Passo a passo
                    </span>
                </Link>
            </div>
        </section>

        <!-- Escape pro jeito antigo -->
        <div v-if="temDashboard" class="mt-10 text-center">
            <Link
                :href="route('admin.dashboard')"
                class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-macaybas-primary transition-colors"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Abrir painel de números
            </Link>
        </div>
    </AdminLayout>
</template>

<style scoped>
/*
 * Não uso @apply em style scoped (segurança: scoped pode bagunçar camadas).
 * CSS puro, com variáveis que casam com as cores Tailwind do projeto.
 */
.hub-card {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-height: 150px;
    padding: 16px;
    background: #ffffff;
    border: 1px solid rgb(226 232 240); /* slate-200 */
    border-top-width: 4px;
    border-radius: 14px;
    text-decoration: none;
    transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

@media (min-width: 640px) {
    .hub-card {
        min-height: 170px;
        padding: 20px;
    }
}

.hub-card:hover,
.hub-card:focus-visible {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
    border-color: rgb(203 213 225); /* slate-300 */
    outline: none;
}

.hub-card:active {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.hub-card__emoji {
    font-size: 32px;
    line-height: 1;
    margin-bottom: 6px;
}

@media (min-width: 640px) {
    .hub-card__emoji {
        font-size: 40px;
    }
}

.hub-card__nome {
    font-weight: 600;
    color: rgb(15 23 42); /* slate-900 */
    font-size: 15px;
    line-height: 1.25;
}

@media (min-width: 640px) {
    .hub-card__nome {
        font-size: 16px;
    }
}

.hub-card__desc {
    font-size: 12px;
    line-height: 1.35;
    color: rgb(100 116 139); /* slate-500 */
    margin-top: 4px;
    flex: 1;
}

@media (min-width: 640px) {
    .hub-card__desc {
        font-size: 13px;
    }
}

.hub-card__badge {
    display: inline-block;
    width: fit-content;
    margin-top: 8px;
    padding: 2px 8px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    background: rgb(236 253 245); /* macaybas-primary-50 equivalent */
    color: rgb(6 78 59);          /* macaybas-primary-900 equivalent */
    border-radius: 6px;
}

/* Tom por grupo — só muda a faixa de topo */
.hub-card--primary { border-top-color: rgb(21 128 61); }   /* macaybas-primary */
.hub-card--sky     { border-top-color: rgb(14 165 233); }  /* sky-500 */
.hub-card--amber   { border-top-color: rgb(245 158 11); }  /* amber-500 */
.hub-card--slate   { border-top-color: rgb(100 116 139); } /* slate-500 */

.hub-card--primary:hover { border-color: rgb(167 243 208); } /* emerald-200 */
.hub-card--sky:hover     { border-color: rgb(186 230 253); } /* sky-200 */
.hub-card--amber:hover   { border-color: rgb(253 230 138); } /* amber-200 */
.hub-card--slate:hover   { border-color: rgb(203 213 225); } /* slate-300 */

/* Destaque pro card com wizard pronto (Vender boi) */
.hub-card--destaque {
    box-shadow: 0 0 0 2px rgb(21 128 61 / 0.15), 0 4px 12px rgba(15, 23, 42, 0.06);
}
</style>
