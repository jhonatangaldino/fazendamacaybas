<script setup>
/**
 * Dashboard inicial por espécie — versão B (rico):
 *   • KPIs base + KPIs específicos do profile (leite/postura/aquicultura)
 *   • GRÁFICOS REAIS (Chart.js): distribuição sexo/idade, evolução peso,
 *     produção leite mensal, postura diária, etc. — adaptados ao profile
 *   • Ações rápidas em CARDS COLORIDOS por categoria (preventivo/manejo/
 *     reprodução), abrem MODAL in-place (não wizards de página inteira)
 *   • Lotes como cards com info útil (nome + count + peso médio)
 */
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import RegistrarEventoRapidoModal from '@/Components/RegistrarEventoRapidoModal.vue';
import { emojiEspecie } from '@/utils/emojiEspecie.js';
import { allowedEventsFor, EVENT_CATALOG } from '@/utils/animalProfile.js';
import { useToast } from '@/composables/useToast.js';
import {
    Chart as ChartJS, ArcElement, BarElement, LineElement, PointElement,
    LinearScale, CategoryScale, Tooltip, Legend, Title, Filler,
} from 'chart.js';
import { Pie, Bar, Line } from 'vue-chartjs';
ChartJS.register(ArcElement, BarElement, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Title, Filler);

const props = defineProps({
    species: { type: Object, required: true },
    kpis: { type: Object, required: true },
    kpis_profile: { type: Object, default: null },
    charts: { type: Object, default: () => ({}) },
    lots: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    animals: { type: Array, default: () => [] },
});

const emoji = computed(() => emojiEspecie(props.species.nome));
const isLote = computed(() => props.species.gestao === 'lote');

// Categorização das ações rápidas pra cor/agrupamento visual.
// Cada grupo tem cor distinta — preventivo verde, manejo azul, reprodução rosa.
const CATEGORIAS_ACAO = {
    preventivo: { label: 'Preventivo', cor: 'emerald', tipos: ['vacinacao', 'medicacao', 'vermifugacao'] },
    manejo: { label: 'Manejo', cor: 'sky', tipos: ['pesagem', 'ordenha', 'movimentacao', 'biometria_amostral', 'alimentacao', 'qualidade_agua', 'tosquia', 'ferrageamento', 'postura_diaria'] },
    reproducao: { label: 'Reprodução', cor: 'pink', tipos: ['reproducao', 'secagem', 'castracao'] },
};

function categoriaParaTipo(tipo) {
    for (const [key, cfg] of Object.entries(CATEGORIAS_ACAO)) {
        if (cfg.tipos.includes(tipo)) return cfg;
    }
    return { label: 'Outro', cor: 'slate', tipos: [] };
}

const acoesRapidas = computed(() => {
    const eventos = allowedEventsFor(props.species, null);
    return eventos
        .filter(e => !['observacao', 'mortalidade'].includes(e))
        .filter(e => EVENT_CATALOG[e]?.label)
        .map(tipo => ({
            tipo,
            ...EVENT_CATALOG[tipo],
            cat: categoriaParaTipo(tipo),
        }));
});

// Modal de registro
const modalAberto = ref(false);
const tipoAtivo = ref(null);
const { toast } = useToast();
function abrirModal(tipo) {
    if (props.animals.length === 0) {
        toast?.(`Cadastre pelo menos um ${props.species.nome.toLowerCase()} antes de registrar eventos.`, 'atencao');
        return;
    }
    tipoAtivo.value = tipo;
    modalAberto.value = true;
}
function fecharModal() {
    modalAberto.value = false;
}
function onSucesso() {
    // Recarrega KPIs/gráficos sem voltar ao topo
    router.reload({ preserveScroll: true });
}

// KPIs base — só os que têm valor real, evita cards repetidos
const kpisVisiveis = computed(() => {
    const k = props.kpis;
    const cards = [
        { label: 'Ativos', valor: k.total_ativos, icon: '🐾', color: 'emerald' },
    ];
    if (k.total_ativos > 0) {
        if (k.sexo_m + k.sexo_f > 0) {
            cards.push({ label: 'Machos / Fêmeas', valor: `${k.sexo_m} / ${k.sexo_f}`, icon: '⚥', color: 'sky' });
        }
        if (k.peso_medio !== null) {
            cards.push({ label: 'Peso médio', valor: formatPeso(k.peso_medio), icon: '⚖️', color: 'amber' });
        }
        if (k.lots_count > 0) {
            cards.push({ label: 'Lotes ativos', valor: k.lots_count, icon: '🏷', color: 'slate' });
        }
        cards.push({ label: 'Eventos 7d', valor: k.eventos_7d, icon: '📋', color: 'indigo' });
    }
    if (k.vendidos_mes > 0) cards.push({ label: 'Vendidos no mês', valor: k.vendidos_mes, icon: '💰', color: 'emerald' });
    if (k.baixas_mes > 0) cards.push({ label: 'Baixas no mês', valor: k.baixas_mes, icon: '⚰️', color: 'rose' });
    return cards;
});

function formatPeso(v) {
    return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(v) + ' kg';
}

const COLOR_CLASSES = {
    emerald: 'bg-emerald-50 ring-emerald-200 text-emerald-800',
    sky: 'bg-sky-50 ring-sky-200 text-sky-800',
    amber: 'bg-amber-50 ring-amber-200 text-amber-800',
    slate: 'bg-slate-50 ring-slate-200 text-slate-800',
    indigo: 'bg-indigo-50 ring-indigo-200 text-indigo-800',
    rose: 'bg-rose-50 ring-rose-200 text-rose-800',
    pink: 'bg-pink-50 ring-pink-200 text-pink-800',
};

const ACAO_CARD_CLASSES = {
    emerald: 'bg-emerald-500 hover:bg-emerald-600 text-white shadow-emerald-200',
    sky: 'bg-sky-500 hover:bg-sky-600 text-white shadow-sky-200',
    pink: 'bg-pink-500 hover:bg-pink-600 text-white shadow-pink-200',
    slate: 'bg-slate-500 hover:bg-slate-600 text-white shadow-slate-200',
};

// Helpers Chart.js
const chartOptions = (yLabel = '') => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#0f172a',
            padding: 10,
            titleFont: { size: 12 },
            bodyFont: { size: 13, weight: 'bold' },
        },
    },
    scales: yLabel ? {
        y: { beginAtZero: true, title: { display: !!yLabel, text: yLabel, font: { size: 10 } } },
        x: { ticks: { font: { size: 11 } } },
    } : undefined,
});

const pieOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { font: { size: 12 } } },
        tooltip: { backgroundColor: '#0f172a' },
    },
};

function chartLineData(c) {
    return {
        labels: c.labels,
        datasets: [{
            data: c.data,
            borderColor: c.cor,
            backgroundColor: c.cor + '33',
            tension: 0.3,
            fill: true,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: c.cor,
        }],
    };
}
function chartBarData(c) {
    return {
        labels: c.labels,
        datasets: [{
            data: c.data,
            backgroundColor: c.cores || '#0ea5e9',
            borderRadius: 4,
        }],
    };
}
function chartPieData(c) {
    return {
        labels: c.labels,
        datasets: [{
            data: c.data,
            backgroundColor: c.cores || ['#0ea5e9', '#ec4899', '#f59e0b'],
            borderWidth: 2,
            borderColor: '#fff',
        }],
    };
}
</script>

<template>
    <Head :title="`Rebanho · ${species.nome}`" />
    <AdminLayout>
        <template #page-title>{{ species.nome }}</template>

        <PageHeader :title="`${emoji} ${species.nome}`"
                    :subtitle="kpis.total_ativos > 0
                        ? (isLote
                            ? `${kpis.total_ativos} cabeça(s) em ${kpis.lots_count} lote(s) — visão geral, ações rápidas e cadastro`
                            : `${kpis.total_ativos} ${species.nome.toLowerCase()}(s) ativo(s) — visão geral, ações rápidas e cadastro`)
                        : (isLote
                            ? `Nenhum lote de ${species.nome.toLowerCase()} cadastrado ainda — comece criando um lote abaixo`
                            : `Nenhum ${species.nome.toLowerCase()} cadastrado ainda — comece pelo botão de cadastro abaixo`)">
            <template #actions>
                <Link :href="route('admin.rebanho.animais.index', { species_id: species.id })" class="btn-outline">
                    📋 Ver todos
                </Link>
                <Link v-if="isLote"
                      :href="route('admin.rebanho.lotes.create', { species_id: species.id })"
                      class="btn-primary">
                    + Novo lote
                </Link>
                <Link v-else
                      :href="route('admin.rebanho.animais.create', { species_id: species.id })"
                      class="btn-primary">
                    + Cadastrar {{ species.nome.toLowerCase() }}
                </Link>
            </template>
        </PageHeader>

        <!-- KPIs base -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-6">
            <div v-for="card in kpisVisiveis" :key="card.label"
                 class="rounded-xl ring-1 p-4"
                 :class="COLOR_CLASSES[card.color] ?? COLOR_CLASSES.slate">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] uppercase tracking-widest font-semibold opacity-80">{{ card.label }}</span>
                    <span class="text-xl">{{ card.icon }}</span>
                </div>
                <div class="mt-1 text-2xl font-serif font-bold">{{ card.valor }}</div>
            </div>
        </div>

        <!-- Indicadores específicos por profile -->
        <div v-if="kpis_profile" class="rounded-2xl bg-white ring-1 ring-slate-200 p-5 mb-6">
            <div class="flex items-start justify-between mb-3">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">{{ kpis_profile.titulo }}</h3>
                <Link v-if="kpis_profile.link" :href="kpis_profile.link"
                      class="text-xs text-macaybas-primary-700 hover:underline font-medium">
                    {{ kpis_profile.link_label || 'Ver detalhes' }} →
                </Link>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div v-for="card in kpis_profile.cards" :key="card.label"
                     class="rounded-xl ring-1 p-3"
                     :class="COLOR_CLASSES[card.cor] ?? COLOR_CLASSES.slate">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] uppercase tracking-widest font-semibold opacity-80">{{ card.label }}</span>
                        <span class="text-base">{{ card.icon }}</span>
                    </div>
                    <div class="mt-1 text-xl font-serif font-bold">{{ card.valor }}</div>
                </div>
            </div>
        </div>

        <!-- GRÁFICOS — visualizações reais dos dados -->
        <div v-if="Object.keys(charts).length > 0" class="grid lg:grid-cols-2 gap-4 mb-6">
            <!-- Distribuição por sexo -->
            <div v-if="charts.sexo" class="rounded-2xl bg-white ring-1 ring-slate-200 p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">{{ charts.sexo.titulo }}</h3>
                <div class="h-56">
                    <Pie :data="chartPieData(charts.sexo)" :options="pieOptions" />
                </div>
            </div>
            <!-- Distribuição etária -->
            <div v-if="charts.idade" class="rounded-2xl bg-white ring-1 ring-slate-200 p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">{{ charts.idade.titulo }}</h3>
                <div class="h-56">
                    <Bar :data="chartBarData(charts.idade)" :options="chartOptions('animais')" />
                </div>
            </div>
            <!-- Evolução de peso -->
            <div v-if="charts.peso_evolucao" class="rounded-2xl bg-white ring-1 ring-slate-200 p-5 lg:col-span-2">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">{{ charts.peso_evolucao.titulo }}</h3>
                <div class="h-64">
                    <Line :data="chartLineData(charts.peso_evolucao)" :options="chartOptions('kg')" />
                </div>
            </div>
            <!-- Produção mensal de leite -->
            <div v-if="charts.leite_mensal" class="rounded-2xl bg-white ring-1 ring-slate-200 p-5 lg:col-span-2">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">{{ charts.leite_mensal.titulo }}</h3>
                <div class="h-64">
                    <Line :data="chartLineData(charts.leite_mensal)" :options="chartOptions('litros')" />
                </div>
            </div>
            <!-- Postura diária -->
            <div v-if="charts.postura_diaria" class="rounded-2xl bg-white ring-1 ring-slate-200 p-5 lg:col-span-2">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">{{ charts.postura_diaria.titulo }}</h3>
                <div class="h-64">
                    <Line :data="chartLineData(charts.postura_diaria)" :options="chartOptions('ovos')" />
                </div>
            </div>
        </div>

        <!-- AÇÕES RÁPIDAS — cards coloridos por categoria, abre modal -->
        <div v-if="acoesRapidas.length > 0" class="mb-6">
            <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">
                Ações rápidas {{ isLote ? '(em lote)' : '(por animal)' }}
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                <button v-for="acao in acoesRapidas" :key="acao.tipo"
                        @click="abrirModal(acao.tipo)" type="button"
                        class="group flex flex-col items-center justify-center gap-2 p-5 rounded-2xl shadow-md hover:shadow-xl transition-all hover:-translate-y-0.5"
                        :class="ACAO_CARD_CLASSES[acao.cat.cor] ?? ACAO_CARD_CLASSES.slate">
                    <span class="text-4xl">{{ acao.icon }}</span>
                    <span class="text-sm font-semibold text-center">{{ acao.label }}</span>
                    <span class="text-[10px] uppercase tracking-widest opacity-70">{{ acao.cat.label }}</span>
                </button>
            </div>
            <p class="text-xs text-slate-500 mt-3 italic">
                💡 Click pra abrir um registro rápido — sem sair desta tela.
            </p>
        </div>

        <!-- Lotes — cards com info, não chips -->
        <div v-if="lots.length > 0" class="mb-6">
            <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">
                Lotes ({{ lots.length }})
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <Link v-for="lot in lots" :key="lot.id"
                      :href="route('admin.rebanho.animais.index', { species_id: species.id, lot_id: lot.id })"
                      class="group block p-4 rounded-xl bg-white ring-1 ring-slate-200 hover:ring-macaybas-primary-400 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-base font-semibold text-slate-900 truncate group-hover:text-macaybas-primary-800">
                                🏷 {{ lot.nome }}
                            </div>
                            <div v-if="lot.gestao_modo === 'agregada' && lot.quantidade_atual" class="text-xs text-slate-500 mt-1">
                                {{ Math.round(lot.quantidade_atual) }} cabeça(s) no lote
                            </div>
                            <div v-else class="text-xs text-slate-500 mt-1">
                                Lote convencional
                            </div>
                        </div>
                        <span class="text-slate-400 group-hover:text-macaybas-primary-700">→</span>
                    </div>
                </Link>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="kpis.total_ativos === 0" class="rounded-2xl bg-macaybas-primary-50 ring-1 ring-macaybas-primary-200 p-8 text-center">
            <div class="text-6xl mb-3">{{ emoji }}</div>
            <h3 class="text-lg font-semibold text-macaybas-primary-900 mb-1">Comece a usar {{ species.nome }}</h3>
            <p v-if="isLote" class="text-sm text-macaybas-primary-800 mb-4">
                {{ species.nome }} é gerido em LOTE. Cadastre o primeiro lote informando quantas cabeças entraram.
            </p>
            <p v-else class="text-sm text-macaybas-primary-800 mb-4">
                Cadastre o primeiro {{ species.nome.toLowerCase() }} e comece a registrar pesagens, vacinas, eventos.
            </p>
            <Link v-if="isLote" :href="route('admin.rebanho.lotes.create', { species_id: species.id })" class="btn-primary inline-flex items-center gap-2">
                + Cadastrar primeiro lote
            </Link>
            <Link v-else :href="route('admin.rebanho.animais.create', { species_id: species.id })" class="btn-primary inline-flex items-center gap-2">
                + Cadastrar primeiro {{ species.nome.toLowerCase() }}
            </Link>
        </div>

        <!-- Modal de evento rápido -->
        <RegistrarEventoRapidoModal
            :open="modalAberto"
            :tipo="tipoAtivo"
            :species="species"
            :animals="animals"
            :lots="lots"
            :locations="locations"
            @close="fecharModal"
            @success="onSucesso" />
    </AdminLayout>
</template>
