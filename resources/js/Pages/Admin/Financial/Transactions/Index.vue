<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import MobileFilters from '@/Components/MobileFilters.vue';
import InputDate from '@/Components/InputDate.vue';
import InputLabel from '@/Components/InputLabel.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import { brl, dataBR, hojeBR } from '@/utils/format.js';
import { useForm } from '@inertiajs/vue3';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';

const props = defineProps({
    transactions: Object,
    filters: Object,
    accounts: Array,
    totais: Object,
});

// B4.4 fix · defaults '' garantem que option value="" seja auto-selecionado
const filtros = reactive({
    tipo: '',
    status: '',
    account_id: '',
    from: '',
    to: '',
    ...props.filters,
});
const confirmDelete = ref(null);

function filtrar() {
    router.get(route('admin.financeiro.transacoes.index'), filtros, { preserveState: true, replace: true });
}

function askDelete(t) { confirmDelete.value = t; }
function doDelete() {
    router.delete(route('admin.financeiro.transacoes.destroy', confirmDelete.value.id), {
        onSuccess: () => (confirmDelete.value = null),
    });
}

const payModal = ref(null);
useBodyScrollLock(computed(() => !!payModal.value));
const payForm = useForm({ data_pagamento: '', forma_pagamento: 'pix' });
function askPay(t) {
    payModal.value = t;
    payForm.data_pagamento = hojeBR();
    payForm.forma_pagamento = 'pix';
}
function confirmPay() {
    payForm.post(route('admin.financeiro.transacoes.pay', payModal.value.id), {
        onSuccess: () => { payModal.value = null; payForm.reset(); },
    });
}

const statusBadge = (s) => ({
    pendente: 'badge-yellow',
    pago: 'badge-green',
    atrasado: 'badge-red',
    cancelado: 'badge-slate',
})[s] || 'badge-slate';

// Hub v4 · Modo "Pagar contas" — quando o usuário chega via card do Hub
// com ?status=pendente&tipo=despesa, a tela troca de "Lançamentos financeiros"
// (título técnico) para "Contas a pagar" com banner instrutivo.
const modoPagar = computed(() =>
    props.filters?.status === 'pendente' && props.filters?.tipo === 'despesa'
);
const modoReceber = computed(() =>
    props.filters?.status === 'pendente' && props.filters?.tipo === 'receita'
);
</script>

<template>
    <Head :title="modoPagar ? 'Contas a pagar' : modoReceber ? 'Contas a receber' : 'Lançamentos financeiros'" />
    <AdminLayout>
        <template #page-title>Financeiro</template>

        <PageHeader
            :title="modoPagar ? 'Contas a pagar' : modoReceber ? 'Contas a receber' : 'Lançamentos financeiros'"
            :subtitle="modoPagar ? 'Veja abaixo o que ainda não foi pago. Clique em \'Marcar como pago\' para dar baixa.' : (modoReceber ? 'O que ainda está para cair no caixa. Clique em \'Registrar recebimento\' para dar baixa.' : 'Contas a pagar, contas a receber e fluxo de caixa')"
        >
            <template #actions>
                <Link :href="route('admin.financeiro.transacoes.create')" class="btn-primary">Novo lançamento</Link>
            </template>
        </PageHeader>

        <!-- Banner contextual quando chega pelo card do Hub "Pagar conta" / "Receber pagamento" -->
        <div v-if="modoPagar" class="mb-6 p-4 rounded-xl border-l-4 border-amber-400 bg-amber-50 flex items-start gap-3">
            <span class="text-2xl flex-shrink-0" aria-hidden="true">💳</span>
            <div class="min-w-0 flex-1">
                <div class="font-semibold text-amber-900">Contas a pagar</div>
                <div class="text-sm text-amber-800 mt-0.5">
                    Cada linha abaixo é uma conta que ainda não foi paga. Clique no botão <strong>verde ✓</strong> ao lado de cada conta para marcá-la como paga.
                </div>
            </div>
            <Link :href="route('admin.financeiro.transacoes.index')" class="text-xs text-amber-800 hover:underline flex-shrink-0">Ver tudo</Link>
        </div>
        <div v-else-if="modoReceber" class="mb-6 p-4 rounded-xl border-l-4 border-emerald-400 bg-emerald-50 flex items-start gap-3">
            <span class="text-2xl flex-shrink-0" aria-hidden="true">💰</span>
            <div class="min-w-0 flex-1">
                <div class="font-semibold text-emerald-900">Contas a receber</div>
                <div class="text-sm text-emerald-800 mt-0.5">
                    Dinheiro que ainda está para cair. Clique no <strong>✓</strong> ao lado da conta para marcar como recebida.
                </div>
            </div>
            <Link :href="route('admin.financeiro.transacoes.index')" class="text-xs text-emerald-800 hover:underline flex-shrink-0">Ver tudo</Link>
        </div>

        <!-- KPIs clicáveis — filtram o tipo correspondente -->
        <div class="grid gap-4 sm:grid-cols-3 mb-6">
            <button @click="filtros.tipo = 'receita'; filtrar()"
                    :class="[filtros.tipo === 'receita' ? 'ring-2 ring-emerald-300' : '']"
                    class="card p-5 text-left hover:shadow-md hover:ring-emerald-200 transition-all cursor-pointer">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Receitas no filtro</div>
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <div class="mt-1 text-xl font-bold text-green-700">{{ brl(totais.receitas) }}</div>
                <div class="text-xs text-slate-400 mt-1">Clique para filtrar receitas</div>
            </button>
            <button @click="filtros.tipo = 'despesa'; filtrar()"
                    :class="[filtros.tipo === 'despesa' ? 'ring-2 ring-red-300' : '']"
                    class="card p-5 text-left hover:shadow-md hover:ring-red-200 transition-all cursor-pointer">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Despesas no filtro</div>
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                </div>
                <div class="mt-1 text-xl font-bold text-red-700">{{ brl(totais.despesas) }}</div>
                <div class="text-xs text-slate-400 mt-1">Clique para filtrar despesas</div>
            </button>
            <button @click="filtros.tipo = ''; filtrar()"
                    :class="[!filtros.tipo ? 'ring-2 ring-macaybas-primary-200' : '']"
                    class="card p-5 text-left hover:shadow-md transition-all cursor-pointer">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Saldo</div>
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8"/></svg>
                </div>
                <div class="mt-1 text-xl font-bold" :class="totais.saldo >= 0 ? 'text-macaybas-primary' : 'text-red-700'">{{ brl(totais.saldo) }}</div>
                <div class="text-xs text-slate-400 mt-1">Clique para ver tudo</div>
            </button>
        </div>

        <!-- F4.2 · Filtros colapsáveis em mobile -->
        <MobileFilters cols="sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <select v-model="filtros.tipo" @change="filtrar" class="form-select">
                <option value="">Todos os tipos</option>
                <option value="receita">Receitas</option>
                <option value="despesa">Despesas</option>
            </select>
            <select v-model="filtros.status" @change="filtrar" class="form-select">
                <option value="">Todos os status</option>
                <option value="pendente">Pendente</option>
                <option value="pago">Pago</option>
                <option value="atrasado">Atrasado</option>
                <option value="cancelado">Cancelado</option>
            </select>
            <select v-model="filtros.account_id" @change="filtrar" class="form-select">
                <option value="">Todas as contas</option>
                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.nome }}</option>
            </select>
            <InputDate v-model="filtros.from" :max="filtros.to || undefined" @update:modelValue="filtrar" />
            <InputDate v-model="filtros.to" :min="filtros.from || undefined" @update:modelValue="filtrar" />
        </MobileFilters>

        <DataTable
            :columns="[
                { key: 'data_vencimento', label: 'Vencimento', format: dataBR },
                { key: 'descricao', label: 'Descrição' },
                { key: 'tipo', label: 'Tipo' },
                { key: 'account', label: 'Conta' },
                { key: 'valor', label: 'Valor', align: 'right', format: brl },
                { key: 'status', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="transactions.data"
        >
            <template #cell-tipo="{ row }">
                <span :class="row.tipo === 'receita' ? 'badge-green' : 'badge-red'">{{ row.tipo }}</span>
            </template>
            <template #cell-account="{ row }">
                {{ row.account?.nome ?? '—' }}
            </template>
            <template #cell-status="{ row }">
                <span :class="statusBadge(row.status)">{{ row.status }}</span>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex items-center gap-1 justify-end">
                    <ActionIcon v-if="row.status === 'pendente'" type="pay"
                                :title="row.tipo === 'receita' ? 'Marcar como recebida' : 'Marcar como paga'"
                                @click="askPay(row)" />
                    <Link :href="route('admin.financeiro.transacoes.edit', row.id)" class="inline-flex">
                        <ActionIcon type="edit" title="Editar lançamento" />
                    </Link>
                    <ActionIcon type="delete" title="Excluir lançamento" @click="askDelete(row)" />
                </div>
            </template>
        </DataTable>

        <!-- Modal: Marcar como pago -->
        <Teleport to="body">
            <div v-if="payModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="payModal = null"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <h3 class="text-lg font-semibold text-slate-900 mb-1">
                        {{ payModal.tipo === 'receita' ? 'Marcar como recebida' : 'Marcar conta como paga' }}
                    </h3>
                    <p class="text-sm text-slate-500 mb-4">
                        <strong>{{ payModal.descricao }}</strong> · <strong class="text-slate-900">{{ brl(payModal.valor) }}</strong>
                    </p>
                    <div class="space-y-3">
                        <div>
                            <InputLabel :value="payModal.tipo === 'receita' ? 'Quando o dinheiro caiu?' : 'Quando você pagou?'" />
                            <InputDate v-model="payForm.data_pagamento" :max="new Date().toISOString().slice(0,10)" required />
                            <p v-if="payForm.errors.data_pagamento" class="text-xs text-red-600 mt-1">{{ payForm.errors.data_pagamento }}</p>
                        </div>
                        <div>
                            <InputLabel :value="payModal.tipo === 'receita' ? 'Como recebeu?' : 'Como pagou?'" />
                            <select v-model="payForm.forma_pagamento" class="form-select">
                                <option value="pix">PIX</option>
                                <option value="dinheiro">Dinheiro</option>
                                <option value="transferencia">Transferência bancária</option>
                                <option value="boleto">Boleto</option>
                                <option value="cartao">Cartão</option>
                                <option value="cheque">Cheque</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="payModal = null" class="btn-outline">Cancelar</button>
                        <button @click="confirmPay" :disabled="payForm.processing" class="btn-primary bg-emerald-600 hover:bg-emerald-700">
                            {{ payForm.processing ? 'Salvando…' : 'Confirmar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <div v-if="transactions.links" class="mt-4 flex gap-2 flex-wrap justify-end">
            <Link v-for="link in transactions.links" :key="link.label"
                  :href="link.url ?? '#'"
                  v-html="link.label"
                  :class="['btn-outline btn-sm', link.active ? '!bg-macaybas-primary !text-white !border-transparent' : '', !link.url ? 'opacity-40 pointer-events-none' : '']" />
        </div>

        <ConfirmModal
            :show="!!confirmDelete"
            title="Excluir lançamento"
            :message="`Excluir ${confirmDelete?.descricao}?`"
            @cancel="confirmDelete = null"
            @confirm="doDelete"
        />
    </AdminLayout>
</template>
