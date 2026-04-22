<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputDate from '@/Components/InputDate.vue';
import { brl, dataBR } from '@/utils/format.js';

const props = defineProps({
    transactions: Object,
    filters: Object,
    accounts: Array,
    totais: Object,
});

const filtros = reactive({ ...props.filters });
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

function pay(t) {
    const d = prompt('Data de pagamento (dd/mm/aaaa):', new Date().toLocaleDateString('pt-BR'));
    if (!d) return;
    const [dd, mm, yy] = d.split('/');
    router.post(route('admin.financeiro.transacoes.pay', t.id), {
        data_pagamento: `${yy}-${mm}-${dd}`,
        forma_pagamento: 'pix',
    });
}

const statusBadge = (s) => ({
    pendente: 'badge-yellow',
    pago: 'badge-green',
    atrasado: 'badge-red',
    cancelado: 'badge-slate',
})[s] || 'badge-slate';
</script>

<template>
    <Head title="Lançamentos financeiros" />
    <AdminLayout>
        <template #page-title>Financeiro</template>

        <PageHeader title="Lançamentos financeiros" subtitle="Contas a pagar, contas a receber e fluxo de caixa">
            <template #actions>
                <Link :href="route('admin.financeiro.transacoes.create')" class="btn-primary">Novo lançamento</Link>
            </template>
        </PageHeader>

        <!-- KPIs -->
        <div class="grid gap-4 sm:grid-cols-3 mb-6">
            <div class="card p-5"><div class="text-xs uppercase tracking-wider text-slate-500">Receitas no filtro</div><div class="mt-1 text-xl font-bold text-green-700">{{ brl(totais.receitas) }}</div></div>
            <div class="card p-5"><div class="text-xs uppercase tracking-wider text-slate-500">Despesas no filtro</div><div class="mt-1 text-xl font-bold text-red-700">{{ brl(totais.despesas) }}</div></div>
            <div class="card p-5"><div class="text-xs uppercase tracking-wider text-slate-500">Saldo</div><div class="mt-1 text-xl font-bold" :class="totais.saldo >= 0 ? 'text-macaybas-primary' : 'text-red-700'">{{ brl(totais.saldo) }}</div></div>
        </div>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-5">
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
            </div>
        </div>

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
                <div class="flex items-center gap-2 justify-end">
                    <button v-if="row.status === 'pendente'" @click="pay(row)" class="text-green-700 hover:underline">Quitar</button>
                    <Link :href="route('admin.financeiro.transacoes.edit', row.id)" class="text-slate-500 hover:text-macaybas-primary">Editar</Link>
                    <button @click="askDelete(row)" class="text-red-600 hover:underline">Excluir</button>
                </div>
            </template>
        </DataTable>

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
