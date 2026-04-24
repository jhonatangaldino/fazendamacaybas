<script setup>
/**
 * Assistente — Receber mercadoria (entrada de estoque).
 * 3 passos + sucesso.
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMoney from '@/Components/InputMoney.vue';
import { hojeBR, dataBR, brl } from '@/utils/format.js';

const props = defineProps({
    itens: { type: Array, required: true },
    armazens: { type: Array, required: true },
    fornecedores: { type: Array, required: true },
});

const PASSOS = [
    { n: 1, titulo: 'O que chegou', icon: '📦' },
    { n: 2, titulo: 'Detalhes',     icon: '📝' },
    { n: 3, titulo: 'Conferência',  icon: '📋' },
    { n: 4, titulo: 'Pronto!',      icon: '✅' },
];

const passo = ref(1);
const sucesso = ref(null);

const form = useForm({
    item_id: null,
    warehouse_id: props.armazens[0]?.id ?? null,
    partner_id: null,
    quantidade: '',
    valor_unitario: '',
    numero_documento: '',
    data: hojeBR(),
    observacoes: '',
});

const itemAtual = computed(() => props.itens.find(i => i.id === form.item_id));
const armazemAtual = computed(() => props.armazens.find(a => a.id === form.warehouse_id));
const fornecedorAtual = computed(() => props.fornecedores.find(f => f.id === form.partner_id));

const podeAvancar1 = computed(() => !!form.item_id && !!form.warehouse_id);
const podeAvancar2 = computed(() => {
    const q = parseFloat(String(form.quantidade).replace(',', '.'));
    return !isNaN(q) && q > 0;
});

const valorTotal = computed(() => {
    const q = parseFloat(String(form.quantidade).replace(',', '.')) || 0;
    const v = parseFloat(String(form.valor_unitario).replace(',', '.')) || 0;
    return q * v;
});

function avancar() { if (passo.value < PASSOS.length) passo.value++; }
function voltar() { if (passo.value > 1) passo.value--; }
function irPara(n) { passo.value = n; }

function confirmar() {
    form.quantidade = parseFloat(String(form.quantidade).replace(',', '.'));
    if (form.valor_unitario) form.valor_unitario = parseFloat(String(form.valor_unitario).replace(',', '.'));

    const snapshot = {
        item: itemAtual.value?.nome,
        quantidade: form.quantidade,
        unidade: itemAtual.value?.unidade,
        valorTotal: valorTotal.value,
    };

    // Avança OTIMISTA para passo 4 antes do post.
    sucesso.value = snapshot;
    passo.value = 4;

    form.post(route('admin.fluxos.receber-mercadoria.store'), {
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
    form.data = hojeBR();
    sucesso.value = null;
    passo.value = 1;
}
</script>

<template>
    <Head title="Receber mercadoria" />
    <AdminLayout>
        <template #page-title>Assistente · Receber mercadoria</template>

        <PageHeader title="Receber mercadoria" subtitle="Vamos registrar a entrada passo a passo.">
            <template #actions>
                <Link :href="route('admin.inicio')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <div v-if="itens.length === 0" class="max-w-2xl mx-auto card border-amber-300 bg-amber-50 p-4 mb-4">
            <div class="font-semibold text-amber-900">Cadastre um item de estoque primeiro</div>
            <Link :href="route('admin.estoque.itens.index')" class="btn-outline mt-3">Ir para itens</Link>
        </div>

        <template v-else>
        <div v-if="passo === 1" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">O que chegou?</h2>

                <div>
                    <InputLabel value="Qual produto chegou?" />
                    <select v-model="form.item_id" class="form-select text-base py-3">
                        <option :value="null">— Escolha o produto —</option>
                        <option v-for="i in itens" :key="i.id" :value="i.id">{{ i.nome }} ({{ i.unidade }})</option>
                    </select>
                </div>

                <div>
                    <InputLabel value="Onde você guardou?" />
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
                <h2 class="text-2xl font-semibold text-slate-900">Quanto chegou e quem trouxe?</h2>
                <p class="text-base text-slate-600">Produto: <strong>{{ itemAtual?.nome }}</strong></p>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel :value="`Quantidade (${itemAtual?.unidade || 'un'})`" />
                        <input v-model="form.quantidade" type="number" step="0.01" min="0" inputmode="decimal"
                               class="form-input text-xl py-3 font-mono">
                    </div>
                    <div>
                        <InputLabel value="Preço por unidade (opcional)" />
                        <InputMoney v-model="form.valor_unitario" class="form-input py-3" />
                    </div>
                </div>

                <div v-if="valorTotal > 0" class="text-sm text-slate-600 bg-slate-50 p-3 rounded-lg">
                    Valor total: <strong class="text-slate-900">{{ brl(valorTotal) }}</strong>
                </div>

                <div>
                    <InputLabel value="Quem entregou? (opcional)" />
                    <select v-model="form.partner_id" class="form-select text-base py-3">
                        <option :value="null">— Não informar —</option>
                        <option v-for="f in fornecedores" :key="f.id" :value="f.id">{{ f.nome }}</option>
                    </select>
                </div>

                <div>
                    <InputLabel value="Número da nota ou recibo (opcional)" />
                    <input v-model="form.numero_documento" type="text" maxlength="50"
                           placeholder="Ex: 123456"
                           class="form-input">
                </div>

                <div>
                    <InputLabel value="Quando chegou?" />
                    <InputDate v-model="form.data" :max="hojeBR()" />
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar2" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

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
                            <div class="text-xs uppercase tracking-wider text-slate-500">O que e onde</div>
                            <div class="font-semibold mt-1">{{ itemAtual?.nome }}</div>
                            <div class="text-sm text-slate-500">Guardado em: {{ armazemAtual?.nome }}</div>
                        </div>
                        <button @click="irPara(1)" class="text-sm text-macaybas-primary hover:underline">Trocar</button>
                    </div>
                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-slate-500">Quanto chegou</div>
                            <div class="text-2xl font-bold mt-1">{{ form.quantidade }} {{ itemAtual?.unidade }}</div>
                            <div v-if="valorTotal > 0" class="text-sm text-slate-600 mt-1">Total: {{ brl(valorTotal) }}</div>
                            <div v-if="fornecedorAtual" class="text-sm text-slate-500 mt-0.5">Entregue por: {{ fornecedorAtual.nome }}</div>
                        </div>
                        <button @click="irPara(2)" class="text-sm text-macaybas-primary hover:underline">Trocar</button>
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

        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto">
            <div class="card-body text-center space-y-5 py-8">
                <div class="text-6xl">📦</div>
                <h2 class="text-2xl font-semibold text-slate-900">Mercadoria recebida!</h2>
                <p class="text-base text-slate-600">
                    {{ sucesso.quantidade }} {{ sucesso.unidade }} de <strong>{{ sucesso.item }}</strong><br>
                    <span v-if="sucesso.valorTotal > 0">Total: <strong>{{ brl(sucesso.valorTotal) }}</strong></span>
                </p>
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-left">
                    <ul class="text-sm text-emerald-800 space-y-1">
                        <li>✓ Saldo do item foi somado no armazém</li>
                        <li>✓ Entrada registrada no histórico</li>
                    </ul>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 pt-3">
                    <button @click="reiniciar" class="btn-primary flex-1 py-3">Receber outra</button>
                    <Link :href="route('admin.estoque.movimentos.index')" class="btn-outline flex-1 py-3 text-center">Ver movimentações</Link>
                    <Link :href="route('admin.inicio')" class="btn-outline flex-1 py-3 text-center">Voltar ao início</Link>
                </div>
            </div>
        </div>
        </template>
    </AdminLayout>
</template>
