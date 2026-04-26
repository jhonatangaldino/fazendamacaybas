<script setup>
/**
 * Assistente guiado — Registrar receita (dinheiro que entrou).
 *
 * 4 passos:
 *   1 · De onde veio?      (descrição + categoria + cliente)
 *   2 · Quanto foi?        (valor + data + status recebido/a receber + conta)
 *   3 · Confere?           (resumo)
 *   4 · Pronto!            (sucesso)
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
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
    clientes: { type: Array, required: true },
});

// BLOCO 4.4 — Receita inline conta (replicado de Despesa.vue)
const contasLocal = ref([...props.contas]);
const contaInlineOpen = ref(false);
const contaInlineSalvando = ref(false);
const contaInlineErro = ref(null);
const contaInlineForm = ref({ nome: '', tipo: 'corrente' });

function abrirNovaContaInline() {
    contaInlineOpen.value = true;
    contaInlineErro.value = null;
    contaInlineForm.value = { nome: '', tipo: 'corrente' };
}

async function salvarContaInline() {
    contaInlineErro.value = null;
    if (! contaInlineForm.value.nome?.trim()) {
        contaInlineErro.value = 'Informe o nome da conta.';
        return;
    }
    contaInlineSalvando.value = true;
    try {
        const xsrf = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || '';
        const r = await fetch(route('admin.financeiro.contas.inline'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(xsrf),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(contaInlineForm.value),
        });
        if (! r.ok) {
            const body = await r.json().catch(() => ({}));
            contaInlineErro.value = body.errors?.nome?.[0] || body.message || 'Erro ao criar conta.';
            return;
        }
        const nova = await r.json();
        contasLocal.value.push(nova);
        form.account_id = nova.id;
        contaInlineOpen.value = false;
    } finally {
        contaInlineSalvando.value = false;
    }
}

const PASSOS = [
    { n: 1, titulo: 'De onde veio', icon: '💰' },
    { n: 2, titulo: 'Quanto foi',   icon: '🏦' },
    { n: 3, titulo: 'Conferência',  icon: '📋' },
    { n: 4, titulo: 'Pronto!',      icon: '✅' },
];

const passo = ref(1);
const sucesso = ref(null);

const form = useForm({
    tipo: 'receita',
    descricao: '',
    category_id: null,
    partner_id: null,
    valor: '',
    data_vencimento: hojeBR(),
    data_pagamento: '',
    status: 'pago',
    account_id: props.contas[0]?.id ?? null,
    observacoes: '',
});

const podeAvancar1 = computed(() => form.descricao.trim().length >= 2);
const podeAvancar2 = computed(() => {
    const v = parseFloat(String(form.valor).replace(',', '.'));
    return !isNaN(v) && v > 0 && !!form.data_vencimento && !!form.account_id;
});

const categoriaNome = computed(() => props.categorias.find(c => c.id === form.category_id)?.nome ?? null);
const clienteNome = computed(() => props.clientes.find(c => c.id === form.partner_id)?.nome ?? null);
const contaNome = computed(() => props.contas.find(c => c.id === form.account_id)?.nome ?? null);

function avancar() { if (passo.value < PASSOS.length) passo.value++; }
function voltar() { if (passo.value > 1) passo.value--; }
function irPara(n) { passo.value = n; }

function confirmar() {
    if (form.status === 'pago' && !form.data_pagamento) {
        form.data_pagamento = hojeBR();
    } else if (form.status !== 'pago') {
        form.data_pagamento = '';
    }
    form.valor = parseFloat(String(form.valor).replace(',', '.'));

    form.post(route('admin.fluxos.registrar-receita.store'), {
        preserveScroll: false,
        onSuccess: (page) => {
            const ctx = page?.props?.flash?.financeiro_contexto
                ?? usePage()?.props?.flash?.financeiro_contexto
                ?? null;
            sucesso.value = { descricao: form.descricao, valor: form.valor, status: form.status, ctx };
            passo.value = 4;
        },
    });
}

function reiniciar() {
    form.reset();
    form.tipo = 'receita';
    form.status = 'pago';
    form.data_vencimento = hojeBR();
    form.account_id = props.contas[0]?.id ?? null;
    sucesso.value = null;
    passo.value = 1;
}
</script>

<template>
    <Head title="Registrar receita" />
    <AdminLayout>
        <template #page-title>Assistente · Receita</template>

        <PageHeader
            title="Registrar receita"
            subtitle="Vamos anotar o dinheiro que entrou, passo a passo."
        >
            <template #actions>
                <Link :href="route('admin.inicio')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- BLOCO 4.4 — se não há conta, oferece criar inline (sem sair do fluxo) -->
        <div v-if="contas.length === 0" class="max-w-2xl mx-auto card border-amber-300 bg-amber-50 p-4 mb-4">
            <div class="font-semibold text-amber-900">Você precisa de uma conta financeira primeiro</div>
            <p class="text-sm text-amber-800 mt-1">Cadastre rapidamente sem sair desta tela:</p>
            <button @click="abrirNovaContaInline" class="btn-primary mt-3">+ Cadastrar conta agora</button>
            <Link :href="route('admin.financeiro.contas.index')" class="btn-outline mt-3 ml-2">Gerenciar contas</Link>
        </div>

        <template v-else>
        <!-- PASSO 1 -->
        <div v-if="passo === 1" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">De onde veio esse dinheiro?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Descreva a entrada de forma curta. Exemplos: "Venda de leite — laticínio", "Venda de ovo na feira", "Aluguel da terra".
                    </p>
                </div>

                <div>
                    <InputLabel value="Descrição" />
                    <input v-model="form.descricao" type="text" maxlength="255"
                           placeholder="Ex: Venda de leite — Laticínio Serrano"
                           class="form-input text-lg py-3">
                    <p v-if="form.errors.descricao" class="text-sm text-red-700 mt-1">{{ form.errors.descricao }}</p>
                </div>

                <div>
                    <InputLabel value="Tipo de receita (opcional)" />
                    <select v-model="form.category_id" class="form-select text-base py-3">
                        <option :value="null">— Sem tipo —</option>
                        <option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nome }}</option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Ajuda a ver, no fim do mês, de onde está vindo mais dinheiro.</p>
                </div>

                <div>
                    <InputLabel value="De quem recebeu? (opcional)" />
                    <select v-model="form.partner_id" class="form-select text-base py-3">
                        <option :value="null">— Não informar —</option>
                        <option v-for="c in clientes" :key="c.id" :value="c.id">{{ c.nome }}</option>
                    </select>
                </div>

                <div class="flex justify-end pt-4">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2 -->
        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Quanto foi?</h2>

                <div>
                    <InputLabel value="Valor" />
                    <InputMoney v-model="form.valor" class="form-input text-2xl py-4 font-mono" />
                    <p v-if="form.errors.valor" class="text-sm text-red-700 mt-1">{{ form.errors.valor }}</p>
                </div>

                <div>
                    <InputLabel value="Já caiu na conta ou ainda vai entrar?" />
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="form.status = 'pago'"
                                class="rounded-xl border-2 p-4 text-left transition-all"
                                :class="form.status === 'pago' ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200'">
                            <div class="font-semibold text-slate-900">✅ Já recebi</div>
                            <div class="text-xs text-slate-600 mt-1">Soma no saldo do caixa</div>
                        </button>
                        <button type="button" @click="form.status = 'pendente'"
                                class="rounded-xl border-2 p-4 text-left transition-all"
                                :class="form.status === 'pendente' ? 'border-amber-500 bg-amber-50' : 'border-slate-200'">
                            <div class="font-semibold text-slate-900">⏳ Ainda vou receber</div>
                            <div class="text-xs text-slate-600 mt-1">Entra como conta a receber</div>
                        </button>
                    </div>
                </div>

                <div>
                    <InputLabel :value="form.status === 'pago' ? 'Quando caiu o dinheiro?' : 'Até quando deve cair?'" />
                    <InputDate v-model="form.data_vencimento" />
                </div>

                <div>
                    <InputLabel :value="form.status === 'pago' ? 'Em qual conta entrou o dinheiro?' : 'Em qual conta vai entrar?'" />
                    <div class="flex gap-2">
                        <select v-model="form.account_id" class="form-select text-base py-3 flex-1">
                            <option v-for="c in contasLocal" :key="c.id" :value="c.id">{{ c.nome }}</option>
                        </select>
                        <button type="button" @click="abrirNovaContaInline"
                                class="px-3 py-2 rounded-lg text-sm font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100 whitespace-nowrap"
                                title="Criar nova conta sem sair deste assistente">
                            + Nova conta
                        </button>
                    </div>
                </div>

                <!-- BLOCO 4.4 — modal inline criar conta -->
                <Teleport to="body">
                    <div v-if="contaInlineOpen" class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center p-0 sm:p-4">
                        <div class="absolute inset-0 bg-slate-900/60" @click="contaInlineOpen = false"></div>
                        <div class="relative w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl ring-1 ring-slate-200">
                            <div class="px-5 pt-5 pb-3 border-b border-slate-100">
                                <h3 class="text-base font-semibold text-slate-900">Nova conta financeira</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Cadastro rápido sem sair do assistente.</p>
                            </div>
                            <div class="px-5 py-4 space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Nome *</label>
                                    <input v-model="contaInlineForm.nome" type="text" placeholder="Ex.: Banco do Brasil PJ"
                                           class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500 focus:outline-none text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Tipo *</label>
                                    <select v-model="contaInlineForm.tipo"
                                            class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500 focus:outline-none text-sm">
                                        <option value="corrente">Conta corrente</option>
                                        <option value="poupanca">Poupança</option>
                                        <option value="caixa">Caixa interno</option>
                                        <option value="dinheiro">Dinheiro em espécie</option>
                                        <option value="cartao">Cartão de crédito</option>
                                        <option value="outro">Outro</option>
                                    </select>
                                </div>
                                <p v-if="contaInlineErro" class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-md px-2 py-1.5">⚠ {{ contaInlineErro }}</p>
                            </div>
                            <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2">
                                <button type="button" @click="contaInlineOpen = false" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-200">Cancelar</button>
                                <button type="button" @click="salvarContaInline" :disabled="contaInlineSalvando"
                                        class="px-4 py-2 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50">
                                    {{ contaInlineSalvando ? 'Salvando…' : 'Cadastrar conta' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Teleport>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar2" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 3 -->
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
                            <div class="text-xs uppercase tracking-wider text-slate-500">De onde veio</div>
                            <div class="font-semibold text-slate-900 mt-1">{{ form.descricao }}</div>
                            <div v-if="categoriaNome" class="text-sm text-slate-500 mt-0.5">Tipo: {{ categoriaNome }}</div>
                            <div v-if="clienteNome" class="text-sm text-slate-500">
                                {{ form.status === 'pago' ? 'Recebeu de:' : 'De:' }} {{ clienteNome }}
                            </div>
                        </div>
                        <button @click="irPara(1)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>

                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-slate-500">Valor</div>
                            <div class="text-2xl font-bold text-emerald-700 mt-1">{{ brl(parseFloat(String(form.valor).replace(',', '.')) || 0) }}</div>
                            <div class="text-sm text-slate-500 mt-1">
                                <span v-if="form.status === 'pago'">✅ Recebido em {{ dataBR(form.data_vencimento) }} · entrando em {{ contaNome }}</span>
                                <span v-else>⏳ A receber até {{ dataBR(form.data_vencimento) }} · {{ contaNome }}</span>
                            </div>
                        </div>
                        <button @click="irPara(2)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
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

        <!-- PASSO 4 -->
        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto">
            <div class="card-body text-center space-y-5 py-8">
                <div class="text-6xl" aria-hidden="true">💰</div>
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Receita registrada!</h2>
                    <p class="text-base text-slate-600 mt-2">
                        <strong>{{ sucesso.descricao }}</strong><br>
                        Valor: <strong class="text-emerald-700">{{ brl(sucesso.valor) }}</strong>
                    </p>
                </div>

                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-left">
                    <div class="text-sm font-semibold text-emerald-900 mb-2">O que vai acontecer:</div>
                    <ul class="text-sm text-emerald-800 space-y-1">
                        <li v-if="sucesso.status === 'pago'">✓ Saldo da conta foi aumentado em {{ brl(sucesso.valor) }}</li>
                        <li v-else>✓ Entrou na sua lista de contas a receber</li>
                        <li>✓ Entrou no fluxo de caixa / relatório de receitas do mês</li>
                    </ul>
                </div>

                <!-- Impacto financeiro real do mês corrente -->
                <div v-if="sucesso.ctx" class="rounded-lg p-4 bg-white border-2 border-slate-200 text-left">
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold mb-3">
                        📊 Seu mês agora
                    </div>
                    <div class="grid grid-cols-3 gap-3 text-sm text-center">
                        <div>
                            <div class="text-xs text-slate-500">Receitas</div>
                            <div class="font-mono font-semibold text-emerald-700 mt-1">{{ brl(sucesso.ctx.receitas_mes) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500">Despesas</div>
                            <div class="font-mono font-semibold text-red-700 mt-1">{{ brl(sucesso.ctx.despesas_mes) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500">Saldo</div>
                            <div class="font-mono font-bold text-lg mt-1"
                                 :class="sucesso.ctx.saldo_mes >= 0 ? 'text-emerald-700' : 'text-red-700'">
                                {{ brl(sucesso.ctx.saldo_mes) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-3">
                    <button @click="reiniciar" class="btn-primary flex-1 py-3">Registrar outra receita</button>
                    <Link :href="route('admin.financeiro.transacoes.index')" class="btn-outline flex-1 py-3 text-center">Ver lançamentos</Link>
                    <Link :href="route('admin.inicio')" class="btn-outline flex-1 py-3 text-center">Voltar ao início</Link>
                </div>
            </div>
        </div>
        </template>
    </AdminLayout>
</template>
