<script setup>
/**
 * Wizard "Controle do leite" — registra produção mensal de cada vaca em lactação.
 *
 * Mobile-first: lista vertical, inputs grandes, botão "+ ordenha" pra fazendas
 * que ordenham mais de 2 vezes/dia. Total calculado automaticamente.
 */
import { ref, computed, watch } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    vacas: Array,
    data_hoje: String,
});

// Estado: cada vaca tem array de ordenhas e total computado.
// Inicializa com 2 ordenhas padrão (1ª, 2ª) zeradas.
const linhas = ref(props.vacas.map(v => ({
    animal_id: v.id,
    identificacao: v.identificacao,
    nome: v.nome,
    lote: v.lote,
    producao_anterior: v.producao_anterior_litros,
    ordenhas: [
        { label: '1ª', litros: '' },
        { label: '2ª', litros: '' },
    ],
})));

function adicionarOrdenha(linha) {
    const proximo = linha.ordenhas.length + 1;
    if (proximo > 6) return;
    const labels = ['1ª', '2ª', '3ª', '4ª', '5ª', '6ª'];
    linha.ordenhas.push({ label: labels[proximo - 1], litros: '' });
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

const form = useForm({
    data: props.data_hoje,
    vacas: [],
});

function submit() {
    form.vacas = linhasComProducao.value.map(l => ({
        animal_id: l.animal_id,
        ordenhas: l.ordenhas
            .filter(o => parseFloat(o.litros) > 0)
            .map(o => ({ label: o.label, litros: parseFloat(o.litros) })),
    }));
    form.post(route('admin.fluxos.controle-leiteiro.store'));
}

function comparacao(linha) {
    if (! linha.producao_anterior) return null;
    const atual = totalLinha(linha);
    if (atual === 0) return null;
    const diff = atual - linha.producao_anterior;
    const pct = ((diff / linha.producao_anterior) * 100).toFixed(0);
    if (Math.abs(pct) < 5) return { label: 'estável', cor: 'text-slate-600' };
    if (pct > 0) return { label: `+${pct}%`, cor: 'text-emerald-700 font-semibold' };
    return { label: `${pct}%`, cor: 'text-rose-700 font-semibold' };
}
</script>

<template>
    <Head title="Controle do leite" />
    <AdminLayout>
        <div class="max-w-3xl mx-auto pb-32">
            <div class="mb-6">
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">🥛 Controle do leite</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Registre quantos litros cada vaca produziu hoje. <strong>Faz 1 vez por mês.</strong>
                    Em vacas com mais de 2 ordenhas, use o botão "+ ordenha".
                </p>
            </div>

            <!-- Data -->
            <div class="rounded-xl bg-white ring-1 ring-slate-200 p-4 mb-4">
                <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider">Data do controle</label>
                <input
                    v-model="form.data"
                    type="date"
                    class="mt-2 w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base"
                >
            </div>

            <!-- Lista de vacas -->
            <div v-if="linhas.length === 0" class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-6 text-center">
                <p class="text-amber-900">Nenhuma vaca em lactação encontrada.</p>
                <p class="mt-1 text-xs text-amber-700">São fêmeas bovinas com ≥24 meses, ativas, ainda não secas.</p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="linha in linhas"
                    :key="linha.animal_id"
                    class="rounded-xl bg-white ring-1 ring-slate-200 p-4"
                    :class="{ 'ring-emerald-300 bg-emerald-50/30': totalLinha(linha) > 0 }"
                >
                    <!-- Cabeçalho da vaca -->
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
                                {{ totalLinha(linha).toFixed(1) }}
                                <span class="text-sm font-normal text-slate-500">L</span>
                            </div>
                            <div v-if="comparacao(linha)" class="text-xs" :class="comparacao(linha).cor">
                                {{ comparacao(linha).label }}
                            </div>
                        </div>
                    </div>

                    <!-- Ordenhas -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <div v-for="(o, idx) in linha.ordenhas" :key="idx" class="relative">
                            <label class="block text-xs font-medium text-slate-600 mb-1">{{ o.label }} ordenha</label>
                            <div class="relative">
                                <input
                                    v-model="o.litros"
                                    type="number"
                                    inputmode="decimal"
                                    step="0.1"
                                    min="0"
                                    placeholder="0.0"
                                    class="w-full px-3 py-3 pr-10 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base font-mono text-right"
                                >
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">L</span>
                                <button
                                    v-if="idx >= 2"
                                    type="button"
                                    @click="removerOrdenha(linha, idx)"
                                    class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-rose-500 text-white text-xs flex items-center justify-center"
                                    title="Remover esta ordenha"
                                >×</button>
                            </div>
                        </div>
                    </div>

                    <button
                        v-if="linha.ordenhas.length < 6"
                        type="button"
                        @click="adicionarOrdenha(linha)"
                        class="mt-3 inline-flex items-center min-h-9 px-3 py-2 text-xs text-macaybas-primary hover:bg-macaybas-primary-50 rounded-md"
                    >
                        + ordenha extra (3ª, 4ª…)
                    </button>
                </div>
            </div>

            <!-- Resumo -->
            <div v-if="linhasComProducao.length > 0" class="mt-6 rounded-xl bg-emerald-50 ring-1 ring-emerald-300 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs text-emerald-700 uppercase tracking-wider font-semibold">Total do dia</div>
                        <div class="text-3xl font-bold text-emerald-900 mt-1">{{ totalGeral.toFixed(1) }} L</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-emerald-700">Em</div>
                        <div class="text-2xl font-bold text-emerald-900">{{ linhasComProducao.length }} vaca(s)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer fixo: Salvar -->
        <div class="fixed bottom-0 left-0 right-0 lg:left-64 bg-white ring-1 ring-slate-200 p-4 z-20">
            <div class="max-w-3xl mx-auto flex items-center gap-3">
                <Link :href="route('admin.inicio')" class="inline-flex items-center min-h-12 px-4 py-3 rounded-lg bg-slate-100 text-slate-700 font-medium hover:bg-slate-200">
                    ← Voltar
                </Link>
                <button
                    type="button"
                    @click="submit"
                    :disabled="linhasComProducao.length === 0 || form.processing"
                    class="flex-1 inline-flex items-center justify-center min-h-12 px-6 py-3 rounded-lg bg-macaybas-primary text-white font-semibold hover:bg-macaybas-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-base"
                >
                    <span v-if="form.processing">Salvando…</span>
                    <span v-else-if="linhasComProducao.length === 0">Preencha pelo menos 1 vaca</span>
                    <span v-else>Salvar produção de {{ linhasComProducao.length }} vaca(s)</span>
                </button>
            </div>
        </div>
    </AdminLayout>
</template>
