<script setup>
/**
 * Wizard "Controle do leite" — registra produção mensal de cada vaca em lactação.
 *
 * Mobile-first: lista vertical, inputs grandes, botão "+ ordenha" pra fazendas
 * que ordenham mais de 2 vezes/dia. Total calculado automaticamente.
 */
import { ref, computed, watch, onMounted } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    vacas: Array,
    data_hoje: String,
    preselectId: { type: Number, default: null },
});

// Se chegou via Animal show com ?animal_id=X, mostra só aquela vaca
const vacasFiltradas = props.preselectId
    ? props.vacas.filter(v => v.id === props.preselectId)
    : props.vacas;

// Estado: cada vaca tem array de ordenhas (label, hora, litros) + total computado.
// Inicializa com APENAS 1 ordenha (1ª às 08:00). Botão "+ ordenha extra" pra
// adicionar 2ª, 3ª, etc — só a 1ª é obrigatória, vacas que produzem só 1 vez
// no dia não precisam de campo vazio na 2ª.
// Labels e horas padrão calculados POR POSIÇÃO no array, não armazenados.
// Antes: cada ordenha guardava o label "1ª"/"2ª"/etc — bug: ao remover do meio,
// novas adições reciclavam o mesmo label baseado em array.length.
// Agora: label = LABELS[idx do v-for], sempre correto.
const LABELS = ['1ª', '2ª', '3ª', '4ª', '5ª', '6ª'];
const HORAS_PADRAO = ['08:00', '15:00', '20:00', '04:00', '12:00', '18:00'];

function ordenhaInicial(idx) {
    return {
        hora: HORAS_PADRAO[idx] || '',
        litros: '',
    };
}
const linhas = ref(vacasFiltradas.map(v => ({
    animal_id: v.id,
    identificacao: v.identificacao,
    nome: v.nome,
    lote: v.lote,
    producao_anterior: v.producao_anterior_litros,
    ordenhas: [ordenhaInicial(0)], // só a 1ª por default
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

// Lê return_to da URL pra voltar pra origem após salvar
const returnTo = new URLSearchParams(window.location.search).get('return_to') || null;

// Data de hoje em formato YYYY-MM-DD respeitando fuso local (não UTC).
// `toISOString()` retorna UTC e pode dar dia anterior se for tarde da noite no Brasil.
function hojeLocal() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

// `data` é palavra reservada do Vue Options API e gera bug bizarro no useForm
// (renderiza código JS no value do input). Renomeado pra data_controle.
// Backend já aceita ambas as keys via fallback no controller.
const form = useForm({
    data_controle: props.data_hoje || hojeLocal(),
    vacas: [],
    return_to: returnTo,
});

// Garantia: se por qualquer motivo form.data_controle ficar vazio, força hoje
onMounted(() => {
    if (! form.data_controle) form.data_controle = hojeLocal();
});

function submit() {
    form.vacas = linhasComProducao.value.map(l => ({
        animal_id: l.animal_id,
        ordenhas: l.ordenhas
            .map((o, idx) => ({ label: LABELS[idx] || `${idx+1}ª`, hora: o.hora || null, litros: parseFloat(o.litros) }))
            .filter(o => o.litros > 0),
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
        <template #page-title>Assistente · Controle do leite</template>

        <PageHeader
            title="🥛 Controle do leite"
            subtitle="Registre quantos litros cada vaca produziu hoje. Só a 1ª ordenha é obrigatória — adicione 2ª/3ª se a vaca produzir mais vezes no dia."
        >
            <template #actions>
                <Link :href="returnTo || route('admin.inicio')" class="btn-outline">← Voltar</Link>
            </template>
        </PageHeader>

        <div class="max-w-3xl mx-auto pb-8 space-y-4">

            <!-- Data -->
            <div class="rounded-xl bg-white ring-1 ring-slate-200 p-4 mb-4">
                <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider">Data do controle</label>
                <input
                    :value="form.data_controle"
                    @input="form.data_controle = $event.target.value"
                    type="date"
                    class="mt-2 w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base"
                >
            </div>

            <!-- Lista de vacas -->
            <div v-if="linhas.length === 0" class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-6 text-center">
                <p class="text-amber-900">Nenhuma vaca encontrada.</p>
                <p class="mt-1 text-xs text-amber-700">Cadastre fêmeas bovinas no rebanho para começar a registrar produção.</p>
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

                    <!-- Ordenhas — cada uma com hora + litros -->
                    <div class="space-y-2">
                        <div v-for="(o, idx) in linha.ordenhas" :key="idx"
                             class="flex gap-2 items-end">
                            <!-- Label da ordenha (1ª, 2ª...) -->
                            <div class="flex-shrink-0 w-9 text-center pb-3 text-sm font-semibold text-slate-600">
                                {{ LABELS[idx] || `${idx+1}ª` }}
                            </div>
                            <!-- Hora -->
                            <div class="flex-shrink-0">
                                <label class="block text-[10px] font-medium text-slate-500 mb-0.5">Hora</label>
                                <input
                                    v-model="o.hora"
                                    type="time"
                                    class="px-2 py-2.5 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-sm font-mono w-[110px]"
                                >
                            </div>
                            <!-- Litros -->
                            <div class="flex-1 relative">
                                <label class="block text-[10px] font-medium text-slate-500 mb-0.5">Litros produzidos</label>
                                <input
                                    v-model="o.litros"
                                    type="number"
                                    inputmode="decimal"
                                    step="0.1"
                                    min="0"
                                    placeholder="0.0"
                                    class="w-full px-3 py-2.5 pr-9 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base font-mono text-right"
                                >
                                <span class="absolute right-3 top-7 text-xs text-slate-400">L</span>
                            </div>
                            <button
                                v-if="linha.ordenhas.length > 1"
                                type="button"
                                @click="removerOrdenha(linha, idx)"
                                class="flex-shrink-0 w-9 h-10 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center"
                                title="Remover esta ordenha"
                            >×</button>
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

            <!-- Botão Salvar (segue padrão do sistema — dentro do card, não fixed) -->
            <div class="card max-w-3xl mx-auto">
                <div class="card-body flex items-center justify-end gap-3">
                    <Link :href="returnTo || route('admin.inicio')" class="btn-outline">Cancelar</Link>
                    <button
                        type="button"
                        @click="submit"
                        :disabled="linhasComProducao.length === 0 || form.processing"
                        class="btn-primary"
                    >
                        <span v-if="form.processing">Salvando…</span>
                        <span v-else-if="linhasComProducao.length === 0">Digite os litros de pelo menos 1 ordenha</span>
                        <span v-else>✓ Salvar produção de {{ linhasComProducao.length }} vaca(s)</span>
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
