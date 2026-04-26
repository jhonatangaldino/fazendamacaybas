<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import KpiDrawer from '@/Components/KpiDrawer.vue';
import { brl, dataBR } from '@/utils/format.js';

const drawer = ref(null);
function abrir(nome) { drawer.value = nome; }
function fechar() { drawer.value = null; }

const props = defineProps({
    widgets: Object,
    contas_a_pagar: Array,
    contas_a_receber: Array,
    itens_baixo_estoque: Array,
    tarefas_pendentes: Array,
    drillReceitasMes: Array,
    drillDespesasMes: Array,
});

// BLOCO 4.4 onboarding · Tenant ainda sem dados? Mostra empty state guiando
// para o primeiro cadastro. Resolve fricção do piloto Joaquim:
// "Dashboard com zero em tudo me deu sensação ruim"
const ehOnboarding = computed(() => {
    const w = props.widgets;
    return (w?.financeiro?.receitas_mes ?? 0) === 0
        && (w?.financeiro?.despesas_mes ?? 0) === 0
        && (w?.rebanho?.total ?? 0) === 0
        && (w?.financeiro?.contas_atrasadas ?? 0) === 0
        && (w?.tarefas?.pendentes ?? 0) === 0;
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
    <Head title="Painel" />
    <AdminLayout>
        <template #page-title>Painel</template>

        <PageHeader title="Visão geral" subtitle="Resumo da operação da fazenda" />

        <!-- BLOCO 4.4 · Onboarding empty state — substitui dashboard zerado por CTAs claros
             Resolve fricção do piloto: 'parece que tá quebrado' quando todos KPIs = 0 -->
        <div v-if="ehOnboarding" class="mb-8 rounded-2xl bg-gradient-to-br from-macaybas-primary-50 to-emerald-50 ring-1 ring-macaybas-primary-200 p-6 sm:p-8">
            <div class="max-w-2xl">
                <div class="text-3xl mb-3">🌱</div>
                <h2 class="text-2xl font-serif font-bold text-slate-900 mb-2">Vamos começar a usar a fazenda no sistema!</h2>
                <p class="text-slate-700 mb-5">
                    Você ainda não cadastrou movimentos. Comece pelo que é mais comum no dia a dia —
                    os números deste painel vão aparecer assim que você registrar.
                </p>

                <div class="grid sm:grid-cols-3 gap-3">
                    <Link :href="route('admin.fluxos.cadastrar-animal')"
                          class="flex items-start gap-3 p-3.5 rounded-xl bg-white ring-1 ring-slate-200 hover:ring-macaybas-primary-400 hover:shadow-sm transition">
                        <div class="text-2xl">🐮</div>
                        <div class="min-w-0">
                            <div class="font-semibold text-slate-900 text-sm">Cadastrar 1º animal</div>
                            <div class="text-xs text-slate-500 mt-0.5">Espécie, brinco, lote — passo a passo</div>
                        </div>
                    </Link>
                    <Link :href="route('admin.fluxos.registrar-despesa')"
                          class="flex items-start gap-3 p-3.5 rounded-xl bg-white ring-1 ring-slate-200 hover:ring-macaybas-primary-400 hover:shadow-sm transition">
                        <div class="text-2xl">💸</div>
                        <div class="min-w-0">
                            <div class="font-semibold text-slate-900 text-sm">Registrar 1ª despesa</div>
                            <div class="text-xs text-slate-500 mt-0.5">Compra de ração, sal, vacina…</div>
                        </div>
                    </Link>
                    <Link :href="route('admin.fluxos.criar-tarefa')"
                          class="flex items-start gap-3 p-3.5 rounded-xl bg-white ring-1 ring-slate-200 hover:ring-macaybas-primary-400 hover:shadow-sm transition">
                        <div class="text-2xl">📋</div>
                        <div class="min-w-0">
                            <div class="font-semibold text-slate-900 text-sm">Criar 1ª tarefa</div>
                            <div class="text-xs text-slate-500 mt-0.5">"Vacinar lote A na 6ª"</div>
                        </div>
                    </Link>
                </div>

                <p class="mt-4 text-xs text-slate-500">
                    Prefere ver tudo organizado por categoria? Vai em <Link :href="route('admin.inicio')" class="text-macaybas-primary-700 font-medium hover:underline">Início</Link>.
                </p>
            </div>
        </div>

        <!-- KPIs: cada card abre um drawer lateral com o detalhamento (fecha com ESC/clique fora) -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
            <button @click="abrir('receitas')"
                    class="card p-5 text-left hover:shadow-md hover:ring-emerald-200 transition-all group">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Receitas do mês</div>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H3m12 0l-4-4m4 4l-4 4m10-8v8"/></svg>
                </div>
                <div class="mt-2 text-2xl font-bold text-green-700">{{ brl(widgets.financeiro.receitas_mes) }}</div>
                <div class="text-xs text-slate-400 mt-1">Ver quais receitas entraram</div>
            </button>
            <button @click="abrir('despesas')"
                    class="card p-5 text-left hover:shadow-md hover:ring-red-200 transition-all group">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Despesas do mês</div>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-red-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H3m12 0l-4-4m4 4l-4 4m10-8v8"/></svg>
                </div>
                <div class="mt-2 text-2xl font-bold text-red-700">{{ brl(widgets.financeiro.despesas_mes) }}</div>
                <div class="text-xs text-slate-400 mt-1">Ver onde o dinheiro saiu</div>
            </button>
            <button @click="abrir('saldo')"
                    class="card p-5 text-left hover:shadow-md transition-all group">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Saldo do mês</div>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-macaybas-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H3m12 0l-4-4m4 4l-4 4m10-8v8"/></svg>
                </div>
                <div class="mt-2 text-2xl font-bold"
                     :class="widgets.financeiro.saldo_mes >= 0 ? 'text-macaybas-primary' : 'text-red-700'">
                    {{ brl(widgets.financeiro.saldo_mes) }}
                </div>
                <div class="text-xs text-slate-400 mt-1">Receitas menos despesas</div>
            </button>
            <button @click="abrir('rebanho')"
                    class="card p-5 text-left hover:shadow-md hover:ring-macaybas-primary-200 transition-all group">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Rebanho</div>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-macaybas-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H3m12 0l-4-4m4 4l-4 4m10-8v8"/></svg>
                </div>
                <div class="mt-2 text-2xl font-bold text-slate-900">{{ widgets.rebanho.total }}</div>
                <div class="text-xs text-slate-500 mt-1">animais ativos — ver por espécie</div>
            </button>
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

        <!-- Drawers KPI: compõem o que está sendo somado -->
        <KpiDrawer :open="drawer === 'receitas'" title="Receitas pagas no mês"
                   :subtitle="`Total: ${brl(widgets.financeiro.receitas_mes)}`"
                   :full-link="{ href: route('admin.financeiro.transacoes.index', { tipo: 'receita', status: 'pago', from: mesInicio, to: mesFim }), label: 'Ver todas no Financeiro' }"
                   @close="fechar">
            <div v-if="!drillReceitasMes?.length" class="text-center text-slate-500 py-10">
                Sem receitas pagas neste mês ainda.
            </div>
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="r in drillReceitasMes" :key="r.id" class="py-3 flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-slate-900 truncate">{{ r.descricao }}</div>
                        <div class="text-xs text-slate-500 mt-0.5">Pago em {{ dataBR(r.data_pagamento) }}</div>
                    </div>
                    <div class="text-sm font-mono font-semibold text-emerald-700 flex-shrink-0">{{ brl(r.valor) }}</div>
                </li>
            </ul>
        </KpiDrawer>

        <KpiDrawer :open="drawer === 'despesas'" title="Despesas pagas no mês"
                   :subtitle="`Total: ${brl(widgets.financeiro.despesas_mes)}`"
                   :full-link="{ href: route('admin.financeiro.transacoes.index', { tipo: 'despesa', status: 'pago', from: mesInicio, to: mesFim }), label: 'Ver todas no Financeiro' }"
                   @close="fechar">
            <div v-if="!drillDespesasMes?.length" class="text-center text-slate-500 py-10">
                Sem despesas pagas neste mês ainda.
            </div>
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="d in drillDespesasMes" :key="d.id" class="py-3 flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-slate-900 truncate">{{ d.descricao }}</div>
                        <div class="text-xs text-slate-500 mt-0.5">Pago em {{ dataBR(d.data_pagamento) }}</div>
                    </div>
                    <div class="text-sm font-mono font-semibold text-red-700 flex-shrink-0">{{ brl(d.valor) }}</div>
                </li>
            </ul>
        </KpiDrawer>

        <KpiDrawer :open="drawer === 'saldo'" title="Saldo do mês"
                   :subtitle="`${brl(widgets.financeiro.receitas_mes)} de receitas − ${brl(widgets.financeiro.despesas_mes)} de despesas`"
                   :full-link="{ href: route('admin.financeiro.transacoes.index', { from: mesInicio, to: mesFim }), label: 'Ver lançamentos do mês' }"
                   @close="fechar">
            <div class="space-y-4">
                <div class="p-4 rounded-lg bg-emerald-50 border border-emerald-200">
                    <div class="text-xs uppercase tracking-wider text-emerald-700">Entradas (receitas pagas)</div>
                    <div class="mt-1 text-2xl font-bold text-emerald-700">+ {{ brl(widgets.financeiro.receitas_mes) }}</div>
                </div>
                <div class="p-4 rounded-lg bg-red-50 border border-red-200">
                    <div class="text-xs uppercase tracking-wider text-red-700">Saídas (despesas pagas)</div>
                    <div class="mt-1 text-2xl font-bold text-red-700">− {{ brl(widgets.financeiro.despesas_mes) }}</div>
                </div>
                <div class="p-4 rounded-lg" :class="widgets.financeiro.saldo_mes >= 0 ? 'bg-macaybas-primary-50 border border-macaybas-primary-200' : 'bg-red-100 border border-red-300'">
                    <div class="text-xs uppercase tracking-wider" :class="widgets.financeiro.saldo_mes >= 0 ? 'text-macaybas-primary-900' : 'text-red-800'">Saldo</div>
                    <div class="mt-1 text-3xl font-bold" :class="widgets.financeiro.saldo_mes >= 0 ? 'text-macaybas-primary' : 'text-red-700'">{{ brl(widgets.financeiro.saldo_mes) }}</div>
                </div>
                <p class="text-sm text-slate-600">
                    Inclui apenas lançamentos com status <strong>pago</strong> no mês corrente. Lançamentos em aberto
                    (a pagar / a receber) aparecem nas tabelas abaixo.
                </p>
            </div>
        </KpiDrawer>

        <KpiDrawer :open="drawer === 'rebanho'" title="Rebanho ativo"
                   :subtitle="`${widgets.rebanho.total} animal${widgets.rebanho.total === 1 ? '' : 'is'} em atividade`"
                   :full-link="{ href: route('admin.rebanho.animais.index', { status: 'ativo' }), label: 'Ver todos os animais' }"
                   @close="fechar">
            <div v-if="!widgets.rebanho.por_especie?.length" class="text-center text-slate-500 py-10">
                Sem animais ativos cadastrados.
            </div>
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="(e, i) in widgets.rebanho.por_especie" :key="i" class="py-3 flex items-center justify-between gap-3">
                    <div class="font-medium text-slate-900">{{ e.especie || '(sem espécie)' }}</div>
                    <div class="text-sm font-mono font-semibold text-macaybas-primary">{{ e.total }}</div>
                </li>
            </ul>
        </KpiDrawer>
    </AdminLayout>
</template>
