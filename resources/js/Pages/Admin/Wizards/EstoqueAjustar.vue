<script setup>
/**
 * Assistente — Ajustar estoque (correção de saldo).
 * 3 passos + sucesso.
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import { hojeBR } from '@/utils/format.js';

const props = defineProps({
    itens: { type: Array, required: true },
    armazens: { type: Array, required: true },
});

const PASSOS = [
    { n: 1, titulo: 'O produto',   icon: '📦' },
    { n: 2, titulo: 'A diferença', icon: '🔢' },
    { n: 3, titulo: 'Conferência', icon: '📋' },
    { n: 4, titulo: 'Pronto!',     icon: '✅' },
];

const MOTIVOS = [
    { id: 'contagem', nome: 'Contagem física não bate' },
    { id: 'perda',    nome: 'Perda (vencimento, avaria)' },
    { id: 'roubo',    nome: 'Roubo ou sumiço' },
    { id: 'outro',    nome: 'Outro motivo' },
];

const passo = ref(1);
const sucesso = ref(null);

const form = useForm({
    item_id: null,
    warehouse_id: props.armazens[0]?.id ?? null,
    quantidade: '',           // pode ser negativa (diferença)
    motivo: 'contagem',
    data: hojeBR(),
    observacoes: '',
});

const itemAtual = computed(() => props.itens.find(i => i.id === form.item_id));
const armazemAtual = computed(() => props.armazens.find(a => a.id === form.warehouse_id));

const podeAvancar1 = computed(() => !!form.item_id && !!form.warehouse_id);
const podeAvancar2 = computed(() => {
    const q = parseFloat(String(form.quantidade).replace(',', '.'));
    return !isNaN(q) && q !== 0 && !!form.motivo;
});

function avancar() { if (passo.value < PASSOS.length) passo.value++; }
function voltar() { if (passo.value > 1) passo.value--; }
function irPara(n) { passo.value = n; }

function confirmar() {
    form.quantidade = parseFloat(String(form.quantidade).replace(',', '.'));

    // Snapshot ANTES do post (porque o redirect pode recriar o componente
    // e computed's derivados do form ficariam vazios)
    const snapshot = {
        item: itemAtual.value?.nome,
        quantidade: form.quantidade,
        unidade: itemAtual.value?.unidade,
    };

    // Avança OTIMISTA para passo 4 antes do post.
    // Se der erro de validação, onError reverte pra conferência.
    sucesso.value = snapshot;
    passo.value = 4;

    form.post(route('admin.fluxos.ajustar-estoque.store'), {
        preserveScroll: false,
        onError: () => {
            sucesso.value = null;
            passo.value = 3;
        },
    });
}

function reiniciar() {
    form.reset();
    form.warehouse_id = props.armazens[0]?.id ?? null;
    form.motivo = 'contagem';
    form.data = hojeBR();
    sucesso.value = null;
    passo.value = 1;
}
</script>

<template>
    <Head title="Ajustar estoque" />
    <AdminLayout>
        <template #page-title>Assistente · Ajustar estoque</template>

        <PageHeader title="Ajustar estoque" subtitle="Corrigir saldo do armazém.">
            <template #actions>
                <Link :href="route('admin.inicio')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <template v-if="itens.length > 0">
        <div v-if="passo === 1" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Qual produto precisa ajustar?</h2>
                <div>
                    <InputLabel value="Produto" />
                    <select v-model="form.item_id" class="form-select text-base py-3">
                        <option :value="null">Escolha…</option>
                        <option v-for="i in itens" :key="i.id" :value="i.id">{{ i.nome }} ({{ i.unidade }})</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Em qual armazém?" />
                    <select v-model="form.warehouse_id" class="form-select text-base py-3">
                        <option v-for="a in armazens" :key="a.id" :value="a.id">{{ a.nome }}</option>
                    </select>
                </div>
                <div class="flex justify-end pt-4">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Qual a diferença?</h2>
                <p class="text-sm text-slate-600">
                    Se <strong>sobrou</strong> (conferi e tem mais do que aparecia), use valor <strong>positivo</strong>.<br>
                    Se <strong>faltou</strong> (conferi e tem menos), use valor <strong>negativo</strong> (com sinal de menos).
                </p>

                <div>
                    <InputLabel :value="`Diferença em ${itemAtual?.unidade || 'un'} (use negativo se faltou)`" />
                    <input v-model="form.quantidade" type="number" step="0.01" inputmode="decimal"
                           placeholder="Ex: 5 ou -3"
                           class="form-input text-2xl py-4 font-mono">
                </div>

                <div>
                    <InputLabel value="Motivo" />
                    <div class="grid gap-2">
                        <button v-for="m in MOTIVOS" :key="m.id" type="button"
                                @click="form.motivo = m.id"
                                class="text-left rounded-lg border-2 p-3 transition-all"
                                :class="form.motivo === m.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200'">
                            {{ m.nome }}
                        </button>
                    </div>
                </div>

                <div>
                    <InputLabel value="Detalhes (opcional)" />
                    <textarea v-model="form.observacoes" rows="2" class="form-input"></textarea>
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar2" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <div v-if="passo === 3" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Confere?</h2>
                <div class="space-y-3">
                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between">
                        <div>
                            <div class="text-xs uppercase tracking-wider text-slate-500">Produto</div>
                            <div class="font-semibold mt-1">{{ itemAtual?.nome }} · {{ armazemAtual?.nome }}</div>
                        </div>
                        <button @click="irPara(1)" class="text-sm text-macaybas-primary hover:underline">Trocar</button>
                    </div>
                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between">
                        <div>
                            <div class="text-xs uppercase tracking-wider text-slate-500">Ajuste</div>
                            <div class="text-2xl font-bold mt-1" :class="form.quantidade > 0 ? 'text-emerald-700' : 'text-red-700'">
                                {{ form.quantidade > 0 ? '+' : '' }}{{ form.quantidade }} {{ itemAtual?.unidade }}
                            </div>
                            <div class="text-sm text-slate-500 mt-1">{{ MOTIVOS.find(m => m.id === form.motivo)?.nome }}</div>
                        </div>
                        <button @click="irPara(2)" class="text-sm text-macaybas-primary hover:underline">Trocar</button>
                    </div>
                </div>
                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="form.processing" class="btn-primary px-8 py-3 text-base">
                        {{ form.processing ? 'Salvando…' : 'Confirmar ✓' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto">
            <div class="card-body text-center space-y-5 py-8">
                <div class="text-6xl">🔢</div>
                <h2 class="text-2xl font-semibold text-slate-900">Estoque ajustado!</h2>
                <p class="text-base text-slate-600">
                    <strong>{{ sucesso.item }}</strong>: {{ sucesso.quantidade > 0 ? '+' : '' }}{{ sucesso.quantidade }} {{ sucesso.unidade }}
                </p>
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-left">
                    <ul class="text-sm text-emerald-800 space-y-1">
                        <li>✓ Saldo corrigido para bater com a realidade</li>
                        <li>✓ Ajuste registrado no histórico</li>
                    </ul>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 pt-3">
                    <button @click="reiniciar" class="btn-primary flex-1 py-3">Ajustar outro</button>
                    <Link :href="route('admin.estoque.movimentos.index')" class="btn-outline flex-1 py-3 text-center">Ver movimentações</Link>
                    <Link :href="route('admin.inicio')" class="btn-outline flex-1 py-3 text-center">Voltar ao início</Link>
                </div>
            </div>
        </div>
        </template>
    </AdminLayout>
</template>
