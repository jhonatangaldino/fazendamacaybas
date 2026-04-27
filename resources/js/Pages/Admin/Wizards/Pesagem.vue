<script setup>
/**
 * Assistente guiado — Registrar peso do animal.
 *
 * 4 passos:
 *   1 · Qual animal?         (grid de cards com busca)
 *   2 · Qual o peso?         (input + data, padrão hoje)
 *   3 · Confere?             (resumo com links "Trocar")
 *   4 · Pronto!              (sucesso + próximas ações)
 *
 * Submit: reutiliza `admin.rebanho.animais.eventos.store` com tipo=pesagem.
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import { hojeBR, dataBR } from '@/utils/format.js';
import { emojiEspecie } from '@/utils/emojiEspecie.js';

const props = defineProps({
    animais: { type: Array, required: true },
    lotesAgregados: { type: Array, default: () => [] },
});

const PASSOS = [
    { n: 1, titulo: 'O que pesar', icon: '🐄' },
    { n: 2, titulo: 'O peso',     icon: '⚖️' },
    { n: 3, titulo: 'Conferência', icon: '📋' },
    { n: 4, titulo: 'Pronto!',    icon: '✅' },
];

// MODO da pesagem (auditoria 2026-04-27)
//   individual → pesar 1 animal (bovino, equino) → atual
//   amostragem → pesar N animais aleatórios de um lote → calcula média
//   biomassa   → kg total / qtd no lote → média
const modo = ref('individual');
const loteSelecionado = ref(null);
// Amostragem: nº pesados + peso total da amostra → calcula média
const amostraQtd = ref('');
const amostraPesoTotal = ref('');
// Biomassa: kg total + qtd no lote → média
const biomassaPesoTotal = ref('');
const biomassaQtd = ref('');

const passo = ref(1);
const selecionado = ref(null);
const busca = ref('');
const animalRegistrado = ref(null);
const pesoRegistrado = ref(null);
const pesoAnterior = ref(null); // snapshot pre-submit para calcular ganho no passo 4

const form = useForm({
    tipo: 'pesagem',
    data_evento: hojeBR(),
    peso: '',
    observacoes: '',
});

// Peso médio calculado dinâmico para amostragem/biomassa
const pesoMedioCalculado = computed(() => {
    if (modo.value === 'amostragem') {
        const q = parseFloat(String(amostraQtd.value).replace(',', '.'));
        const p = parseFloat(String(amostraPesoTotal.value).replace(',', '.'));
        if (!q || !p || q <= 0) return null;
        return p / q;
    }
    if (modo.value === 'biomassa') {
        const q = parseFloat(String(biomassaQtd.value).replace(',', '.'));
        const p = parseFloat(String(biomassaPesoTotal.value).replace(',', '.'));
        if (!q || !p || q <= 0) return null;
        return p / q;
    }
    return null;
});

const animaisFiltrados = computed(() => {
    if (!busca.value.trim()) return props.animais;
    const q = busca.value.toLowerCase();
    return props.animais.filter((a) =>
        (a.identificacao || '').toLowerCase().includes(q) ||
        (a.nome || '').toLowerCase().includes(q) ||
        (a.breed?.nome || '').toLowerCase().includes(q) ||
        (a.lot?.nome || '').toLowerCase().includes(q)
    );
});

const podeAvancar1 = computed(() => {
    if (modo.value === 'individual') return !!selecionado.value;
    return !!loteSelecionado.value; // amostragem ou biomassa exigem lote
});
const podeAvancar2 = computed(() => {
    if (modo.value === 'individual') {
        const n = parseFloat(String(form.peso).replace(',', '.'));
        return !isNaN(n) && n > 0 && !!form.data_evento;
    }
    if (modo.value === 'amostragem') {
        return pesoMedioCalculado.value !== null && pesoMedioCalculado.value > 0 && !!form.data_evento;
    }
    if (modo.value === 'biomassa') {
        return pesoMedioCalculado.value !== null && pesoMedioCalculado.value > 0 && !!form.data_evento;
    }
    return false;
});

// emojiEspecie vem de '@/utils/emojiEspecie.js' — centralizado.

function idadeHumana(dn) {
    if (!dn) return '';
    const nasc = new Date(dn);
    const agora = new Date();
    const diff = agora - nasc;
    if (diff < 0) return '';
    const dias = Math.floor(diff / (1000 * 60 * 60 * 24));
    if (dias < 30) return `${dias} ${dias === 1 ? 'dia' : 'dias'}`;
    const meses = Math.floor(dias / 30.44);
    if (meses < 12) return `${meses} ${meses === 1 ? 'mês' : 'meses'}`;
    const anos = Math.floor(meses / 12);
    const resto = meses % 12;
    return resto ? `${anos}a ${resto}m` : `${anos}a`;
}

function pesoAtualHumano(p) {
    if (!p) return 'sem peso registrado';
    return `${Number(p).toLocaleString('pt-BR', { minimumFractionDigits: 1 })} kg`;
}

function avancar() { if (passo.value < PASSOS.length) passo.value++; }
function voltar() { if (passo.value > 1) passo.value--; }
function irPara(n) { passo.value = n; }

function confirmar() {
    if (!podeAvancar1.value || !podeAvancar2.value) return;

    // ─── INDIVIDUAL: pesa 1 animal específico (rota antiga) ───────────────
    if (modo.value === 'individual') {
        pesoAnterior.value = selecionado.value?.peso_atual ? Number(selecionado.value.peso_atual) : null;

        form
            .transform((d) => {
                const { data_evento, ...r } = d;
                return { ...r, data: data_evento, peso: parseFloat(String(d.peso).replace(',', '.')) };
            })
            .post(route('admin.rebanho.animais.eventos.store', selecionado.value.id), {
                preserveScroll: false,
                onSuccess: () => {
                    animalRegistrado.value = selecionado.value;
                    pesoRegistrado.value = parseFloat(String(form.peso).replace(',', '.'));
                    passo.value = 4;
                },
            });
        return;
    }

    // ─── AMOSTRAGEM ou BIOMASSA: evento agregado por LOTE (rota nova) ───
    pesoAnterior.value = loteSelecionado.value?.peso_medio_kg ? Number(loteSelecionado.value.peso_medio_kg) : null;
    const pesoMedio = pesoMedioCalculado.value;
    // Quantos animais participaram do evento (para registrar quantidade_animais)
    const qtdEvento = modo.value === 'amostragem'
        ? parseInt(String(amostraQtd.value)) || 0
        : (parseInt(String(biomassaQtd.value)) || loteSelecionado.value.quantidade_atual);

    const obs = modo.value === 'amostragem'
        ? `Pesagem amostral · ${amostraQtd.value} animais pesados · ${amostraPesoTotal.value} kg total`
        : `Pesagem por biomassa · ${biomassaQtd.value} animais · ${biomassaPesoTotal.value} kg total`;

    form
        .transform(() => ({
            tipo: 'pesagem',
            data: form.data_evento,
            peso: pesoMedio,
            quantidade_animais: qtdEvento,
            observacoes: (form.observacoes ? form.observacoes + ' · ' : '') + obs,
        }))
        .post(route('admin.rebanho.lotes.eventos.store', loteSelecionado.value.id), {
            preserveScroll: false,
            onSuccess: () => {
                animalRegistrado.value = { identificacao: loteSelecionado.value.nome, especie_lote: true };
                pesoRegistrado.value = pesoMedio;
                passo.value = 4;
            },
        });
}

// Ganho = novo - anterior (null se era a primeira pesagem)
const ganhoKg = computed(() => {
    if (pesoAnterior.value === null || pesoRegistrado.value === null) return null;
    return pesoRegistrado.value - pesoAnterior.value;
});

// Interpretação humana do ganho — espelha heurística da ficha do animal (Show.vue)
// mas mais curta, adequada ao momento pós-pesagem. Sem benchmark por GMD aqui
// porque não temos o intervalo entre pesagens no wizard.
const interpretacaoGanho = computed(() => {
    const g = ganhoKg.value;
    if (g === null) return { tipo: 'primeira', titulo: 'Primeira pesagem registrada', texto: 'Faça outra pesagem em alguns dias para o sistema começar a calcular o ganho de peso.' };
    if (g > 0.1) return { tipo: 'positivo', titulo: `Ganhou ${g.toLocaleString('pt-BR', { maximumFractionDigits: 1 })} kg desde a última pesagem`, texto: 'O animal está evoluindo bem.' };
    if (g < -0.1) return { tipo: 'negativo', titulo: `Perdeu ${Math.abs(g).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} kg desde a última pesagem`, texto: 'Atenção: perda de peso pode indicar problema de saúde ou alimentação. Verifique o animal.' };
    return { tipo: 'estavel', titulo: 'Peso estável desde a última pesagem', texto: 'Variação mínima — pesagens mais espaçadas ajudam a avaliar melhor.' };
});

function reiniciar() {
    selecionado.value = null;
    busca.value = '';
    animalRegistrado.value = null;
    pesoRegistrado.value = null;
    form.reset();
    form.tipo = 'pesagem';
    form.data_evento = hojeBR();
    passo.value = 1;
}
</script>

<template>
    <Head title="Registrar peso do animal" />
    <AdminLayout>
        <template #page-title>Assistente · Registrar peso</template>

        <PageHeader
            title="Registrar peso do animal"
            subtitle="Vamos anotar o peso passo a passo — não precisa saber onde clicar."
        >
            <template #actions>
                <Link :href="route('admin.inicio')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- PASSO 1 · O animal/lote -->
        <div v-if="passo === 1" class="card max-w-5xl mx-auto">
            <div class="card-body space-y-5">
                <!-- Tabs de modo (auditoria 2026-04-27) -->
                <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-3">
                    <button type="button" @click="modo = 'individual'"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition"
                            :class="modo === 'individual' ? 'bg-macaybas-primary-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                        🐄 Animal individual
                    </button>
                    <button type="button" @click="modo = 'amostragem'"
                            :disabled="lotesAgregados.length === 0"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
                            :class="modo === 'amostragem' ? 'bg-macaybas-primary-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                        🐔 Pesagem amostral (lote)
                    </button>
                    <button type="button" @click="modo = 'biomassa'"
                            :disabled="lotesAgregados.length === 0"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
                            :class="modo === 'biomassa' ? 'bg-macaybas-primary-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                        🐟 Biomassa (despesca)
                    </button>
                </div>

                <!-- ── MODO INDIVIDUAL ── -->
                <template v-if="modo === 'individual'">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Qual animal você quer pesar?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        A lista mostra só os animais que estão na fazenda. Toque em um animal para escolher.
                    </p>
                    <p v-if="animais.length === 0" class="text-sm text-amber-700 mt-2">
                        Nenhum animal disponível. Cadastre um animal antes de registrar peso.
                    </p>
                </div>

                <div v-if="animais.length > 6" class="relative">
                    <input v-model="busca" placeholder="Buscar por brinco, nome ou raça…"
                           class="form-input pl-10 text-base py-3">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                <div v-if="animaisFiltrados.length === 0" class="text-center text-slate-500 py-12 text-base">
                    <div v-if="animais.length === 0">Ainda não há animais cadastrados.</div>
                    <div v-else>Nenhum animal encontrado com "{{ busca }}".</div>
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 max-h-[55vh] overflow-y-auto pr-1">
                    <button v-for="a in animaisFiltrados" :key="a.id" type="button"
                            @click="selecionado = a"
                            data-cy="card-animal"
                            :data-animal-id="a.id"
                            class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary hover:shadow-md"
                            :class="selecionado?.id === a.id ? 'border-macaybas-primary bg-emerald-50 shadow-md' : 'border-slate-200 bg-white'">
                        <div class="flex items-start gap-3">
                            <img v-if="a.photo_url" :src="a.photo_url" class="h-14 w-14 rounded-lg object-cover flex-shrink-0">
                            <div v-else class="h-14 w-14 rounded-lg bg-slate-100 flex items-center justify-center text-3xl flex-shrink-0">
                                {{ emojiEspecie(a.species?.nome) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-base font-bold text-slate-900 truncate">{{ a.identificacao }}</div>
                                <div v-if="a.nome" class="text-sm text-slate-700 truncate">{{ a.nome }}</div>
                                <div class="text-sm text-slate-500 mt-1">
                                    {{ a.species?.nome ?? '—' }}<span v-if="a.breed?.nome"> · {{ a.breed.nome }}</span>
                                </div>
                                <div v-if="a.data_nascimento" class="text-sm text-slate-500">
                                    {{ idadeHumana(a.data_nascimento) }}
                                </div>
                                <div class="text-sm mt-1 font-medium" :class="a.peso_atual ? 'text-slate-700' : 'text-slate-400'">
                                    ⚖ {{ pesoAtualHumano(a.peso_atual) }}
                                </div>
                            </div>
                        </div>
                    </button>
                </div>
                </template>

                <!-- ── MODO AMOSTRAGEM ou BIOMASSA: escolhe o LOTE ── -->
                <template v-if="modo === 'amostragem' || modo === 'biomassa'">
                    <div>
                        <h2 class="text-2xl font-semibold text-slate-900">
                            {{ modo === 'amostragem' ? 'Qual lote você está amostrando?' : 'Qual lote da despesca?' }}
                        </h2>
                        <p class="text-base text-slate-600 mt-2">
                            <span v-if="modo === 'amostragem'">
                                Pesa um grupo pequeno (10-30 animais) e o sistema calcula o peso médio do lote inteiro.
                                Útil para aves de corte, postura e suínos em recria.
                            </span>
                            <span v-else>
                                Pesa o total da despesca (kg) com a quantidade de animais e o sistema calcula
                                o peso médio. Útil para piscicultura.
                            </span>
                        </p>
                        <p v-if="lotesAgregados.length === 0" class="text-sm text-amber-700 mt-2">
                            Não há lotes agregados (aves/peixes) cadastrados ainda.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <button v-for="l in lotesAgregados" :key="l.id" type="button"
                                @click="loteSelecionado = l"
                                class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary hover:shadow-md"
                                :class="loteSelecionado?.id === l.id ? 'border-macaybas-primary bg-emerald-50 shadow-md' : 'border-slate-200 bg-white'">
                            <div class="font-bold text-slate-900">{{ l.nome }}</div>
                            <div class="text-xs text-slate-500 font-mono mt-0.5">{{ l.codigo }}</div>
                            <div class="text-sm text-slate-700 mt-2">
                                Efetivo atual: <strong>{{ Number(l.quantidade_atual).toLocaleString('pt-BR') }}</strong>
                            </div>
                            <div v-if="l.peso_medio_kg" class="text-sm text-slate-700">
                                Peso médio: <strong>{{ Number(l.peso_medio_kg).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }} kg</strong>
                            </div>
                            <div v-else class="text-xs text-slate-400 mt-1">Sem peso médio registrado</div>
                        </button>
                    </div>
                </template>

                <div class="flex justify-end pt-4">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2 · O peso (depende do modo) -->
        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">

                <!-- ── INDIVIDUAL ── -->
                <template v-if="modo === 'individual'">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Qual o peso do {{ selecionado.identificacao }}?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Peso atual registrado no sistema: <strong>{{ pesoAtualHumano(selecionado.peso_atual) }}</strong>.
                    </p>
                </div>

                <div>
                    <InputLabel value="Peso em quilos" />
                    <input v-model="form.peso" type="text" inputmode="decimal"
                           placeholder="Ex: 450,5"
                           class="form-input text-2xl py-4 font-mono w-full"
                           @input="form.peso = form.peso.replace(/[^0-9.,]/g, '')">
                    <p class="text-xs text-slate-500 mt-1">Pode usar vírgula (450,5) ou ponto (450.5).</p>
                    <p v-if="form.errors.peso" class="text-sm text-red-700 mt-1">{{ form.errors.peso }}</p>
                </div>
                </template>

                <!-- ── AMOSTRAGEM ── -->
                <template v-if="modo === 'amostragem'">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Pesagem amostral · {{ loteSelecionado.nome }}</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Informe quantos animais você pesou e o peso TOTAL dessa amostra. O sistema calcula o peso médio.
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Quantos animais foram pesados?" />
                        <input v-model="amostraQtd" type="number" min="1" :max="loteSelecionado.quantidade_atual"
                               class="form-input text-xl py-3 font-mono"
                               placeholder="Ex.: 30">
                        <p class="text-xs text-slate-500 mt-1">Lote tem {{ Number(loteSelecionado.quantidade_atual).toLocaleString('pt-BR') }} no total.</p>
                    </div>
                    <div>
                        <InputLabel value="Peso total da amostra (kg)" />
                        <input v-model="amostraPesoTotal" type="text" inputmode="decimal"
                               class="form-input text-xl py-3 font-mono"
                               placeholder="Ex.: 75,5"
                               @input="amostraPesoTotal = String(amostraPesoTotal).replace(/[^0-9.,]/g, '')">
                        <p class="text-xs text-slate-500 mt-1">Soma dos pesos dos animais pesados.</p>
                    </div>
                </div>

                <div v-if="pesoMedioCalculado" class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 p-4 text-center">
                    <div class="text-xs text-emerald-700 uppercase tracking-wider">Peso médio calculado</div>
                    <div class="text-3xl font-bold text-emerald-900 font-mono mt-1">
                        {{ pesoMedioCalculado.toLocaleString('pt-BR', { minimumFractionDigits: 3 }) }} kg
                    </div>
                    <div class="text-xs text-emerald-700 mt-1">por animal — atualiza o peso médio do lote</div>
                </div>
                </template>

                <!-- ── BIOMASSA ── -->
                <template v-if="modo === 'biomassa'">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Biomassa · {{ loteSelecionado.nome }}</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Informe o peso total da despesca e quantos animais participaram. Comum em piscicultura.
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Peso total (kg)" />
                        <input v-model="biomassaPesoTotal" type="text" inputmode="decimal"
                               class="form-input text-2xl py-4 font-mono"
                               placeholder="Ex.: 1250,5"
                               @input="biomassaPesoTotal = String(biomassaPesoTotal).replace(/[^0-9.,]/g, '')">
                        <p class="text-xs text-slate-500 mt-1">kg total na balança/caixa.</p>
                    </div>
                    <div>
                        <InputLabel value="Quantidade de animais" />
                        <input v-model="biomassaQtd" type="number" min="1" :max="loteSelecionado.quantidade_atual"
                               class="form-input text-2xl py-4 font-mono"
                               :placeholder="String(loteSelecionado.quantidade_atual)">
                        <p class="text-xs text-slate-500 mt-1">Total no lote: {{ Number(loteSelecionado.quantidade_atual).toLocaleString('pt-BR') }}</p>
                    </div>
                </div>

                <div v-if="pesoMedioCalculado" class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 p-4 text-center">
                    <div class="text-xs text-emerald-700 uppercase tracking-wider">Peso médio calculado</div>
                    <div class="text-3xl font-bold text-emerald-900 font-mono mt-1">
                        {{ pesoMedioCalculado.toLocaleString('pt-BR', { minimumFractionDigits: 3 }) }} kg
                    </div>
                    <div class="text-xs text-emerald-700 mt-1">por animal · atualiza peso médio do lote</div>
                </div>
                </template>

                <div>
                    <InputLabel value="Quando você pesou?" />
                    <InputDate v-model="form.data_evento" :max="hojeBR()" />
                    <p v-if="form.errors.data" class="text-sm text-red-700 mt-1">{{ form.errors.data }}</p>
                </div>

                <div>
                    <InputLabel value="Observação (opcional)" />
                    <textarea v-model="form.observacoes" rows="2"
                              placeholder="Ex: Pesagem mensal do lote de engorda"
                              class="form-input text-base"></textarea>
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar2" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 3 · Conferência -->
        <div v-if="passo === 3" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Confere os dados?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Antes de salvar, dê uma olhada. Se precisar mudar algo, clique em <strong>Trocar</strong> ao lado da informação.
                    </p>
                </div>

                <div class="space-y-3">
                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-slate-500">Animal</div>
                            <div class="font-semibold text-slate-900 mt-1">{{ selecionado.identificacao }}<span v-if="selecionado.nome" class="text-slate-600"> · {{ selecionado.nome }}</span></div>
                            <div class="text-sm text-slate-500">{{ selecionado.species?.nome }}<span v-if="selecionado.breed?.nome"> · {{ selecionado.breed.nome }}</span></div>
                        </div>
                        <button @click="irPara(1)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>

                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-slate-500">Peso novo</div>
                            <div class="font-semibold text-slate-900 mt-1 text-2xl font-mono">
                                {{ Number(form.peso).toLocaleString('pt-BR', { minimumFractionDigits: 1 }) }} kg
                            </div>
                            <div class="text-sm text-slate-500">Em {{ dataBR(form.data_evento) }}</div>
                        </div>
                        <button @click="irPara(2)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>

                    <div v-if="form.observacoes" class="p-4 rounded-lg border border-slate-200 bg-white">
                        <div class="text-xs uppercase tracking-wider text-slate-500">Observação</div>
                        <div class="text-slate-700 mt-1">{{ form.observacoes }}</div>
                    </div>
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="form.processing" class="btn-primary px-8 py-3 text-base">
                        {{ form.processing ? 'Salvando…' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO 4 · Sucesso
             Não é só "registrado" — mostra o que mudou: peso anterior → novo,
             variação e interpretação humana. Dados vêm do snapshot gravado
             antes do submit (pesoAnterior) + pesoRegistrado. -->
        <div v-if="passo === 4" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
            <div class="card-body text-center space-y-5 py-8">
                <div class="text-6xl" aria-hidden="true">⚖️</div>
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Peso registrado com sucesso!</h2>
                    <p class="text-base text-slate-600 mt-2">
                        <strong>{{ animalRegistrado.identificacao }}</strong> agora pesa
                        <strong class="text-macaybas-primary">{{ pesoRegistrado.toLocaleString('pt-BR', { minimumFractionDigits: 1 }) }} kg</strong>.
                    </p>
                </div>

                <!-- Evolução: comparação com última pesagem + interpretação contextual -->
                <div
                    class="rounded-lg p-4 text-left border-2"
                    :class="{
                        'bg-emerald-50 border-emerald-300': interpretacaoGanho.tipo === 'positivo',
                        'bg-red-50 border-red-300': interpretacaoGanho.tipo === 'negativo',
                        'bg-slate-50 border-slate-200': interpretacaoGanho.tipo === 'estavel' || interpretacaoGanho.tipo === 'primeira',
                    }"
                >
                    <div class="flex items-start gap-3">
                        <div class="text-3xl leading-none flex-shrink-0" aria-hidden="true">
                            {{ interpretacaoGanho.tipo === 'positivo' ? '📈'
                              : interpretacaoGanho.tipo === 'negativo' ? '📉'
                              : interpretacaoGanho.tipo === 'primeira' ? '🌱'
                              : '➖' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div
                                class="font-semibold"
                                :class="{
                                    'text-emerald-900': interpretacaoGanho.tipo === 'positivo',
                                    'text-red-900': interpretacaoGanho.tipo === 'negativo',
                                    'text-slate-800': interpretacaoGanho.tipo === 'estavel' || interpretacaoGanho.tipo === 'primeira',
                                }"
                            >
                                {{ interpretacaoGanho.titulo }}
                            </div>
                            <div v-if="pesoAnterior !== null" class="text-sm text-slate-600 mt-1">
                                Peso anterior: <strong>{{ pesoAnterior.toLocaleString('pt-BR', { minimumFractionDigits: 1 }) }} kg</strong>
                                → agora: <strong>{{ pesoRegistrado.toLocaleString('pt-BR', { minimumFractionDigits: 1 }) }} kg</strong>
                            </div>
                            <p class="text-sm mt-2"
                               :class="{
                                    'text-emerald-800': interpretacaoGanho.tipo === 'positivo',
                                    'text-red-800': interpretacaoGanho.tipo === 'negativo',
                                    'text-slate-600': interpretacaoGanho.tipo === 'estavel' || interpretacaoGanho.tipo === 'primeira',
                               }"
                            >
                                {{ interpretacaoGanho.texto }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-left">
                    <div class="text-sm font-semibold text-slate-800 mb-2">O que foi feito:</div>
                    <ul class="text-sm text-slate-700 space-y-1">
                        <li>✓ Pesagem salva no histórico do animal</li>
                        <li>✓ Peso atual do cadastro atualizado</li>
                        <li v-if="pesoAnterior !== null">✓ Ganho/perda calculado automaticamente</li>
                    </ul>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-3">
                    <button @click="reiniciar" class="btn-primary flex-1 py-3">
                        Pesar outro animal
                    </button>
                    <Link :href="route('admin.rebanho.animais.show', animalRegistrado.id)" class="btn-outline flex-1 py-3 text-center">
                        Ver ficha do animal
                    </Link>
                    <Link :href="route('admin.inicio')" class="btn-outline flex-1 py-3 text-center">
                        Voltar ao início
                    </Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
