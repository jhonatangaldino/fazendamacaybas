<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputMoney from '@/Components/InputMoney.vue';
import InputDate from '@/Components/InputDate.vue';
import { brl, dataBR } from '@/utils/format.js';

const props = defineProps({
    animais: Array,
    partners: Array,
});

// ═════════════════════════════════════════════════════════════════════
// FASE 4 · F4.1 (refinado) — ASSISTENTE DE VENDA DE ANIMAL
//
// Objetivo: o usuário (incluindo o dono da fazenda de 70 anos) sente
// que o sistema está conversando com ele. Nada de "AnimalEvent",
// "FT receita", "lançamento financeiro" no texto principal — só
// verbos e perguntas simples do dia a dia: qual animal, para quem,
// quanto, confirmar.
//
// Camada técnica existe e funciona (wizard → storeEvent → F2.1), mas
// fica escondida do usuário. A integração aparece como "impacto
// financeiro" no passo final, em linguagem humana.
// ═════════════════════════════════════════════════════════════════════

const PASSOS = [
    { n: 1, titulo: 'O animal',    icon: '🐄' },
    { n: 2, titulo: 'O comprador', icon: '🤝' },
    { n: 3, titulo: 'O valor',     icon: '💰' },
    { n: 4, titulo: 'Conferência', icon: '📋' },
    { n: 5, titulo: 'Pronto!',     icon: '✅' },
];

const passo = ref(1);
const busca = ref('');
const selecionado = ref(null);
const compradorId = ref(null);
const dataVenda = ref(new Date().toISOString().slice(0, 10));
const valor = ref('');
const observacoes = ref('');

const form = useForm({
    tipo: 'venda',
    data: '',
    valor: 0,
    partner_id: null,
    observacoes: '',
});

// ── Emojis por espécie (usados como avatar fallback) ────────────────
function emojiEspecie(nome) {
    const s = (nome ?? '').toLowerCase();
    if (s.includes('ave')) return '🐔';
    if (s.includes('peixe')) return '🐟';
    if (s.includes('cão') || s.includes('cao')) return '🐕';
    if (s.includes('gato')) return '🐈';
    if (s.includes('suí') || s.includes('sui')) return '🐖';
    if (s.includes('ovin')) return '🐑';
    if (s.includes('capr')) return '🐐';
    if (s.includes('equi')) return '🐴';
    if (s.includes('búf') || s.includes('buf')) return '🐃';
    if (s.includes('coel')) return '🐇';
    return '🐄'; // bovino default
}

// ── Labels amigáveis ────────────────────────────────────────────────
function sexoLabel(s) {
    return s === 'F' ? 'Fêmea' : s === 'M' ? 'Macho' : '—';
}

function idadeHumana(dataNasc) {
    if (!dataNasc) return null;
    const nasc = new Date(dataNasc);
    const hoje = new Date();
    const meses = (hoje.getFullYear() - nasc.getFullYear()) * 12 + (hoje.getMonth() - nasc.getMonth());
    if (meses < 1) return 'menos de 1 mês';
    if (meses < 12) return `${meses} ${meses === 1 ? 'mês' : 'meses'}`;
    const anos = Math.floor(meses / 12);
    const restoMes = meses % 12;
    if (restoMes === 0) return `${anos} ${anos === 1 ? 'ano' : 'anos'}`;
    return `${anos} ${anos === 1 ? 'ano' : 'anos'} e ${restoMes} ${restoMes === 1 ? 'mês' : 'meses'}`;
}

function pesoHumano(p) {
    if (!p) return null;
    return `${Number(p).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} kg`;
}

function tituloAnimal(a) {
    if (!a) return '';
    if (a.nome) return `${a.identificacao} — ${a.nome}`;
    return a.identificacao;
}

// ── Passo 1: Animal ─────────────────────────────────────────────────
const animaisFiltrados = computed(() => {
    const q = (busca.value ?? '').toLowerCase().trim();
    if (!q) return props.animais;
    return props.animais.filter((a) =>
        (a.identificacao ?? '').toLowerCase().includes(q) ||
        (a.nome ?? '').toLowerCase().includes(q) ||
        (a.breed?.nome ?? '').toLowerCase().includes(q) ||
        (a.species?.nome ?? '').toLowerCase().includes(q) ||
        (a.lot?.nome ?? '').toLowerCase().includes(q),
    );
});
const podeAvancar1 = computed(() => selecionado.value !== null);

// ── Passo 2: Comprador ──────────────────────────────────────────────
const comprador = computed(() =>
    props.partners.find((p) => p.id === compradorId.value) ?? null,
);
const podeAvancar2 = computed(() => compradorId.value !== null);

// ── Passo 3: Valor ──────────────────────────────────────────────────
const valorNumerico = computed(() => {
    const n = parseFloat(String(valor.value ?? '').replace(/\D/g, '')) / 100;
    return isNaN(n) ? 0 : n;
});
const dataValida = computed(() => {
    if (!dataVenda.value) return false;
    return dataVenda.value <= new Date().toISOString().slice(0, 10);
});
const podeAvancar3 = computed(() => valorNumerico.value > 0 && dataValida.value);

// ── Passo 4: Revisão ────────────────────────────────────────────────
const podeConfirmar = computed(
    () => podeAvancar1.value && podeAvancar2.value && podeAvancar3.value,
);

// Resumo em frase única (para o header do passo 4)
const resumoFrase = computed(() => {
    if (!podeConfirmar.value) return '';
    return `Você está vendendo ${tituloAnimal(selecionado.value)} para ${comprador.value?.nome} por ${brl(valorNumerico.value)}.`;
});

// ── Navegação ───────────────────────────────────────────────────────
function proximo() {
    if (passo.value === 1 && !podeAvancar1.value) return;
    if (passo.value === 2 && !podeAvancar2.value) return;
    if (passo.value === 3 && !podeAvancar3.value) return;
    if (passo.value < 5) passo.value++;
}
function voltar() {
    if (passo.value > 1) passo.value--;
}
function reiniciar() {
    passo.value = 1;
    busca.value = '';
    selecionado.value = null;
    compradorId.value = null;
    dataVenda.value = new Date().toISOString().slice(0, 10);
    valor.value = '';
    observacoes.value = '';
    form.clearErrors();
}

// Guardamos o resumo da venda concluída para exibir no passo 5
// (mesmo depois que o usuário clica "registrar outra venda" a página
// permanece na aba até ele navegar, mas o reiniciar zera tudo).
const vendaConcluida = ref(null);

function confirmar() {
    if (!podeConfirmar.value) return;

    form.tipo = 'venda';
    form.data = dataVenda.value;
    form.valor = valorNumerico.value;
    form.partner_id = compradorId.value;
    form.observacoes = observacoes.value;

    form.post(route('admin.rebanho.animais.eventos.store', selecionado.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            // Snapshot para mostrar no passo 5 (os refs podem ser reiniciados)
            vendaConcluida.value = {
                animal: selecionado.value,
                comprador: comprador.value,
                valor: valorNumerico.value,
                data: dataVenda.value,
            };
            passo.value = 5;
        },
    });
}
</script>

<template>
    <Head title="Vender animal" />
    <AdminLayout>
        <PageHeader
            title="Assistente de venda"
            subtitle="Vamos te guiar passo a passo — não precisa saber onde clicar."
        >
            <template #actions>
                <Link :href="route('admin.rebanho.animais.index')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <!-- Barra de passos -->
        <div class="mb-10">
            <div class="flex items-center justify-between max-w-3xl mx-auto">
                <template v-for="(p, i) in PASSOS" :key="p.n">
                    <div class="flex items-center flex-col">
                        <div
                            class="h-12 w-12 rounded-full flex items-center justify-center text-base font-bold transition-all"
                            :class="{
                                'bg-macaybas-primary text-white ring-4 ring-emerald-100': passo === p.n,
                                'bg-emerald-500 text-white': passo > p.n,
                                'bg-slate-200 text-slate-500': passo < p.n,
                            }"
                        >
                            <span v-if="passo > p.n">✓</span>
                            <span v-else class="text-lg">{{ p.icon }}</span>
                        </div>
                        <span class="text-sm mt-2 font-medium whitespace-nowrap"
                              :class="passo === p.n ? 'text-macaybas-primary font-semibold' : 'text-slate-500'">
                            {{ p.titulo }}
                        </span>
                    </div>
                    <div v-if="i < PASSOS.length - 1" class="flex-1 h-1 mx-2 mb-7 rounded-full"
                         :class="passo > p.n ? 'bg-emerald-400' : 'bg-slate-200'"></div>
                </template>
            </div>
        </div>

        <!-- ══════ PASSO 1 — O ANIMAL ══════ -->
        <div v-if="passo === 1" class="card max-w-5xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Qual animal você quer vender?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        A lista mostra só os animais que ainda estão no rebanho (ativos). Escolha um clicando no quadrinho.
                    </p>
                    <p v-if="animais.length === 0" class="text-sm text-amber-700 mt-2">
                        Nenhum animal disponível para venda no momento.
                    </p>
                    <p v-else class="text-sm text-slate-500 mt-2">
                        {{ animais.length }} {{ animais.length === 1 ? 'animal disponível' : 'animais disponíveis' }}.
                    </p>
                </div>

                <div v-if="animais.length > 6" class="relative">
                    <input
                        v-model="busca"
                        placeholder="Buscar pelo brinco, nome, raça ou lote…"
                        class="form-input pl-10 text-base py-3"
                    >
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                <div v-if="animaisFiltrados.length === 0" class="text-center text-slate-500 py-12 text-base">
                    <div v-if="animais.length === 0">Ainda não há animais cadastrados para venda.</div>
                    <div v-else>Nenhum animal encontrado com "{{ busca }}".</div>
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 max-h-[55vh] overflow-y-auto pr-1">
                    <button
                        v-for="a in animaisFiltrados"
                        :key="a.id"
                        type="button"
                        @click="selecionado = a"
                        class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary hover:shadow-md"
                        :class="selecionado?.id === a.id
                            ? 'border-macaybas-primary bg-emerald-50 shadow-md'
                            : 'border-slate-200 bg-white'"
                    >
                        <div class="flex items-start gap-3">
                            <img v-if="a.photo_url" :src="a.photo_url" class="h-14 w-14 rounded-lg object-cover flex-shrink-0">
                            <div v-else class="h-14 w-14 rounded-lg bg-slate-100 flex items-center justify-center text-3xl flex-shrink-0">
                                {{ emojiEspecie(a.species?.nome) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-base font-bold text-slate-900 truncate">
                                    {{ a.identificacao }}
                                </div>
                                <div v-if="a.nome" class="text-sm text-slate-700 truncate">{{ a.nome }}</div>
                                <div class="text-sm text-slate-500 mt-1">
                                    {{ a.species?.nome ?? '—' }}
                                    <span v-if="a.breed?.nome"> · {{ a.breed.nome }}</span>
                                </div>
                                <div class="text-sm text-slate-500">
                                    {{ sexoLabel(a.sexo) }}
                                    <span v-if="idadeHumana(a.data_nascimento)"> · {{ idadeHumana(a.data_nascimento) }}</span>
                                </div>
                                <div v-if="a.peso_atual" class="text-sm text-slate-600 mt-1 font-medium">
                                    ⚖ {{ pesoHumano(a.peso_atual) }}
                                </div>
                                <div v-if="a.lot?.nome" class="text-xs text-slate-400 mt-1">no lote {{ a.lot.nome }}</div>
                            </div>
                            <div v-if="selecionado?.id === a.id" class="text-emerald-600">
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>
                    </button>
                </div>

                <div class="flex justify-end pt-5 border-t border-slate-100">
                    <button
                        @click="proximo"
                        :disabled="!podeAvancar1"
                        class="btn-primary text-base px-6 py-2.5"
                        :title="!podeAvancar1 ? 'Escolha um animal primeiro' : ''"
                    >
                        Continuar →
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════ PASSO 2 — O COMPRADOR ══════ -->
        <div v-if="passo === 2" class="card max-w-4xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Para quem você vendeu?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Clique no quadrinho da pessoa ou empresa que comprou o animal.
                    </p>
                </div>

                <!-- Lembrete visual do animal escolhido (continuidade) -->
                <div v-if="selecionado" class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600 flex items-center gap-2">
                    <span class="text-lg">{{ emojiEspecie(selecionado.species?.nome) }}</span>
                    <span>Você está vendendo: <strong class="text-slate-900">{{ tituloAnimal(selecionado) }}</strong></span>
                </div>

                <div v-if="partners.length === 0" class="rounded-xl border-2 border-amber-200 bg-amber-50 p-5 text-base text-amber-900">
                    <div class="font-semibold text-lg flex items-center gap-2">
                        <span class="text-2xl">⚠</span>
                        Você ainda não cadastrou nenhum comprador
                    </div>
                    <p class="mt-2">
                        Para vender, primeiro precisamos saber <strong>para quem</strong>. Cadastre a pessoa ou empresa compradora em "Parceiros" e volte aqui.
                    </p>
                    <Link :href="route('admin.parceiros.index')" class="inline-block mt-3 btn-primary">
                        Cadastrar comprador →
                    </Link>
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2 max-h-[55vh] overflow-y-auto pr-1">
                    <button
                        v-for="p in partners"
                        :key="p.id"
                        type="button"
                        @click="compradorId = p.id"
                        class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary hover:shadow-md"
                        :class="compradorId === p.id
                            ? 'border-macaybas-primary bg-emerald-50 shadow-md'
                            : 'border-slate-200 bg-white'"
                    >
                        <div class="flex items-start gap-3">
                            <div class="h-12 w-12 rounded-full flex items-center justify-center flex-shrink-0 text-2xl"
                                 :class="p.pessoa === 'pj' ? 'bg-indigo-100' : 'bg-emerald-100'">
                                {{ p.pessoa === 'pj' ? '🏢' : '👤' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-base font-bold text-slate-900 truncate">{{ p.nome }}</div>
                                <div class="text-sm text-slate-500 mt-1">
                                    {{ p.pessoa === 'pj' ? 'Empresa' : 'Pessoa' }}
                                    <span v-if="p.documento"> · {{ p.documento }}</span>
                                </div>
                                <div v-if="p.celular || p.telefone" class="text-sm text-slate-500 mt-1">
                                    📞 {{ p.celular || p.telefone }}
                                </div>
                            </div>
                            <div v-if="compradorId === p.id" class="text-emerald-600">
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>
                    </button>
                </div>

                <div class="flex justify-between pt-5 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline text-base px-6 py-2.5">← Voltar</button>
                    <button
                        @click="proximo"
                        :disabled="!podeAvancar2"
                        class="btn-primary text-base px-6 py-2.5"
                        :title="!podeAvancar2 ? 'Escolha o comprador primeiro' : ''"
                    >
                        Continuar →
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════ PASSO 3 — O VALOR ══════ -->
        <div v-if="passo === 3" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Quanto você recebeu?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Informe o valor total e o dia da venda.
                    </p>
                </div>

                <!-- Lembrete visual -->
                <div v-if="selecionado && comprador" class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">{{ emojiEspecie(selecionado.species?.nome) }}</span>
                        <span>Animal: <strong class="text-slate-900">{{ tituloAnimal(selecionado) }}</strong></span>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-lg">{{ comprador.pessoa === 'pj' ? '🏢' : '👤' }}</span>
                        <span>Comprador: <strong class="text-slate-900">{{ comprador.nome }}</strong></span>
                    </div>
                </div>

                <div class="space-y-5 max-w-lg">
                    <div>
                        <InputLabel value="Valor total recebido" class="text-base" />
                        <InputMoney v-model="valor" class="text-xl py-3 font-semibold" />
                        <p class="text-sm text-slate-500 mt-1">Quanto o comprador pagou (ou vai pagar) pelo animal.</p>
                    </div>
                    <div>
                        <InputLabel value="Dia da venda" class="text-base" />
                        <InputDate v-model="dataVenda" :max="new Date().toISOString().slice(0, 10)" required />
                        <p v-if="!dataValida && dataVenda" class="text-sm text-red-600 mt-1">
                            A data não pode ser depois de hoje.
                        </p>
                        <p v-else class="text-sm text-slate-500 mt-1">Hoje por padrão. Ajuste se a venda foi em outro dia.</p>
                    </div>
                    <div>
                        <InputLabel value="Observações (opcional)" class="text-base" />
                        <textarea v-model="observacoes" rows="2" class="form-textarea"
                                  placeholder="Algum detalhe que queira anotar — ex: 'pagou à vista no PIX', 'entregue na feira'"></textarea>
                    </div>
                </div>

                <div class="flex justify-between pt-5 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline text-base px-6 py-2.5">← Voltar</button>
                    <button
                        @click="proximo"
                        :disabled="!podeAvancar3"
                        class="btn-primary text-base px-6 py-2.5"
                        :title="!podeAvancar3 ? 'Informe um valor maior que zero e a data' : ''"
                    >
                        Continuar →
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════ PASSO 4 — CONFERÊNCIA ══════ -->
        <div v-if="passo === 4" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-6">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Vamos conferir antes de salvar</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Leia com calma. Se algo estiver errado, clique em "Trocar" no canto de cada quadro.
                    </p>
                </div>

                <!-- Frase-resumo em destaque -->
                <div class="rounded-xl border-2 border-macaybas-primary bg-emerald-50 p-5 text-center">
                    <div class="text-sm uppercase tracking-wide text-emerald-700 font-semibold">Resumo da venda</div>
                    <p class="text-lg text-slate-900 mt-2 leading-relaxed">
                        Você está vendendo
                        <strong class="text-macaybas-primary">{{ tituloAnimal(selecionado) }}</strong>
                        para
                        <strong class="text-macaybas-primary">{{ comprador?.nome }}</strong>
                        por
                        <strong class="text-emerald-700 text-2xl">{{ brl(valorNumerico) }}</strong>.
                    </p>
                    <p class="text-sm text-slate-500 mt-2">em {{ dataBR(dataVenda) }}</p>
                </div>

                <!-- Detalhes em 3 cartões -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg border border-slate-200 p-4 bg-white">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Animal</div>
                            <button @click="passo = 1" class="text-xs text-macaybas-primary hover:underline">Trocar</button>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">{{ emojiEspecie(selecionado?.species?.nome) }}</span>
                            <div class="min-w-0">
                                <div class="font-semibold text-slate-900 truncate">{{ selecionado?.identificacao }}</div>
                                <div v-if="selecionado?.nome" class="text-sm text-slate-600 truncate">{{ selecionado.nome }}</div>
                            </div>
                        </div>
                        <div class="text-xs text-slate-500 mt-2">
                            {{ selecionado?.species?.nome }}
                            <span v-if="selecionado?.breed?.nome"> · {{ selecionado.breed.nome }}</span>
                        </div>
                        <div v-if="selecionado?.peso_atual" class="text-xs text-slate-500">
                            {{ pesoHumano(selecionado.peso_atual) }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-4 bg-white">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Comprador</div>
                            <button @click="passo = 2" class="text-xs text-macaybas-primary hover:underline">Trocar</button>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">{{ comprador?.pessoa === 'pj' ? '🏢' : '👤' }}</span>
                            <div class="min-w-0">
                                <div class="font-semibold text-slate-900 truncate">{{ comprador?.nome }}</div>
                                <div class="text-xs text-slate-500 truncate">{{ comprador?.documento ?? '' }}</div>
                            </div>
                        </div>
                        <div v-if="comprador?.celular || comprador?.telefone" class="text-xs text-slate-500 mt-2">
                            📞 {{ comprador?.celular || comprador?.telefone }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-4 bg-white">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Valor e data</div>
                            <button @click="passo = 3" class="text-xs text-macaybas-primary hover:underline">Trocar</button>
                        </div>
                        <div class="text-xl font-bold text-emerald-700">{{ brl(valorNumerico) }}</div>
                        <div class="text-xs text-slate-500 mt-2">Dia: {{ dataBR(dataVenda) }}</div>
                        <div v-if="observacoes" class="text-xs text-slate-600 mt-2 pt-2 border-t border-slate-100">
                            <em>{{ observacoes }}</em>
                        </div>
                    </div>
                </div>

                <!-- O que vai acontecer (transparência do impacto) -->
                <div class="rounded-lg bg-slate-50 border border-slate-200 p-4">
                    <div class="text-sm font-semibold text-slate-700 mb-2">O que vai acontecer quando você confirmar:</div>
                    <ul class="text-sm text-slate-700 space-y-1.5">
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-600">✓</span>
                            <span>O animal <strong>{{ selecionado?.identificacao }}</strong> será marcado como <strong>vendido</strong> e sai do rebanho ativo.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-600">✓</span>
                            <span>A venda fica registrada no histórico do animal — dá para consultar quando quiser.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-600">✓</span>
                            <span>Uma conta a receber de <strong>{{ brl(valorNumerico) }}</strong> aparece no financeiro, aguardando você marcar como "recebida".</span>
                        </li>
                    </ul>
                </div>

                <!-- Erros eventuais -->
                <div v-if="Object.keys(form.errors).length > 0 || $page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                    <div class="font-semibold">Não foi possível salvar:</div>
                    <ul class="mt-2 list-disc pl-5">
                        <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
                        <li v-if="$page.props.flash?.error">{{ $page.props.flash.error }}</li>
                    </ul>
                </div>

                <div class="flex flex-col sm:flex-row justify-between gap-3 pt-5 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline text-base px-6 py-2.5">← Voltar</button>
                    <button
                        @click="confirmar"
                        :disabled="!podeConfirmar || form.processing"
                        class="btn-primary text-base px-8 py-2.5 font-semibold"
                    >
                        <span v-if="form.processing">Registrando venda...</span>
                        <span v-else>✓ Confirmar venda do animal</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════ PASSO 5 — PRONTO! ══════ -->
        <div v-if="passo === 5 && vendaConcluida" class="card max-w-2xl mx-auto">
            <div class="card-body text-center py-10 space-y-5">
                <div class="inline-flex h-24 w-24 rounded-full bg-emerald-100 items-center justify-center mx-auto">
                    <svg class="h-14 w-14 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>

                <div>
                    <h2 class="text-3xl font-bold text-slate-900">Venda registrada com sucesso!</h2>
                    <p class="text-base text-slate-600 mt-3">
                        <strong>{{ tituloAnimal(vendaConcluida.animal) }}</strong>
                        foi vendido para
                        <strong>{{ vendaConcluida.comprador?.nome }}</strong>
                        em {{ dataBR(vendaConcluida.data) }}.
                    </p>
                </div>

                <!-- Impacto financeiro em destaque -->
                <div class="rounded-xl border-2 border-emerald-200 bg-emerald-50 p-5 text-left">
                    <div class="text-sm uppercase tracking-wide text-emerald-700 font-semibold mb-2">
                        💰 Impacto no seu caixa
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold text-emerald-700">+ {{ brl(vendaConcluida.valor) }}</span>
                        <span class="text-sm text-emerald-800">a receber</span>
                    </div>
                    <p class="text-sm text-emerald-900 mt-3">
                        Criamos automaticamente uma <strong>conta a receber</strong> no financeiro.
                        Quando o comprador pagar, você só precisa entrar lá e clicar em "recebido" — o resto o sistema já fez.
                    </p>
                </div>

                <!-- Ações pós-venda -->
                <div class="pt-4 space-y-2">
                    <p class="text-sm text-slate-500">O que você quer fazer agora?</p>
                    <div class="flex flex-col sm:flex-row justify-center gap-2">
                        <button @click="reiniciar" class="btn-primary text-base px-6 py-2.5">
                            Registrar outra venda
                        </button>
                        <Link :href="route('admin.rebanho.animais.show', vendaConcluida.animal.id)" class="btn-outline text-base px-6 py-2.5">
                            Ver histórico do animal
                        </Link>
                        <Link :href="route('admin.financeiro.transacoes.index')" class="btn-outline text-base px-6 py-2.5">
                            Ir para o financeiro
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
