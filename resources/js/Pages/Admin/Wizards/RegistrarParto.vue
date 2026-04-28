<script setup>
/**
 * Wizard "Registrar parto" — disparado ao concluir tarefa com auto_action='parto'.
 *
 * Fluxo mobile-first:
 *   1. Resumo da mãe + escolhe data do parto + escolhe pai (touro)
 *   2. Define quantidade de filhotes (auto-suggested por espécie)
 *   3. Lista de filhotes com ID sequencial editável + sexo + peso + status
 *   4. Escolhe lote destino (mesmo da mãe / novo lote / existente)
 *   5. Confirma → backend cria animals + tarefa de desmame + fecha tarefa de parto
 */
import { ref, computed, watch } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    task: Object,
    mae: Object,
    config: Object,
    machos: Array,
    lotes: Array,
    proximos_ids: Array,
    data_hoje: String,
});

const passo = ref(1);

const form = useForm({
    task_id: props.task.id,
    mae_id: props.mae.id,
    data_parto: props.data_hoje,
    pai_id: '',
    lote_destino: 'mesmo_da_mae',
    novo_lote_nome: `Leitegada ${props.mae.nome || props.mae.identificacao} ${new Date().toLocaleDateString('pt-BR')}`,
    lote_existente_id: '',
    quantidade: props.config.quantidade_default,
    filhotes: [],
});

// Inicializa filhotes a partir da quantidade
function inicializarFilhotes(qtd) {
    const novos = [];
    for (let i = 0; i < qtd; i++) {
        novos.push({
            identificacao: props.proximos_ids[i] || `${props.mae.identificacao}/F${String(i + 1).padStart(2, '0')}`,
            sexo: 'F',
            peso_nascimento: '',
            status: 'vivo',
            observacao: '',
        });
    }
    form.filhotes = novos;
}
inicializarFilhotes(form.quantidade);

watch(() => form.quantidade, (q) => {
    const qtd = Math.max(1, Math.min(30, parseInt(q) || 1));
    inicializarFilhotes(qtd);
});

function preencherTodosIguais() {
    if (form.filhotes.length === 0) return;
    const primeiro = form.filhotes[0];
    for (let i = 1; i < form.filhotes.length; i++) {
        form.filhotes[i].sexo = primeiro.sexo;
        form.filhotes[i].peso_nascimento = primeiro.peso_nascimento;
        form.filhotes[i].status = primeiro.status;
    }
}

const totalVivos = computed(() => form.filhotes.filter(f => f.status === 'vivo').length);
const totalMortos = computed(() => form.filhotes.filter(f => f.status !== 'vivo').length);

// Resolução do lote_destino que vai pro backend
const loteDestinoFinal = computed(() => {
    if (form.lote_destino === 'mesmo_da_mae') return 'mesmo_da_mae';
    if (form.lote_destino === 'novo') return 'novo:' + (form.novo_lote_nome || '').trim();
    if (form.lote_destino === 'existente' && form.lote_existente_id) return 'existente:' + form.lote_existente_id;
    return 'mesmo_da_mae';
});

function avancar() {
    if (passo.value < 3) passo.value++;
}
function voltar() {
    if (passo.value > 1) passo.value--;
}

function submit() {
    form.transform(d => ({
        ...d,
        lote_destino: loteDestinoFinal.value,
    }));
    form.post(route('admin.fluxos.registrar-parto.store'));
}

const podeAvancarPasso1 = computed(() => form.data_parto && form.quantidade > 0);
const podeSubmeter = computed(() => form.filhotes.length > 0
    && form.filhotes.every(f => f.identificacao && f.sexo && f.status));

const statusLabels = {
    vivo: { label: 'Vivo', icon: '✓', cor: 'bg-emerald-100 text-emerald-900 ring-emerald-300' },
    natimorto: { label: 'Natimorto', icon: '✕', cor: 'bg-rose-100 text-rose-900 ring-rose-300' },
    morto_pos_parto: { label: 'Morreu após parto', icon: '✕', cor: 'bg-rose-100 text-rose-900 ring-rose-300' },
};
</script>

<template>
    <Head title="Registrar parto" />
    <AdminLayout>
        <div class="max-w-3xl mx-auto pb-32">

            <!-- Cabeçalho com mãe -->
            <div class="mb-6 rounded-xl bg-gradient-to-br from-emerald-50 to-amber-50 ring-1 ring-emerald-200 p-5">
                <div class="text-xs uppercase tracking-wider text-emerald-700 font-semibold">Registrando parto da mãe</div>
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 mt-1">
                    🤱 {{ mae.identificacao }}<span v-if="mae.nome" class="text-slate-700 font-normal"> — {{ mae.nome }}</span>
                </h1>
                <div class="mt-2 text-sm text-slate-700">
                    <span>{{ mae.especie }}</span>
                    <span v-if="mae.lote_nome"> · 📋 {{ mae.lote_nome }}</span>
                    <span v-if="mae.location_nome"> · 📍 {{ mae.location_nome }}</span>
                </div>
            </div>

            <!-- Indicador de passos -->
            <div class="mb-6 flex items-center gap-2">
                <div v-for="n in 3" :key="n" class="flex-1 h-2 rounded-full"
                    :class="n <= passo ? 'bg-macaybas-primary' : 'bg-slate-200'"></div>
            </div>

            <!-- ───── PASSO 1: dados do parto ───── -->
            <div v-if="passo === 1" class="rounded-xl bg-white ring-1 ring-slate-200 p-5 space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Passo 1 · Dados do parto</h2>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Data do parto</label>
                    <input
                        v-model="form.data_parto"
                        type="date"
                        class="form-input"
                    >
                    <p class="mt-1 text-xs text-slate-500">Pode ser hoje ou alguns dias atrás (parto fora do horário previsto).</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">
                        Quantos filhote(s) nasceram?
                    </label>
                    <input
                        v-model.number="form.quantidade"
                        type="number"
                        min="1"
                        max="30"
                        inputmode="numeric"
                        class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-2xl font-bold text-center font-mono"
                    >
                    <p v-if="config.is_multi_fetal" class="mt-1 text-xs text-slate-500">
                        💡 {{ mae.especie }} normalmente tem partos múltiplos. Sugerido: <strong>{{ config.quantidade_default }}</strong> filhotes.
                    </p>
                    <p v-else class="mt-1 text-xs text-slate-500">
                        💡 {{ mae.especie }} costuma ter parto único. Em caso de gêmeos, ajuste para 2.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">
                        Pai (touro/varrão) — opcional
                    </label>
                    <select
                        v-model="form.pai_id"
                        class="form-input"
                    >
                        <option value="">— não informado —</option>
                        <option v-for="m in machos" :key="m.id" :value="m.id">
                            {{ m.identificacao }}<span v-if="m.nome"> — {{ m.nome }}</span>
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">
                        Filhotes serão registrados com este pai automaticamente.
                    </p>
                </div>
            </div>

            <!-- ───── PASSO 2: lote destino ───── -->
            <div v-if="passo === 2" class="rounded-xl bg-white ring-1 ring-slate-200 p-5 space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Passo 2 · Lote dos filhotes</h2>
                <p class="text-sm text-slate-600">
                    Em qual lote os filhotes vão ser cadastrados?
                </p>

                <div class="space-y-2">
                    <label class="block rounded-lg ring-2 p-4 cursor-pointer transition"
                        :class="form.lote_destino === 'mesmo_da_mae' ? 'ring-macaybas-primary bg-emerald-50' : 'ring-slate-200 hover:ring-slate-300'">
                        <input type="radio" v-model="form.lote_destino" value="mesmo_da_mae" class="sr-only">
                        <div class="font-semibold text-slate-900">📋 Mesmo lote da mãe</div>
                        <div class="text-xs text-slate-600 mt-0.5">{{ mae.lote_nome || '(mãe sem lote)' }}</div>
                    </label>

                    <label class="block rounded-lg ring-2 p-4 cursor-pointer transition"
                        :class="form.lote_destino === 'novo' ? 'ring-macaybas-primary bg-emerald-50' : 'ring-slate-200 hover:ring-slate-300'">
                        <input type="radio" v-model="form.lote_destino" value="novo" class="sr-only">
                        <div class="font-semibold text-slate-900">➕ Criar novo lote pra leitegada</div>
                        <div class="text-xs text-slate-600 mt-0.5">Recomendado pra ninhadas grandes (suíno, cão, gato)</div>
                        <input
                            v-if="form.lote_destino === 'novo'"
                            v-model="form.novo_lote_nome"
                            type="text"
                            placeholder="Nome do novo lote"
                            class="mt-3 form-input"
                            @click.stop
                        >
                    </label>

                    <label class="block rounded-lg ring-2 p-4 cursor-pointer transition"
                        :class="form.lote_destino === 'existente' ? 'ring-macaybas-primary bg-emerald-50' : 'ring-slate-200 hover:ring-slate-300'">
                        <input type="radio" v-model="form.lote_destino" value="existente" class="sr-only">
                        <div class="font-semibold text-slate-900">🔄 Outro lote já existente</div>
                        <select
                            v-if="form.lote_destino === 'existente'"
                            v-model="form.lote_existente_id"
                            class="mt-3 form-input"
                            @click.stop
                        >
                            <option value="">— escolha um lote —</option>
                            <option v-for="l in lotes" :key="l.id" :value="l.id">{{ l.nome }}</option>
                        </select>
                    </label>
                </div>
            </div>

            <!-- ───── PASSO 3: filhotes ───── -->
            <div v-if="passo === 3" class="space-y-3">
                <div class="rounded-xl bg-white ring-1 ring-slate-200 p-4">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <h2 class="text-lg font-bold text-slate-900">Passo 3 · {{ form.filhotes.length }} filhote(s)</h2>
                        <button
                            v-if="form.filhotes.length > 1"
                            type="button"
                            @click="preencherTodosIguais"
                            class="inline-flex items-center min-h-9 px-3 py-2 text-xs text-macaybas-primary hover:bg-macaybas-primary-50 rounded-md font-medium"
                        >
                            ⚡ Preencher todos como o 1º
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-slate-600">Edite o ID se quiser. Sexo e status são obrigatórios.</p>
                </div>

                <div
                    v-for="(f, idx) in form.filhotes"
                    :key="idx"
                    class="rounded-xl bg-white ring-1 ring-slate-200 p-4"
                    :class="{ 'ring-rose-300 bg-rose-50/30': f.status !== 'vivo' }"
                >
                    <div class="flex items-center justify-between mb-3">
                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Filhote #{{ idx + 1 }}</div>
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1"
                            :class="statusLabels[f.status]?.cor"
                        >
                            {{ statusLabels[f.status]?.icon }} {{ statusLabels[f.status]?.label }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Identificação</label>
                            <input
                                v-model="f.identificacao"
                                type="text"
                                class="form-input font-mono"
                            >
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Sexo</label>
                            <div class="grid grid-cols-2 gap-1">
                                <button type="button" @click="f.sexo = 'F'"
                                    class="py-2 rounded-lg ring-1 text-sm font-medium transition"
                                    :class="f.sexo === 'F' ? 'bg-pink-100 ring-pink-400 text-pink-900' : 'bg-white ring-slate-200 text-slate-600 hover:ring-slate-300'">
                                    ♀ Fêmea
                                </button>
                                <button type="button" @click="f.sexo = 'M'"
                                    class="py-2 rounded-lg ring-1 text-sm font-medium transition"
                                    :class="f.sexo === 'M' ? 'bg-blue-100 ring-blue-400 text-blue-900' : 'bg-white ring-slate-200 text-slate-600 hover:ring-slate-300'">
                                    ♂ Macho
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Peso ao nascer (kg) — opcional</label>
                            <input
                                v-model="f.peso_nascimento"
                                type="number"
                                step="0.1"
                                min="0"
                                inputmode="decimal"
                                placeholder="Ex.: 35"
                                class="form-input font-mono"
                            >
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Status</label>
                            <select v-model="f.status" class="form-input">
                                <option value="vivo">Vivo</option>
                                <option value="natimorto">Natimorto</option>
                                <option value="morto_pos_parto">Morreu após parto</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Resumo -->
                <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 p-4">
                    <div class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">Resumo</div>
                    <div class="mt-2 text-emerald-900">
                        <strong>{{ totalVivos }}</strong> filhote(s) vivo(s)
                        <span v-if="totalMortos > 0" class="text-rose-700"> · <strong>{{ totalMortos }}</strong> morto(s)</span>
                    </div>
                    <div class="mt-1 text-xs text-emerald-700">
                        Será criada tarefa automática de desmame em {{ config.desmame_dias }} dias.
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer fixo: navegação -->
        <div class="fixed bottom-0 left-0 right-0 lg:left-64 bg-white ring-1 ring-slate-200 p-4 z-20">
            <div class="max-w-3xl mx-auto flex items-center gap-3">
                <button
                    v-if="passo === 1"
                    type="button"
                    @click="$inertia.visit(route('admin.tarefas.index'))"
                    class="inline-flex items-center min-h-12 px-4 py-3 rounded-lg bg-slate-100 text-slate-700 font-medium hover:bg-slate-200"
                >
                    ← Cancelar
                </button>
                <button
                    v-else
                    type="button"
                    @click="voltar"
                    class="inline-flex items-center min-h-12 px-4 py-3 rounded-lg bg-slate-100 text-slate-700 font-medium hover:bg-slate-200"
                >
                    ← Voltar
                </button>

                <button
                    v-if="passo < 3"
                    type="button"
                    @click="avancar"
                    :disabled="passo === 1 && ! podeAvancarPasso1"
                    class="flex-1 inline-flex items-center justify-center min-h-12 px-6 py-3 rounded-lg bg-macaybas-primary text-white font-semibold hover:bg-macaybas-primary-700 disabled:opacity-50 text-base"
                >
                    Continuar →
                </button>
                <button
                    v-else
                    type="button"
                    @click="submit"
                    :disabled="! podeSubmeter || form.processing"
                    class="flex-1 inline-flex items-center justify-center min-h-12 px-6 py-3 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 disabled:opacity-50 text-base"
                >
                    <span v-if="form.processing">Salvando…</span>
                    <span v-else>✓ Confirmar parto · cadastrar {{ form.filhotes.length }} filhote(s)</span>
                </button>
            </div>
        </div>
    </AdminLayout>
</template>
