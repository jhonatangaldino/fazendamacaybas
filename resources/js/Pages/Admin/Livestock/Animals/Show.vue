<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMoney from '@/Components/InputMoney.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import AvatarUpload from '@/Components/AvatarUpload.vue';
import { dataBR, brl, hojeBR } from '@/utils/format.js';
import {
    Chart as ChartJS, LineElement, PointElement, LinearScale, TimeScale,
    CategoryScale, Tooltip, Legend, Title, Filler,
} from 'chart.js';
import { Line } from 'vue-chartjs';
ChartJS.register(LineElement, PointElement, LinearScale, CategoryScale, TimeScale, Tooltip, Legend, Title, Filler);

const props = defineProps({ animal: Object, events: Array, pesagens: Array, partners: Array, lots: Array });

// Idade amigável em anos/meses/dias
const idadeFormatada = computed(() => {
    if (!props.animal?.data_nascimento) return '—';
    const nasc = new Date(props.animal.data_nascimento);
    const agora = new Date();
    const diffMs = agora - nasc;
    if (diffMs < 0) return '—';
    const dias = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    if (dias < 30) return `${dias} dia${dias === 1 ? '' : 's'}`;
    const meses = Math.floor(dias / 30.44);
    if (meses < 12) return `${meses} ${meses === 1 ? 'mês' : 'meses'}`;
    const anos = Math.floor(meses / 12);
    const mesesRestantes = meses % 12;
    if (mesesRestantes === 0) return `${anos} ano${anos === 1 ? '' : 's'}`;
    return `${anos} ano${anos === 1 ? '' : 's'} e ${mesesRestantes} ${mesesRestantes === 1 ? 'mês' : 'meses'}`;
});

const activeTab = ref('timeline'); // timeline | grafico | dados

// Modal de novo evento
//
// ⚠ IMPORTANTE: useForm do Inertia tem método interno chamado .data() que
// retorna o payload. NÃO podemos criar um campo chamado `data` porque ele
// sobrescreve o método → `TypeError: this.data is not a function` no submit.
// Renomeamos para `data_evento` e usamos transform() para enviar ao backend
// com a chave esperada (`data`).
const novoEvento = ref(false);
const eventForm = useForm({
    tipo: 'pesagem',
    data_evento: hojeBR(),
    peso: '',
    vacina: '',
    medicamento: '',
    dose: '',
    via_aplicacao: '',
    responsavel: '',
    valor: '',
    partner_id: null,
    lot_origem_id: null,
    lot_destino_id: null,
    observacoes: '',
});

function abrirEvento(tipo = 'pesagem') {
    eventForm.reset();
    eventForm.tipo = tipo;
    eventForm.data_evento = hojeBR();
    novoEvento.value = true;
}

function salvarEvento() {
    eventForm
        .transform((d) => {
            // Renomeia data_evento → data para o backend (que espera 'data')
            const { data_evento, ...rest } = d;
            return { ...rest, data: data_evento };
        })
        .post(route('admin.rebanho.animais.eventos.store', props.animal.id), {
            preserveScroll: true,
            onSuccess: () => { novoEvento.value = false; eventForm.reset(); },
        });
}

function removerEvento(event) {
    if (!confirm('Remover este evento?')) return;
    router.delete(route('admin.rebanho.animais.eventos.destroy', [props.animal.id, event.id]), {
        preserveScroll: true,
    });
}

// Métricas · ordenação DEFENSIVA (não confia que props.pesagens já veio ordenado)
// Empate de data → desempata por id (pesagem cadastrada depois fica depois)
const pesagensOrdenadas = computed(() => {
    return [...props.pesagens].sort((a, b) => {
        if (a.data !== b.data) return a.data < b.data ? -1 : 1;
        return (a.id ?? 0) - (b.id ?? 0);
    });
});

const totalPesagens = computed(() => pesagensOrdenadas.value.length);

const primeiraPesagem = computed(() => pesagensOrdenadas.value[0] ?? null);
const ultimaPesagem = computed(() =>
    pesagensOrdenadas.value[pesagensOrdenadas.value.length - 1] ?? null,
);

const ganhoTotal = computed(() => {
    if (totalPesagens.value < 2) return null;
    // Diferença entre MAIS RECENTE e MAIS ANTIGA (pode ser negativa se animal perdeu peso)
    return ultimaPesagem.value.peso - primeiraPesagem.value.peso;
});

const ganhoMedioDiario = computed(() => {
    if (totalPesagens.value < 2) return null;
    const first = primeiraPesagem.value, last = ultimaPesagem.value;
    const dias = (new Date(last.data) - new Date(first.data)) / (1000 * 60 * 60 * 24);
    if (dias <= 0) return null;
    return (last.peso - first.peso) / dias;
});

const ganhoSinal = computed(() => {
    const g = ganhoTotal.value;
    if (g === null) return 'neutro';
    if (g > 0.01) return 'positivo';
    if (g < -0.01) return 'negativo';
    return 'neutro';
});

const diasEntrePesagens = computed(() => {
    if (totalPesagens.value < 2) return 0;
    const first = primeiraPesagem.value, last = ultimaPesagem.value;
    const dias = Math.round((new Date(last.data) - new Date(first.data)) / (1000 * 60 * 60 * 24));
    return dias > 0 ? dias : 0;
});

// Dados do gráfico (usa pesagens já ordenadas)
const chartData = computed(() => ({
    labels: pesagensOrdenadas.value.map(p => dataBR(p.data)),
    datasets: [{
        label: 'Peso (kg)',
        data: pesagensOrdenadas.value.map(p => p.peso),
        borderColor: '#166534',
        backgroundColor: 'rgba(22, 101, 52, 0.1)',
        borderWidth: 2,
        tension: 0.25,
        fill: true,
        pointRadius: 4,
        pointHoverRadius: 7,
        pointBackgroundColor: '#166534',
    }],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => `${ctx.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 1 })} kg`,
            },
        },
    },
    scales: {
        y: {
            ticks: { callback: (v) => `${v} kg` },
            grid: { color: 'rgba(148, 163, 184, 0.15)' },
        },
        x: { grid: { display: false } },
    },
};

const eventoIcone = (tipo) => ({
    pesagem: '⚖️',
    vacinacao: '💉',
    medicacao: '💊',
    reproducao: '❤️',
    movimentacao: '🔄',
    observacao: '📝',
    nascimento: '🐣',
    morte: '⚰️',
    compra: '🛒',
    venda: '💰',
})[tipo] || '📌';

const eventoLabel = (tipo) => ({
    pesagem: 'Pesagem',
    vacinacao: 'Vacinação',
    medicacao: 'Medicação',
    reproducao: 'Reprodução',
    movimentacao: 'Movimentação',
    observacao: 'Observação',
    nascimento: 'Nascimento',
    morte: 'Morte',
    compra: 'Compra',
    venda: 'Venda',
})[tipo] || tipo;
</script>

<template>
    <Head :title="`${animal.identificacao} — ${animal.nome || 'Animal'}`" />
    <AdminLayout>
        <template #page-title>Rebanho</template>

        <PageHeader :title="`${animal.identificacao}${animal.nome ? ' — ' + animal.nome : ''}`"
                    subtitle="Histórico completo e evolução do animal">
            <template #actions>
                <Link :href="route('admin.rebanho.animais.index')" class="btn-outline">← Voltar</Link>
                <Link :href="route('admin.rebanho.animais.edit', animal.id)" class="btn-outline">Editar cadastro</Link>
                <button @click="abrirEvento('pesagem')" class="btn-primary">+ Novo evento</button>
            </template>
        </PageHeader>

        <!-- Cabeçalho com foto editável + dados principais -->
        <div class="card mb-6">
            <div class="card-body flex flex-col sm:flex-row gap-6">
                <!-- Foto clicável: clique na imagem abre o seletor (mesmo componente do form) -->
                <div class="flex-shrink-0">
                    <AvatarUpload
                        :url="animal.photo_url"
                        :name="animal.identificacao"
                        size="h-40 w-40"
                        shape="square"
                        layout="stacked"
                        :upload-url="route('admin.rebanho.animais.foto.upload', animal.id)"
                        :remove-url="route('admin.rebanho.animais.foto.remove', animal.id)"
                    />
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 flex-1">
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Espécie</div><div class="font-medium">{{ animal.species?.nome || '—' }}</div></div>
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Raça</div><div class="font-medium">{{ animal.breed?.nome || '—' }}</div></div>
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Sexo</div><div class="font-medium">{{ animal.sexo === 'M' ? 'Macho' : 'Fêmea' }}</div></div>
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Idade</div><div class="font-medium">{{ idadeFormatada }}</div></div>
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Peso atual</div><div class="font-medium">{{ animal.peso_atual ? Number(animal.peso_atual).toLocaleString('pt-BR', { minimumFractionDigits: 1 }) + ' kg' : '—' }}</div></div>
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Categoria</div><div class="font-medium">{{ animal.categoria || '—' }}</div></div>
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Lote</div><div class="font-medium">{{ animal.lot?.nome || '—' }}</div></div>
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Status</div><div class="font-medium">{{ animal.status }}</div></div>
                </div>
            </div>
        </div>

        <!-- ═══ KPIs redesenhados para usuário leigo ═══
             · Linguagem natural ("pesado 4 vezes", "De X até Y")
             · Hierarquia visual forte (número grande, label pequeno, contexto em baixo)
             · Cor semântica com fundo tonalizado (verde/vermelho/slate)
             · Emoji como apoio cognitivo
             · Alerta explicativo quando há perda ou pouca informação
             · Mobile: cards empilham naturalmente, texto confortável -->
        <div class="grid gap-4 sm:grid-cols-3 mb-6">

            <!-- CARD 1 · Pesagens registradas -->
            <div class="card p-5">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs uppercase tracking-wider font-semibold text-slate-500">
                        Pesagens registradas
                    </div>
                    <div class="text-2xl leading-none" aria-hidden="true">⚖️</div>
                </div>
                <div class="text-3xl font-bold text-slate-900 leading-tight">
                    {{ totalPesagens }}
                    <span v-if="totalPesagens > 0" class="text-base font-medium text-slate-500">
                        {{ totalPesagens === 1 ? 'vez' : 'vezes' }}
                    </span>
                </div>
                <div v-if="totalPesagens >= 2" class="text-sm text-slate-600 mt-3 leading-relaxed">
                    De <strong>{{ dataBR(primeiraPesagem.data) }}</strong>
                    até <strong>{{ dataBR(ultimaPesagem.data) }}</strong>
                </div>
                <div v-else-if="totalPesagens === 1" class="text-sm text-amber-700 mt-3 leading-relaxed">
                    Faça outra pesagem para o sistema calcular o ganho de peso.
                </div>
                <div v-else class="text-sm text-slate-500 mt-3 leading-relaxed">
                    Clique em <strong>+ Novo evento</strong> para registrar a primeira pesagem.
                </div>
            </div>

            <!-- CARD 2 · Ganho/Perda total com contexto forte -->
            <div class="card p-5 relative overflow-hidden"
                 :class="{
                    'ring-2 ring-emerald-100': ganhoSinal === 'positivo',
                    'ring-2 ring-red-100':     ganhoSinal === 'negativo',
                 }">
                <!-- Barra colorida lateral para reforço visual -->
                <div v-if="ganhoSinal !== 'neutro'" class="absolute left-0 top-0 bottom-0 w-1"
                     :class="ganhoSinal === 'positivo' ? 'bg-emerald-500' : 'bg-red-500'"></div>

                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs uppercase tracking-wider font-semibold"
                         :class="{
                            'text-emerald-700': ganhoSinal === 'positivo',
                            'text-red-700':     ganhoSinal === 'negativo',
                            'text-slate-500':   ganhoSinal === 'neutro',
                         }">
                        {{ ganhoSinal === 'negativo' ? 'Perdeu peso' : ganhoSinal === 'positivo' ? 'Ganhou peso' : 'Ganho de peso' }}
                    </div>
                    <div class="text-2xl leading-none" aria-hidden="true">
                        {{ ganhoSinal === 'positivo' ? '📈' : ganhoSinal === 'negativo' ? '📉' : '—' }}
                    </div>
                </div>
                <div class="text-3xl font-bold leading-tight"
                     :class="{
                        'text-emerald-700': ganhoSinal === 'positivo',
                        'text-red-700':     ganhoSinal === 'negativo',
                        'text-slate-400':   ganhoSinal === 'neutro',
                     }">
                    <template v-if="ganhoTotal != null">
                        {{ ganhoTotal >= 0 ? '+' : '' }}{{ ganhoTotal.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) }}
                        <span class="text-base font-medium">kg</span>
                    </template>
                    <template v-else>—</template>
                </div>

                <div v-if="ganhoTotal != null && primeiraPesagem && ultimaPesagem"
                     class="text-sm text-slate-600 mt-3 leading-relaxed">
                    De <strong>{{ primeiraPesagem.peso.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) }} kg</strong>
                    em {{ dataBR(primeiraPesagem.data) }}<br>
                    para <strong>{{ ultimaPesagem.peso.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) }} kg</strong>
                    em {{ dataBR(ultimaPesagem.data) }}
                </div>
                <div v-else-if="totalPesagens < 2" class="text-sm text-slate-400 mt-3">
                    Disponível após a 2ª pesagem.
                </div>

                <!-- Alerta quando perdeu peso -->
                <div v-if="ganhoSinal === 'negativo'"
                     class="text-sm text-red-800 bg-red-50 rounded-md px-3 py-2 mt-3 leading-relaxed">
                    <strong>Atenção:</strong> o animal está mais leve do que na primeira pesagem.
                    Pode ser doença, falta de ração ou estresse — vale uma olhada de perto.
                </div>
            </div>

            <!-- CARD 3 · Média por dia -->
            <div class="card p-5">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs uppercase tracking-wider font-semibold text-slate-500">
                        Média por dia
                    </div>
                    <div class="text-2xl leading-none" aria-hidden="true">📅</div>
                </div>
                <div class="text-3xl font-bold leading-tight"
                     :class="{
                        'text-macaybas-primary': ganhoSinal === 'positivo',
                        'text-red-700':          ganhoSinal === 'negativo',
                        'text-slate-400':        ganhoSinal === 'neutro',
                     }">
                    <template v-if="ganhoMedioDiario != null">
                        {{ ganhoMedioDiario >= 0 ? '+' : '' }}{{ ganhoMedioDiario.toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }}
                        <span class="text-base font-medium">kg</span>
                    </template>
                    <template v-else>—</template>
                </div>
                <div v-if="ganhoMedioDiario != null" class="text-sm text-slate-600 mt-3 leading-relaxed">
                    {{ ganhoSinal === 'negativo' ? 'Perdeu' : 'Ganhou' }}
                    <strong>{{ Math.abs(ganhoMedioDiario).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }} kg por dia</strong>,
                    em média, durante <strong>{{ diasEntrePesagens }}</strong> dias.
                </div>
                <div v-else class="text-sm text-slate-400 mt-3">
                    Disponível após a 2ª pesagem.
                </div>
            </div>
        </div>

        <!-- Rodapé discreto explicando o cálculo (só quando há ganho/perda calculados) -->
        <div v-if="ganhoTotal != null" class="text-xs text-slate-500 mb-6 -mt-4 flex items-start gap-1.5">
            <svg class="h-3.5 w-3.5 mt-0.5 flex-shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>
                Os cálculos consideram a <strong>primeira</strong> e a <strong>última</strong> pesagem
                ({{ totalPesagens }} no total). A média é simples: diferença dividida pelos dias entre elas.
            </span>
        </div>

        <!-- Tabs -->
        <div class="border-b border-slate-200 mb-4 flex gap-6">
            <button @click="activeTab = 'timeline'"
                    :class="activeTab === 'timeline' ? 'border-macaybas-primary text-macaybas-primary' : 'border-transparent text-slate-500 hover:text-slate-800'"
                    class="py-3 border-b-2 font-medium transition-colors">
                📋 Linha do tempo
            </button>
            <button @click="activeTab = 'grafico'"
                    :class="activeTab === 'grafico' ? 'border-macaybas-primary text-macaybas-primary' : 'border-transparent text-slate-500 hover:text-slate-800'"
                    class="py-3 border-b-2 font-medium transition-colors">
                📈 Evolução de peso
            </button>
        </div>

        <!-- Timeline -->
        <div v-if="activeTab === 'timeline'" class="card">
            <div class="card-body">
                <div v-if="!events.length" class="text-center py-10 text-slate-500">
                    Nenhum evento registrado ainda. Clique em <strong>+ Novo evento</strong> pra começar o histórico.
                </div>
                <ol v-else class="relative border-l-2 border-slate-200 ml-3 space-y-6">
                    <li v-for="e in events" :key="e.id" class="ml-6">
                        <span class="absolute -left-[13px] flex items-center justify-center w-6 h-6 bg-white rounded-full ring-2 ring-macaybas-primary-100 text-sm">
                            {{ eventoIcone(e.tipo) }}
                        </span>
                        <div class="flex items-start justify-between gap-3 p-4 bg-slate-50 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-semibold text-slate-900">{{ eventoLabel(e.tipo) }}</span>
                                    <span class="text-xs text-slate-500">{{ dataBR(e.data) }}</span>
                                </div>
                                <div class="grid sm:grid-cols-2 gap-2 text-sm text-slate-600">
                                    <div v-if="e.peso">Peso: <strong>{{ Number(e.peso).toLocaleString('pt-BR', { minimumFractionDigits: 1 }) }} kg</strong></div>
                                    <div v-if="e.vacina">Vacina: {{ e.vacina }}</div>
                                    <div v-if="e.medicamento">Medicamento: {{ e.medicamento }}</div>
                                    <div v-if="e.dose">Dose: {{ e.dose }} {{ e.via_aplicacao || '' }}</div>
                                    <div v-if="e.responsavel">Responsável: {{ e.responsavel }}</div>
                                    <div v-if="e.valor">Valor: <strong class="text-red-700">{{ brl(e.valor) }}</strong></div>
                                    <div v-if="e.partner">Parceiro: {{ e.partner.nome }}</div>
                                    <div v-if="e.lot_origem || e.lot_destino" class="sm:col-span-2">
                                        Movimentação: {{ e.lot_origem?.nome || '—' }} → {{ e.lot_destino?.nome || '—' }}
                                    </div>
                                </div>
                                <p v-if="e.observacoes" class="mt-2 text-sm text-slate-500 italic">{{ e.observacoes }}</p>
                                <p v-if="e.creator" class="mt-1 text-xs text-slate-400">Registrado por {{ e.creator.name }}</p>
                            </div>
                            <ActionIcon type="delete" size="sm" title="Remover evento" @click="removerEvento(e)" />
                        </div>
                    </li>
                </ol>
            </div>
        </div>

        <!-- Gráfico -->
        <div v-if="activeTab === 'grafico'" class="card">
            <div class="card-body">
                <div v-if="pesagens.length < 2" class="text-center py-10 text-slate-500">
                    São necessárias pelo menos 2 pesagens para exibir o gráfico de evolução.
                </div>
                <div v-else class="h-80">
                    <Line :data="chartData" :options="chartOptions" />
                </div>
            </div>
        </div>

        <!-- Modal: Novo evento -->
        <Teleport to="body">
            <div v-if="novoEvento" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novoEvento = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
                    <h3 class="text-lg font-semibold text-slate-900 mb-4">Novo evento — {{ animal.identificacao }}</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <InputLabel value="Tipo de evento *" />
                            <select v-model="eventForm.tipo" class="form-select">
                                <option value="pesagem">⚖️ Pesagem</option>
                                <option value="vacinacao">💉 Vacinação</option>
                                <option value="medicacao">💊 Medicação</option>
                                <option value="reproducao">❤️ Reprodução</option>
                                <option value="movimentacao">🔄 Movimentação (troca de lote)</option>
                                <option value="venda">💰 Venda (encerra ciclo · gera receita)</option>
                                <option value="mortalidade">⚰️ Mortalidade (encerra ciclo)</option>
                                <option value="observacao">📝 Observação</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Data *" />
                            <InputDate v-model="eventForm.data_evento" :max="new Date().toISOString().slice(0,10)" required />
                            <p v-if="eventForm.errors.data" class="text-xs text-red-600 mt-1">{{ eventForm.errors.data }}</p>
                        </div>

                        <!-- Pesagem -->
                        <div v-if="eventForm.tipo === 'pesagem'">
                            <InputLabel value="Peso (kg) *" />
                            <input type="number" step="0.01" min="0" v-model="eventForm.peso" class="form-input" required>
                            <p v-if="eventForm.errors.peso" class="text-xs text-red-600 mt-1">{{ eventForm.errors.peso }}</p>
                        </div>

                        <!-- Vacinação -->
                        <template v-if="eventForm.tipo === 'vacinacao'">
                            <div class="sm:col-span-2"><InputLabel value="Vacina *" /><input v-model="eventForm.vacina" class="form-input" placeholder="Ex: Febre Aftosa"></div>
                            <div><InputLabel value="Dose (ml)" /><input type="number" step="0.01" v-model="eventForm.dose" class="form-input"></div>
                            <div><InputLabel value="Via aplicação" /><input v-model="eventForm.via_aplicacao" class="form-input" placeholder="Ex: subcutânea"></div>
                        </template>

                        <!-- Medicação -->
                        <template v-if="eventForm.tipo === 'medicacao'">
                            <div class="sm:col-span-2"><InputLabel value="Medicamento *" /><input v-model="eventForm.medicamento" class="form-input" placeholder="Ex: Ivermectina"></div>
                            <div><InputLabel value="Dose" /><input type="number" step="0.01" v-model="eventForm.dose" class="form-input"></div>
                            <div><InputLabel value="Via aplicação" /><input v-model="eventForm.via_aplicacao" class="form-input"></div>
                        </template>

                        <!-- Movimentação -->
                        <template v-if="eventForm.tipo === 'movimentacao'">
                            <div>
                                <InputLabel value="Lote de origem" />
                                <select v-model="eventForm.lot_origem_id" class="form-select">
                                    <option :value="null">—</option>
                                    <option v-for="l in lots" :key="l.id" :value="l.id">{{ l.nome }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Lote de destino *" />
                                <select v-model="eventForm.lot_destino_id" class="form-select" required>
                                    <option :value="null">—</option>
                                    <option v-for="l in lots" :key="l.id" :value="l.id">{{ l.nome }}</option>
                                </select>
                            </div>
                        </template>

                        <!-- Comuns -->
                        <div v-if="['vacinacao', 'medicacao', 'pesagem'].includes(eventForm.tipo)">
                            <InputLabel value="Responsável" />
                            <input v-model="eventForm.responsavel" class="form-input" placeholder="Ex: Dr. João">
                        </div>
                        <div v-if="['vacinacao', 'medicacao', 'compra', 'venda'].includes(eventForm.tipo)">
                            <InputLabel value="Valor (opcional)" />
                            <InputMoney v-model="eventForm.valor" />
                            <p class="text-xs text-slate-400 mt-1">Se informado, gera despesa no Financeiro.</p>
                        </div>
                        <div v-if="['vacinacao', 'medicacao', 'compra', 'venda'].includes(eventForm.tipo)">
                            <InputLabel value="Fornecedor" />
                            <select v-model="eventForm.partner_id" class="form-select">
                                <option :value="null">—</option>
                                <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.nome }}</option>
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <InputLabel value="Observações" />
                            <textarea v-model="eventForm.observacoes" rows="2" class="form-textarea"></textarea>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoEvento = false" class="btn-outline">Cancelar</button>
                        <button @click="salvarEvento" :disabled="eventForm.processing" class="btn-primary">
                            {{ eventForm.processing ? 'Salvando...' : 'Registrar evento' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
