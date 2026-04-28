<script setup>
/**
 * Assistente — Receber mercadoria (entrada de estoque) — MULTI-ITEM.
 * 1 nota fiscal pode conter N itens diferentes. Cabeçalho da nota
 * (armazém, fornecedor, data, nº doc) + tabela dinâmica de itens.
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
import { useInlineCreate } from '@/composables/useInlineCreate.js';

const props = defineProps({
    itens: { type: Array, required: true },
    armazens: { type: Array, required: true },
    fornecedores: { type: Array, required: true },
});

// Lista local — recebe novos itens criados inline
const itensLocal = ref([...props.itens]);

// Qual linha da tabela está esperando o item recém-criado?
const linhaAtivaParaCadastro = ref(0);

const novoItem = useInlineCreate({
    endpoint: route('admin.estoque.itens.inline'),
    initialForm: { nome: '', tipo: 'insumo', unidade: 'un' },
    onCreated: (i) => {
        itensLocal.value = [...itensLocal.value, i];
        const idx = linhaAtivaParaCadastro.value;
        if (form.items[idx]) {
            form.items[idx].item_id = i.id;
        }
    },
});

function abrirNovoItem(idx = 0) {
    linhaAtivaParaCadastro.value = idx;
    novoItem.abrir();
}

const PASSOS = [
    { n: 1, titulo: 'O que chegou', icon: '📦' },
    { n: 2, titulo: 'Detalhes',     icon: '📝' },
    { n: 3, titulo: 'Conferência',  icon: '📋' },
    { n: 4, titulo: 'Pronto!',      icon: '✅' },
];

const passo = ref(1);
const sucesso = ref(null);

// Não usamos `data` como nome de field — colide com o método `data()` do useForm
// do Inertia e quebra o submit ("this.data is not a function").
const form = useForm({
    warehouse_id: props.armazens[0]?.id ?? null,
    partner_id: null,
    numero_documento: '',
    data_recebimento: hojeBR(),
    observacoes: '',
    items: [{ item_id: null, quantidade: '', valor_unitario: '' }],
});

function adicionarItem() {
    form.items.push({ item_id: null, quantidade: '', valor_unitario: '' });
}
function removerItem(idx) {
    if (form.items.length > 1) form.items.splice(idx, 1);
}

const armazemAtual = computed(() => props.armazens.find(a => a.id === form.warehouse_id));
const fornecedorAtual = computed(() => props.fornecedores.find(f => f.id === form.partner_id));

function getItem(itemId) { return itensLocal.value.find(i => i.id === itemId); }

const podeAvancar1 = computed(() => {
    if (! form.warehouse_id) return false;
    if (form.items.length === 0) return false;
    return form.items.every(row => {
        if (! row.item_id) return false;
        const q = parseFloat(String(row.quantidade).replace(',', '.'));
        return !isNaN(q) && q > 0;
    });
});

function rowSubtotal(row) {
    const q = parseFloat(String(row.quantidade).replace(',', '.')) || 0;
    const v = parseFloat(String(row.valor_unitario).replace(',', '.')) || 0;
    return q * v;
}

const valorTotal = computed(() => form.items.reduce((acc, row) => acc + rowSubtotal(row), 0));
const totalItens = computed(() => form.items.length);
const totalUnidades = computed(() => form.items.reduce((acc, row) => {
    const q = parseFloat(String(row.quantidade).replace(',', '.')) || 0;
    return acc + q;
}, 0));

function avancar() { if (passo.value < PASSOS.length) passo.value++; }
function voltar() { if (passo.value > 1) passo.value--; }
function irPara(n) { passo.value = n; }

function confirmar() {
    const snapshot = {
        qtdItens: form.items.length,
        valorTotal: valorTotal.value,
        primeiroItem: getItem(form.items[0]?.item_id)?.nome,
        armazem: armazemAtual.value?.nome,
    };

    sucesso.value = snapshot;
    passo.value = 4;

    form.transform((d) => ({
        warehouse_id: d.warehouse_id,
        partner_id: d.partner_id,
        numero_documento: d.numero_documento,
        data: d.data_recebimento, // backend espera 'data'
        observacoes: d.observacoes,
        items: (d.items || []).map((row) => ({
            ...row,
            quantidade: parseFloat(String(row.quantidade).replace(',', '.')),
            valor_unitario:
                row.valor_unitario === '' || row.valor_unitario == null
                    ? 0
                    : parseFloat(String(row.valor_unitario).replace(',', '.')),
        })),
    }));

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
    form.data_recebimento = hojeBR();
    form.items = [{ item_id: null, quantidade: '', valor_unitario: '' }];
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

        <!-- Aviso visível só se NÃO houver nenhum item.
             Não bloqueia mais o wizard — basta usar "+ Cadastrar produto novo" inline. -->
        <div v-if="itensLocal.length === 0" class="max-w-3xl mx-auto card border-amber-300 bg-amber-50 p-4 mb-4">
            <div class="font-semibold text-amber-900">Nenhum produto cadastrado ainda</div>
            <p class="text-sm text-amber-800 mt-1">Você pode cadastrar agora mesmo, sem sair daqui — use o botão abaixo.</p>
            <button type="button" @click="abrirNovoItem(0)" class="btn-primary mt-3">+ Cadastrar produto novo</button>
        </div>

        <template v-else>
        <!-- PASSO 1: O QUE CHEGOU (armazém + tabela de itens) -->
        <div v-if="passo === 1" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">O que chegou?</h2>

                <div>
                    <InputLabel value="Onde você guardou?" />
                    <select v-model="form.warehouse_id" data-cy="select-armazem" class="form-select text-base py-3">
                        <option v-for="a in armazens" :key="a.id" :value="a.id">{{ a.nome }}</option>
                    </select>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <InputLabel value="Itens recebidos" class="!mb-0" />
                        <span class="text-xs text-slate-500">Adicione quantos forem necessários</span>
                    </div>

                    <div class="space-y-3">
                        <div v-for="(row, idx) in form.items" :key="idx"
                             class="border border-slate-200 rounded-lg p-3 bg-slate-50/50 space-y-3">
                            <div class="flex items-start gap-2">
                                <div class="flex-1">
                                    <InputLabel :value="`Produto ${idx + 1}`" class="!text-xs" />
                                    <select v-model="row.item_id" :data-cy="`select-item-${idx}`"
                                            class="form-select text-base py-2.5">
                                        <option :value="null">— Escolha o produto —</option>
                                        <option v-for="i in itensLocal" :key="i.id" :value="i.id">
                                            {{ i.nome }} ({{ i.unidade }})
                                        </option>
                                    </select>
                                    <button type="button" @click="abrirNovoItem(idx)"
                                            class="inline-flex items-center min-h-9 px-2 mt-1 text-xs text-macaybas-primary hover:underline lg:min-h-0 lg:px-0">
                                        + Cadastrar produto novo
                                    </button>
                                </div>
                                <button v-if="form.items.length > 1" type="button"
                                        @click="removerItem(idx)"
                                        :data-cy="`btn-remover-item-${idx}`"
                                        class="mt-6 text-slate-400 hover:text-red-600 text-xl leading-none px-2"
                                        title="Remover este item">
                                    ✕
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <InputLabel value="Quantidade" class="!text-xs" />
                                    <input v-model="row.quantidade" type="number" step="0.01" min="0"
                                           inputmode="decimal" :data-cy="`input-quantidade-${idx}`"
                                           class="form-input py-2 font-mono"
                                           :placeholder="getItem(row.item_id)?.unidade || '0'">
                                </div>
                                <div>
                                    <InputLabel value="Preço unit. (opcional)" class="!text-xs" />
                                    <InputMoney v-model="row.valor_unitario" class="form-input py-2" />
                                </div>
                            </div>

                            <div v-if="rowSubtotal(row) > 0"
                                 class="text-xs text-slate-600 text-right">
                                Subtotal: <strong>{{ brl(rowSubtotal(row)) }}</strong>
                            </div>
                        </div>
                    </div>

                    <button type="button" @click="adicionarItem" data-cy="btn-adicionar-item"
                            class="mt-3 w-full border-2 border-dashed border-slate-300 rounded-lg py-3 text-sm text-slate-600 hover:bg-slate-50 hover:border-macaybas-primary hover:text-macaybas-primary transition">
                        + Adicionar outro item
                    </button>

                    <div v-if="valorTotal > 0"
                         class="mt-3 bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-right">
                        <span class="text-sm text-emerald-800">Total da nota:</span>
                        <strong class="text-lg text-emerald-900 ml-2">{{ brl(valorTotal) }}</strong>
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <button @click="avancar" :disabled="!podeAvancar1" data-cy="btn-continuar"
                            class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2: DETALHES (fornecedor + nº doc + data) -->
        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Quem trouxe?</h2>
                <p class="text-base text-slate-600">
                    {{ totalItens }} {{ totalItens === 1 ? 'item' : 'itens' }} ·
                    <span v-if="valorTotal > 0">Total: <strong>{{ brl(valorTotal) }}</strong></span>
                    <span v-else>Sem valor informado</span>
                </p>

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
                    <InputDate v-model="form.data_recebimento" :max="hojeBR()" />
                </div>

                <div>
                    <InputLabel value="Observações (opcional)" />
                    <textarea v-model="form.observacoes" rows="2" maxlength="500"
                              class="form-input" placeholder="Algo que precisa lembrar?"></textarea>
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" data-cy="btn-continuar"
                            class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 3: CONFERÊNCIA -->
        <div v-if="passo === 3" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Confere os dados?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Antes de salvar, dê uma olhada. Se precisar mudar algo, clique em <strong>Trocar</strong>.
                    </p>
                </div>

                <div class="space-y-3">
                    <!-- Cabeçalho da nota -->
                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs uppercase tracking-wider text-slate-500">Cabeçalho</div>
                            <div class="font-semibold mt-1">Armazém: {{ armazemAtual?.nome }}</div>
                            <div v-if="fornecedorAtual" class="text-sm text-slate-600">Fornecedor: {{ fornecedorAtual.nome }}</div>
                            <div v-if="form.numero_documento" class="text-sm text-slate-600">Nº documento: {{ form.numero_documento }}</div>
                            <div class="text-sm text-slate-600">Data: {{ dataBR(form.data_recebimento) }}</div>
                        </div>
                        <button @click="irPara(2)" class="text-sm text-macaybas-primary hover:underline">Trocar</button>
                    </div>

                    <!-- Tabela de itens -->
                    <div class="p-4 rounded-lg border border-slate-200 bg-white">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <div class="text-xs uppercase tracking-wider text-slate-500">
                                    Itens ({{ totalItens }})
                                </div>
                            </div>
                            <button @click="irPara(1)" class="text-sm text-macaybas-primary hover:underline">Trocar</button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs uppercase text-slate-500 border-b border-slate-200">
                                        <th class="py-2 pr-2">Produto</th>
                                        <th class="py-2 pr-2 text-right">Qtd</th>
                                        <th class="py-2 pr-2 text-right">Unit.</th>
                                        <th class="py-2 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, idx) in form.items" :key="idx"
                                        class="border-b border-slate-100 last:border-0">
                                        <td class="py-2 pr-2 font-medium">{{ getItem(row.item_id)?.nome || '—' }}</td>
                                        <td class="py-2 pr-2 text-right font-mono">
                                            {{ row.quantidade }} {{ getItem(row.item_id)?.unidade }}
                                        </td>
                                        <td class="py-2 pr-2 text-right font-mono text-slate-600">
                                            {{ rowSubtotal(row) > 0 ? brl(parseFloat(String(row.valor_unitario).replace(',', '.')) || 0) : '—' }}
                                        </td>
                                        <td class="py-2 text-right font-mono font-semibold">
                                            {{ rowSubtotal(row) > 0 ? brl(rowSubtotal(row)) : '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="valorTotal > 0">
                                    <tr class="border-t-2 border-slate-300">
                                        <td colspan="3" class="py-2 text-right font-semibold">Total da nota:</td>
                                        <td class="py-2 text-right font-mono font-bold text-emerald-700">
                                            {{ brl(valorTotal) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div v-if="form.observacoes" class="p-4 rounded-lg border border-slate-200 bg-white">
                        <div class="text-xs uppercase tracking-wider text-slate-500">Observações</div>
                        <div class="text-sm mt-1">{{ form.observacoes }}</div>
                    </div>
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="form.processing" data-cy="confirmar"
                            class="btn-primary px-8 py-3 text-base">
                        {{ form.processing ? 'Salvando…' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO 4: SUCESSO -->
        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
            <div class="card-body text-center space-y-5 py-8">
                <div class="text-6xl">📦</div>
                <h2 class="text-2xl font-semibold text-slate-900">Mercadoria recebida!</h2>
                <p class="text-base text-slate-600">
                    <strong>{{ sucesso.qtdItens }}</strong>
                    {{ sucesso.qtdItens === 1 ? 'item recebido' : 'itens recebidos' }}
                    <span v-if="sucesso.qtdItens === 1 && sucesso.primeiroItem">
                        — {{ sucesso.primeiroItem }}
                    </span>
                    <br>
                    <span v-if="sucesso.armazem" class="text-sm">Guardado em: {{ sucesso.armazem }}</span><br>
                    <span v-if="sucesso.valorTotal > 0">Total: <strong>{{ brl(sucesso.valorTotal) }}</strong></span>
                </p>
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-left">
                    <ul class="text-sm text-emerald-800 space-y-1">
                        <li>✓ Saldos somados no armazém</li>
                        <li>✓ {{ sucesso.qtdItens === 1 ? 'Entrada registrada' : `${sucesso.qtdItens} entradas registradas` }} no histórico</li>
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

        <!-- Modal inline: cadastrar produto novo sem sair do assistente -->
        <Teleport to="body">
            <div v-if="novoItem.modalAberto.value" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novoItem.fechar"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <h3 class="text-lg font-semibold mb-1">Novo produto de estoque</h3>
                    <p class="text-xs text-slate-500 mb-3">Cadastro rápido sem sair do assistente.</p>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Nome *" />
                            <input v-model="novoItem.form.value.nome" data-cy="item-nome"
                                class="form-input" placeholder="Ex.: Ração bovino" />
                        </div>
                        <div>
                            <InputLabel value="Unidade *" />
                            <select v-model="novoItem.form.value.unidade" class="form-select">
                                <option value="un">unidade</option>
                                <option value="kg">kg</option>
                                <option value="L">litro</option>
                                <option value="sc">saca</option>
                                <option value="cx">caixa</option>
                            </select>
                        </div>
                    </div>
                    <p v-if="novoItem.erro.value" class="mt-3 text-xs text-red-700">{{ novoItem.erro.value }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoItem.fechar" class="btn-outline">Cancelar</button>
                        <button @click="novoItem.salvar" data-cy="item-salvar"
                                :disabled="novoItem.salvando.value || !novoItem.form.value.nome?.trim()"
                                class="btn-primary">
                            {{ novoItem.salvando.value ? 'Salvando…' : 'Cadastrar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
