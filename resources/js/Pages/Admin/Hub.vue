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
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

// Busca incremental — filtra cards do Hub a partir de 3+ caracteres digitados
// Sem reload de página, sem requisição ao backend (filtragem 100% client-side
// já que o catálogo de ações já está carregado).
const busca = ref('');
function normalizar(s) {
    return (s || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, ''); // remove acentos
}
const buscaAtiva = computed(() => normalizar(busca.value).length >= 3);
const termoNormalizado = computed(() => normalizar(busca.value));
function matchItem(item) {
    if (! buscaAtiva.value) return true;
    const t = termoNormalizado.value;
    return normalizar(item.nome).includes(t)
        || normalizar(item.desc).includes(t)
        || normalizar(item.id).includes(t);
}

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
 * Catálogo das operações. Cada item tem:
 *   id        — slug único (usado como key de tracking: `hub:<id>`)
 *   nome      — ação em tom equilibrado (verbo + substantivo neutro)
 *   desc      — 3-6 palavras complementando
 *   emoji     — reconhecimento visual
 *   rota      — nome da rota Laravel
 *   query     — opcional: pré-filtro/pré-seleção na tela destino
 *   perm      — permissão requerida (filtra no front)
 *   tipo      — wizard | lista | acao  (controla badge visual obrigatório)
 *
 * REGRA DO BADGE:
 *   - tipo='wizard' → badge "PASSO A PASSO" (verde)
 *   - tipo='lista'  → badge "AÇÃO RÁPIDA"  (azul)
 *   - tipo='acao'   → badge "AÇÃO"         (âmbar)
 *
 * Nenhum card pode ficar sem `tipo` — o usuário sempre sabe o que vai acontecer
 * antes de clicar.
 */
const grupos = [
    {
        id: 'hoje',
        titulo: 'Todo dia',
        subtitulo: 'O que você faz com mais frequência',
        emoji: '🌅',
        tom: 'primary',
        itens: [
            { id: 'pesar-animal',         tipo: 'wizard', nome: 'Registrar peso do animal',   desc: 'Atualiza o peso atual e o histórico de ganho',         emoji: '⚖️', rota: 'admin.fluxos.pesar-animal',      perm: 'operational.rebanho.eventos.create' },
            { id: 'registrar-despesa',    tipo: 'wizard', nome: 'Registrar despesa',          desc: 'Lança no caixa, escolhe categoria/conta na hora',      emoji: '💸', rota: 'admin.fluxos.registrar-despesa', perm: 'operational.financeiro.transacoes.create' },
            { id: 'receber-mercadoria',   tipo: 'wizard', nome: 'Receber mercadoria',         desc: 'Entra no estoque, cria o item se não existir',         emoji: '📦', rota: 'admin.fluxos.receber-mercadoria', perm: 'operational.estoque.movimentos.create' },
            { id: 'pagar-conta',          tipo: 'lista',  nome: 'Pagar conta',                desc: 'Lista de contas pendentes — 1 clique pra baixar',      emoji: '💳', rota: 'admin.financeiro.transacoes.index', query: { status: 'pendente', tipo: 'despesa' }, perm: 'operational.financeiro.transacoes.update' },
            { id: 'receber-pagamento',    tipo: 'wizard', nome: 'Receber pagamento',          desc: 'Soma no caixa e fecha a conta a receber',              emoji: '💰', rota: 'admin.fluxos.registrar-receita', perm: 'operational.financeiro.transacoes.create' },
            { id: 'criar-tarefa',         tipo: 'wizard', nome: 'Criar tarefa',               desc: 'Define o que fazer, quem faz e até quando',            emoji: '📋', rota: 'admin.fluxos.criar-tarefa',       perm: 'operational.funcionarios.tarefas.create' },
            { id: 'concluir-tarefa',      tipo: 'lista',  nome: 'Concluir tarefa',            desc: 'Lista pendentes — ✓ verde de 1 clique pra fechar',     emoji: '✅', rota: 'admin.tarefas.index',             query: { status: 'pendente' }, perm: 'operational.funcionarios.tarefas.update' },
            { id: 'saida-estoque',        tipo: 'wizard', nome: 'Registrar saída de estoque', desc: 'Item + motivo + saldo final visível antes de salvar',  emoji: '📤', rota: 'admin.fluxos.saida-estoque', perm: 'operational.estoque.movimentos.create' },
        ],
    },
    {
        id: 'semana',
        titulo: 'Essa semana',
        subtitulo: 'Manejo do rebanho e da lavoura',
        emoji: '📅',
        tom: 'sky',
        itens: [
            { id: 'vacinar-animal',       tipo: 'wizard', nome: 'Aplicar vacina no animal',   desc: 'Um animal, lote inteiro ou pasto — escolhe na hora',   emoji: '💉', rota: 'admin.fluxos.evento-rebanho',    query: { tipo: 'vacinacao' },   perm: 'operational.rebanho.eventos.create' },
            { id: 'medicar-animal',       tipo: 'wizard', nome: 'Aplicar medicamento',        desc: 'Individual ou em lote, com dose e via',                emoji: '💊', rota: 'admin.fluxos.evento-rebanho',    query: { tipo: 'medicacao' },   perm: 'operational.rebanho.eventos.create' },
            { id: 'vermifugar-animal',    tipo: 'wizard', nome: 'Aplicar vermífugo',          desc: 'Bicho a bicho ou pasto inteiro, sem retrabalho',       emoji: '🧴', rota: 'admin.fluxos.evento-rebanho',    query: { tipo: 'vermifugacao' }, perm: 'operational.rebanho.eventos.create' },
            { id: 'aplicar-defensivo',    tipo: 'wizard', nome: 'Aplicar defensivo',          desc: 'Talhão, dose por hectare, carência da cultura',        emoji: '🌿', rota: 'admin.fluxos.aplicar-produto',   query: { tipo: 'herbicida' },  perm: 'operational.agricola.aplicacoes.create' },
            { id: 'aplicar-adubo',        tipo: 'wizard', nome: 'Aplicar adubo',              desc: 'Vincula talhão, NPK e quantidade aplicada',            emoji: '🌱', rota: 'admin.fluxos.aplicar-produto',   query: { tipo: 'adubacao' },   perm: 'operational.agricola.aplicacoes.create' },
            { id: 'manutencao-maquina',   tipo: 'wizard', nome: 'Arrumar máquina',            desc: 'Anota troca de óleo, peça, valor e horímetro',         emoji: '🔧', rota: 'admin.fluxos.arrumar-maquina',   perm: 'operational.maquinas.manutencoes.create' },
            { id: 'mover-lote',           tipo: 'wizard', nome: 'Mover animal de lote',       desc: 'Muda o grupo lógico — registra origem e destino',      emoji: '🐄', rota: 'admin.fluxos.evento-rebanho',    query: { tipo: 'movimentacao' }, perm: 'operational.rebanho.eventos.create' },
            { id: 'mover-pasto',          tipo: 'wizard', nome: 'Mover animal de pasto',      desc: 'Atualiza o local físico (pasto/curral/tanque)',        emoji: '📍', rota: 'admin.fluxos.evento-rebanho',    query: { tipo: 'movimentacao_local' }, perm: 'operational.rebanho.eventos.create' },
            { id: 'observar-animal',      tipo: 'wizard', nome: 'Registrar observação',       desc: 'Anota mancando, brigando, com bicheira, etc.',         emoji: '📝', rota: 'admin.fluxos.evento-rebanho',    query: { tipo: 'observacao' },  perm: 'operational.rebanho.eventos.create' },
            // Onda 3 — Pecuária leiteira
            { id: 'controle-leiteiro',    tipo: 'wizard', nome: 'Controle do leite',          desc: 'Quantos litros cada vaca produziu — 1× por mês',       emoji: '🥛', rota: 'admin.fluxos.controle-leiteiro', perm: 'operational.rebanho.eventos.create' },
            { id: 'secar-vaca',           tipo: 'wizard', nome: 'Secar vaca',                 desc: 'Cessa lactação 60 dias antes do parto previsto',       emoji: '💧', rota: 'admin.fluxos.secar-vaca',        perm: 'operational.rebanho.eventos.create' },
            { id: 'exame-toque',          tipo: 'wizard', nome: 'Exame de toque',             desc: 'Palpação · prenhe/vazia + cria tarefas automáticas',   emoji: '🩺', rota: 'admin.fluxos.exame-toque',       perm: 'operational.rebanho.eventos.create' },
        ],
    },
    {
        id: 'safra',
        titulo: 'Época da safra',
        subtitulo: 'Plantio, colheita e comercialização',
        emoji: '🌾',
        tom: 'amber',
        itens: [
            { id: 'registrar-plantio',    tipo: 'wizard', nome: 'Registrar plantio',          desc: 'Cultura, talhão e área — sugere colheita pelo ciclo', emoji: '🌾', rota: 'admin.fluxos.registrar-plantio',  perm: 'operational.agricola.plantios.create' },
            { id: 'registrar-colheita',   tipo: 'wizard', nome: 'Registrar colheita',         desc: 'Fecha plantio, calcula produtividade e gera receita', emoji: '🌽', rota: 'admin.fluxos.registrar-colheita', perm: 'operational.agricola.colheitas.create' },
            { id: 'vender-animal',        tipo: 'wizard', nome: 'Vender animal',              desc: 'Cabeça, arroba, kg ou unidade — adapta ao mercado',    emoji: '🐂', rota: 'admin.fluxos.venda-animal',       perm: 'operational.rebanho.eventos.create' },
            { id: 'comprar-animal',       tipo: 'wizard', nome: 'Comprar animal',             desc: 'Fornecedor, valor e data de aquisição — guiado',       emoji: '🛒', rota: 'admin.fluxos.cadastrar-animal', query: { modo: 'compra' }, perm: 'operational.rebanho.animais.create' },
            { id: 'ajustar-estoque',      tipo: 'wizard', nome: 'Ajustar estoque',            desc: 'Conferência física: corrige saldo com motivo',         emoji: '🔢', rota: 'admin.fluxos.ajustar-estoque',   perm: 'operational.estoque.movimentos.create' },
        ],
    },
    {
        id: 'ocasional',
        titulo: 'Quando precisar',
        subtitulo: 'Nem sempre, mas acontece',
        emoji: '🏥',
        tom: 'slate',
        itens: [
            { id: 'registrar-morte',      tipo: 'wizard', nome: 'Registrar morte do animal',  desc: 'Causa, data e baixa do rebanho ativo',                 emoji: '⚰️', rota: 'admin.fluxos.evento-rebanho',  query: { tipo: 'mortalidade' }, perm: 'operational.rebanho.eventos.create' },
            { id: 'registrar-nascimento', tipo: 'wizard', nome: 'Registrar nascimento',       desc: 'Vincula à mãe, peso ao nascer — cria entra no rebanho', emoji: '🐣', rota: 'admin.fluxos.cadastrar-animal', query: { modo: 'nascimento' }, perm: 'operational.rebanho.animais.create' },
            { id: 'cadastrar-funcionario',tipo: 'wizard', nome: 'Cadastrar funcionário',      desc: 'Vínculo (CLT/PJ/diarista/safrista) — adapta os campos', emoji: '👷', rota: 'admin.fluxos.cadastrar-funcionario', perm: 'operational.funcionarios.cadastro.create' },
            { id: 'desligar-funcionario', tipo: 'acao',   nome: 'Desligar funcionário',       desc: 'Modal com data e motivo — mantém histórico',           emoji: '👋', rota: 'admin.funcionarios.index', perm: 'operational.funcionarios.cadastro.update' },
            { id: 'anexar-documento',     tipo: 'wizard', nome: 'Anexar documento',           desc: 'GTA / licença / receita / contrato — adapta validade', emoji: '📄', rota: 'admin.fluxos.anexar-documento', perm: 'operational.documentos.create' },
            { id: 'cadastrar-animal',     tipo: 'wizard', nome: 'Cadastrar animal',           desc: 'Espécie, brinco, sexo, lote — passo a passo guiado',   emoji: '🐾', rota: 'admin.fluxos.cadastrar-animal', perm: 'operational.rebanho.animais.create' },
        ],
    },
];

// Mapa de badge visual por tipo (regra obrigatória do hardening)
const BADGE_POR_TIPO = {
    wizard: { texto: 'PASSO A PASSO', classe: 'badge-wizard' },
    lista:  { texto: 'AÇÃO RÁPIDA',   classe: 'badge-lista' },
    acao:   { texto: 'AÇÃO',          classe: 'badge-acao' },
};

// Filtragem por permissão E pela busca textual (≥3 chars)
const gruposVisiveis = computed(() =>
    grupos
        .map((g) => ({
            ...g,
            itens: g.itens.filter((i) => (!i.perm || can(i.perm)) && matchItem(i)),
        }))
        .filter((g) => g.itens.length > 0)
);

// Quantos cards estão sendo mostrados após filtros
const totalCardsVisiveis = computed(() =>
    gruposVisiveis.value.reduce((acc, g) => acc + g.itens.length, 0)
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
        <div class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900">
                {{ cumprimento }}<template v-if="primeiroNome">, {{ primeiroNome }}</template>!
            </h1>
            <p class="mt-1 text-base sm:text-lg text-slate-600">
                O que você quer fazer agora?
            </p>
        </div>

        <!-- Busca rápida — filtra cards a partir de 3 caracteres -->
        <div class="mb-6 sm:mb-8">
            <div class="relative max-w-2xl">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </span>
                <input
                    v-model="busca"
                    type="search"
                    placeholder="Buscar atalho — digite pelo menos 3 letras (ex: leite, vacina, despesa)"
                    class="w-full pl-12 pr-12 py-4 rounded-xl bg-white ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base shadow-sm"
                >
                <button
                    v-if="busca"
                    type="button"
                    @click="busca = ''"
                    class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500"
                    title="Limpar busca"
                >×</button>
            </div>
            <p v-if="buscaAtiva" class="mt-2 text-xs text-slate-500">
                <span v-if="totalCardsVisiveis === 0" class="text-amber-700 font-medium">
                    Nenhum atalho encontrado para "{{ busca }}". Tente outra palavra.
                </span>
                <span v-else>
                    Mostrando <strong class="text-slate-900">{{ totalCardsVisiveis }}</strong> atalho(s) para "<strong class="text-slate-900">{{ busca }}</strong>"
                </span>
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

        <!-- Seção "Você usa mais" — oculta durante busca pra não confundir -->
        <section v-if="maisUsados.length > 0 && ! buscaAtiva" class="mb-8 sm:mb-10">
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
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Link
                    v-for="item in maisUsados"
                    :key="`fav-${item.id}`"
                    :href="hrefPara(item)"
                    @click="registrarUso(item.id)"
                    class="hub-card hub-card--favorito"
                    :class="[
                        `hub-card--${item.grupoTom}`,
                        item.tipo === 'wizard' ? 'hub-card--destaque' : '',
                    ]"
                >
                    <span class="hub-card__emoji" aria-hidden="true">{{ item.emoji }}</span>
                    <span class="hub-card__nome">{{ item.nome }}</span>
                    <span class="hub-card__desc">{{ item.desc }}</span>
                    <span class="hub-card__badge" :class="BADGE_POR_TIPO[item.tipo].classe">
                        {{ BADGE_POR_TIPO[item.tipo].texto }}
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

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Link
                    v-for="item in grupo.itens"
                    :key="item.id"
                    :href="hrefPara(item)"
                    @click="registrarUso(item.id)"
                    class="hub-card"
                    :class="[
                        `hub-card--${grupo.tom}`,
                        item.tipo === 'wizard' ? 'hub-card--destaque' : '',
                    ]"
                >
                    <span class="hub-card__emoji" aria-hidden="true">{{ item.emoji }}</span>
                    <span class="hub-card__nome">{{ item.nome }}</span>
                    <span class="hub-card__desc">{{ item.desc }}</span>
                    <span class="hub-card__badge" :class="BADGE_POR_TIPO[item.tipo].classe">
                        {{ BADGE_POR_TIPO[item.tipo].texto }}
                    </span>
                </Link>
            </div>
        </section>

        <!-- Escape pro jeito antigo -->
        <div v-if="temDashboard" class="mt-10 text-center">
            <Link
                :href="route('admin.dashboard')"
                class="inline-flex items-center gap-2 px-3 py-2 min-h-[40px] text-sm text-slate-500 hover:text-macaybas-primary hover:bg-slate-50 rounded-lg transition-colors"
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
    border-radius: 6px;
}

/* Badge WIZARD — verde escuro: indica fluxo guiado passo a passo */
.badge-wizard {
    background: rgb(236 253 245);
    color: rgb(6 78 59);
}

/* Badge LISTA — azul: indica ação rápida sobre lista filtrada */
.badge-lista {
    background: rgb(219 234 254);
    color: rgb(30 64 175);
}

/* Badge AÇÃO — âmbar: indica modal/ação focada (ex: desligar) */
.badge-acao {
    background: rgb(254 243 199);
    color: rgb(146 64 14);
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
