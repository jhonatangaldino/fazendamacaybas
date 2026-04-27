<script setup>
/**
 * Assistente guiado — Aplicar produto na plantação.
 *
 * 4 passos:
 *   1 · O que você vai fazer?   (finalidade: adubar / passar veneno / etc)
 *   2 · Onde e o quê?           (talhão + produto + dose)
 *   3 · Confere?                (resumo)
 *   4 · Pronto!                 (sucesso)
 */
import { ref, computed, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import { hojeBR, dataBR } from '@/utils/format.js';

const props = defineProps({
    talhoes: { type: Array, required: true },
    plantios: { type: Array, required: true },
    produtosEstoque: { type: Array, required: true },
});

const PASSOS = [
    { n: 1, titulo: 'Finalidade',  icon: '🌿' },
    { n: 2, titulo: 'Onde e o quê', icon: '🚜' },
    { n: 3, titulo: 'Conferência', icon: '📋' },
    { n: 4, titulo: 'Pronto!',     icon: '✅' },
];

// Finalidades — pensadas em fazendês, mapeadas pro tipo técnico aceito
const FINALIDADES = [
    { id: 'adubacao',   nome: 'Adubar',       desc: 'Aplicar adubo (NPK, ureia, fosfato)', emoji: '🌱' },
    { id: 'herbicida',  nome: 'Matar mato',   desc: 'Aplicar herbicida (capim, folha larga)', emoji: '🌿' },
    { id: 'fungicida',  nome: 'Combater fungo', desc: 'Aplicar fungicida (ferrugem, mofo)',  emoji: '🧫' },
    { id: 'inseticida', nome: 'Matar praga',  desc: 'Aplicar inseticida (lagarta, percevejo)', emoji: '🐛' },
    { id: 'calagem',    nome: 'Corrigir solo', desc: 'Aplicar calcário',                    emoji: '🪨' },
    { id: 'irrigacao',  nome: 'Irrigar',      desc: 'Molhar a roça',                       emoji: '💧' },
    { id: 'outros',     nome: 'Outra coisa',  desc: 'Aplicação diferente',                 emoji: '📦' },
];

const passo = ref(1);
const sucesso = ref(null);

// A2 — multi-talhão: form usa `field_ids[]` (array). Backend distribui
// quantidade proporcional à área de cada talhão.
const form = useForm({
    tipo: 'adubacao',
    field_ids: [],
    planting_id: null,
    produto: '',
    quantidade: '',
    unidade: 'kg',
    data_aplicacao: hojeBR(),
    valor_total: '',
    responsavel: '',
    observacoes: '',
});

function toggleField(id) {
    const idx = form.field_ids.indexOf(id);
    if (idx === -1) form.field_ids.push(id);
    else form.field_ids.splice(idx, 1);
    // Quando troca os talhões, plantio do antigo pode não fazer sentido — limpa.
    form.planting_id = null;
}

onMounted(() => {
    const qs = new URLSearchParams(window.location.search);
    const tipo = qs.get('tipo');
    if (tipo && FINALIDADES.some(f => f.id === tipo)) form.tipo = tipo;
});

const finalidadeAtual = computed(() => FINALIDADES.find(f => f.id === form.tipo));

const talhoesSelecionados = computed(() =>
    props.talhoes.filter(t => form.field_ids.includes(t.id)));
const areaTotalSelecionada = computed(() =>
    talhoesSelecionados.value.reduce((acc, t) => acc + parseFloat(t.area_ha || 0), 0));

const plantiosDosTalhoes = computed(() => {
    if (form.field_ids.length === 0) return [];
    return props.plantios.filter(p => form.field_ids.includes(p.field_id));
});

const distribuicaoPorTalhao = computed(() => {
    const qtdTotal = parseFloat(String(form.quantidade).replace(',', '.')) || 0;
    const areaTot = areaTotalSelecionada.value;
    if (qtdTotal <= 0 || areaTot <= 0) return [];
    return talhoesSelecionados.value.map(t => {
        const proporcao = parseFloat(t.area_ha || 0) / areaTot;
        return {
            id: t.id,
            nome: t.nome,
            area_ha: parseFloat(t.area_ha || 0),
            quantidade: qtdTotal * proporcao,
        };
    });
});

const podeAvancar1 = computed(() => !!form.tipo);
const podeAvancar2 = computed(() => {
    const q = parseFloat(String(form.quantidade).replace(',', '.'));
    return form.field_ids.length > 0 && !!form.produto && !isNaN(q) && q > 0 && !!form.data_aplicacao;
});

function avancar() { if (passo.value < PASSOS.length) passo.value++; }
function voltar() { if (passo.value > 1) passo.value--; }
function irPara(n) { passo.value = n; }

function confirmar() {
    form.quantidade = parseFloat(String(form.quantidade).replace(',', '.'));
    if (form.valor_total) {
        form.valor_total = parseFloat(String(form.valor_total).replace(',', '.'));
    }

    const qtdSnap = form.quantidade;
    const totalTalhoes = form.field_ids.length;
    const nomesTalhoes = talhoesSelecionados.value.map(t => t.nome);

    form.post(route('admin.agricola.aplicacoes.store'), {
        preserveScroll: false,
        onSuccess: () => {
            sucesso.value = {
                finalidade: finalidadeAtual.value.nome,
                totalTalhoes,
                nomesTalhoes,
                produto: form.produto,
                quantidade: qtdSnap,
                unidade: form.unidade,
                areaTotal: areaTotalSelecionada.value,
            };
            passo.value = 4;
        },
    });
}

function reiniciar() {
    form.reset();
    form.tipo = 'adubacao';
    form.field_ids = [];
    form.unidade = 'kg';
    form.data_aplicacao = hojeBR();
    sucesso.value = null;
    passo.value = 1;
}
</script>

<template>
    <Head title="Aplicar produto na plantação" />
    <AdminLayout>
        <template #page-title>Assistente · Aplicação</template>

        <PageHeader
            title="Aplicar produto na plantação"
            subtitle="Vamos registrar a aplicação passo a passo."
        >
            <template #actions>
                <Link :href="route('admin.inicio')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <div v-if="talhoes.length === 0" class="max-w-2xl mx-auto card border-amber-300 bg-amber-50 p-4 mb-4">
            <div class="font-semibold text-amber-900">Você precisa cadastrar um talhão primeiro</div>
            <Link :href="route('admin.agricola.talhoes.index')" class="btn-outline mt-3">Cadastrar talhão</Link>
        </div>

        <template v-else>
        <!-- PASSO 1 · Finalidade -->
        <div v-if="passo === 1" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">O que você vai fazer na plantação?</h2>
                    <p class="text-base text-slate-600 mt-2">Escolha o tipo de aplicação tocando em uma das opções.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <button v-for="f in FINALIDADES" :key="f.id" type="button"
                            @click="form.tipo = f.id"
                            data-cy="card-finalidade"
                            :data-finalidade="f.id"
                            class="rounded-xl border-2 p-4 text-left transition-all hover:border-macaybas-primary"
                            :class="form.tipo === f.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                        <div class="flex items-start gap-3">
                            <span class="text-3xl flex-shrink-0" aria-hidden="true">{{ f.emoji }}</span>
                            <div>
                                <div class="font-semibold text-slate-900">{{ f.nome }}</div>
                                <div class="text-sm text-slate-500 mt-0.5">{{ f.desc }}</div>
                            </div>
                        </div>
                    </button>
                </div>

                <div class="flex justify-end pt-4">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2 · Onde e o quê -->
        <div v-if="passo === 2" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">
                    Onde e com o quê você vai {{ finalidadeAtual?.nome?.toLowerCase() }}?
                </h2>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <InputLabel value="Em qual(is) talhão(ões)?" class="!mb-0" />
                        <span class="text-xs text-slate-500">Toque para selecionar — pode marcar vários</span>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-2">
                        <button v-for="t in talhoes" :key="t.id" type="button"
                                @click="toggleField(t.id)"
                                :data-cy="`card-talhao-${t.id}`"
                                class="rounded-lg border-2 p-3 text-left transition-all"
                                :class="form.field_ids.includes(t.id)
                                    ? 'border-emerald-500 bg-emerald-50'
                                    : 'border-slate-200 bg-white hover:border-emerald-300'">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" :checked="form.field_ids.includes(t.id)"
                                       class="w-4 h-4 accent-emerald-600 pointer-events-none" tabindex="-1">
                                <div>
                                    <div class="font-semibold text-sm">{{ t.nome }}</div>
                                    <div class="text-xs text-slate-500">{{ t.area_ha }} ha</div>
                                </div>
                            </div>
                        </button>
                    </div>
                    <div v-if="form.field_ids.length > 1"
                         class="mt-2 text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md px-3 py-2">
                        <strong>{{ form.field_ids.length }} talhões</strong> · {{ areaTotalSelecionada.toFixed(2) }} ha no total —
                        a quantidade do produto será dividida proporcional à área de cada um.
                    </div>
                </div>

                <div v-if="plantiosDosTalhoes.length > 0">
                    <InputLabel value="Qual plantação (opcional)?" />
                    <select v-model="form.planting_id" class="form-select text-base py-3">
                        <option :value="null">— Aplicar no talhão inteiro —</option>
                        <option v-for="p in plantiosDosTalhoes" :key="p.id" :value="p.id">
                            {{ p.crop?.nome }} — plantado em {{ dataBR(p.data_plantio) }}
                        </option>
                    </select>
                </div>

                <div>
                    <InputLabel value="Nome do produto" />
                    <input v-model="form.produto" type="text" maxlength="200"
                           list="produtos-estoque"
                           data-cy="input-produto"
                           placeholder="Ex: Ureia, Glifosato, Calcário dolomítico…"
                           class="form-input text-lg py-3">
                    <datalist id="produtos-estoque">
                        <option v-for="p in produtosEstoque" :key="p.id" :value="p.nome" />
                    </datalist>
                    <p class="text-xs text-slate-500 mt-1">
                        Se esse produto já estiver no estoque, ele dá baixa sozinho.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel :value="form.field_ids.length > 1 ? 'Quantidade total' : 'Quantidade'" />
                        <input v-model="form.quantidade" type="number" step="0.1" min="0" inputmode="decimal"
                               data-cy="input-quantidade"
                               placeholder="Ex: 100"
                               class="form-input text-xl py-3 font-mono">
                    </div>
                    <div>
                        <InputLabel value="Unidade" />
                        <select v-model="form.unidade" class="form-select text-base py-3">
                            <option value="kg">kg (quilos)</option>
                            <option value="g">g (gramas)</option>
                            <option value="L">L (litros)</option>
                            <option value="mL">mL (mililitros)</option>
                            <option value="t">t (toneladas)</option>
                            <option value="saca">saca</option>
                            <option value="ha">ha (hectares)</option>
                        </select>
                    </div>
                </div>

                <!-- A2 — preview da distribuição quando há mais de 1 talhão -->
                <div v-if="form.field_ids.length > 1 && distribuicaoPorTalhao.length > 0"
                     class="rounded-lg border border-emerald-200 bg-emerald-50/40 p-3">
                    <div class="text-xs uppercase tracking-wider text-emerald-800 font-semibold mb-2">
                        Distribuição prevista
                    </div>
                    <ul class="text-sm space-y-1">
                        <li v-for="d in distribuicaoPorTalhao" :key="d.id"
                            class="flex justify-between gap-2 text-slate-700">
                            <span class="truncate">{{ d.nome }} ({{ d.area_ha.toFixed(2) }} ha)</span>
                            <span class="font-mono font-semibold text-emerald-800">
                                {{ d.quantidade.toFixed(2) }} {{ form.unidade }}
                            </span>
                        </li>
                    </ul>
                </div>

                <div>
                    <InputLabel value="Quando você aplicou?" />
                    <InputDate v-model="form.data_aplicacao" :max="hojeBR()" />
                </div>

                <div>
                    <InputLabel value="Observação (opcional)" />
                    <textarea v-model="form.observacoes" rows="2"
                              placeholder="Ex: Vento forte no início, aplicação concluída às 16h"
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
                        Antes de salvar, dê uma olhada. Se precisar mudar algo, clique em <strong>Trocar</strong> ao lado.
                    </p>
                </div>

                <div class="space-y-3">
                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-slate-500">Finalidade</div>
                            <div class="font-semibold text-slate-900 mt-1">{{ finalidadeAtual?.emoji }} {{ finalidadeAtual?.nome }}</div>
                        </div>
                        <button @click="irPara(1)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>

                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs uppercase tracking-wider text-slate-500">Onde e o quê</div>
                            <div v-if="talhoesSelecionados.length === 1" class="font-semibold text-slate-900 mt-1">
                                {{ talhoesSelecionados[0].nome }}
                            </div>
                            <div v-else class="mt-1">
                                <div class="font-semibold text-slate-900">
                                    {{ talhoesSelecionados.length }} talhões ({{ areaTotalSelecionada.toFixed(2) }} ha)
                                </div>
                                <ul class="text-xs text-slate-600 mt-1 space-y-0.5">
                                    <li v-for="d in distribuicaoPorTalhao" :key="d.id">
                                        • {{ d.nome }}: {{ d.quantidade.toFixed(2) }} {{ form.unidade }}
                                    </li>
                                </ul>
                            </div>
                            <div class="text-sm text-slate-600 mt-1">
                                {{ form.produto }} · <strong>{{ form.quantidade }} {{ form.unidade }}</strong>
                                <span v-if="talhoesSelecionados.length > 1" class="text-xs text-slate-500"> (total)</span>
                            </div>
                            <div class="text-sm text-slate-500 mt-0.5">Em {{ dataBR(form.data_aplicacao) }}</div>
                        </div>
                        <button @click="irPara(2)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="form.processing" data-cy="confirmar" class="btn-primary px-8 py-3 text-base">
                        {{ form.processing ? 'Salvando…' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO 4 · Sucesso -->
        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
            <div class="card-body text-center space-y-5 py-8">
                <div class="text-6xl" aria-hidden="true">🌾</div>
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Aplicação registrada!</h2>
                    <p class="text-base text-slate-600 mt-2">
                        <strong>{{ sucesso.finalidade }}</strong>
                        <span v-if="sucesso.totalTalhoes === 1">
                            em <strong>{{ sucesso.nomesTalhoes[0] }}</strong>
                        </span>
                        <span v-else>
                            em <strong>{{ sucesso.totalTalhoes }} talhões</strong>
                            ({{ sucesso.areaTotal.toFixed(2) }} ha)
                        </span>
                        <br>
                        {{ sucesso.produto }} · {{ sucesso.quantidade }} {{ sucesso.unidade }}
                        <span v-if="sucesso.totalTalhoes > 1" class="text-xs text-slate-500">(total)</span>
                    </p>
                </div>

                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-left">
                    <div class="text-sm font-semibold text-emerald-900 mb-2">O que vai acontecer:</div>
                    <ul class="text-sm text-emerald-800 space-y-1">
                        <li v-if="sucesso.totalTalhoes > 1">
                            ✓ {{ sucesso.totalTalhoes }} aplicações criadas — uma por talhão, proporcional à área
                        </li>
                        <li v-else>✓ Aplicação entrou no histórico do talhão</li>
                        <li>✓ Se o produto estava no estoque, foi dado baixa automaticamente</li>
                        <li>✓ Carência e rastreabilidade ficam registradas</li>
                    </ul>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-3">
                    <button @click="reiniciar" class="btn-primary flex-1 py-3">Nova aplicação</button>
                    <Link :href="route('admin.agricola.aplicacoes.index')" class="btn-outline flex-1 py-3 text-center">Ver todas</Link>
                    <Link :href="route('admin.inicio')" class="btn-outline flex-1 py-3 text-center">Voltar ao início</Link>
                </div>
            </div>
        </div>
        </template>
    </AdminLayout>
</template>
