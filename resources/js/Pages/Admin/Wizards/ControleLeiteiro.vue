<script setup>
/**
 * Wizard "Controle do leite" — multi-passo, padrão Aplicar Vacina/Despesa.
 *
 * Passo 1 · Quando? · escolhe a data
 * Passo 2 · Quanto cada vaca produziu? · lista vacas com ordenhas (hora + litros)
 * Passo 3 · Conferência · resumo do lançamento
 * Passo 4 · Pronto! · confirmação + atalhos
 */
import { ref, computed, onMounted } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputDecimal from '@/Components/InputDecimal.vue';

const props = defineProps({
    vacas: Array,
    data_hoje: String,
    preselectId: { type: Number, default: null },
});

// Vacas filtradas (single quando vem do animal show)
const vacasFiltradas = props.preselectId
    ? props.vacas.filter(v => v.id === props.preselectId)
    : props.vacas;

const LABELS = ['1ª', '2ª', '3ª', '4ª', '5ª', '6ª'];
const HORAS_PADRAO = ['08:00', '15:00', '20:00', '04:00', '12:00', '18:00'];

function ordenhaInicial(idx) {
    return { hora: HORAS_PADRAO[idx] || '', litros: '' };
}
function hojeLocal() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

const linhas = ref(vacasFiltradas.map(v => ({
    animal_id: v.id,
    identificacao: v.identificacao,
    nome: v.nome,
    lote: v.lote,
    producao_anterior: v.producao_anterior_litros,
    ordenhas: [ordenhaInicial(0)],
})));

function adicionarOrdenha(linha) {
    if (linha.ordenhas.length >= 6) return;
    linha.ordenhas.push(ordenhaInicial(linha.ordenhas.length));
}
function removerOrdenha(linha, idx) {
    if (linha.ordenhas.length <= 1) return;
    linha.ordenhas.splice(idx, 1);
}
function totalLinha(linha) {
    return linha.ordenhas.reduce((acc, o) => acc + (parseFloat(o.litros) || 0), 0);
}

const linhasComProducao = computed(() => linhas.value.filter(l => totalLinha(l) > 0));
const totalGeral = computed(() => linhasComProducao.value.reduce((acc, l) => acc + totalLinha(l), 0));

const returnTo = new URLSearchParams(window.location.search).get('return_to') || null;

// Wizard multi-step
const PASSOS = [
    { n: 1, titulo: 'Quando?',      icon: '📅' },
    { n: 2, titulo: 'Litros',       icon: '🥛' },
    { n: 3, titulo: 'Conferência',  icon: '📋' },
    { n: 4, titulo: 'Pronto!',      icon: '✅' },
];
const passo = ref(1);
const sucesso = ref(null);

const form = useForm({
    data_controle: props.data_hoje || hojeLocal(),
    vacas: [],
    return_to: returnTo,
});

onMounted(() => {
    if (! form.data_controle) form.data_controle = hojeLocal();
});

function avancar() { if (passo.value < PASSOS.length) passo.value++; }
function voltar() { if (passo.value > 1) passo.value--; }

const podeAvancarP1 = computed(() => !! form.data_controle);
const podeAvancarP2 = computed(() => linhasComProducao.value.length > 0);

function submit() {
    form.vacas = linhasComProducao.value.map(l => ({
        animal_id: l.animal_id,
        ordenhas: l.ordenhas
            .map((o, idx) => ({ label: LABELS[idx] || `${idx+1}ª`, hora: o.hora || null, litros: parseFloat(o.litros) }))
            .filter(o => o.litros > 0),
    }));
    form.post(route('admin.fluxos.controle-leiteiro.store'), {
        preserveScroll: true,
        onSuccess: () => {
            sucesso.value = {
                vacas: linhasComProducao.value.length,
                total_litros: totalGeral.value,
                data: form.data_controle,
            };
            passo.value = 4;
        },
    });
}

function dataBR(iso) {
    if (! iso) return '';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}
</script>

<template>
    <Head title="Controle do leite" />
    <AdminLayout>
        <template #page-title>Assistente · Controle do leite</template>

        <PageHeader
            title="🥛 Controle do leite"
            subtitle="Vamos registrar passo a passo — não precisa saber onde clicar."
        >
            <template #actions>
                <Link :href="returnTo || route('admin.inicio')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- ─── PASSO 1 · Quando? ─── -->
        <div v-if="passo === 1" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Quando foi a ordenha?</h2>
                <p class="text-sm text-slate-600">A data padrão é hoje. Edite se está lançando ordenha de outro dia.</p>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Data do controle</label>
                    <input
                        :value="form.data_controle"
                        @input="form.data_controle = $event.target.value"
                        type="date"
                        class="form-input"
                    >
                </div>

                <div v-if="linhas.length === 0" class="rounded-lg bg-amber-50 ring-1 ring-amber-200 p-4 text-sm text-amber-900">
                    ⚠ Nenhuma vaca encontrada. Cadastre fêmeas bovinas no rebanho antes.
                </div>
                <div v-else class="rounded-lg bg-emerald-50 ring-1 ring-emerald-200 p-4 text-sm text-emerald-900">
                    ✓ <strong>{{ linhas.length }} vaca(s)</strong> em condições de ordenha estarão disponíveis no próximo passo.
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-100">
                    <button @click="avancar" :disabled="! podeAvancarP1 || linhas.length === 0" class="btn-primary">
                        Continuar →
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── PASSO 2 · Litros por vaca ─── -->
        <div v-if="passo === 2" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-4">
                <h2 class="text-2xl font-semibold text-slate-900">Quanto cada vaca produziu?</h2>
                <p class="text-sm text-slate-600">
                    Cada vaca tem 1 ordenha por padrão (08:00). Adicione a 2ª, 3ª… se essa vaca foi ordenhada mais vezes.
                    <strong>Vacas que não produziram ficam de fora automaticamente.</strong>
                </p>

                <div class="space-y-3">
                    <div
                        v-for="linha in linhas"
                        :key="linha.animal_id"
                        class="rounded-xl bg-white ring-1 ring-slate-200 p-4"
                        :class="{ 'ring-emerald-300 bg-emerald-50/30': totalLinha(linha) > 0 }"
                    >
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-slate-900">
                                    {{ linha.identificacao }}<span v-if="linha.nome" class="text-slate-600 font-normal"> — {{ linha.nome }}</span>
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    <span v-if="linha.lote">📋 {{ linha.lote }}</span>
                                    <span v-if="linha.producao_anterior" class="ml-2">· Mês passado: {{ linha.producao_anterior }} L</span>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-2xl font-bold" :class="totalLinha(linha) > 0 ? 'text-emerald-700' : 'text-slate-300'">
                                    {{ totalLinha(linha).toFixed(1) }}<span class="text-sm font-normal text-slate-500"> L</span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div v-for="(o, idx) in linha.ordenhas" :key="idx" class="flex gap-2 items-end">
                                <div class="flex-shrink-0 w-9 text-center pb-2 text-sm font-semibold text-slate-600">
                                    {{ LABELS[idx] || `${idx+1}ª` }}
                                </div>
                                <div class="flex-shrink-0">
                                    <label class="block text-[10px] font-medium text-slate-500 mb-0.5">Hora</label>
                                    <input v-model="o.hora" type="time" class="px-2 py-2.5 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-sm font-mono w-[110px]">
                                </div>
                                <div class="flex-1 relative">
                                    <label class="block text-[10px] font-medium text-slate-500 mb-0.5">Litros</label>
                                    <InputDecimal v-model="o.litros" :decimals="1" :min="0" placeholder="0,0" input-class="w-full px-3 py-2.5 pr-9 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base font-mono text-right" />
                                    <span class="absolute right-3 top-7 text-xs text-slate-400">L</span>
                                </div>
                                <button v-if="linha.ordenhas.length > 1" type="button" @click="removerOrdenha(linha, idx)" class="flex-shrink-0 w-9 h-10 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center" title="Remover">×</button>
                            </div>
                        </div>

                        <button v-if="linha.ordenhas.length < 6" type="button" @click="adicionarOrdenha(linha)" class="mt-3 inline-flex items-center min-h-9 px-3 py-2 text-xs text-macaybas-primary hover:bg-macaybas-primary-50 rounded-md">
                            + ordenha extra (2ª, 3ª…)
                        </button>
                    </div>
                </div>

                <div class="flex justify-between pt-4 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="! podeAvancarP2" class="btn-primary">
                        Continuar →
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── PASSO 3 · Conferência ─── -->
        <div v-if="passo === 3" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Confira antes de salvar</h2>

                <div class="rounded-lg bg-slate-50 ring-1 ring-slate-200 p-4 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Data:</span><strong>{{ dataBR(form.data_controle) }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">Vacas com produção:</span><strong>{{ linhasComProducao.length }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">Total do dia:</span><strong class="text-emerald-700 text-lg">{{ totalGeral.toFixed(1) }} L</strong></div>
                </div>

                <div class="rounded-lg bg-white ring-1 ring-slate-200 divide-y divide-slate-100">
                    <div v-for="l in linhasComProducao" :key="l.animal_id" class="p-3 flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-slate-900 text-sm">{{ l.identificacao }}<span v-if="l.nome" class="font-normal text-slate-600"> — {{ l.nome }}</span></div>
                            <div class="text-xs text-slate-500">{{ l.ordenhas.filter(o => parseFloat(o.litros) > 0).length }} ordenha(s)</div>
                        </div>
                        <div class="text-emerald-700 font-bold">{{ totalLinha(l).toFixed(1) }} L</div>
                    </div>
                </div>

                <div class="flex justify-between pt-4 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="submit" :disabled="form.processing" class="btn-primary">
                        <span v-if="form.processing">Salvando…</span>
                        <span v-else>✓ Salvar lançamento</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── PASSO 4 · Pronto! ─── -->
        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto">
            <div class="card-body text-center space-y-5 py-12">
                <div class="text-6xl">✅</div>
                <h2 class="text-2xl font-semibold text-slate-900">Pronto!</h2>
                <p class="text-slate-600">
                    Produção registrada para <strong>{{ sucesso.vacas }} vaca(s)</strong> em {{ dataBR(sucesso.data) }}.<br>
                    Total: <strong class="text-emerald-700">{{ sucesso.total_litros.toFixed(1) }} L</strong>.
                </p>
                <div class="flex flex-wrap gap-3 justify-center pt-4">
                    <Link :href="returnTo || route('admin.inicio')" class="btn-primary">Voltar ao início</Link>
                    <button @click="passo = 1; sucesso = null" type="button" class="btn-outline">Lançar de outro dia</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
