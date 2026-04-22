<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import { brl, dataBR } from '@/utils/format.js';

defineProps({
    widgets: Object,
    contas_a_pagar: Array,
    contas_a_receber: Array,
    itens_baixo_estoque: Array,
    tarefas_pendentes: Array,
});

// Intervalo do mês corrente (usado nos filtros dos drill-downs)
const hoje = new Date();
const mesInicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1).toISOString().slice(0, 10);
const mesFim = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0).toISOString().slice(0, 10);

const statusBadge = (status) => ({
    pendente: 'badge-yellow',
    pago: 'badge-green',
    atrasado: 'badge-red',
    cancelado: 'badge-slate',
    em_andamento: 'badge-blue',
    concluida: 'badge-green',
})[status] || 'badge-slate';

const prioridadeBadge = (p) => ({
    baixa: 'badge-slate',
    media: 'badge-blue',
    alta: 'badge-yellow',
    urgente: 'badge-red',
})[p] || 'badge-slate';
</script>

<template>
    <Head title="Dashboard" />
    <AdminLayout>
        <template #page-title>Dashboard</template>

        <PageHeader title="Visão geral" subtitle="Resumo da operação da fazenda" />

        <!-- KPIs clicáveis — cada card navega pro módulo correspondente com filtro -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
            <Link :href="route('admin.financeiro.transacoes.index', { tipo: 'receita', status: 'pago', from: mesInicio, to: mesFim })"
                  class="card p-5 cursor-pointer hover:shadow-md hover:ring-macaybas-primary-200 transition-all group">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Receitas do mês</div>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-macaybas-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
                <div class="mt-2 text-2xl font-bold text-green-700">{{ brl(widgets.financeiro.receitas_mes) }}</div>
                <div class="text-xs text-slate-400 mt-1">Clique para ver detalhes</div>
            </Link>
            <Link :href="route('admin.financeiro.transacoes.index', { tipo: 'despesa', from: mesInicio, to: mesFim })"
                  class="card p-5 cursor-pointer hover:shadow-md hover:ring-red-200 transition-all group">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Despesas do mês</div>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-red-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
                <div class="mt-2 text-2xl font-bold text-red-700">{{ brl(widgets.financeiro.despesas_mes) }}</div>
                <div class="text-xs text-slate-400 mt-1">Clique para ver origem</div>
            </Link>
            <Link :href="route('admin.financeiro.transacoes.index', { from: mesInicio, to: mesFim })"
                  class="card p-5 cursor-pointer hover:shadow-md transition-all group">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Saldo do mês</div>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-macaybas-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
                <div class="mt-2 text-2xl font-bold"
                     :class="widgets.financeiro.saldo_mes >= 0 ? 'text-macaybas-primary' : 'text-red-700'">
                    {{ brl(widgets.financeiro.saldo_mes) }}
                </div>
                <div class="text-xs text-slate-400 mt-1">Ver lançamentos completos</div>
            </Link>
            <Link :href="route('admin.rebanho.animais.index', { status: 'ativo' })"
                  class="card p-5 cursor-pointer hover:shadow-md hover:ring-macaybas-primary-200 transition-all group">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Rebanho</div>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-macaybas-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
                <div class="mt-2 text-2xl font-bold text-slate-900">{{ widgets.rebanho.total }}</div>
                <div class="text-xs text-slate-500 mt-1">animais ativos — clique para listar</div>
            </Link>
        </div>

        <!-- Alertas com bullets clicáveis pro drill-down -->
        <div v-if="widgets.financeiro.contas_atrasadas > 0 || widgets.estoque.itens_baixo_estoque > 0 || widgets.tarefas.atrasadas > 0"
             class="card mb-8 border-l-4 border-amber-400">
            <div class="card-body">
                <h2 class="card-title mb-3">⚠️ Alertas</h2>
                <ul class="space-y-2 text-sm">
                    <li v-if="widgets.financeiro.contas_atrasadas > 0">
                        <Link :href="route('admin.financeiro.transacoes.index', { status: 'atrasado' })"
                              class="flex items-center gap-2 p-2 rounded-lg hover:bg-red-50 transition-colors group">
                            <span class="badge-red">Financeiro</span>
                            <span><strong>{{ widgets.financeiro.contas_atrasadas }}</strong> contas atrasadas</span>
                            <svg class="h-4 w-4 text-slate-400 group-hover:text-red-600 ml-auto transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </Link>
                    </li>
                    <li v-if="widgets.estoque.itens_baixo_estoque > 0">
                        <Link :href="route('admin.estoque.itens.index')"
                              class="flex items-center gap-2 p-2 rounded-lg hover:bg-amber-50 transition-colors group">
                            <span class="badge-yellow">Estoque</span>
                            <span><strong>{{ widgets.estoque.itens_baixo_estoque }}</strong> itens abaixo do mínimo</span>
                            <svg class="h-4 w-4 text-slate-400 group-hover:text-amber-600 ml-auto transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </Link>
                    </li>
                    <li v-if="widgets.tarefas.atrasadas > 0">
                        <Link :href="route('admin.tarefas.index', { status: 'atrasada' })"
                              class="flex items-center gap-2 p-2 rounded-lg hover:bg-red-50 transition-colors group">
                            <span class="badge-red">Tarefas</span>
                            <span><strong>{{ widgets.tarefas.atrasadas }}</strong> tarefas atrasadas</span>
                            <svg class="h-4 w-4 text-slate-400 group-hover:text-red-600 ml-auto transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </Link>
                    </li>
                </ul>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Contas a pagar -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-semibold text-slate-900">Contas a pagar (próximos 30 dias)</h2>
                </div>
                <DataTable
                    :columns="[
                        { key: 'descricao', label: 'Descrição' },
                        { key: 'data_vencimento', label: 'Vencimento', format: dataBR },
                        { key: 'valor', label: 'Valor', align: 'right', format: brl },
                    ]"
                    :rows="contas_a_pagar"
                    empty-text="Nada a pagar nos próximos 30 dias."
                />
            </div>

            <!-- Contas a receber -->
            <div>
                <h2 class="text-base font-semibold text-slate-900 mb-3">Contas a receber (próximos 30 dias)</h2>
                <DataTable
                    :columns="[
                        { key: 'descricao', label: 'Descrição' },
                        { key: 'data_vencimento', label: 'Vencimento', format: dataBR },
                        { key: 'valor', label: 'Valor', align: 'right', format: brl },
                    ]"
                    :rows="contas_a_receber"
                    empty-text="Nada a receber nos próximos 30 dias."
                />
            </div>

            <!-- Itens com estoque baixo -->
            <div>
                <h2 class="text-base font-semibold text-slate-900 mb-3">Itens com estoque baixo</h2>
                <DataTable
                    :columns="[
                        { key: 'nome', label: 'Item' },
                        { key: 'saldo', label: 'Saldo', align: 'right' },
                        { key: 'estoque_minimo', label: 'Mínimo', align: 'right' },
                    ]"
                    :rows="itens_baixo_estoque"
                    empty-text="Estoque em ordem."
                >
                    <template #cell-saldo="{ row }">
                        <span class="text-red-700 font-semibold">{{ Number(row.saldo || 0).toLocaleString('pt-BR') }} {{ row.unidade }}</span>
                    </template>
                </DataTable>
            </div>

            <!-- Tarefas pendentes -->
            <div>
                <h2 class="text-base font-semibold text-slate-900 mb-3">Tarefas pendentes</h2>
                <DataTable
                    :columns="[
                        { key: 'titulo', label: 'Tarefa' },
                        { key: 'prioridade', label: 'Prioridade' },
                        { key: 'data_vencimento', label: 'Vence em', format: dataBR },
                    ]"
                    :rows="tarefas_pendentes"
                    empty-text="Sem tarefas pendentes."
                >
                    <template #cell-prioridade="{ row }">
                        <span :class="prioridadeBadge(row.prioridade)">{{ row.prioridade }}</span>
                    </template>
                </DataTable>
            </div>
        </div>
    </AdminLayout>
</template>
