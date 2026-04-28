<script setup>
/**
 * ═════ FASE 5 · CAMADA DE INTELIGÊNCIA · VENDA ADAPTATIVA ═════
 *
 * O wizard NÃO assume mais "1 venda = 1 animal". Pergunta primeiro
 * "O QUE você quer vender?" e adapta passos conforme o modo.
 *
 * Passos:
 *   1 · MODO        → individual, múltiplos, lote inteiro, qtd do lote, por peso
 *   2 · SELEÇÃO     → cards/select adaptados ao modo + filtro por espécie
 *   3 · COMPRADOR   → com criação inline
 *   4 · PREÇO       → unidade real (cabeça/kg/arroba/saca/litro/un) × qtd × valor
 *   5 · RESUMO      → o que, quantos, qual unidade, valor unit, total, comprador
 *   6 · PRONTO!     → confirmação + impacto financeiro
 */
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputMoney from '@/Components/InputMoney.vue';
import InputDate from '@/Components/InputDate.vue';
import { brl, dataBR, hojeBR } from '@/utils/format.js';
import { emojiEspecie } from '@/utils/emojiEspecie.js';
import { useInlineCreate } from '@/composables/useInlineCreate.js';

const props = defineProps({
    animais: { type: Array, required: true },
    lotes: { type: Array, default: () => [] },
    species: { type: Array, default: () => [] },
    partners: { type: Array, required: true },
});

// ── Modos de venda (camada de inteligência) ─────────────────────────
const MODOS = [
    {
        id: 'individual',
        rotulo: 'Um animal',
        descricao: 'Vendendo só um animal específico (ex.: o cavalo, o pet, uma matriz).',
        icone: '🐎',
    },
    {
        id: 'multiplos',
        rotulo: 'Vários animais',
        descricao: 'Selecionar vários da lista (ex.: 5 bovinos para o frigorífico).',
        icone: '🐄',
    },
    {
        id: 'lote_total',
        rotulo: 'Lote/tanque inteiro',
        descricao: 'Vender todos os animais ativos de um lote ou tanque.',
        icone: '🐟',
    },
    {
        id: 'lote_quantidade',
        rotulo: 'Quantidade do lote',
        descricao: 'Sem identificar individual — só dizer "300 frangos" ou "200 kg de tilápia".',
        icone: '🐔',
    },
    {
        id: 'peso',
        rotulo: 'Por peso (arroba/kg)',
        descricao: 'Animais identificados, mas o preço é por arroba ou kg total.',
        icone: '⚖',
    },
];

const PASSOS = [
    { n: 1, titulo: 'O que vender', icon: '🛒' },
    { n: 2, titulo: 'Seleção',      icon: '🐾' },
    { n: 3, titulo: 'Comprador',    icon: '🤝' },
    { n: 4, titulo: 'Preço',        icon: '💰' },
    { n: 5, titulo: 'Conferir',     icon: '📋' },
    { n: 6, titulo: 'Pronto!',      icon: '✅' },
];

// ── Estado ──────────────────────────────────────────────────────────
const passo = ref(1);
const modo = ref(null);
const filtroEspecieId = ref(null);
const busca = ref('');

// Seleção (depende do modo)
const animalUnico = ref(null);          // modo individual
const animalIdsSelecionados = ref([]);  // multiplos / peso
const loteId = ref(null);               // lote_total / lote_quantidade
const qtdLote = ref('');                // lote_quantidade

// Comprador
const compradorId = ref(null);
const partnersLocal = ref([...(props.partners ?? [])]);

const novoComprador = useInlineCreate({
    endpoint: route('admin.parceiros.inline'),
    initialForm: { nome: '', tipo: 'cliente', pessoa: 'fisica', documento: '', telefone: '' },
    onCreated: (p) => {
        partnersLocal.value = [...partnersLocal.value, p];
        compradorId.value = p.id;
    },
});

// Preço
const unidade = ref('cabeca');
const quantidade = ref('');         // qtd em "unidade" (ex.: 30 arrobas, 5 cabeças, 200 kg)
const valorUnitarioStr = ref('');   // R$ por unidade
const pesoMedio = ref('');          // kg por cabeça (modo peso)
const dataVenda = ref(hojeBR());
const observacoes = ref('');

const sucesso = ref(null);

// ── Helpers ─────────────────────────────────────────────────────────
function tituloAnimal(a) {
    if (!a) return '';
    return a.nome ? `${a.identificacao} — ${a.nome}` : a.identificacao;
}

function profileDoAnimal(a) {
    return a?.species?.profile ?? null;
}

function unidadeDefaultParaSpecies(speciesId) {
    const s = props.species.find((x) => x.id === speciesId);
    return s?.unidade_default ?? 'cabeca';
}

const ESPECIE_DO_MODO = computed(() => filtroEspecieId.value);

// Animais elegíveis (filtrados por espécie quando aplicável)
const animaisElegiveis = computed(() => {
    let lista = props.animais;
    if (filtroEspecieId.value) {
        lista = lista.filter((a) => a.species?.id === filtroEspecieId.value);
    }
    const q = busca.value.trim().toLowerCase();
    if (q) {
        lista = lista.filter((a) =>
            (a.identificacao ?? '').toLowerCase().includes(q) ||
            (a.nome ?? '').toLowerCase().includes(q) ||
            (a.breed?.nome ?? '').toLowerCase().includes(q) ||
            (a.species?.nome ?? '').toLowerCase().includes(q) ||
            (a.lot?.nome ?? '').toLowerCase().includes(q),
        );
    }
    return lista;
});

const lotesElegiveis = computed(() => {
    if (!filtroEspecieId.value) return props.lotes;
    return props.lotes.filter((l) => l.species_id === filtroEspecieId.value);
});

// Resolução do que está sendo vendido (cross-modo)
const animaisAfetados = computed(() => {
    if (modo.value === 'individual') return animalUnico.value ? [animalUnico.value] : [];
    if (modo.value === 'multiplos' || modo.value === 'peso') {
        return props.animais.filter((a) => animalIdsSelecionados.value.includes(a.id));
    }
    if (modo.value === 'lote_total') {
        if (!loteId.value) return [];
        return props.animais.filter((a) => a.lot_id === loteId.value);
    }
    if (modo.value === 'lote_quantidade') {
        if (!loteId.value) return [];
        const lote = props.lotes.find((l) => l.id === loteId.value);
        return Array.from({ length: Math.min(parseInt(qtdLote.value || 0), lote?.ativos_count || 0) }).fill(null);
    }
    return [];
});

const totalItens = computed(() => {
    if (modo.value === 'lote_quantidade') return parseInt(qtdLote.value || 0);
    if (modo.value === 'lote_total') {
        const lote = props.lotes.find((l) => l.id === loteId.value);
        return lote?.ativos_count ?? 0;
    }
    return animaisAfetados.value.length;
});

const especiesContexto = computed(() => {
    const ids = new Set();
    if (modo.value === 'individual' && animalUnico.value) {
        ids.add(animalUnico.value.species?.id);
    } else if (modo.value === 'multiplos' || modo.value === 'peso') {
        animaisAfetados.value.forEach((a) => ids.add(a.species?.id));
    } else if (loteId.value) {
        const lote = props.lotes.find((l) => l.id === loteId.value);
        if (lote) ids.add(lote.species_id);
    }
    return [...ids].filter(Boolean);
});

// Cálculos
// InputMoney emite Number direto (já dividido por 100 internamente).
// Aceitamos também string para compatibilidade com type/Playwright fill.
const valorUnitarioNum = computed(() => {
    const v = valorUnitarioStr.value;
    if (v === null || v === undefined || v === '') return 0;
    if (typeof v === 'number') return v;
    // Fallback string — interpreta como float pt-BR
    const n = parseFloat(String(v).replace(/[^\d,.\-]/g, '').replace('.', '').replace(',', '.'));
    return isNaN(n) ? 0 : n;
});
const quantidadeNum = computed(() => {
    const n = parseFloat(String(quantidade.value).replace(',', '.'));
    return isNaN(n) ? 0 : n;
});
const valorTotal = computed(() => {
    return Math.round(valorUnitarioNum.value * quantidadeNum.value * 100) / 100;
});

// Opções de unidade adaptadas ao contexto
const unidadesDisponiveis = computed(() => {
    const profile = animaisAfetados.value[0]?.species?.profile
        ?? (loteId.value ? props.species.find((s) => s.id === props.lotes.find((l) => l.id === loteId.value)?.species_id)?.profile : null);

    // Mapa por profile (recomendadas primeiro, mas todas selecionáveis)
    const todas = [
        { id: 'cabeca', rotulo: 'cabeça (animal)', curto: 'cab' },
        { id: 'kg', rotulo: 'kg (peso total)', curto: 'kg' },
        { id: 'arroba', rotulo: 'arroba (15 kg)', curto: '@' },
        { id: 'saca', rotulo: 'saca', curto: 'sc' },
        { id: 'litro', rotulo: 'litro', curto: 'L' },
        { id: 'unidade', rotulo: 'unidade', curto: 'un' },
    ];

    if (!profile) return todas;
    if (['ruminante_corte', 'ruminante_lan'].includes(profile)) {
        // Bovino corte: arroba (mais comum), kg, cabeça
        return ['arroba', 'kg', 'cabeca'].map((id) => todas.find((u) => u.id === id));
    }
    if (profile === 'ruminante_leite') {
        return ['cabeca', 'litro', 'kg'].map((id) => todas.find((u) => u.id === id));
    }
    if (profile === 'aquicultura_lote') {
        return ['kg', 'unidade', 'cabeca'].map((id) => todas.find((u) => u.id === id));
    }
    if (['ave_postura', 'ave_corte'].includes(profile)) {
        return ['unidade', 'kg', 'cabeca'].map((id) => todas.find((u) => u.id === id));
    }
    if (['suino', 'equino', 'pet'].includes(profile)) {
        return ['cabeca', 'kg'].map((id) => todas.find((u) => u.id === id));
    }
    return todas;
});

// Quando o modo for `peso`, força arroba como sugestão inicial
watch(modo, (m) => {
    if (m === 'peso') {
        unidade.value = 'arroba';
    } else if (m === 'lote_quantidade') {
        // Sugestão de unidade pelo profile do lote
        if (loteId.value) {
            const lote = props.lotes.find((l) => l.id === loteId.value);
            if (lote) unidade.value = unidadeDefaultParaSpecies(lote.species_id);
        }
    } else {
        // Sugestão default por animal selecionado
        const a = animaisAfetados.value[0];
        if (a?.species_id) unidade.value = unidadeDefaultParaSpecies(a.species_id);
    }
});

// Quando o lote for selecionado, atualiza unidade-default
watch(loteId, (id) => {
    if (!id) return;
    const lote = props.lotes.find((l) => l.id === id);
    if (lote) unidade.value = unidadeDefaultParaSpecies(lote.species_id);
});

// Quando seleciona animal, sugere unidade
watch(animalUnico, (a) => {
    if (a?.species_id) unidade.value = unidadeDefaultParaSpecies(a.species_id);
});

// Auto-cálculo da quantidade default quando faz sentido
watch([modo, animaisAfetados, totalItens], () => {
    if (!quantidade.value && (modo.value === 'multiplos' || modo.value === 'individual')) {
        if (unidade.value === 'cabeca') quantidade.value = String(totalItens.value || 1);
    }
});

// ── Validações por passo ────────────────────────────────────────────
const podeAvancar1 = computed(() => modo.value !== null);

const podeAvancar2 = computed(() => {
    if (modo.value === 'individual') return !!animalUnico.value;
    if (modo.value === 'multiplos' || modo.value === 'peso') return animalIdsSelecionados.value.length > 0;
    if (modo.value === 'lote_total') return !!loteId.value;
    if (modo.value === 'lote_quantidade') return !!loteId.value && parseInt(qtdLote.value || 0) > 0;
    return false;
});

const podeAvancar3 = computed(() => !!compradorId.value);

const podeAvancar4 = computed(() =>
    quantidadeNum.value > 0
    && valorUnitarioNum.value > 0
    && !!unidade.value
    && !!dataVenda.value
    && dataVenda.value <= hojeBR(),
);

const comprador = computed(() => partnersLocal.value.find((p) => p.id === compradorId.value));

// ── Navegação ───────────────────────────────────────────────────────
function avancar() {
    if (passo.value === 1 && !podeAvancar1.value) return;
    if (passo.value === 2 && !podeAvancar2.value) return;
    if (passo.value === 3 && !podeAvancar3.value) return;
    if (passo.value === 4 && !podeAvancar4.value) return;
    if (passo.value < 6) passo.value++;
}
function voltar() { if (passo.value > 1) passo.value--; }
function irPara(n) { passo.value = n; }

function toggleAnimalSelecao(a) {
    const i = animalIdsSelecionados.value.indexOf(a.id);
    if (i === -1) {
        animalIdsSelecionados.value.push(a.id);
    } else {
        animalIdsSelecionados.value.splice(i, 1);
    }
}

function reiniciar() {
    passo.value = 1;
    modo.value = null;
    filtroEspecieId.value = null;
    busca.value = '';
    animalUnico.value = null;
    animalIdsSelecionados.value = [];
    loteId.value = null;
    qtdLote.value = '';
    compradorId.value = null;
    unidade.value = 'cabeca';
    quantidade.value = '';
    valorUnitarioStr.value = '';
    pesoMedio.value = '';
    dataVenda.value = hojeBR();
    observacoes.value = '';
    sucesso.value = null;
    form.clearErrors();
}

// ── Submit ──────────────────────────────────────────────────────────
// IMPORTANT: campo `data` colide com método `.data()` do useForm Inertia.
// Usamos `data_venda` no form e transformamos para `data` antes de enviar.
const form = useForm({
    modo: '',
    data_venda: '',
    partner_id: null,
    unidade: '',
    quantidade: 0,        // unidade negociada (kg, arroba, cabeça, ...)
    qtd_animais: null,    // quantos animais saem do lote (modo lote_quantidade)
    valor_unitario: 0,
    valor_total: 0,
    peso_medio: null,
    animal_id: null,
    animal_ids: [],
    lot_id: null,
    observacoes: '',
});

function confirmar() {
    form.modo = modo.value;
    form.data_venda = dataVenda.value;
    form.partner_id = compradorId.value;
    form.unidade = unidade.value;
    form.quantidade = quantidadeNum.value;
    form.valor_unitario = valorUnitarioNum.value;
    form.valor_total = valorTotal.value;
    form.peso_medio = pesoMedio.value ? parseFloat(String(pesoMedio.value).replace(',', '.')) : null;
    form.observacoes = observacoes.value;

    form.animal_id = (modo.value === 'individual') ? animalUnico.value?.id : null;
    form.animal_ids = (modo.value === 'multiplos' || modo.value === 'peso')
        ? animalIdsSelecionados.value
        : [];
    form.lot_id = ['lote_total', 'lote_quantidade'].includes(modo.value) ? loteId.value : null;
    // qtd_animais só faz sentido em lote_quantidade — é o input "Quantos animais saem"
    form.qtd_animais = (modo.value === 'lote_quantidade')
        ? parseInt(qtdLote.value || 0)
        : null;

    // Snapshot ANTES do submit — após o submit, props.animais é atualizado
    // (animais vendidos saem do array) e os computeds zeram. Capturamos o
    // estado atual para mostrar na tela de sucesso.
    const snapshot = {
        modo: modo.value,
        total: totalItens.value,
        unidade: unidade.value,
        quantidade: quantidadeNum.value,
        valor_unitario: valorUnitarioNum.value,
        valor_total: valorTotal.value,
        comprador_nome: comprador.value?.nome,
    };

    form.transform((d) => {
        const { data_venda, ...rest } = d;
        return { ...rest, data: data_venda };
    }).post(route('admin.fluxos.venda-animal.store'), {
        preserveScroll: false,
        onSuccess: (page) => {
            const ctx = page?.props?.flash?.venda_contexto ?? null;
            sucesso.value = {
                ...snapshot,
                // Backend confirma o total real persistido — preferir esse valor
                total: ctx?.total_animais ?? snapshot.total,
                ctx,
            };
            passo.value = 6;
        },
    });
}

// ── Rótulos UX ──────────────────────────────────────────────────────
function rotuloUnidade(u) {
    return unidadesDisponiveis.value.find((x) => x.id === u)?.curto ?? u;
}

const tituloModo = computed(() => MODOS.find((m) => m.id === modo.value)?.rotulo ?? '');
</script>

<template>
    <Head title="Vender — Fluxo adaptativo" />
    <AdminLayout>
        <template #page-title>Vender animal</template>
        <PageHeader
            title="Assistente de venda"
            subtitle="Vendendo o quê? Fala primeiro o tipo — depois a gente adapta o resto."
        >
            <template #actions>
                <Link :href="route('admin.rebanho.animais.index')" class="btn-outline">Sair</Link>
            </template>
        </PageHeader>

        <!-- Stepper -->
        <div class="mb-8">
            <div class="flex items-center justify-between max-w-4xl mx-auto">
                <template v-for="(p, i) in PASSOS" :key="p.n">
                    <div class="flex flex-col items-center">
                        <div class="h-10 w-10 rounded-full flex items-center justify-center font-bold transition-all"
                            :class="{
                                'bg-macaybas-primary text-white ring-4 ring-emerald-100': passo === p.n,
                                'bg-emerald-500 text-white': passo > p.n,
                                'bg-slate-200 text-slate-500': passo < p.n,
                            }">
                            <span v-if="passo > p.n">✓</span>
                            <span v-else>{{ p.icon }}</span>
                        </div>
                        <span class="text-xs mt-1.5 whitespace-nowrap"
                            :class="passo === p.n ? 'text-macaybas-primary font-semibold' : 'text-slate-500'">
                            {{ p.titulo }}
                        </span>
                    </div>
                    <div v-if="i < PASSOS.length - 1" class="flex-1 h-1 mx-1 mb-5 rounded-full"
                        :class="passo > p.n ? 'bg-emerald-400' : 'bg-slate-200'"></div>
                </template>
            </div>
        </div>

        <!-- ══════ PASSO 1 — O QUE VENDER ══════ -->
        <div v-if="passo === 1" class="card max-w-4xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">O que você quer vender?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Escolha o cenário que combina com a sua venda. Vamos guiar o resto.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="m in MODOS"
                        :key="m.id"
                        type="button"
                        :data-modo="m.id"
                        @click="modo = m.id"
                        class="text-left rounded-xl border-2 p-5 transition-all hover:border-macaybas-primary hover:shadow-md"
                        :class="modo === m.id
                            ? 'border-macaybas-primary bg-emerald-50 shadow-md'
                            : 'border-slate-200 bg-white'">
                        <div class="flex items-start gap-3">
                            <div class="text-3xl">{{ m.icone }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-slate-900">{{ m.rotulo }}</div>
                                <div class="text-sm text-slate-600 mt-1">{{ m.descricao }}</div>
                            </div>
                            <div v-if="modo === m.id" class="text-emerald-600 flex-shrink-0">
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>
                    </button>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-100">
                    <button @click="avancar" :disabled="!podeAvancar1"
                        class="btn-primary px-6 py-2.5 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- ══════ PASSO 2 — SELEÇÃO ══════ -->
        <div v-if="passo === 2" class="card max-w-5xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">
                        <span v-if="modo === 'individual'">Qual animal você está vendendo?</span>
                        <span v-else-if="modo === 'multiplos'">Quais animais entram nesta venda?</span>
                        <span v-else-if="modo === 'lote_total'">Qual lote/tanque você quer vender inteiro?</span>
                        <span v-else-if="modo === 'lote_quantidade'">De qual lote/tanque, e quantos?</span>
                        <span v-else-if="modo === 'peso'">Quais animais estão indo (vendidos por peso)?</span>
                    </h2>
                    <p class="text-base text-slate-600 mt-2" v-if="modo === 'multiplos' || modo === 'peso'">
                        Pode clicar em vários cards. O contador aparece embaixo.
                    </p>
                    <p class="text-base text-slate-600 mt-2" v-else-if="modo === 'lote_quantidade'">
                        Útil para peixe/ave/suíno em massa: você só diz a quantidade, o sistema baixa do estoque do lote.
                    </p>
                </div>

                <!-- Filtro por espécie (sempre disponível) -->
                <div v-if="props.species.length > 1" class="flex flex-wrap gap-2 items-center">
                    <span class="text-sm text-slate-600">Filtrar por espécie:</span>
                    <button type="button" @click="filtroEspecieId = null"
                        class="text-xs px-3 py-1 rounded-full border"
                        :class="filtroEspecieId === null ? 'bg-macaybas-primary text-white border-macaybas-primary' : 'bg-white border-slate-300 text-slate-700'">
                        Todas
                    </button>
                    <button v-for="s in props.species" :key="s.id"
                        type="button" @click="filtroEspecieId = s.id"
                        class="text-xs px-3 py-1 rounded-full border"
                        :class="filtroEspecieId === s.id ? 'bg-macaybas-primary text-white border-macaybas-primary' : 'bg-white border-slate-300 text-slate-700'">
                        {{ emojiEspecie(s.nome) }} {{ s.nome }}
                    </button>
                </div>

                <!-- ─── Modo: individual ─── -->
                <template v-if="modo === 'individual'">
                    <input v-model="busca" placeholder="Buscar pelo brinco, nome ou raça…"
                        class="form-input text-base py-2.5" v-if="animaisElegiveis.length > 6">
                    <div v-if="animaisElegiveis.length === 0" class="text-center text-slate-500 py-8">
                        Nenhum animal disponível.
                    </div>
                    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 max-h-[55vh] overflow-y-auto pr-1">
                        <button v-for="a in animaisElegiveis" :key="a.id"
                            type="button" @click="animalUnico = a"
                            class="text-left rounded-xl border-2 p-3 transition-all hover:border-macaybas-primary"
                            :class="animalUnico?.id === a.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                            <div class="flex items-start gap-2">
                                <span class="text-2xl">{{ emojiEspecie(a.species?.nome) }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-slate-900 truncate">{{ a.identificacao }}</div>
                                    <div v-if="a.nome" class="text-sm text-slate-600 truncate">{{ a.nome }}</div>
                                    <div class="text-xs text-slate-500">{{ a.species?.nome }}<span v-if="a.breed?.nome"> · {{ a.breed.nome }}</span></div>
                                    <div v-if="a.peso_atual" class="text-xs text-slate-500">⚖ {{ a.peso_atual }} kg</div>
                                </div>
                            </div>
                        </button>
                    </div>
                </template>

                <!-- ─── Modo: múltiplos ou peso ─── -->
                <template v-else-if="modo === 'multiplos' || modo === 'peso'">
                    <input v-model="busca" placeholder="Buscar pelo brinco, nome ou raça…"
                        class="form-input text-base py-2.5" v-if="animaisElegiveis.length > 6">

                    <div v-if="animaisElegiveis.length === 0" class="text-center text-slate-500 py-8">
                        Nenhum animal disponível com esse filtro.
                    </div>

                    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 max-h-[55vh] overflow-y-auto pr-1">
                        <button v-for="a in animaisElegiveis" :key="a.id"
                            type="button" @click="toggleAnimalSelecao(a)"
                            :data-animal-id="a.id"
                            class="text-left rounded-xl border-2 p-3 transition-all hover:border-macaybas-primary relative"
                            :class="animalIdsSelecionados.includes(a.id) ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                            <div v-if="animalIdsSelecionados.includes(a.id)"
                                class="absolute top-2 right-2 h-6 w-6 rounded-full bg-emerald-500 text-white flex items-center justify-center text-sm font-bold">
                                ✓
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-2xl">{{ emojiEspecie(a.species?.nome) }}</span>
                                <div class="min-w-0 flex-1 pr-6">
                                    <div class="font-bold text-slate-900 truncate">{{ a.identificacao }}</div>
                                    <div v-if="a.nome" class="text-sm text-slate-600 truncate">{{ a.nome }}</div>
                                    <div class="text-xs text-slate-500">{{ a.species?.nome }}<span v-if="a.breed?.nome"> · {{ a.breed.nome }}</span></div>
                                    <div v-if="a.peso_atual" class="text-xs text-slate-500">⚖ {{ a.peso_atual }} kg</div>
                                </div>
                            </div>
                        </button>
                    </div>

                    <!-- Contador fixo embaixo -->
                    <div class="sticky bottom-0 bg-white border-t-2 border-emerald-200 px-4 py-3 rounded-b-xl flex items-center justify-between">
                        <div class="text-sm">
                            <strong class="text-emerald-700">{{ animalIdsSelecionados.length }}</strong>
                            {{ animalIdsSelecionados.length === 1 ? 'animal selecionado' : 'animais selecionados' }}
                        </div>
                        <button v-if="animalIdsSelecionados.length > 0"
                            @click="animalIdsSelecionados = []"
                            class="text-xs text-slate-500 hover:text-red-600">Limpar seleção</button>
                    </div>
                </template>

                <!-- ─── Modo: lote_total ou lote_quantidade ─── -->
                <template v-else-if="modo === 'lote_total' || modo === 'lote_quantidade'">
                    <div v-if="lotesElegiveis.length === 0" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
                        Nenhum lote/tanque com animais ativos disponível.
                    </div>
                    <div v-else class="grid gap-3 sm:grid-cols-2">
                        <button v-for="l in lotesElegiveis" :key="l.id"
                            type="button" @click="loteId = l.id"
                            :data-lote-id="l.id"
                            class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary"
                            :class="loteId === l.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                            <div class="font-bold text-slate-900">{{ l.nome }}</div>
                            <div class="text-sm text-slate-600 mt-1">
                                {{ l.ativos_count }} {{ l.ativos_count === 1 ? 'animal ativo' : 'animais ativos' }}
                            </div>
                        </button>
                    </div>

                    <div v-if="modo === 'lote_quantidade' && loteId" class="max-w-md">
                        <InputLabel value="Quantos animais saem deste lote?" />
                        <input v-model="qtdLote" type="number" min="1"
                            :max="props.lotes.find((l) => l.id === loteId)?.ativos_count"
                            class="form-input text-lg py-3 font-mono" placeholder="Ex.: 300">
                        <p class="text-xs text-slate-500 mt-1">
                            Lote tem {{ props.lotes.find((l) => l.id === loteId)?.ativos_count }} ativos no momento.
                        </p>
                    </div>
                </template>

                <div class="flex justify-between pt-5 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar2" class="btn-primary px-6 py-2.5 text-base">
                        Continuar →
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════ PASSO 3 — COMPRADOR ══════ -->
        <div v-if="passo === 3" class="card max-w-4xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Para quem é a venda?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Escolha o comprador. Se ele ainda não estiver cadastrado, dá para criar agora — sem sair daqui.
                    </p>
                </div>

                <!-- Estado vazio: sem compradores → call-to-action criar inline -->
                <div v-if="partnersLocal.length === 0" class="rounded-xl border-2 border-amber-200 bg-amber-50 p-5">
                    <div class="font-semibold text-lg text-amber-900 flex items-center gap-2">
                        <span class="text-2xl">⚠</span>
                        Você ainda não cadastrou nenhum comprador
                    </div>
                    <p class="mt-2 text-amber-800">Cadastre o primeiro aqui mesmo — depois a venda continua.</p>
                    <button data-cy="abrir-novo-comprador" type="button" @click="novoComprador.abrir()"
                        class="inline-block mt-3 btn-primary">+ Cadastrar comprador agora</button>
                </div>

                <div v-else class="flex justify-end">
                    <button data-cy="abrir-novo-comprador" type="button" @click="novoComprador.abrir()"
                        class="text-sm text-macaybas-primary hover:underline">+ Novo comprador</button>
                </div>

                <div v-if="partnersLocal.length > 0" class="grid gap-3 sm:grid-cols-2 max-h-[55vh] overflow-y-auto pr-1">
                    <button v-for="p in partnersLocal" :key="p.id"
                        type="button" @click="compradorId = p.id"
                        :data-partner-id="p.id"
                        class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary"
                        :class="compradorId === p.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                        <div class="flex items-start gap-3">
                            <div class="h-12 w-12 rounded-full flex items-center justify-center text-2xl flex-shrink-0"
                                :class="p.pessoa === 'pj' ? 'bg-indigo-100' : 'bg-emerald-100'">
                                {{ p.pessoa === 'pj' ? '🏢' : '👤' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-slate-900 truncate">{{ p.nome }}</div>
                                <div class="text-sm text-slate-500 mt-1">
                                    {{ p.pessoa === 'pj' ? 'Empresa' : 'Pessoa' }}
                                    <span v-if="p.documento"> · {{ p.documento }}</span>
                                </div>
                            </div>
                        </div>
                    </button>
                </div>

                <div class="flex justify-between pt-5 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar3" class="btn-primary px-6 py-2.5 text-base">
                        Continuar →
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════ PASSO 4 — PREÇO ══════ -->
        <div v-if="passo === 4" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Quanto e em que unidade?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Mercado: bovino vai por arroba; frango por unidade; peixe por kg; leite por litro. Escolha como faz mais sentido para você.
                    </p>
                </div>

                <!-- Lembrete -->
                <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600 border border-slate-200">
                    <div><strong class="text-slate-900">{{ tituloModo }}</strong></div>
                    <div class="mt-1">
                        <span v-if="totalItens > 0">
                            <strong class="text-slate-900">{{ totalItens }}</strong> {{ totalItens === 1 ? 'item' : 'itens' }}
                        </span>
                        <span v-if="comprador"> · Comprador: <strong class="text-slate-900">{{ comprador.nome }}</strong></span>
                    </div>
                </div>

                <div class="space-y-5">
                    <!-- Unidade -->
                    <div>
                        <InputLabel value="Unidade da venda" class="text-base" />
                        <div class="grid grid-cols-3 gap-2 mt-1">
                            <button v-for="u in unidadesDisponiveis" :key="u.id"
                                type="button" @click="unidade = u.id"
                                :data-unidade="u.id"
                                class="rounded-lg border-2 px-3 py-2.5 text-sm text-left transition-all"
                                :class="unidade === u.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                                <div class="font-semibold">{{ u.curto }}</div>
                                <div class="text-xs text-slate-500">{{ u.rotulo }}</div>
                            </button>
                        </div>
                    </div>

                    <!-- Quantidade + Valor unitário -->
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel :value="'Quantidade (em ' + rotuloUnidade(unidade) + ')'" class="text-base" />
                            <input v-model="quantidade" type="text" inputmode="decimal"
                                data-cy="input-quantidade"
                                class="form-input text-xl py-3 font-mono" placeholder="Ex.: 30">
                            <p class="text-xs text-slate-500 mt-1">
                                Quantos {{ rotuloUnidade(unidade) }} no total.
                            </p>
                        </div>
                        <div>
                            <InputLabel :value="'Valor por ' + rotuloUnidade(unidade)" class="text-base" />
                            <InputMoney v-model="valorUnitarioStr" data-cy="input-valor-unit"
                                class="text-xl py-3 font-semibold" />
                            <p class="text-xs text-slate-500 mt-1">Preço unitário praticado.</p>
                        </div>
                    </div>

                    <!-- Total calculado -->
                    <div class="rounded-xl border-2 border-emerald-200 bg-emerald-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-emerald-700 font-semibold">Valor total da venda</div>
                        <div class="text-3xl font-bold text-emerald-700 mt-1 font-mono" data-cy="valor-total">
                            {{ brl(valorTotal) }}
                        </div>
                        <div class="text-xs text-emerald-800 mt-1">
                            {{ Number(quantidade || 0).toLocaleString('pt-BR') }} {{ rotuloUnidade(unidade) }}
                            × {{ brl(valorUnitarioNum) }}
                        </div>
                    </div>

                    <!-- Peso médio (modo peso ou se quiser anotar) -->
                    <div v-if="modo === 'peso' || modo === 'multiplos'">
                        <InputLabel value="Peso médio por cabeça (opcional, kg)" class="text-base" />
                        <input v-model="pesoMedio" type="text" inputmode="decimal"
                            class="form-input py-2.5 font-mono" placeholder="Ex.: 480">
                        <p class="text-xs text-slate-500 mt-1">Útil para histórico e cálculo de @ por animal.</p>
                    </div>

                    <div>
                        <InputLabel value="Dia da venda" class="text-base" />
                        <InputDate v-model="dataVenda" :max="hojeBR()" />
                    </div>

                    <div>
                        <InputLabel value="Observações (opcional)" class="text-base" />
                        <textarea v-model="observacoes" rows="2" class="form-textarea"
                            placeholder="Ex.: 'pagou à vista PIX', 'entregue na feira'"></textarea>
                    </div>
                </div>

                <div class="flex justify-between pt-5 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar4" class="btn-primary px-6 py-2.5 text-base">
                        Continuar →
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════ PASSO 5 — RESUMO ══════ -->
        <div v-if="passo === 5" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Confere antes de salvar</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Tudo certo? Clique em "Trocar" no canto se quiser ajustar.
                    </p>
                </div>

                <!-- Frase resumo destacada -->
                <div class="rounded-xl border-2 border-macaybas-primary bg-emerald-50 p-5 text-center" data-cy="resumo">
                    <div class="text-sm uppercase tracking-wide text-emerald-700 font-semibold">Resumo da venda</div>
                    <p class="text-lg text-slate-900 mt-2 leading-relaxed">
                        Vendendo
                        <strong class="text-macaybas-primary">{{ totalItens }} {{ totalItens === 1 ? 'item' : 'itens' }}</strong>
                        <span> ({{ Number(quantidade).toLocaleString('pt-BR') }} {{ rotuloUnidade(unidade) }})</span>
                        para
                        <strong class="text-macaybas-primary">{{ comprador?.nome }}</strong>
                        por
                        <strong class="text-emerald-700 text-2xl">{{ brl(valorTotal) }}</strong>.
                    </p>
                    <p class="text-sm text-slate-500 mt-2">em {{ dataBR(dataVenda) }} · {{ tituloModo }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- O que -->
                    <div class="rounded-lg border border-slate-200 p-4 bg-white">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold">O que</div>
                            <button @click="irPara(1)" class="text-xs text-macaybas-primary hover:underline">Trocar</button>
                        </div>
                        <div class="font-semibold text-slate-900">{{ tituloModo }}</div>
                        <div v-if="modo === 'individual' && animalUnico" class="text-sm text-slate-600 mt-1">
                            {{ tituloAnimal(animalUnico) }} ({{ animalUnico.species?.nome }})
                        </div>
                        <div v-else-if="(modo === 'multiplos' || modo === 'peso') && animaisAfetados.length > 0" class="text-sm text-slate-600 mt-1">
                            <div class="text-xs">{{ animaisAfetados.length }} animais selecionados:</div>
                            <ul class="text-xs mt-1 space-y-0.5 max-h-24 overflow-y-auto">
                                <li v-for="a in animaisAfetados.slice(0, 5)" :key="a.id">
                                    • {{ a.identificacao }}<span v-if="a.nome"> ({{ a.nome }})</span>
                                </li>
                                <li v-if="animaisAfetados.length > 5" class="text-slate-400 italic">
                                    + {{ animaisAfetados.length - 5 }} restantes
                                </li>
                            </ul>
                        </div>
                        <div v-else-if="loteId" class="text-sm text-slate-600 mt-1">
                            Lote: {{ props.lotes.find((l) => l.id === loteId)?.nome }}
                            <span v-if="modo === 'lote_quantidade'"> · {{ qtdLote }} animais</span>
                            <span v-else> · {{ totalItens }} animais (todos)</span>
                        </div>
                    </div>

                    <!-- Comprador -->
                    <div class="rounded-lg border border-slate-200 p-4 bg-white">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Comprador</div>
                            <button @click="irPara(3)" class="text-xs text-macaybas-primary hover:underline">Trocar</button>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">{{ comprador?.pessoa === 'pj' ? '🏢' : '👤' }}</span>
                            <div class="font-semibold text-slate-900 truncate">{{ comprador?.nome }}</div>
                        </div>
                        <div v-if="comprador?.documento" class="text-xs text-slate-500 mt-1">{{ comprador.documento }}</div>
                    </div>

                    <!-- Quantidade × valor -->
                    <div class="rounded-lg border border-slate-200 p-4 bg-white">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Quantidade × preço</div>
                            <button @click="irPara(4)" class="text-xs text-macaybas-primary hover:underline">Trocar</button>
                        </div>
                        <div class="text-sm">
                            <span class="font-mono">{{ Number(quantidade).toLocaleString('pt-BR') }} {{ rotuloUnidade(unidade) }}</span>
                            × <span class="font-mono">{{ brl(valorUnitarioNum) }}</span>
                        </div>
                        <div class="mt-2 pt-2 border-t border-slate-100 text-xl font-bold text-emerald-700 font-mono">
                            = {{ brl(valorTotal) }}
                        </div>
                    </div>

                    <!-- Data + obs -->
                    <div class="rounded-lg border border-slate-200 p-4 bg-white">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Data e detalhes</div>
                            <button @click="irPara(4)" class="text-xs text-macaybas-primary hover:underline">Trocar</button>
                        </div>
                        <div class="text-sm">📅 {{ dataBR(dataVenda) }}</div>
                        <div v-if="pesoMedio" class="text-sm text-slate-600 mt-1">
                            ⚖ Peso médio: {{ pesoMedio }} kg/cabeça
                        </div>
                        <div v-if="observacoes" class="text-xs text-slate-600 mt-2 pt-2 border-t border-slate-100 italic">
                            "{{ observacoes }}"
                        </div>
                    </div>
                </div>

                <!-- O que vai acontecer -->
                <div class="rounded-lg bg-slate-50 border border-slate-200 p-4 text-sm">
                    <div class="font-semibold text-slate-700 mb-2">Quando você confirmar:</div>
                    <ul class="text-slate-700 space-y-1.5">
                        <li>✓ {{ totalItens }} {{ totalItens === 1 ? 'animal sai' : 'animais saem' }} do rebanho ativo</li>
                        <li>✓ Receita de {{ brl(valorTotal) }} entra no financeiro como conta a receber</li>
                        <li>✓ Histórico fica gravado para consulta posterior</li>
                    </ul>
                </div>

                <div v-if="Object.keys(form.errors).length > 0" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                    <div class="font-semibold">Não foi possível salvar:</div>
                    <ul class="mt-1 list-disc pl-5">
                        <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
                    </ul>
                </div>

                <div class="flex justify-between pt-5 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="form.processing" class="btn-primary px-8 py-2.5 text-base font-semibold"
                        data-cy="confirmar-venda">
                        <span v-if="form.processing">Registrando…</span>
                        <span v-else>✓ Confirmar venda</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════ PASSO 6 — PRONTO! ══════ -->
        <div v-if="passo === 6 && sucesso" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
            <div class="card-body text-center py-10 space-y-5">
                <div class="inline-flex h-24 w-24 rounded-full bg-emerald-100 items-center justify-center mx-auto">
                    <svg class="h-14 w-14 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>

                <div>
                    <h2 class="text-3xl font-bold text-slate-900">Venda registrada!</h2>
                    <p class="text-base text-slate-600 mt-2">
                        {{ sucesso.total }} {{ sucesso.total === 1 ? 'animal' : 'animais' }} vendidos para
                        <strong>{{ sucesso.comprador_nome }}</strong>.
                    </p>
                </div>

                <div class="rounded-xl border-2 border-emerald-200 bg-emerald-50 p-5 text-left">
                    <div class="text-xs uppercase tracking-wide text-emerald-700 font-semibold mb-2">
                        💰 Impacto no caixa
                    </div>
                    <div class="text-3xl font-bold text-emerald-700 font-mono">+ {{ brl(sucesso.valor_total) }}</div>
                    <div class="text-sm text-emerald-800 mt-2">
                        {{ Number(sucesso.quantidade).toLocaleString('pt-BR') }} {{ rotuloUnidade(sucesso.unidade) }}
                        × {{ brl(sucesso.valor_unitario) }} = {{ brl(sucesso.valor_total) }}
                    </div>
                    <p class="text-sm text-emerald-900 mt-3">
                        Conta a receber criada no financeiro. Quando o comprador pagar, marque como "recebida".
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row justify-center gap-2 pt-3">
                    <button @click="reiniciar" class="btn-primary px-6 py-2.5">Registrar outra venda</button>
                    <Link :href="route('admin.financeiro.transacoes.index')" class="btn-outline px-6 py-2.5">Ir ao financeiro</Link>
                    <Link :href="route('admin.rebanho.animais.index')" class="btn-outline px-6 py-2.5">Voltar ao rebanho</Link>
                </div>
            </div>
        </div>

        <!-- Modal: criar comprador inline -->
        <Teleport to="body">
            <div v-if="novoComprador.modalAberto.value" class="fixed inset-0 z-50 flex items-center justify-center p-4" data-cy="modal-comprador">
                <div class="absolute inset-0 bg-black/40" @click="novoComprador.fechar"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <h3 class="text-lg font-semibold mb-1">Novo comprador</h3>
                    <p class="text-sm text-slate-500 mb-4">Cadastre o mínimo: nome e tipo. Os outros campos podem ficar para depois.</p>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Nome *" />
                            <input v-model="novoComprador.form.value.nome" data-cy="comprador-nome"
                                class="form-input" placeholder="Ex.: Frigorífico XYZ" required>
                        </div>
                        <div>
                            <InputLabel value="Pessoa" />
                            <div class="flex gap-2">
                                <button type="button" @click="novoComprador.form.value.pessoa = 'fisica'"
                                    class="flex-1 py-2 rounded border-2 text-sm"
                                    :class="novoComprador.form.value.pessoa === 'fisica' ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200'">
                                    👤 Pessoa
                                </button>
                                <button type="button" @click="novoComprador.form.value.pessoa = 'juridica'"
                                    class="flex-1 py-2 rounded border-2 text-sm"
                                    :class="novoComprador.form.value.pessoa === 'juridica' ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200'">
                                    🏢 Empresa
                                </button>
                            </div>
                        </div>
                        <div>
                            <InputLabel value="CPF/CNPJ (opcional)" />
                            <input v-model="novoComprador.form.value.documento" class="form-input" placeholder="000.000.000-00">
                        </div>
                        <div>
                            <InputLabel value="Telefone (opcional)" />
                            <input v-model="novoComprador.form.value.telefone" class="form-input" placeholder="(00) 00000-0000">
                        </div>
                    </div>
                    <p v-if="novoComprador.erro.value" class="mt-3 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1.5">
                        {{ novoComprador.erro.value }}
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoComprador.fechar" class="btn-outline">Cancelar</button>
                        <button @click="novoComprador.salvar" data-cy="salvar-comprador"
                            :disabled="novoComprador.salvando.value || !novoComprador.form.value.nome?.trim()"
                            class="btn-primary">
                            {{ novoComprador.salvando.value ? 'Salvando…' : 'Cadastrar e voltar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
