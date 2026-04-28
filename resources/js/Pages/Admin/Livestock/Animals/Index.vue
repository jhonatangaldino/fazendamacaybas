<script setup>
import { reactive, ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { emojiEspecie } from '@/utils/emojiEspecie.js';

const page = usePage();
import ConfirmModal from '@/Components/ConfirmModal.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import MobileFilters from '@/Components/MobileFilters.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMoney from '@/Components/InputMoney.vue';
import { dataBR, brl, hojeBR } from '@/utils/format.js';
import { tableActionsFor, EVENT_CATALOG, vendaConfigFor } from '@/utils/animalProfile.js';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';
import InputDecimal from '@/Components/InputDecimal.vue';

const props = defineProps({
    animals: Object,
    filters: Object,
    species: Array,
    lots: Array,
    locations: { type: Array, default: () => [] },
    categorias: Array,
    partners: Array,
    resumo: { type: Object, default: () => ({ ativos: 0, vendidos: 0, baixas: 0, peso_total: 0, ativos_com_peso: 0 }) },
    tem_manejo_leiteiro: { type: Boolean, default: false },
});

// Peso médio do rebanho (apenas considera animais com peso registrado)
const pesoMedio = computed(() => {
    const total = Number(props.resumo.peso_total || 0);
    const n = Number(props.resumo.ativos_com_peso || 0);
    return n > 0 ? total / n : 0;
});

// Espécie selecionada via filtro vindo da URL — quando vem do menu lateral
// (item "Bovinos", "Aves" etc.), o título da página vira contextual da
// espécie e o botão de cadastro pré-fixa species_id pra abrir já contexto.
// Usa tenantSpecies (compartilhado via Inertia, já filtrado pelo backend) em
// vez de props.species — esta última pode vir vazia quando species ficam
// num "catálogo global" (tenant_id=1) e o BelongsToTenantScope filtra fora.
const especieAtiva = computed(() => {
    const id = Number(props.filters?.species_id);
    if (! id) return null;
    const fromTenant = (page.props.tenantSpecies || []).find(s => s.id === id);
    if (fromTenant) return fromTenant;
    return props.species?.find(s => s.id === id) ?? null;
});
const tituloHeader = computed(() => {
    if (! especieAtiva.value) return 'Animais';
    return `${emojiEspecie(especieAtiva.value.nome)} ${especieAtiva.value.nome}`;
});
const subtituloHeader = computed(() => {
    if (! especieAtiva.value) return 'Cadastro individual com histórico incremental de pesagens, vacinas e eventos';
    const totais = props.resumo?.ativos > 0 ? `${props.resumo.ativos} ${especieAtiva.value.nome.toLowerCase()}(s) ativo(s)` : `Nenhum ${especieAtiva.value.nome.toLowerCase()} ativo`;
    return totais + ' · cadastros, eventos e ações específicas dessa espécie';
});
const labelBotaoNovo = computed(() => {
    if (! especieAtiva.value) return 'Novo animal';
    return `+ Cadastrar ${especieAtiva.value.nome.toLowerCase()}`;
});
const linkNovoAnimal = computed(() => {
    const params = especieAtiva.value ? { species_id: especieAtiva.value.id } : {};
    return route('admin.rebanho.animais.create', params);
});
// species_id, lot_id, location_id chegam como STRING no query (?species_id=1)
// mas o <option :value="s.id"> usa o number. Sem cast o select renderiza
// vazio (UX: usuário não vê que está filtrado). Cast pra number sempre.
const toNumOrEmpty = (v) => {
    const n = Number(v);
    return Number.isFinite(n) && n > 0 ? n : '';
};
const filtros = reactive({
    search: props.filters?.search ?? '',
    species_id: toNumOrEmpty(props.filters?.species_id),
    lot_id: toNumOrEmpty(props.filters?.lot_id),
    location_id: toNumOrEmpty(props.filters?.location_id),
    status: props.filters?.status ?? '',
    categoria: props.filters?.categoria ?? '',
});

function filtrar() { router.get(route('admin.rebanho.animais.index'), filtros, { preserveState: true, replace: true }); }

const statusBadge = (s) => ({ ativo: 'badge-green', vendido: 'badge-blue', morto: 'badge-red', abatido: 'badge-slate', transferido: 'badge-yellow' })[s] || 'badge-slate';
const categoriaBadge = (c) => ({ leite: 'badge-blue', corte: 'badge-red', reproducao: 'badge-yellow', misto: 'badge-slate', pet: 'badge-green', servico: 'badge-slate' })[c] || 'badge-slate';
const categoriaLabel = (c) => props.categorias.find(x => x.value === c)?.label || c || '—';
const labelEvento = (tipo) => EVENT_CATALOG[tipo]?.label || tipo;
const acoesDoAnimal = (row) => tableActionsFor(row.species, row.categoria);

// =========================================================================
// Seleção múltipla para ações em lote (Vender, Medicar, Transferir, etc.)
// =========================================================================
const selecionados = ref(new Set()); // Set<id>
const selecionadosAtivos = computed(() =>
    (props.animals?.data || []).filter(a => selecionados.value.has(a.id) && a.status === 'ativo')
);
const especieSelecionada = computed(() => {
    const primeiro = selecionadosAtivos.value[0];
    return primeiro?.species ?? null;
});
const misturouEspecies = computed(() => {
    if (selecionadosAtivos.value.length <= 1) return false;
    const ids = new Set(selecionadosAtivos.value.map(a => a.species?.id));
    return ids.size > 1;
});

function toggle(row) {
    if (row.status !== 'ativo') return;
    const next = new Set(selecionados.value);
    // Restrição de mesma espécie — se tentar adicionar de outra espécie, troca tudo
    if (!next.has(row.id) && selecionadosAtivos.value.length && especieSelecionada.value?.id !== row.species?.id) {
        next.clear();
    }
    next.has(row.id) ? next.delete(row.id) : next.add(row.id);
    selecionados.value = next;
}
function selecionarTodos() {
    // Seleciona todos os ATIVOS da mesma espécie do primeiro item da página
    const ativos = (props.animals?.data || []).filter(a => a.status === 'ativo');
    if (!ativos.length) return;
    const primeiraEspecieId = ativos[0].species?.id;
    selecionados.value = new Set(
        ativos.filter(a => a.species?.id === primeiraEspecieId).map(a => a.id)
    );
}
function limparSelecao() { selecionados.value = new Set(); }
const todosSelecionados = computed(() => {
    const ativos = (props.animals?.data || []).filter(a => a.status === 'ativo');
    return ativos.length > 0 && ativos.every(a => selecionados.value.has(a.id));
});

// =========================================================================
// Ação rápida: pesagem / vacinação / ordenha / biometria / postura
// =========================================================================
const eventoRapido = ref(null);
const eventoTipo = ref('pesagem');
// ⚠ `data` colide com método .data() do useForm — usar data_evento + transform
const eventoForm = useForm({
    tipo: 'pesagem', data_evento: '', peso: '', vacina: '', medicamento: '',
    dose: '', via_aplicacao: '', observacoes: '',
});

function abrirEventoRapido(row, tipo = 'pesagem') {
    eventoRapido.value = row;
    eventoTipo.value = tipo;
    eventoForm.reset();
    eventoForm.tipo = tipo;
    eventoForm.data_evento = hojeBR();
}
function confirmarEventoRapido() {
    eventoForm
        .transform((d) => { const { data_evento, ...r } = d; return { ...r, data: data_evento }; })
        .post(route('admin.rebanho.animais.eventos.store', eventoRapido.value.id), {
            preserveScroll: true, only: ['animals', 'flash'],
            onSuccess: () => { eventoRapido.value = null; eventoForm.reset(); },
        });
}

// =========================================================================
// Venda em lote contextual (arroba/kg/unidade/cabeça)
// =========================================================================
const vendaAberta = ref(false);
useBodyScrollLock(computed(() => !!eventoRapido.value || vendaAberta.value));

// ESC fecha modais inline da página (eventoRapido + vendaAberta).
// ConfirmModal já tem ESC próprio via componente.
function handleEsc(e) {
    if (e.key !== 'Escape') return;
    if (eventoRapido.value) { e.stopPropagation(); eventoRapido.value = null; return; }
    if (vendaAberta.value) { e.stopPropagation(); vendaAberta.value = false; return; }
}
onMounted(() => window.addEventListener('keydown', handleEsc));
onBeforeUnmount(() => window.removeEventListener('keydown', handleEsc));
// ⚠ `data` colide com método .data() do useForm — usar data_venda + transform
const vendaForm = useForm({
    animal_ids: [], data_venda: '', partner_id: null, observacoes: '',
    unidade: 'cabeca', quantidade: '', peso_medio: '', valor_unitario: '',
});
const vendaCfg = computed(() => {
    if (!especieSelecionada.value) return null;
    const categ = selecionadosAtivos.value[0]?.categoria;
    return vendaConfigFor(especieSelecionada.value, categ);
});

function abrirVenda() {
    if (!selecionadosAtivos.value.length || misturouEspecies.value) return;
    vendaForm.reset();
    vendaForm.animal_ids = selecionadosAtivos.value.map(a => a.id);
    vendaForm.data_venda = hojeBR();
    vendaForm.unidade = vendaCfg.value?.unidadePadrao || 'cabeca';
    vendaAberta.value = true;
}

// Cálculos contextuais:
// - Quando unidade tem kg definido (arroba=15kg, kg=1kg) e pergunta peso médio → quantidade total em unidades vem do peso_medio × n_cabecas
// - Quando não (cabeça, unidade de peixe, dúzia) → usuário informa quantidade direta
const nCabecas = computed(() => selecionadosAtivos.value.length);
const kgPorUnidade = computed(() => vendaCfg.value?.kgPorUnidade?.[vendaForm.unidade] || null);
const perguntaPesoMedio = computed(() => vendaCfg.value?.perguntaPesoMedio && kgPorUnidade.value);

const quantidadeCalculada = computed(() => {
    if (perguntaPesoMedio.value) {
        const pm = parseFloat(vendaForm.peso_medio) || 0;
        return (pm * nCabecas.value) / kgPorUnidade.value;
    }
    if (vendaForm.unidade === 'cabeca') return nCabecas.value;
    return parseFloat(vendaForm.quantidade) || 0;
});
const totalVenda = computed(() => {
    const vu = parseFloat(vendaForm.valor_unitario) || 0;
    return quantidadeCalculada.value * vu;
});
const pesoTotalKg = computed(() => {
    if (!kgPorUnidade.value) return null;
    return quantidadeCalculada.value * kgPorUnidade.value;
});
const valorPorCabeca = computed(() => nCabecas.value ? totalVenda.value / nCabecas.value : 0);

function confirmarVenda() {
    vendaForm.transform((d) => {
        const { data_venda, ...rest } = d;
        return {
            ...rest,
            data: data_venda,                       // backend espera 'data'
            quantidade: quantidadeCalculada.value,
            valor_total: totalVenda.value,
        };
    }).post(route('admin.rebanho.animais.vender-lote'), {
        preserveScroll: true, only: ['animals', 'flash'],
        onSuccess: () => { vendaAberta.value = false; limparSelecao(); vendaForm.reset(); },
    });
}

// ═════════════════════════════════════════════════════════════════════
// Hub v3 — Ação contextual vinda do Hub
//
// Quando o usuário clica em "Registrar peso do animal" no Hub, ele chega
// aqui com `?acao=pesar`. Mostramos um banner explicando o contexto e
// convidando o usuário a escolher o animal. Ao clicar na linha (Link pro
// Show), propagamos `?acao=X` — o Show lê e abre o modal com o tipo certo.
//
// Também ativamos o modal de evento rápido com o tipo já pré-preenchido
// quando o usuário clica diretamente no ícone de ação da linha.
// ═════════════════════════════════════════════════════════════════════
const acaoContextual = ref(null); // null | 'pesar' | 'vacinar' | 'medicar' | 'vermifugar' | 'observar' | 'mover' | 'morte'

const MAPA_ACAO_TIPO = {
    pesar:       'pesagem',
    vacinar:     'vacinacao',
    medicar:     'medicacao',
    vermifugar:  'vermifugacao',
    observar:    'observacao',
    mover:       'movimentacao',         // mudança de LOTE (grupo)
    mover_pasto: 'movimentacao_local',   // mudança de PASTO (local físico)
    morte:       'mortalidade',
};

const ACAO_META = {
    pesar:       { titulo: 'Registrar peso',            instrucao: 'Clique no animal que você quer pesar.',    emoji: '⚖️' },
    vacinar:     { titulo: 'Aplicar vacina',            instrucao: 'Clique no animal que você quer vacinar.',  emoji: '💉' },
    medicar:     { titulo: 'Aplicar medicamento',       instrucao: 'Clique no animal que vai receber o medicamento.', emoji: '💊' },
    vermifugar:  { titulo: 'Aplicar vermífugo',         instrucao: 'Clique no animal que você quer vermifugar.', emoji: '🧴' },
    observar:    { titulo: 'Registrar observação',      instrucao: 'Clique no animal sobre o qual você quer anotar.', emoji: '📝' },
    mover:       { titulo: 'Mudar animal de lote',      instrucao: 'Clique no animal que vai mudar de grupo (lote).', emoji: '🐄' },
    mover_pasto: { titulo: 'Mover animal de pasto',     instrucao: 'Clique no animal que vai mudar de pasto (local físico).', emoji: '📍' },
    morte:       { titulo: 'Registrar morte do animal', instrucao: 'Clique no animal que morreu.',            emoji: '⚰️' },
};

onMounted(() => {
    const qs = new URLSearchParams(window.location.search);
    const acao = qs.get('acao');
    if (acao && MAPA_ACAO_TIPO[acao]) {
        acaoContextual.value = acao;
    }
});

/** Gera href pro Show propagando `?acao=X` quando em modo contextual. */
function hrefShow(id) {
    const base = route('admin.rebanho.animais.show', id);
    return acaoContextual.value ? `${base}?acao=${acaoContextual.value}` : base;
}

/** Abre evento rápido respeitando a ação contextual (quando aplicável). */
function abrirEventoContextualOuPadrao(row) {
    if (acaoContextual.value && MAPA_ACAO_TIPO[acaoContextual.value]) {
        abrirEventoRapido(row, MAPA_ACAO_TIPO[acaoContextual.value]);
    }
}

// Excluir (continua individual)
const confirmDelete = ref(null);
function doDelete() {
    router.delete(route('admin.rebanho.animais.destroy', confirmDelete.value.id), {
        onSuccess: () => (confirmDelete.value = null),
    });
}
</script>

<template>
    <Head title="Rebanho — Animais" />
    <AdminLayout>
        <template #page-title>Rebanho</template>
        <PageHeader :title="tituloHeader" :subtitle="subtituloHeader">
            <template #actions>
                <!-- BLOCO 4.3 — Hierarquia invertida: Vender é a ação core do dono (faz mais), Cadastrar é raro -->
                <!-- Dashboard leiteiro só aparece se a fazenda PRATICA manejo leiteiro (raça/categoria leite ou eventos) -->
                <Link v-if="tem_manejo_leiteiro" :href="route('admin.rebanho.controle-leiteiro.dashboard')" class="btn-outline" title="Quadro mensal DROVET com produção, categorias e histórico">
                    📊 Dashboard leiteiro
                </Link>
                <Link :href="linkNovoAnimal" class="btn-outline" :title="labelBotaoNovo">
                    {{ labelBotaoNovo }}
                </Link>
                <Link :href="route('admin.fluxos.venda-animal')" class="btn-primary" title="Fluxo guiado em 5 passos — ação principal do dono">
                    💰 Vender animal
                </Link>
            </template>
        </PageHeader>

        <!-- Resumo do rebanho — responde "quanto tenho?" sem precisar
             abrir o Dashboard. Cards simples (não clicáveis — apenas
             informação). -->
        <div v-if="Number(resumo.ativos) > 0" class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <div class="rounded-lg p-3 bg-emerald-50 border border-emerald-200">
                <div class="text-xs uppercase tracking-wider font-semibold text-emerald-700">🐄 Ativos</div>
                <div class="text-2xl font-bold mt-1 text-emerald-800">{{ resumo.ativos }}</div>
            </div>
            <div class="rounded-lg p-3 bg-slate-50 border border-slate-200">
                <div class="text-xs uppercase tracking-wider font-semibold text-slate-600">⚖️ Peso médio</div>
                <div class="text-2xl font-bold mt-1 text-slate-800">
                    {{ pesoMedio > 0 ? Number(pesoMedio).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + ' kg' : '—' }}
                </div>
                <div v-if="pesoMedio > 0" class="text-xs text-slate-500 mt-0.5">
                    Base: {{ resumo.ativos_com_peso }} {{ Number(resumo.ativos_com_peso) === 1 ? 'animal' : 'animais' }}
                </div>
            </div>
            <div class="rounded-lg p-3 bg-blue-50 border border-blue-200">
                <div class="text-xs uppercase tracking-wider font-semibold text-blue-700">💰 Vendidos</div>
                <div class="text-2xl font-bold mt-1 text-blue-800">{{ resumo.vendidos }}</div>
            </div>
            <div class="rounded-lg p-3 bg-slate-50 border border-slate-200">
                <div class="text-xs uppercase tracking-wider font-semibold text-slate-600">⚰️ Baixas</div>
                <div class="text-2xl font-bold mt-1 text-slate-700">{{ resumo.baixas }}</div>
            </div>
        </div>

        <!-- Hub v3 · Banner contextual quando usuário chega de um card de ação -->
        <div v-if="acaoContextual && ACAO_META[acaoContextual]"
             class="mb-4 p-4 rounded-xl bg-macaybas-primary-50 border-l-4 border-macaybas-primary-500 flex items-start gap-3">
            <span class="text-3xl flex-shrink-0" aria-hidden="true">{{ ACAO_META[acaoContextual].emoji }}</span>
            <div class="flex-1 min-w-0">
                <div class="font-semibold text-macaybas-primary-900">{{ ACAO_META[acaoContextual].titulo }}</div>
                <div class="text-sm text-macaybas-primary-800 mt-0.5">{{ ACAO_META[acaoContextual].instrucao }}</div>
            </div>
            <Link :href="route('admin.rebanho.animais.index')" class="text-sm text-macaybas-primary-800 hover:underline flex-shrink-0">
                Cancelar
            </Link>
        </div>

        <!-- F4.2 · Filtros colapsáveis em mobile (busca sempre visível) -->
        <MobileFilters cols="sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <template #always>
                <input v-model="filtros.search" @keyup.enter="filtrar"
                       placeholder="Buscar por brinco ou nome…" class="form-input">
            </template>
            <select v-model="filtros.categoria" @change="filtrar" class="form-select">
                <option value="">Todas as categorias</option>
                <option v-for="c in categorias" :key="c.value" :value="c.value">{{ c.label }}</option>
            </select>
            <select v-model="filtros.species_id" @change="filtrar" class="form-select">
                <option value="">Todas as espécies</option>
                <option v-for="s in species" :key="s.id" :value="s.id">{{ s.nome }}</option>
            </select>
            <select v-model="filtros.lot_id" @change="filtrar" class="form-select" title="Filtrar por LOTE (grupo lógico)">
                <option value="">🐄 Todos os lotes</option>
                <option v-for="l in lots" :key="l.id" :value="l.id">{{ l.nome }}</option>
            </select>
            <select v-model="filtros.location_id" @change="filtrar" class="form-select" title="Filtrar por PASTO (local físico)">
                <option value="">📍 Todos os pastos</option>
                <option v-for="l in locations" :key="l.id" :value="l.id">
                    {{ l.nome }}<span v-if="l.tipo"> · {{ l.tipo }}</span>
                </option>
            </select>
            <select v-model="filtros.status" @change="filtrar" class="form-select">
                <option value="">Todos os status</option>
                <option value="ativo">Ativo</option>
                <option value="vendido">Vendido</option>
                <option value="morto">Morto</option>
                <option value="abatido">Abatido</option>
                <option value="transferido">Transferido</option>
            </select>
        </MobileFilters>

        <!-- Barra sticky de ações em lote (aparece quando há seleção) -->
        <transition
            enter-active-class="transition-all duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2">
            <div v-if="selecionadosAtivos.length"
                 class="sticky top-16 z-20 mb-4 p-3 rounded-xl bg-macaybas-primary-900 text-white shadow-lg flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <span class="h-8 w-8 rounded-full bg-white/20 flex items-center justify-center text-sm font-bold flex-shrink-0">
                        {{ selecionadosAtivos.length }}
                    </span>
                    <span class="text-sm">
                        <strong>{{ selecionadosAtivos.length }}</strong> {{ especieSelecionada?.nome?.toLowerCase() || 'animal' }}{{ selecionadosAtivos.length === 1 ? '' : 's' }} selecionado{{ selecionadosAtivos.length === 1 ? '' : 's' }}
                    </span>
                    <span v-if="misturouEspecies" class="text-xs bg-amber-400/20 text-amber-200 px-2 py-0.5 rounded">Mistura de espécies — não pode vender</span>
                </div>
                <button @click="limparSelecao" class="text-sm text-white/70 hover:text-white">Limpar</button>
                <button @click="abrirVenda" :disabled="misturouEspecies"
                        class="btn bg-emerald-600 hover:bg-emerald-700 text-white focus:ring-emerald-500 gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
                    Vender selecionados
                </button>
            </div>
        </transition>

        <!-- Tabela custom com checkbox (desktop pleno ≥1280; iPad usa cards) -->
        <div class="hidden xl:block card overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" :checked="todosSelecionados" @change="todosSelecionados ? limparSelecao() : selecionarTodos()"
                                   class="rounded border-slate-300" title="Selecionar todos os ativos desta página">
                        </th>
                        <th></th>
                        <th>Brinco</th>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Espécie</th>
                        <th>Sexo</th>
                        <th class="text-right">Peso atual</th>
                        <th title="LOTE = grupo lógico (ex.: Bezerros 2026)">🐄 Lote</th>
                        <th title="PASTO = local físico (ex.: Pasto 1)">📍 Pasto</th>
                        <th>Status</th>
                        <th class="text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="!animals.data.length">
                        <td colspan="12" class="text-center text-slate-500 py-10">Nenhum animal cadastrado.</td>
                    </tr>
                    <tr v-for="row in animals.data" :key="row.id"
                        :class="[selecionados.has(row.id) ? 'bg-macaybas-primary-50' : '']">
                        <td>
                            <input type="checkbox" :checked="selecionados.has(row.id)" @change="toggle(row)"
                                   :disabled="row.status !== 'ativo'"
                                   class="rounded border-slate-300 disabled:opacity-30">
                        </td>
                        <td>
                            <img v-if="row.photo_url" :src="row.photo_url" class="h-9 w-9 rounded-full object-cover ring-1 ring-slate-200">
                            <div v-else class="h-9 w-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-xs">—</div>
                        </td>
                        <td class="font-medium">
                            <Link v-if="acaoContextual" :href="hrefShow(row.id)" class="text-macaybas-primary-800 hover:underline">{{ row.identificacao }}</Link>
                            <template v-else>{{ row.identificacao }}</template>
                        </td>
                        <td>
                            <Link v-if="acaoContextual && row.nome" :href="hrefShow(row.id)" class="text-macaybas-primary-800 hover:underline">{{ row.nome }}</Link>
                            <template v-else>{{ row.nome || '—' }}</template>
                        </td>
                        <td>
                            <span v-if="row.categoria" :class="categoriaBadge(row.categoria)">{{ categoriaLabel(row.categoria) }}</span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td>{{ row.species?.nome || '—' }}</td>
                        <td>{{ row.sexo }}</td>
                        <td class="text-right font-mono">{{ row.peso_atual ? Number(row.peso_atual).toLocaleString('pt-BR', {minimumFractionDigits: 1}) + ' kg' : '—' }}</td>
                        <td>{{ row.lot?.nome || '—' }}</td>
                        <td>
                            <template v-if="row.location">
                                {{ row.location.nome }}
                                <span v-if="row.location.tipo" class="text-xs text-slate-400">· {{ row.location.tipo }}</span>
                            </template>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td><span :class="statusBadge(row.status)">{{ row.status }}</span></td>
                        <td class="text-right">
                            <div class="flex gap-1 justify-end">
                                <ActionIcon v-for="ac in acoesDoAnimal(row)" :key="ac.key"
                                            :type="ac.icon" :title="ac.title" @click="abrirEventoRapido(row, ac.key)" />
                                <Link :href="hrefShow(row.id)" class="inline-flex">
                                    <ActionIcon type="history" title="Histórico completo" />
                                </Link>
                                <Link :href="route('admin.rebanho.animais.edit', row.id)" class="inline-flex">
                                    <ActionIcon type="edit" title="Editar animal" />
                                </Link>
                                <ActionIcon type="delete" title="Excluir animal" @click="confirmDelete = row" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Cards mobile -->
        <div class="xl:hidden space-y-3">
            <!-- B4.4 fix · empty state nos cards mobile (faltava antes do fix de breakpoint) -->
            <div v-if="!animals.data.length"
                 class="bg-white rounded-xl ring-1 ring-slate-200 shadow-sm p-8 text-center text-slate-500">
                Nenhum animal cadastrado.
            </div>
            <div v-for="row in animals.data" :key="row.id"
                 :class="[selecionados.has(row.id) ? 'ring-2 ring-macaybas-primary-300 bg-macaybas-primary-50' : 'bg-white ring-1 ring-slate-200']"
                 class="rounded-xl shadow-sm p-4">
                <div class="flex items-start gap-3">
                    <input type="checkbox" :checked="selecionados.has(row.id)" @change="toggle(row)"
                           :disabled="row.status !== 'ativo'"
                           class="mt-1 rounded border-slate-300 disabled:opacity-30">
                    <img v-if="row.photo_url" :src="row.photo_url" class="h-12 w-12 rounded-lg object-cover ring-1 ring-slate-200 flex-shrink-0">
                    <Link v-if="acaoContextual" :href="hrefShow(row.id)" class="flex-1 min-w-0 block">
                        <div class="flex items-center justify-between gap-2">
                            <div class="font-semibold text-slate-900 truncate text-macaybas-primary-800">{{ row.identificacao }}<span v-if="row.nome" class="font-normal text-slate-500"> · {{ row.nome }}</span></div>
                            <span :class="statusBadge(row.status)" class="flex-shrink-0">{{ row.status }}</span>
                        </div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            {{ row.species?.nome }} · {{ row.sexo }}<span v-if="row.lot"> · 🐄 {{ row.lot.nome }}</span><span v-if="row.location"> · 📍 {{ row.location.nome }}</span>
                        </div>
                        <div v-if="row.peso_atual" class="text-xs text-slate-600 mt-1">{{ Number(row.peso_atual).toLocaleString('pt-BR', {minimumFractionDigits: 1}) }} kg</div>
                    </Link>
                    <div v-else class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <div class="font-semibold text-slate-900 truncate">{{ row.identificacao }}<span v-if="row.nome" class="font-normal text-slate-500"> · {{ row.nome }}</span></div>
                            <span :class="statusBadge(row.status)" class="flex-shrink-0">{{ row.status }}</span>
                        </div>
                        <div class="text-xs text-slate-500 mt-0.5">
                            {{ row.species?.nome }} · {{ row.sexo }}<span v-if="row.lot"> · 🐄 {{ row.lot.nome }}</span><span v-if="row.location"> · 📍 {{ row.location.nome }}</span>
                        </div>
                        <div v-if="row.peso_atual" class="text-xs text-slate-600 mt-1">{{ Number(row.peso_atual).toLocaleString('pt-BR', {minimumFractionDigits: 1}) }} kg</div>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex flex-wrap gap-2 justify-end">
                    <ActionIcon v-for="ac in acoesDoAnimal(row)" :key="ac.key"
                                :type="ac.icon" :title="ac.title" @click="abrirEventoRapido(row, ac.key)" />
                    <Link :href="hrefShow(row.id)" class="inline-flex">
                        <ActionIcon type="history" title="Histórico" />
                    </Link>
                    <Link :href="route('admin.rebanho.animais.edit', row.id)" class="inline-flex">
                        <ActionIcon type="edit" title="Editar" />
                    </Link>
                    <ActionIcon type="delete" title="Excluir" @click="confirmDelete = row" />
                </div>
            </div>
        </div>

        <!-- Paginação — bug crítico: sem isso, com >25 animais o user só via os primeiros e
             não tinha como acessar o resto. Backend já paginate(25); só faltava renderizar links. -->
        <div v-if="animals.links && animals.links.length > 3" class="mt-4 flex gap-2 flex-wrap justify-end">
            <Link v-for="link in animals.links" :key="link.label"
                  :href="link.url ?? '#'"
                  v-html="link.label"
                  :class="['btn-outline btn-sm', link.active ? '!bg-macaybas-primary !text-white !border-transparent' : '', !link.url ? 'opacity-40 pointer-events-none' : '']"
                  :preserve-state="true" :preserve-scroll="true" only="['animals']" />
        </div>

        <ConfirmModal :show="!!confirmDelete" title="Excluir animal"
                      :message="`Excluir ${confirmDelete?.identificacao}?`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />

        <!-- Modal: evento rápido (pesagem/vacinação/ordenha/biometria/postura) -->
        <Teleport to="body">
            <div v-if="eventoRapido" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="eventoRapido = null"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <h3 class="text-lg font-semibold text-slate-900 mb-1">Registrar {{ labelEvento(eventoTipo).toLowerCase() }}</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        <span v-if="eventoRapido.species?.gestao === 'lote'">Lote</span>
                        <span v-else>Brinco</span>
                        <strong> {{ eventoRapido.identificacao }}</strong>
                    </p>
                    <div class="space-y-3">
                        <div>
                            <InputLabel :value="`Data *`" />
                            <InputDate v-model="eventoForm.data_evento" :max="new Date().toISOString().slice(0,10)" required />
                        </div>
                        <div v-if="eventoTipo === 'pesagem' || eventoTipo === 'biometria_amostral'">
                            <InputLabel :value="eventoTipo === 'biometria_amostral' ? 'Peso médio do lote (kg) *' : 'Peso (kg) *'" />
                            <InputDecimal v-model="eventoForm.peso" :decimals="2" :min="0" placeholder="0,00" required />
                        </div>
                        <div v-if="eventoTipo === 'ordenha'">
                            <InputLabel value="Volume (litros) *" />
                            <InputDecimal v-model="eventoForm.peso" :decimals="2" :min="0" placeholder="0,00" required />
                        </div>
                        <div v-if="eventoTipo === 'postura_diaria'">
                            <InputLabel value="Ovos coletados *" />
                            <input type="number" step="1" min="0" v-model="eventoForm.peso" class="form-input" required>
                        </div>
                        <template v-if="eventoTipo === 'vacinacao'">
                            <div>
                                <InputLabel value="Vacina *" />
                                <input v-model="eventoForm.vacina" class="form-input" placeholder="Ex: Febre Aftosa" required>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div><InputLabel value="Dose (ml)" /><InputDecimal v-model="eventoForm.dose" :decimals="2" :min="0" placeholder="0,00" /></div>
                                <div><InputLabel value="Via" /><input v-model="eventoForm.via_aplicacao" class="form-input" placeholder="subcutânea"></div>
                            </div>
                        </template>
                        <div>
                            <InputLabel value="Observações" />
                            <textarea v-model="eventoForm.observacoes" rows="2" class="form-textarea"></textarea>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="eventoRapido = null" class="btn-outline">Cancelar</button>
                        <button @click="confirmarEventoRapido" :disabled="eventoForm.processing" class="btn-primary">
                            {{ eventoForm.processing ? 'Salvando...' : 'Registrar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal: venda em lote contextual (arroba/kg/unidade/cabeça) -->
        <Teleport to="body">
            <div v-if="vendaAberta" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="vendaAberta = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-xl w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <h3 class="text-lg font-semibold text-slate-900 mb-1">
                        Venda — {{ nCabecas }} {{ especieSelecionada?.nome?.toLowerCase() }}{{ nCabecas === 1 ? '' : 's' }}
                    </h3>
                    <p class="text-sm text-slate-500 mb-4">{{ vendaCfg?.label }}</p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Data da venda *" />
                            <InputDate v-model="vendaForm.data_venda" :max="new Date().toISOString().slice(0,10)" required />
                        </div>
                        <div>
                            <InputLabel value="Comprador" />
                            <select v-model="vendaForm.partner_id" class="form-select">
                                <option :value="null">—</option>
                                <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.nome }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Unidade de venda *" />
                            <select v-model="vendaForm.unidade" class="form-select">
                                <option v-for="u in vendaCfg?.unidades || []" :key="u.v" :value="u.v">{{ u.l }}</option>
                            </select>
                        </div>

                        <!-- Campo peso médio por cabeça (bovino, suíno, ovino) -->
                        <div v-if="perguntaPesoMedio">
                            <InputLabel value="Peso médio por cabeça (kg) *" />
                            <InputDecimal v-model="vendaForm.peso_medio" :decimals="2" :min="0" placeholder="Ex: 480" />
                            <p v-if="vendaForm.peso_medio" class="text-xs text-slate-500 mt-1">
                                Total estimado: {{ (Number(vendaForm.peso_medio) * nCabecas).toLocaleString('pt-BR', {maximumFractionDigits: 2}) }} kg
                            </p>
                        </div>
                        <!-- Campo quantidade direta (peixe, aves, ovos) -->
                        <div v-else-if="vendaForm.unidade !== 'cabeca'">
                            <InputLabel :value="`Quantidade (${vendaForm.unidade}) *`" />
                            <InputDecimal v-model="vendaForm.quantidade" :decimals="3" :min="0" placeholder="0,000" />
                        </div>
                        <!-- Venda por cabeça fixa: só precisa valor por cabeça -->
                        <div v-else>
                            <div class="form-input bg-slate-50 text-slate-600 flex items-center">
                                {{ nCabecas }} cabeça{{ nCabecas === 1 ? '' : 's' }} (da seleção)
                            </div>
                        </div>

                        <div>
                            <InputLabel :value="`Valor por ${vendaForm.unidade} *`" />
                            <InputMoney v-model="vendaForm.valor_unitario" />
                        </div>

                        <!-- Resumo calculado -->
                        <div class="sm:col-span-2 p-4 rounded-lg bg-emerald-50 border border-emerald-200">
                            <div class="text-xs uppercase tracking-wider text-emerald-700 mb-2">Resumo da venda</div>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>Quantidade:</div>
                                <div class="text-right font-mono">{{ Number(quantidadeCalculada).toLocaleString('pt-BR', {maximumFractionDigits: 3}) }} {{ vendaForm.unidade }}</div>
                                <template v-if="pesoTotalKg">
                                    <div>Peso total:</div>
                                    <div class="text-right font-mono">{{ Number(pesoTotalKg).toLocaleString('pt-BR', {maximumFractionDigits: 2}) }} kg</div>
                                </template>
                                <div>Valor por cabeça:</div>
                                <div class="text-right font-mono">{{ brl(valorPorCabeca) }}</div>
                                <div class="font-semibold text-emerald-900">Total:</div>
                                <div class="text-right font-mono font-bold text-emerald-900">{{ brl(totalVenda) }}</div>
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <InputLabel value="Observações" />
                            <textarea v-model="vendaForm.observacoes" rows="2" class="form-textarea"></textarea>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="vendaAberta = false" class="btn-outline">Cancelar</button>
                        <button @click="confirmarVenda" :disabled="vendaForm.processing || !totalVenda"
                                class="btn-primary bg-emerald-600 hover:bg-emerald-700">
                            {{ vendaForm.processing ? 'Salvando...' : `Confirmar venda de ${brl(totalVenda)}` }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
