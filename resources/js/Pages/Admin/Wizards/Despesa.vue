<script setup>
/**
 * Assistente guiado — Registrar despesa.
 *
 * 4 passos:
 *   1 · O que você pagou?    (descrição + categoria)
 *   2 · Quanto foi?          (valor + data + status pago/a pagar + conta)
 *   3 · Confere?             (resumo)
 *   4 · Pronto!              (sucesso)
 *
 * Submit: reutiliza `admin.financeiro.transacoes.store` com tipo=despesa.
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
    contas: { type: Array, required: true },
    categorias: { type: Array, required: true },
    fornecedores: { type: Array, required: true },
});

const PASSOS = [
    { n: 1, titulo: 'O que pagou', icon: '🛒' },
    { n: 2, titulo: 'Quanto foi',  icon: '💸' },
    { n: 3, titulo: 'Conferência', icon: '📋' },
    { n: 4, titulo: 'Pronto!',     icon: '✅' },
];

const passo = ref(1);
const sucesso = ref(null);

const form = useForm({
    tipo: 'despesa',
    descricao: '',
    category_id: null,
    partner_id: null,
    valor: '',
    data_vencimento: hojeBR(),
    data_pagamento: '',
    status: 'pago',                     // default: pago (cenário mais comum)
    account_id: props.contas[0]?.id ?? null,
    observacoes: '',
});

const podeAvancar1 = computed(() => form.descricao.trim().length >= 2);
const podeAvancar2 = computed(() => {
    const v = parseFloat(String(form.valor).replace(',', '.'));
    return !isNaN(v) && v > 0 && !!form.data_vencimento && !!form.account_id;
});

const categoriaNome = computed(() =>
    props.categorias.find((c) => c.id === form.category_id)?.nome ?? null
);
const fornecedorNome = computed(() =>
    props.fornecedores.find((p) => p.id === form.partner_id)?.nome ?? null
);
const contaNome = computed(() =>
    props.contas.find((c) => c.id === form.account_id)?.nome ?? null
);

function avancar() { if (passo.value < PASSOS.length) passo.value++; }
function voltar() { if (passo.value > 1) passo.value--; }
function irPara(n) { passo.value = n; }

function confirmar() {
    // Se status=pago e data_pagamento vazia, usa hoje. Senão, envia null.
    const payload = { ...form.data() };
    if (payload.status === 'pago' && !payload.data_pagamento) {
        form.data_pagamento = hojeBR();
    } else if (payload.status !== 'pago') {
        form.data_pagamento = '';
    }
    form.valor = parseFloat(String(form.valor).replace(',', '.'));

    form.post(route('admin.fluxos.registrar-despesa.store'), {
        preserveScroll: false,
        onSuccess: () => {
            sucesso.value = {
                descricao: form.descricao,
                valor: form.valor,
                status: form.status,
            };
            passo.value = 4;
        },
    });
}

function reiniciar() {
    form.reset();
    form.tipo = 'despesa';
    form.status = 'pago';
    form.data_vencimento = hojeBR();
    form.account_id = props.contas[0]?.id ?? null;
    sucesso.value = null;
    passo.value = 1;
}
</script>

<template>
    <Head title="Registrar despesa" />
    <AdminLayout>
        <template #page-title>Assistente · Despesa</template>

        <PageHeader
            title="Registrar despesa"
            subtitle="Vamos anotar o que saiu do caixa, passo a passo."
        >
            <template #actions>
                <Link :href="route('admin.inicio')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- Aviso se não há conta financeira cadastrada -->
        <div v-if="contas.length === 0" class="max-w-2xl mx-auto card border-amber-300 bg-amber-50 p-4 mb-4">
            <div class="font-semibold text-amber-900">Você precisa de uma conta financeira primeiro</div>
            <p class="text-sm text-amber-800 mt-1">Cadastre uma conta (caixa ou banco) antes de registrar despesas.</p>
            <Link :href="route('admin.financeiro.index')" class="btn-outline mt-3">Ir para o financeiro</Link>
        </div>

        <template v-else>
        <!-- PASSO 1 · O que pagou -->
        <div v-if="passo === 1" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">O que você pagou?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Descreva o gasto de forma curta. Exemplos: "Combustível", "Ração dos bezerros", "Salário do Zé".
                    </p>
                </div>

                <div>
                    <InputLabel value="Descrição" />
                    <input v-model="form.descricao" type="text" maxlength="255"
                           placeholder="Ex: Combustível no posto"
                           class="form-input text-lg py-3">
                    <p v-if="form.errors.descricao" class="text-sm text-red-700 mt-1">{{ form.errors.descricao }}</p>
                </div>

                <div>
                    <InputLabel value="Tipo de gasto (opcional)" />
                    <select v-model="form.category_id" class="form-select text-base py-3">
                        <option :value="null">— Sem tipo —</option>
                        <option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nome }}</option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Ajuda a ver, no fim do mês, onde está saindo mais dinheiro.</p>
                </div>

                <div>
                    <InputLabel value="Para quem você pagou? (opcional)" />
                    <select v-model="form.partner_id" class="form-select text-base py-3">
                        <option :value="null">— Não informar —</option>
                        <option v-for="p in fornecedores" :key="p.id" :value="p.id">{{ p.nome }}</option>
                    </select>
                </div>

                <div class="flex justify-end pt-4">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2 · Quanto foi -->
        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Quanto foi?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Informe o valor e quando o pagamento acontece.
                    </p>
                </div>

                <div>
                    <InputLabel value="Valor" />
                    <InputMoney v-model="form.valor" class="form-input text-2xl py-4 font-mono" />
                    <p v-if="form.errors.valor" class="text-sm text-red-700 mt-1">{{ form.errors.valor }}</p>
                </div>

                <div>
                    <InputLabel value="Já foi pago ou é pra pagar ainda?" />
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="form.status = 'pago'"
                                class="rounded-xl border-2 p-4 text-left transition-all"
                                :class="form.status === 'pago' ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200'">
                            <div class="font-semibold text-slate-900">✅ Já paguei</div>
                            <div class="text-xs text-slate-600 mt-1">Sai do caixa agora</div>
                        </button>
                        <button type="button" @click="form.status = 'pendente'"
                                class="rounded-xl border-2 p-4 text-left transition-all"
                                :class="form.status === 'pendente' ? 'border-amber-500 bg-amber-50' : 'border-slate-200'">
                            <div class="font-semibold text-slate-900">⏳ Vou pagar depois</div>
                            <div class="text-xs text-slate-600 mt-1">Entra como conta a pagar</div>
                        </button>
                    </div>
                </div>

                <div>
                    <InputLabel :value="form.status === 'pago' ? 'Quando foi pago?' : 'Até quando precisa pagar?'" />
                    <InputDate v-model="form.data_vencimento" />
                    <p v-if="form.errors.data_vencimento" class="text-sm text-red-700 mt-1">{{ form.errors.data_vencimento }}</p>
                </div>

                <div>
                    <InputLabel :value="form.status === 'pago' ? 'De onde saiu o dinheiro?' : 'De onde vai sair o dinheiro?'" />
                    <select v-model="form.account_id" class="form-select text-base py-3">
                        <option v-for="c in contas" :key="c.id" :value="c.id">{{ c.nome }}</option>
                    </select>
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
                            <div class="text-xs uppercase tracking-wider text-slate-500">O gasto</div>
                            <div class="font-semibold text-slate-900 mt-1">{{ form.descricao }}</div>
                            <div v-if="categoriaNome" class="text-sm text-slate-500 mt-0.5">Tipo: {{ categoriaNome }}</div>
                            <div v-if="fornecedorNome" class="text-sm text-slate-500">
                                {{ form.status === 'pago' ? 'Pagou para:' : 'Para:' }} {{ fornecedorNome }}
                            </div>
                        </div>
                        <button @click="irPara(1)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>

                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-slate-500">Valor</div>
                            <div class="text-2xl font-bold text-red-700 mt-1">{{ brl(parseFloat(String(form.valor).replace(',', '.')) || 0) }}</div>
                            <div class="text-sm text-slate-500 mt-1">
                                <span v-if="form.status === 'pago'">✅ Pago em {{ dataBR(form.data_vencimento) }} · saindo de {{ contaNome }}</span>
                                <span v-else>⏳ A pagar até {{ dataBR(form.data_vencimento) }} · {{ contaNome }}</span>
                            </div>
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
                <div class="text-6xl" aria-hidden="true">✅</div>
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Despesa registrada!</h2>
                    <p class="text-base text-slate-600 mt-2">
                        <strong>{{ sucesso.descricao }}</strong><br>
                        Valor: <strong class="text-red-700">{{ brl(sucesso.valor) }}</strong>
                    </p>
                </div>

                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-left">
                    <div class="text-sm font-semibold text-emerald-900 mb-2">O que vai acontecer:</div>
                    <ul class="text-sm text-emerald-800 space-y-1">
                        <li v-if="sucesso.status === 'pago'">✓ Saldo da conta foi reduzido em {{ brl(sucesso.valor) }}</li>
                        <li v-else>✓ Entrou na sua lista de contas a pagar</li>
                        <li>✓ Entrou no fluxo de caixa / relatório de despesas do mês</li>
                    </ul>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-3">
                    <button @click="reiniciar" class="btn-primary flex-1 py-3">Registrar outra despesa</button>
                    <Link :href="route('admin.financeiro.transacoes.index')" class="btn-outline flex-1 py-3 text-center">Ver todos os lançamentos</Link>
                    <Link :href="route('admin.inicio')" class="btn-outline flex-1 py-3 text-center">Voltar ao início</Link>
                </div>
            </div>
        </div>
        </template>
    </AdminLayout>
</template>
