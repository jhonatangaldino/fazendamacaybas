<script setup>
/**
 * Assistente guiado POLIMÓRFICO — 1 wizard serve 6 cards do Hub.
 *
 * Tipos aceitos (?tipo=X):
 *   vacinacao | medicacao | vermifugacao | movimentacao | mortalidade | observacao
 *
 * Estrutura:
 *   Passo 1 · Qual animal?          (grid de cards)
 *   Passo 2 · Detalhes              (campos dinâmicos pelo tipo)
 *   Passo 3 · Conferência           (resumo)
 *   Passo 4 · Pronto!               (sucesso)
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import { hojeBR, dataBR } from '@/utils/format.js';

const props = defineProps({
    tipoInicial: { type: String, required: true },
    animais: { type: Array, required: true },
    lotes: { type: Array, required: true },
});

// Metadados por tipo — tudo que muda entre os 6 cards
const TIPOS = {
    vacinacao: {
        titulo: 'Aplicar vacina no animal',
        emoji: '💉',
        passo2Titulo: 'Qual vacina?',
        sucessoMsg: (d) => `Vacinação registrada para ${d._animal.identificacao}.`,
        impactos: ['Vacinação entrou no histórico do animal', 'Calendário sanitário atualizado'],
        campos: ['vacina', 'dose_opcional', 'data', 'observacoes'],
        camposObrigatorios: ['vacina'],
    },
    medicacao: {
        titulo: 'Aplicar medicamento',
        emoji: '💊',
        passo2Titulo: 'Qual medicamento?',
        sucessoMsg: (d) => `Tratamento registrado para ${d._animal.identificacao}.`,
        impactos: ['Tratamento entrou no histórico do animal', 'Se houver carência, será rastreada'],
        campos: ['medicamento', 'dose_opcional', 'data', 'observacoes'],
        camposObrigatorios: ['medicamento'],
    },
    vermifugacao: {
        titulo: 'Aplicar vermífugo',
        emoji: '🧴',
        passo2Titulo: 'Qual vermífugo?',
        sucessoMsg: (d) => `Vermifugação registrada para ${d._animal.identificacao}.`,
        impactos: ['Vermifugação entrou no histórico', 'Próxima vermifugação será programada'],
        campos: ['medicamento', 'dose_opcional', 'data', 'observacoes'],
        camposObrigatorios: ['medicamento'],
    },
    movimentacao: {
        titulo: 'Mover animal de lote',
        emoji: '🐄',
        passo2Titulo: 'Pra qual lote vai?',
        sucessoMsg: (d) => `${d._animal.identificacao} movido para ${d._loteNome}.`,
        impactos: ['Animal agora pertence ao novo lote', 'Movimentação entrou no histórico'],
        campos: ['lot_destino_id', 'data', 'observacoes'],
        camposObrigatorios: ['lot_destino_id'],
    },
    mortalidade: {
        titulo: 'Registrar morte do animal',
        emoji: '⚰️',
        passo2Titulo: 'Como foi?',
        sucessoMsg: (d) => `Morte de ${d._animal.identificacao} registrada.`,
        impactos: ['Animal saiu do rebanho ativo', 'Baixa por mortalidade no relatório'],
        campos: ['causa', 'data', 'observacoes'],
        camposObrigatorios: [],
    },
    observacao: {
        titulo: 'Registrar observação do animal',
        emoji: '📝',
        passo2Titulo: 'O que aconteceu?',
        sucessoMsg: (d) => `Observação registrada para ${d._animal.identificacao}.`,
        impactos: ['Observação entrou no histórico do animal'],
        campos: ['data', 'observacoes'],
        camposObrigatorios: ['observacoes'],
    },
};

const tipoAtivo = ref(props.tipoInicial);
const meta = computed(() => TIPOS[tipoAtivo.value]);

const PASSOS = computed(() => [
    { n: 1, titulo: 'O animal',     icon: '🐄' },
    { n: 2, titulo: meta.value.passo2Titulo, icon: meta.value.emoji },
    { n: 3, titulo: 'Conferência',  icon: '📋' },
    { n: 4, titulo: 'Pronto!',      icon: '✅' },
]);

const passo = ref(1);
const selecionado = ref(null);
const busca = ref('');
const sucesso = ref(null);

const form = useForm({
    tipo: tipoAtivo.value,
    data_evento: hojeBR(),
    vacina: '',
    medicamento: '',
    dose: '',
    via_aplicacao: '',
    responsavel: '',
    lot_destino_id: null,
    causa: '',       // usado pra mortalidade; o backend lê via observacoes
    observacoes: '',
});

const animaisFiltrados = computed(() => {
    if (!busca.value.trim()) return props.animais;
    const q = busca.value.toLowerCase();
    return props.animais.filter((a) =>
        (a.identificacao || '').toLowerCase().includes(q) ||
        (a.nome || '').toLowerCase().includes(q)
    );
});

const podeAvancar1 = computed(() => !!selecionado.value);
const podeAvancar2 = computed(() => {
    if (!form.data_evento) return false;
    const obrig = meta.value.camposObrigatorios;
    for (const c of obrig) {
        if (!form[c] || (typeof form[c] === 'string' && !form[c].trim())) return false;
    }
    return true;
});

function mostraCampo(nome) {
    return meta.value.campos.includes(nome);
}

function emojiEspecie(nome) {
    const n = (nome || '').toLowerCase();
    if (n.includes('bovino') || n.includes('búfalo')) return '🐄';
    if (n.includes('suíno')) return '🐖';
    if (n.includes('ovino') || n.includes('caprino')) return '🐑';
    if (n.includes('ave') || n.includes('galinha')) return '🐔';
    if (n.includes('equino') || n.includes('cavalo')) return '🐎';
    return '🐾';
}

function avancar() { if (passo.value < PASSOS.value.length) passo.value++; }
function voltar() { if (passo.value > 1) passo.value--; }
function irPara(n) { passo.value = n; }

const loteNome = computed(() => props.lotes.find(l => l.id === form.lot_destino_id)?.nome ?? null);

function confirmar() {
    // Para mortalidade com "causa", anexa na observacoes (backend não tem campo causa dedicado)
    if (tipoAtivo.value === 'mortalidade' && form.causa) {
        form.observacoes = form.causa + (form.observacoes ? ` · ${form.observacoes}` : '');
    }

    form
        .transform((d) => {
            const { data_evento, causa, ...r } = d;
            return { ...r, data: data_evento };
        })
        .post(route('admin.rebanho.animais.eventos.store', selecionado.value.id), {
            preserveScroll: false,
            onSuccess: () => {
                sucesso.value = {
                    _animal: selecionado.value,
                    _loteNome: loteNome.value,
                    tipo: tipoAtivo.value,
                };
                passo.value = 4;
            },
        });
}

function reiniciar() {
    selecionado.value = null;
    busca.value = '';
    sucesso.value = null;
    form.reset();
    form.tipo = tipoAtivo.value;
    form.data_evento = hojeBR();
    passo.value = 1;
}
</script>

<template>
    <Head :title="meta.titulo" />
    <AdminLayout>
        <template #page-title>Assistente · {{ meta.titulo }}</template>

        <PageHeader
            :title="meta.titulo"
            subtitle="Vamos registrar passo a passo — não precisa saber onde clicar."
        >
            <template #actions>
                <Link :href="route('admin.inicio')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- PASSO 1 · O animal -->
        <div v-if="passo === 1" class="card max-w-5xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Qual animal?</h2>
                <p v-if="animais.length === 0" class="text-sm text-amber-700">
                    Nenhum animal disponível. Cadastre um antes.
                </p>

                <div v-if="animais.length > 6" class="relative">
                    <input v-model="busca" placeholder="Buscar por brinco, nome ou raça…"
                           class="form-input pl-10 text-base py-3">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                <div v-if="animaisFiltrados.length === 0" class="text-center text-slate-500 py-12">
                    Nenhum animal encontrado.
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 max-h-[55vh] overflow-y-auto pr-1">
                    <button v-for="a in animaisFiltrados" :key="a.id" type="button"
                            @click="selecionado = a"
                            class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary"
                            :class="selecionado?.id === a.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                        <div class="flex items-start gap-3">
                            <img v-if="a.photo_url" :src="a.photo_url" class="h-14 w-14 rounded-lg object-cover flex-shrink-0">
                            <div v-else class="h-14 w-14 rounded-lg bg-slate-100 flex items-center justify-center text-3xl flex-shrink-0">
                                {{ emojiEspecie(a.species?.nome) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-slate-900 truncate">{{ a.identificacao }}</div>
                                <div v-if="a.nome" class="text-sm text-slate-700 truncate">{{ a.nome }}</div>
                                <div class="text-sm text-slate-500 mt-0.5">
                                    {{ a.species?.nome ?? '—' }}<span v-if="a.lot"> · {{ a.lot.nome }}</span>
                                </div>
                            </div>
                        </div>
                    </button>
                </div>

                <div class="flex justify-end pt-4">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2 · Detalhes (dinâmico por tipo) -->
        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">
                    {{ meta.emoji }} {{ meta.passo2Titulo }}
                </h2>
                <p class="text-base text-slate-600">
                    Animal: <strong>{{ selecionado.identificacao }}</strong><span v-if="selecionado.nome"> · {{ selecionado.nome }}</span>
                </p>

                <!-- Campo: vacina -->
                <div v-if="mostraCampo('vacina')">
                    <InputLabel value="Qual vacina foi aplicada?" />
                    <input v-model="form.vacina" type="text" maxlength="150"
                           placeholder="Ex: Aftosa, Brucelose, Clostridiose"
                           class="form-input text-base py-3">
                </div>

                <!-- Campo: medicamento -->
                <div v-if="mostraCampo('medicamento')">
                    <InputLabel :value="tipoAtivo === 'vermifugacao' ? 'Qual vermífugo?' : 'Qual medicamento?'" />
                    <input v-model="form.medicamento" type="text" maxlength="150"
                           :placeholder="tipoAtivo === 'vermifugacao' ? 'Ex: Ivermectina, Albendazol' : 'Ex: Antibiótico, Anti-inflamatório'"
                           class="form-input text-base py-3">
                </div>

                <!-- Dose (opcional) -->
                <div v-if="mostraCampo('dose_opcional')">
                    <InputLabel value="Dose aplicada (opcional)" />
                    <input v-model="form.dose" type="text" maxlength="50"
                           placeholder="Ex: 5 mL, 1 cápsula, 2 comprimidos"
                           class="form-input text-base py-3">
                </div>

                <!-- Lote destino -->
                <div v-if="mostraCampo('lot_destino_id')">
                    <InputLabel value="Para qual lote vai?" />
                    <select v-model="form.lot_destino_id" class="form-select text-base py-3">
                        <option :value="null">Escolha o lote…</option>
                        <option v-for="l in lotes" :key="l.id" :value="l.id">{{ l.nome }}</option>
                    </select>
                    <p v-if="lotes.length === 0" class="text-sm text-amber-700 mt-1">
                        Nenhum lote cadastrado. Cadastre lotes em Rebanho → Lotes.
                    </p>
                </div>

                <!-- Causa (mortalidade) -->
                <div v-if="mostraCampo('causa')">
                    <InputLabel value="Causa aparente (opcional)" />
                    <input v-model="form.causa" type="text" maxlength="200"
                           placeholder="Ex: Doença, acidente, predador, velhice"
                           class="form-input text-base py-3">
                </div>

                <!-- Data -->
                <div v-if="mostraCampo('data')">
                    <InputLabel value="Quando foi?" />
                    <InputDate v-model="form.data_evento" :max="hojeBR()" />
                </div>

                <!-- Observações -->
                <div v-if="mostraCampo('observacoes')">
                    <InputLabel :value="tipoAtivo === 'observacao' ? 'O que você quer anotar?' : 'Observação (opcional)'" />
                    <textarea v-model="form.observacoes" rows="3"
                              :placeholder="tipoAtivo === 'observacao' ? 'Ex: Cio detectado, cobertura feita, sinal clínico observado' : ''"
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
                <h2 class="text-2xl font-semibold text-slate-900">Confere os dados?</h2>

                <div class="space-y-3">
                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-slate-500">Animal</div>
                            <div class="font-semibold text-slate-900 mt-1">{{ selecionado.identificacao }}<span v-if="selecionado.nome" class="text-slate-600"> · {{ selecionado.nome }}</span></div>
                        </div>
                        <button @click="irPara(1)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>

                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-slate-500">{{ meta.emoji }} Detalhes</div>
                            <div class="font-semibold text-slate-900 mt-1">
                                <span v-if="form.vacina">{{ form.vacina }}</span>
                                <span v-else-if="form.medicamento">{{ form.medicamento }}</span>
                                <span v-else-if="loteNome">Mover para {{ loteNome }}</span>
                                <span v-else-if="form.causa">Causa: {{ form.causa }}</span>
                                <span v-else-if="form.observacoes">{{ form.observacoes }}</span>
                            </div>
                            <div v-if="form.dose" class="text-sm text-slate-600 mt-0.5">Dose: {{ form.dose }}</div>
                            <div v-if="mostraCampo('data')" class="text-sm text-slate-500 mt-0.5">Em {{ dataBR(form.data_evento) }}</div>
                        </div>
                        <button @click="irPara(2)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="form.processing" class="btn-primary px-8 py-3 text-base">
                        {{ form.processing ? 'Salvando…' : 'Confirmar e salvar ✓' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO 4 · Sucesso -->
        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto">
            <div class="card-body text-center space-y-5 py-8">
                <div class="text-6xl">🎉</div>
                <h2 class="text-2xl font-semibold text-slate-900">{{ meta.titulo }} — concluído!</h2>
                <p class="text-base text-slate-600">{{ meta.sucessoMsg(sucesso) }}</p>

                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-left">
                    <div class="text-sm font-semibold text-emerald-900 mb-2">O que vai acontecer:</div>
                    <ul class="text-sm text-emerald-800 space-y-1">
                        <li v-for="(i, idx) in meta.impactos" :key="idx">✓ {{ i }}</li>
                    </ul>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-3">
                    <button @click="reiniciar" class="btn-primary flex-1 py-3">Registrar outro</button>
                    <Link :href="route('admin.rebanho.animais.show', sucesso._animal.id)" class="btn-outline flex-1 py-3 text-center">Ver ficha do animal</Link>
                    <Link :href="route('admin.inicio')" class="btn-outline flex-1 py-3 text-center">Voltar ao início</Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
