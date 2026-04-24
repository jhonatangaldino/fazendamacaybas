<script setup>
/**
 * Hub de ações — "O que você quer fazer?"
 *
 * Porta de entrada do sistema. Substitui o dashboard como tela inicial.
 *
 * Design:
 * - Labels em tom equilibrado: verbo claro + substantivo neutro.
 *   Nem coloquial ("Morreu bicho") nem técnico ("Registrar evento de
 *   mortalidade"). Padrão: "Registrar morte do animal".
 * - Agrupamento por FREQUÊNCIA (todo dia / essa semana / safra / ocasional),
 *   nunca por módulo. O fazendeiro não pensa "agora vou no módulo financeiro".
 * - Cards grandes (≥150px), emoji proeminente, mobile-first (2 colunas).
 * - Filtragem por permissão no front.
 *
 * Seção "Você usa mais":
 * - Aparece no topo quando o usuário tem histórico de uso.
 * - Alimentada pelo mesmo MenuUsage da sidebar, com prefixo `hub:<id>` pra
 *   não colidir. Snapshot congelado às 3h (comando `menu:snapshot`), igual
 *   à sidebar — a ordem NÃO muda durante o uso, evitando confusão.
 * - Primeiro dia de uso: seção invisível. A partir do segundo: top 4
 *   ações aparecem no topo, organizados por mais clicados.
 * - Os mesmos cards continuam aparecendo nos grupos originais, de propósito:
 *   o usuário encontra onde "sempre esteve" (consistência) e ainda ganha
 *   atalho no topo (velocidade).
 *
 * Tracking:
 * - Ao clicar em qualquer card, dispara fire-and-forget no endpoint
 *   `admin.menu-usage.bump` com key `hub:<id>`. Não bloqueia navegação.
 *
 * Integração com wizards:
 * - Hoje só "Vender animal" tem wizard real (admin.fluxos.venda-animal,
 *   marcado com `destaque: true`). Os outros linkam pras telas de módulo
 *   existentes; conforme wizards forem criados, só a `rota` do card muda.
 */
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const perms = computed(() => page.props.auth?.user?.permissions ?? []);
const menuUsage = computed(() => page.props.menuUsage ?? {});

function can(permission) {
    const p = perms.value;
    if (!Array.isArray(p)) return false;
    return p.includes(permission);
}

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
 * Catálogo das 27 operações. Cada item tem:
 *   id        — slug único (usado como key de tracking: `hub:<id>`)
 *   nome      — ação em tom equilibrado (verbo + substantivo neutro)
 *   desc      — 3-6 palavras complementando
 *   emoji     — reconhecimento visual
 *   rota      — nome da rota Laravel
 *   query     — opcional: pré-filtro/pré-seleção na tela destino
 *   perm      — permissão requerida (filtra no front)
 *   destaque  — true quando já existe wizard guiado
 */
const grupos = [
    {
        id: 'hoje',
        titulo: 'Todo dia',
        subtitulo: 'O que você faz com mais frequência',
        emoji: '🌅',
        tom: 'primary',
        itens: [
            { id: 'pesar-animal',         nome: 'Registrar peso do animal',   desc: 'Passo a passo guiado',             emoji: '⚖️', rota: 'admin.fluxos.pesar-animal',      perm: 'operational.rebanho.eventos.create', destaque: true },
            { id: 'registrar-despesa',    nome: 'Registrar despesa',          desc: 'Passo a passo guiado',             emoji: '💸', rota: 'admin.fluxos.registrar-despesa', perm: 'operational.financeiro.transacoes.create', destaque: true },
            { id: 'receber-mercadoria',   nome: 'Receber mercadoria',         desc: 'Passo a passo guiado',             emoji: '📦', rota: 'admin.fluxos.receber-mercadoria', perm: 'operational.estoque.movimentos.create', destaque: true },
            { id: 'pagar-conta',          nome: 'Pagar conta',                desc: 'Baixa em conta a pagar',           emoji: '💳', rota: 'admin.financeiro.transacoes.index', query: { status: 'pendente', tipo: 'despesa' }, perm: 'operational.financeiro.transacoes.update' },
            { id: 'receber-pagamento',    nome: 'Receber pagamento',          desc: 'Passo a passo guiado',             emoji: '💰', rota: 'admin.fluxos.registrar-receita', perm: 'operational.financeiro.transacoes.create', destaque: true },
            { id: 'criar-tarefa',         nome: 'Criar tarefa',               desc: 'Passo a passo guiado',             emoji: '📋', rota: 'admin.fluxos.criar-tarefa',       perm: 'operational.funcionarios.tarefas.create', destaque: true },
            { id: 'concluir-tarefa',      nome: 'Concluir tarefa',            desc: 'Marcar tarefa como feita',         emoji: '✅', rota: 'admin.tarefas.index',             query: { status: 'pendente' }, perm: 'operational.funcionarios.tarefas.update' },
            { id: 'saida-estoque',        nome: 'Registrar saída de estoque', desc: 'Baixa de material utilizado',      emoji: '📤', rota: 'admin.estoque.movimentos.index',  query: { novo: 1, tipo: 'saida', motivo: 'uso' }, perm: 'operational.estoque.movimentos.create' },
        ],
    },
    {
        id: 'semana',
        titulo: 'Essa semana',
        subtitulo: 'Manejo do rebanho e da lavoura',
        emoji: '📅',
        tom: 'sky',
        itens: [
            { id: 'vacinar-animal',       nome: 'Aplicar vacina no animal',   desc: 'Passo a passo guiado',             emoji: '💉', rota: 'admin.fluxos.evento-rebanho',    query: { tipo: 'vacinacao' },   perm: 'operational.rebanho.eventos.create', destaque: true },
            { id: 'medicar-animal',       nome: 'Aplicar medicamento',        desc: 'Passo a passo guiado',             emoji: '💊', rota: 'admin.fluxos.evento-rebanho',    query: { tipo: 'medicacao' },   perm: 'operational.rebanho.eventos.create', destaque: true },
            { id: 'vermifugar-animal',    nome: 'Aplicar vermífugo',          desc: 'Passo a passo guiado',             emoji: '🧴', rota: 'admin.fluxos.evento-rebanho',    query: { tipo: 'vermifugacao' }, perm: 'operational.rebanho.eventos.create', destaque: true },
            { id: 'aplicar-defensivo',    nome: 'Aplicar produto na plantação', desc: 'Passo a passo guiado',           emoji: '🌿', rota: 'admin.fluxos.aplicar-produto',   query: { tipo: 'herbicida' },  perm: 'operational.agricola.aplicacoes.create', destaque: true },
            { id: 'aplicar-adubo',        nome: 'Aplicar adubo na plantação', desc: 'Passo a passo guiado',             emoji: '🌱', rota: 'admin.fluxos.aplicar-produto',   query: { tipo: 'adubacao' },   perm: 'operational.agricola.aplicacoes.create', destaque: true },
            { id: 'manutencao-maquina',   nome: 'Arrumar máquina',            desc: 'Passo a passo guiado',             emoji: '🔧', rota: 'admin.fluxos.arrumar-maquina',   perm: 'operational.maquinas.manutencoes.create', destaque: true },
            { id: 'mover-lote',           nome: 'Mover animal de lote',       desc: 'Mudar o grupo (lote) do animal',   emoji: '🐄', rota: 'admin.fluxos.evento-rebanho',    query: { tipo: 'movimentacao' }, perm: 'operational.rebanho.eventos.create', destaque: true },
            { id: 'mover-pasto',          nome: 'Mover animal de pasto',      desc: 'Mudar o local físico do animal',   emoji: '📍', rota: 'admin.fluxos.evento-rebanho',    query: { tipo: 'movimentacao_local' }, perm: 'operational.rebanho.eventos.create', destaque: true },
            { id: 'observar-animal',      nome: 'Registrar observação do animal', desc: 'Passo a passo guiado',         emoji: '📝', rota: 'admin.fluxos.evento-rebanho',    query: { tipo: 'observacao' },  perm: 'operational.rebanho.eventos.create', destaque: true },
        ],
    },
    {
        id: 'safra',
        titulo: 'Época da safra',
        subtitulo: 'Plantio, colheita e comercialização',
        emoji: '🌾',
        tom: 'amber',
        itens: [
            { id: 'registrar-plantio',    nome: 'Registrar plantio',          desc: 'Iniciar um plantio',               emoji: '🌾', rota: 'admin.agricola.plantios.index',   query: { novo: 1 },           perm: 'operational.agricola.plantios.create' },
            { id: 'registrar-colheita',   nome: 'Registrar colheita',         desc: 'Fechamento da safra',              emoji: '🌽', rota: 'admin.agricola.colheitas.index',  query: { novo: 1 },           perm: 'operational.agricola.colheitas.create' },
            { id: 'vender-animal',        nome: 'Vender animal',              desc: 'Passo a passo guiado',             emoji: '🐂', rota: 'admin.fluxos.venda-animal',       perm: 'operational.rebanho.eventos.create', destaque: true },
            { id: 'comprar-animal',       nome: 'Comprar animal',             desc: 'Registrar compra de animal',       emoji: '🛒', rota: 'admin.rebanho.animais.create',    query: { origem: 'compra' },  perm: 'operational.rebanho.animais.create' },
            { id: 'ajustar-estoque',      nome: 'Ajustar estoque',            desc: 'Passo a passo guiado',             emoji: '🔢', rota: 'admin.fluxos.ajustar-estoque',   perm: 'operational.estoque.movimentos.create', destaque: true },
        ],
    },
    {
        id: 'ocasional',
        titulo: 'Quando precisar',
        subtitulo: 'Nem sempre, mas acontece',
        emoji: '🏥',
        tom: 'slate',
        itens: [
            { id: 'registrar-morte',      nome: 'Registrar morte do animal',  desc: 'Passo a passo guiado',             emoji: '⚰️', rota: 'admin.fluxos.evento-rebanho',  query: { tipo: 'mortalidade' }, perm: 'operational.rebanho.eventos.create', destaque: true },
            { id: 'registrar-nascimento', nome: 'Registrar nascimento',       desc: 'Cria nova no rebanho',             emoji: '🐣', rota: 'admin.rebanho.animais.create', query: { origem: 'nascimento' }, perm: 'operational.rebanho.animais.create' },
            { id: 'cadastrar-funcionario',nome: 'Cadastrar funcionário',      desc: 'Novo colaborador na fazenda',      emoji: '👷', rota: 'admin.funcionarios.index',     query: { novo: 1 },           perm: 'operational.funcionarios.cadastro.create' },
            { id: 'desligar-funcionario', nome: 'Desligar funcionário',       desc: 'Encerrar vínculo com o funcionário', emoji: '👋', rota: 'admin.funcionarios.index', perm: 'operational.funcionarios.cadastro.update' },
            { id: 'anexar-documento',     nome: 'Anexar documento',           desc: 'GTA, licença, receita, contrato',  emoji: '📄', rota: 'admin.documentos.index',       query: { novo: 1 },           perm: 'operational.documentos.create' },
            { id: 'cadastrar-animal',     nome: 'Cadastrar animal',           desc: 'Por doação, pedigree, importação', emoji: '🐾', rota: 'admin.rebanho.animais.create', perm: 'operational.rebanho.animais.create' },
        ],
    },
];

// Filtragem por permissão, preservando estrutura dos grupos
const gruposVisiveis = computed(() =>
    grupos
        .map((g) => ({ ...g, itens: g.itens.filter((i) => !i.perm || can(i.perm)) }))
        .filter((g) => g.itens.length > 0)
);

const semAcoes = computed(() => gruposVisiveis.value.length === 0);

/**
 * Top 4 ações mais usadas (score > 0). Score vem do snapshot diário,
 * então só aparece depois do primeiro `menu:snapshot` rodar. Antes disso
 * a seção fica invisível (comportamento correto — nada a ranquear).
 */
const maisUsados = computed(() => {
    const flat = [];
    gruposVisiveis.value.forEach((g) => {
        g.itens.forEach((i) => {
            const score = Number(menuUsage.value[`hub:${i.id}`] ?? 0);
            if (score > 0) {
                flat.push({ ...i, grupoTom: g.tom, score });
            }
        });
    });
    flat.sort((a, b) => b.score - a.score);
    return flat.slice(0, 4);
});

function hrefPara(item) {
    const base = route(item.rota);
    if (!item.query) return base;
    const qs = new URLSearchParams(item.query).toString();
    return base + (base.includes('?') ? '&' : '?') + qs;
}

/**
 * Fire-and-forget: registra clique no endpoint existente. Não uso Inertia
 * pra evitar re-render/reload — só um POST puro que o backend incrementa.
 * Na próxima visita ao Hub, o ranking estará atualizado (após o snapshot).
 */
function registrarUso(id) {
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch(route('admin.menu-usage.bump'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ key: `hub:${id}` }),
            keepalive: true,
        }).catch(() => {});
    } catch (_) { /* noop */ }
}

const temDashboard = computed(() => can('operational.dashboard.view'));
</script>

<template>
    <Head title="Início" />
    <AdminLayout>
        <template #page-title>Início</template>

        <!-- Saudação -->
        <div class="mb-8 sm:mb-10">
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900">
                {{ cumprimento }}<template v-if="primeiroNome">, {{ primeiroNome }}</template>!
            </h1>
            <p class="mt-1 text-base sm:text-lg text-slate-600">
                O que você quer fazer agora?
            </p>
        </div>

        <!-- Estado vazio -->
        <div v-if="semAcoes" class="card p-8 text-center">
            <div class="text-5xl mb-3" aria-hidden="true">🤔</div>
            <div class="font-semibold text-slate-900 mb-1">
                Seu perfil ainda não tem ações liberadas
            </div>
            <div class="text-sm text-slate-500">
                Peça para o administrador do sistema liberar as funções que você precisa usar.
            </div>
        </div>

        <!-- Seção "Você usa mais" (só aparece se houver histórico) -->
        <section v-if="maisUsados.length > 0" class="mb-8 sm:mb-10">
            <div class="flex items-center gap-3 mb-3 sm:mb-4">
                <span class="text-2xl sm:text-3xl" aria-hidden="true">⭐</span>
                <div>
                    <h2 class="text-lg sm:text-xl font-semibold text-slate-900 leading-tight">
                        Você usa mais
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500">
                        Atalho para as ações que você mais fez nos últimos dias
                    </p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <Link
                    v-for="item in maisUsados"
                    :key="`fav-${item.id}`"
                    :href="hrefPara(item)"
                    @click="registrarUso(item.id)"
                    class="hub-card hub-card--favorito"
                    :class="[
                        `hub-card--${item.grupoTom}`,
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
                    :key="item.id"
                    :href="hrefPara(item)"
                    @click="registrarUso(item.id)"
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
.hub-card {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-height: 150px;
    padding: 16px;
    background: #ffffff;
    border: 1px solid rgb(226 232 240);
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
    border-color: rgb(203 213 225);
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
    color: rgb(15 23 42);
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
    color: rgb(100 116 139);
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
    background: rgb(236 253 245);
    color: rgb(6 78 59);
    border-radius: 6px;
}

/* Tom por grupo — faixa superior */
.hub-card--primary { border-top-color: rgb(21 128 61); }
.hub-card--sky     { border-top-color: rgb(14 165 233); }
.hub-card--amber   { border-top-color: rgb(245 158 11); }
.hub-card--slate   { border-top-color: rgb(100 116 139); }

.hub-card--primary:hover { border-color: rgb(167 243 208); }
.hub-card--sky:hover     { border-color: rgb(186 230 253); }
.hub-card--amber:hover   { border-color: rgb(253 230 138); }
.hub-card--slate:hover   { border-color: rgb(203 213 225); }

.hub-card--destaque {
    box-shadow: 0 0 0 2px rgb(21 128 61 / 0.15), 0 4px 12px rgba(15, 23, 42, 0.06);
}

/* Cartão na seção "Você usa mais" — sutil ring amarelo/âmbar pra marcar
   como atalho, sem competir com o destaque do wizard guiado */
.hub-card--favorito {
    background: linear-gradient(180deg, rgb(254 252 232) 0%, #ffffff 35%);
}
</style>
