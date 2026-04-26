<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { brl } from '@/utils/format.js';

defineProps({ kpis: Object });
</script>

<template>
    <Head title="Financeiro" />
    <AdminLayout>
        <template #page-title>Financeiro</template>

        <PageHeader title="Financeiro"
                    subtitle="Contas a pagar, contas a receber, fluxo de caixa e saldos por banco" />

        <!-- KPIs principais -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Saldo total</div>
                <div class="mt-1 text-xl font-bold" :class="kpis.saldo_total >= 0 ? 'text-emerald-700' : 'text-red-700'">
                    {{ brl(kpis.saldo_total) }}
                </div>
                <div class="text-xs text-slate-500 mt-1">{{ kpis.contas_ativas }} conta(s) ativa(s)</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Receitas do mês</div>
                <div class="mt-1 text-xl font-bold text-emerald-700">{{ brl(kpis.receitas_mes) }}</div>
                <div class="text-xs text-slate-500 mt-1">Recebido este mês</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Despesas do mês</div>
                <div class="mt-1 text-xl font-bold text-red-700">{{ brl(kpis.despesas_mes) }}</div>
                <div class="text-xs text-slate-500 mt-1">Pago este mês</div>
            </div>
            <div class="card p-4" :class="kpis.atrasadas > 0 ? 'ring-2 ring-red-300 bg-red-50' : ''">
                <div class="text-xs uppercase tracking-wider font-semibold" :class="kpis.atrasadas > 0 ? 'text-red-700' : 'text-slate-500'">
                    {{ kpis.atrasadas > 0 ? '⚠ Atrasadas' : 'Atrasadas' }}
                </div>
                <div class="mt-1 text-xl font-bold" :class="kpis.atrasadas > 0 ? 'text-red-800' : 'text-slate-900'">
                    {{ kpis.atrasadas }}
                </div>
                <div class="text-xs text-slate-500 mt-1">Vencimento ultrapassado</div>
            </div>
        </div>

        <!-- Atalhos rápidos -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mb-6">
            <Link :href="route('admin.fluxos.registrar-despesa')"
                  class="card hover:ring-macaybas-primary hover:shadow-md transition group">
                <div class="card-body flex items-center gap-3">
                    <div class="flex-shrink-0 h-10 w-10 rounded-xl bg-red-50 ring-1 ring-red-200 flex items-center justify-center text-xl">💸</div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-900">Registrar despesa</div>
                        <div class="text-xs text-slate-500">Assistente passo a passo</div>
                    </div>
                </div>
            </Link>
            <Link :href="route('admin.fluxos.registrar-receita')"
                  class="card hover:ring-macaybas-primary hover:shadow-md transition group">
                <div class="card-body flex items-center gap-3">
                    <div class="flex-shrink-0 h-10 w-10 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 flex items-center justify-center text-xl">💰</div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-900">Registrar receita</div>
                        <div class="text-xs text-slate-500">Assistente passo a passo</div>
                    </div>
                </div>
            </Link>
            <Link :href="route('admin.financeiro.transacoes.index') + '?status=pendente&tipo=despesa'"
                  class="card hover:ring-macaybas-primary hover:shadow-md transition group">
                <div class="card-body flex items-center gap-3">
                    <div class="flex-shrink-0 h-10 w-10 rounded-xl bg-blue-50 ring-1 ring-blue-200 flex items-center justify-center text-xl">💳</div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-900">Pagar contas</div>
                        <div class="text-xs text-slate-500">{{ kpis.contas_pagar }} pendente(s) próximos 30d</div>
                    </div>
                </div>
            </Link>
        </div>

        <!-- Cards de navegação -->
        <div class="grid gap-4 md:grid-cols-2">
            <Link :href="route('admin.financeiro.transacoes.index')"
                  class="card hover:ring-macaybas-primary hover:shadow-md transition group">
                <div class="card-body flex items-start gap-4">
                    <div class="flex-shrink-0 h-12 w-12 rounded-xl bg-macaybas-primary-50 ring-1 ring-macaybas-primary-200 flex items-center justify-center text-2xl group-hover:scale-110 transition">
                        📊
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold mb-1 flex items-center gap-2">
                            Transações
                            <svg class="h-4 w-4 text-slate-400 group-hover:text-macaybas-primary transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </h3>
                        <p class="text-sm text-slate-500">Lista de receitas e despesas, com filtros, status e ações por linha.</p>
                    </div>
                </div>
            </Link>
            <Link :href="route('admin.financeiro.contas.index')"
                  class="card hover:ring-macaybas-primary hover:shadow-md transition group">
                <div class="card-body flex items-start gap-4">
                    <div class="flex-shrink-0 h-12 w-12 rounded-xl bg-amber-50 ring-1 ring-amber-200 flex items-center justify-center text-2xl group-hover:scale-110 transition">
                        🏦
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold mb-1 flex items-center gap-2">
                            Contas financeiras
                            <svg class="h-4 w-4 text-slate-400 group-hover:text-macaybas-primary transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </h3>
                        <p class="text-sm text-slate-500">Bancos, caixa interno, dinheiro físico — necessárias antes de lançar movimentos.</p>
                    </div>
                </div>
            </Link>
        </div>
    </AdminLayout>
</template>
