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
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMoney from '@/Components/InputMoney.vue';
import { hojeBR, dataBR, brl } from '@/utils/format.js';
import { useInlineCreate } from '@/composables/useInlineCreate.js';

const props = defineProps({
    contas: { type: Array, required: true },
    categorias: { type: Array, required: true },
    fornecedores: { type: Array, required: true },
});

// Listas locais — recebem novos itens criados inline
const categoriasLocal = ref([...props.categorias]);
const fornecedoresLocal = ref([...props.fornecedores]);
// BLOCO 4.3 RN2 — conta financeira inline
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
    // A1 — Parcelamento (default: à vista)
    parcelado: false,
    total_parcelas: 2,
});

const podeAvancar1 = computed(() => form.descricao.trim().length >= 2);
const podeAvancar2 = computed(() => {
    const v = parseFloat(String(form.valor).replace(',', '.'));
    if (isNaN(v) || v <= 0) return false;
    if (! form.data_vencimento || ! form.account_id) return false;
    if (form.parcelado) {
        const n = parseInt(form.total_parcelas, 10);
        if (isNaN(n) || n < 2 || n > 60) return false;
    }
    return true;
});

const valorPorParcela = computed(() => {
    if (! form.parcelado) return 0;
    const v = parseFloat(String(form.valor).replace(',', '.')) || 0;
    const n = parseInt(form.total_parcelas, 10) || 1;
    if (n < 1) return 0;
    return v / n;
});

const categoriaNome = computed(() =>
    categoriasLocal.value.find((c) => c.id === form.category_id)?.nome ?? null
);
const fornecedorNome = computed(() =>
    fornecedoresLocal.value.find((p) => p.id === form.partner_id)?.nome ?? null
);
const contaNome = computed(() =>
    props.contas.find((c) => c.id === form.account_id)?.nome ?? null
);

// Criação inline de CATEGORIA financeira sem sair do wizard
const novaCategoria = useInlineCreate({
    endpoint: route('admin.financeiro.categorias.inline'),
    initialForm: { nome: '', tipo: 'financeiro' },
    onCreated: (c) => {
        categoriasLocal.value = [...categoriasLocal.value, c];
        form.category_id = c.id;
    },
});

// Criação inline de FORNECEDOR (parceiro) sem sair do wizard de despesa
const novoFornecedor = useInlineCreate({
    endpoint: route('admin.parceiros.inline'),
    initialForm: { nome: '', tipo: 'fornecedor', pessoa: 'juridica', documento: '', telefone: '' },
    onCreated: (p) => {
        fornecedoresLocal.value = [...fornecedoresLocal.value, p];
        form.partner_id = p.id;
    },
});

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
    if (form.parcelado) {
        form.total_parcelas = parseInt(form.total_parcelas, 10);
    } else {
        // Garante que campos de parcelamento não vão sujar o backend quando à vista.
        form.total_parcelas = null;
    }

    form.post(route('admin.fluxos.registrar-despesa.store'), {
        preserveScroll: false,
        onSuccess: (page) => {
            const ctx = page?.props?.flash?.financeiro_contexto
                ?? usePage()?.props?.flash?.financeiro_contexto
                ?? null;
            sucesso.value = {
                descricao: form.descricao,
                valor: form.valor,
                status: form.status,
                parcelado: form.parcelado,
                total_parcelas: form.parcelado ? form.total_parcelas : 1,
                valor_parcela: form.parcelado ? valorPorParcela.value : form.valor,
                ctx,
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
    form.parcelado = false;
    form.total_parcelas = 2;
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

        <!-- BLOCO 4.3 RN2 — se não há conta, oferece criar inline (sem sair do fluxo) -->
        <div v-if="contas.length === 0" class="max-w-2xl mx-auto card border-amber-300 bg-amber-50 p-4 mb-4">
            <div class="font-semibold text-amber-900">Você precisa de uma conta financeira primeiro</div>
            <p class="text-sm text-amber-800 mt-1">Cadastre rapidamente sem sair desta tela:</p>
            <button @click="abrirNovaContaInline" class="btn-primary mt-3">+ Cadastrar conta agora</button>
            <Link :href="route('admin.financeiro.contas.index')" class="btn-outline mt-3 ml-2">Gerenciar contas</Link>
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
                        <option v-for="c in categoriasLocal" :key="c.id" :value="c.id">{{ c.nome }}</option>
                    </select>
                    <div class="mt-1 flex items-center gap-2">
                        <button type="button" @click="novaCategoria.abrir()" class="inline-flex items-center min-h-9 px-2 text-xs text-macaybas-primary hover:underline lg:min-h-0 lg:px-0">
                            + Criar tipo novo
                        </button>
                        <span v-if="categoriasLocal.length === 0" class="text-xs text-amber-700">
                            Nenhum tipo ainda — crie aqui.
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Ajuda a ver, no fim do mês, onde está saindo mais dinheiro.</p>
                </div>

                <div>
                    <InputLabel value="Para quem você pagou? (opcional)" />
                    <select v-model="form.partner_id" class="form-select text-base py-3">
                        <option :value="null">— Não informar —</option>
                        <option v-for="p in fornecedoresLocal" :key="p.id" :value="p.id">{{ p.nome }}</option>
                    </select>
                    <div class="mt-1">
                        <button type="button" @click="novoFornecedor.abrir()" class="inline-flex items-center min-h-9 px-2 text-xs text-macaybas-primary hover:underline lg:min-h-0 lg:px-0">
                            + Cadastrar fornecedor novo
                        </button>
                    </div>
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
                        <button type="button" @click="form.status = 'pago'; form.parcelado = false"
                                class="rounded-xl border-2 p-4 text-left transition-all"
                                :class="form.status === 'pago' && !form.parcelado ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200'">
                            <div class="font-semibold text-slate-900">✅ Já paguei</div>
                            <div class="text-xs text-slate-600 mt-1">Sai do caixa agora</div>
                        </button>
                        <button type="button" @click="form.status = 'pendente'; form.parcelado = false"
                                class="rounded-xl border-2 p-4 text-left transition-all"
                                :class="form.status === 'pendente' && !form.parcelado ? 'border-amber-500 bg-amber-50' : 'border-slate-200'">
                            <div class="font-semibold text-slate-900">⏳ Vou pagar depois</div>
                            <div class="text-xs text-slate-600 mt-1">Entra como conta a pagar</div>
                        </button>
                    </div>
                    <button type="button" @click="form.parcelado = true; form.status = 'pendente'"
                            class="mt-3 w-full rounded-xl border-2 p-4 text-left transition-all"
                            :class="form.parcelado ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200'">
                        <div class="font-semibold text-slate-900">💳 Parcelar em várias vezes</div>
                        <div class="text-xs text-slate-600 mt-1">
                            Para compras grandes (maquinário, ração em volume) — gera N contas a pagar.
                        </div>
                    </button>
                </div>

                <!-- A1 — Configuração das parcelas -->
                <div v-if="form.parcelado"
                     class="rounded-xl border-2 border-indigo-200 bg-indigo-50/50 p-4 space-y-3">
                    <div class="text-sm font-semibold text-indigo-900">Em quantas vezes?</div>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="n in [2, 3, 6, 10, 12, 24]" :key="n" type="button"
                                @click="form.total_parcelas = n"
                                class="px-4 py-2 rounded-lg border-2 text-sm font-semibold transition-all"
                                :class="form.total_parcelas === n
                                    ? 'border-indigo-500 bg-indigo-100 text-indigo-900'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300'">
                            {{ n }}x
                        </button>
                        <input v-model.number="form.total_parcelas" type="number" min="2" max="60"
                               class="w-24 px-3 py-2 rounded-lg border-2 border-slate-200 text-sm text-center font-mono"
                               placeholder="N">
                    </div>
                    <div v-if="valorPorParcela > 0"
                         class="text-sm text-indigo-900 bg-white border border-indigo-200 rounded-lg p-3">
                        <div>{{ form.total_parcelas }} parcelas de
                            <strong class="text-base">{{ brl(valorPorParcela) }}</strong></div>
                        <div class="text-xs text-slate-600 mt-1">
                            Vencimentos mensais a partir de {{ dataBR(form.data_vencimento) }}.
                            Você pode marcar parcelas como pagas conforme forem sendo quitadas.
                        </div>
                    </div>
                </div>

                <div>
                    <InputLabel :value="form.parcelado
                            ? 'Vencimento da 1ª parcela'
                            : (form.status === 'pago' ? 'Quando foi pago?' : 'Até quando precisa pagar?')" />
                    <InputDate v-model="form.data_vencimento" />
                    <p v-if="form.errors.data_vencimento" class="text-sm text-red-700 mt-1">{{ form.errors.data_vencimento }}</p>
                </div>

                <div>
                    <InputLabel :value="form.status === 'pago' ? 'De onde saiu o dinheiro?' : 'De onde vai sair o dinheiro?'" />
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

                <!-- BLOCO 4.3 RN2 — modal inline criar conta -->
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
                            <div v-if="form.parcelado" class="mt-2 text-sm">
                                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-100 text-indigo-800 text-xs font-semibold">
                                    💳 {{ form.total_parcelas }}x de {{ brl(valorPorParcela) }}
                                </div>
                                <div class="text-slate-500 mt-1">
                                    1ª parcela em {{ dataBR(form.data_vencimento) }} · {{ contaNome }}
                                </div>
                            </div>
                            <div v-else class="text-sm text-slate-500 mt-1">
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
        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
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
                        <li v-if="sucesso.parcelado">
                            ✓ {{ sucesso.total_parcelas }} parcelas geradas de
                            <strong>{{ brl(sucesso.valor_parcela) }}</strong> cada,
                            uma por mês a partir de hoje
                        </li>
                        <li v-else-if="sucesso.status === 'pago'">✓ Saldo da conta foi reduzido em {{ brl(sucesso.valor) }}</li>
                        <li v-else>✓ Entrou na sua lista de contas a pagar</li>
                        <li v-if="sucesso.parcelado">✓ Você pode marcar cada parcela como paga conforme for quitando</li>
                        <li>✓ Entrou no fluxo de caixa / relatório de despesas do mês</li>
                    </ul>
                </div>

                <!-- Impacto financeiro real do mês corrente (dados do DB via flash) -->
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
                    <button @click="reiniciar" class="btn-primary flex-1 py-3">Registrar outra despesa</button>
                    <Link :href="route('admin.financeiro.transacoes.index')" class="btn-outline flex-1 py-3 text-center">Ver todos os lançamentos</Link>
                    <Link :href="route('admin.inicio')" class="btn-outline flex-1 py-3 text-center">Voltar ao início</Link>
                </div>
            </div>
        </div>
        </template>

        <!-- Modais inline: criar CATEGORIA e FORNECEDOR sem sair do wizard -->
        <Teleport to="body">
            <div v-if="novaCategoria.modalAberto.value" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novaCategoria.fechar"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold mb-1">Novo tipo de gasto</h3>
                    <p class="text-sm text-slate-500 mb-4">Ex.: "Ração", "Combustível", "Veterinário".</p>
                    <div>
                        <InputLabel value="Nome *" />
                        <input v-model="novaCategoria.form.value.nome" class="form-input" placeholder="Ex.: Combustível" required>
                    </div>
                    <p v-if="novaCategoria.erro.value" class="mt-3 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1.5">
                        {{ novaCategoria.erro.value }}
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novaCategoria.fechar" class="btn-outline">Cancelar</button>
                        <button @click="novaCategoria.salvar"
                                :disabled="novaCategoria.salvando.value || !novaCategoria.form.value.nome?.trim()"
                                class="btn-primary">
                            {{ novaCategoria.salvando.value ? 'Salvando…' : 'Criar e continuar' }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="novoFornecedor.modalAberto.value" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novoFornecedor.fechar"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold mb-1">Novo fornecedor</h3>
                    <p class="text-sm text-slate-500 mb-4">Quem você pagou ou vai pagar.</p>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Nome *" />
                            <input v-model="novoFornecedor.form.value.nome" class="form-input" placeholder="Ex.: Posto Central" required>
                        </div>
                        <div>
                            <InputLabel value="Pessoa" />
                            <div class="flex gap-2">
                                <button type="button" @click="novoFornecedor.form.value.pessoa = 'fisica'"
                                    class="flex-1 py-2 rounded border-2 text-sm font-medium"
                                    :class="novoFornecedor.form.value.pessoa === 'fisica' ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200'">
                                    👤 Pessoa
                                </button>
                                <button type="button" @click="novoFornecedor.form.value.pessoa = 'juridica'"
                                    class="flex-1 py-2 rounded border-2 text-sm font-medium"
                                    :class="novoFornecedor.form.value.pessoa === 'juridica' ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200'">
                                    🏢 Empresa
                                </button>
                            </div>
                        </div>
                        <div>
                            <InputLabel value="CPF/CNPJ (opcional)" />
                            <input v-model="novoFornecedor.form.value.documento" class="form-input" placeholder="000.000.000-00">
                        </div>
                    </div>
                    <p v-if="novoFornecedor.erro.value" class="mt-3 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1.5">
                        {{ novoFornecedor.erro.value }}
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoFornecedor.fechar" class="btn-outline">Cancelar</button>
                        <button @click="novoFornecedor.salvar"
                                :disabled="novoFornecedor.salvando.value || !novoFornecedor.form.value.nome?.trim()"
                                class="btn-primary">
                            {{ novoFornecedor.salvando.value ? 'Salvando…' : 'Cadastrar e voltar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
