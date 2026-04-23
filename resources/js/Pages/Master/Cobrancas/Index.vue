<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';

defineProps({
    invoices: { type: Array, default: () => [] },
    filter_status: { type: String, default: null },
    totals: { type: Object, default: () => ({}) },
});

function brl(v) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
}

const statusLabel = {
    pending: { text: 'Pendente', color: 'bg-amber-50 text-amber-700 ring-amber-200', dot: 'bg-amber-500' },
    paid: { text: 'Paga', color: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
    overdue: { text: 'Vencida', color: 'bg-rose-50 text-rose-700 ring-rose-200', dot: 'bg-rose-500' },
};

function filtrar(status) {
    router.get(route('master.cobrancas.index'), status ? { status } : {}, {
        preserveScroll: true,
    });
}

function marcarPaga(invoice) {
    if (! confirm(`Confirma marcar a cobrança #${invoice.numero} de "${invoice.tenant_nome}" como PAGA?`)) return;
    router.post(route('master.cobrancas.mark-paid', invoice.id), {}, { preserveScroll: true });
}

function marcarPendente(invoice) {
    if (! confirm(`Reverter cobrança #${invoice.numero} para pendente?`)) return;
    router.post(route('master.cobrancas.mark-pending', invoice.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Cobranças · Plataforma" />
    <MasterLayout>
        <template #page-title>Cobranças</template>

        <div class="mb-6">
            <h2 class="text-xl font-serif font-bold text-slate-900">Cobranças emitidas</h2>
            <p class="mt-1 text-sm text-slate-600">Todas as invoices geradas para os tenants da plataforma.</p>
        </div>

        <!-- Totais -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-5">
            <div class="rounded-xl bg-white ring-1 ring-slate-200 p-4">
                <div class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Total de cobranças</div>
                <div class="mt-1 text-2xl font-serif font-bold text-slate-900">{{ totals.total ?? 0 }}</div>
            </div>
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 p-4">
                <div class="text-[10px] uppercase tracking-widest text-emerald-700 font-semibold">Recebido</div>
                <div class="mt-1 text-2xl font-serif font-bold text-emerald-900">{{ brl(totals.valor_pago) }}</div>
                <div class="text-xs text-emerald-700 mt-0.5">{{ totals.paid ?? 0 }} pagas</div>
            </div>
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4">
                <div class="text-[10px] uppercase tracking-widest text-amber-700 font-semibold">Pendente</div>
                <div class="mt-1 text-2xl font-serif font-bold text-amber-900">{{ brl(totals.valor_pendente) }}</div>
                <div class="text-xs text-amber-700 mt-0.5">{{ (totals.pending ?? 0) + (totals.overdue ?? 0) }} em aberto</div>
            </div>
            <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 p-4">
                <div class="text-[10px] uppercase tracking-widest text-rose-700 font-semibold">Vencidas</div>
                <div class="mt-1 text-2xl font-serif font-bold text-rose-900">{{ totals.overdue ?? 0 }}</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <span class="text-xs uppercase tracking-wider text-slate-500 font-semibold mr-2">Filtrar:</span>
            <button
                @click="filtrar(null)"
                class="px-3 py-1 rounded-full text-xs ring-1 transition"
                :class="! filter_status ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50'"
            >Todas</button>
            <button
                v-for="(lbl, st) in statusLabel"
                :key="st"
                @click="filtrar(st)"
                class="px-3 py-1 rounded-full text-xs ring-1 transition"
                :class="filter_status === st ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50'"
            >{{ lbl.text }}</button>
        </div>

        <!-- Tabela -->
        <div v-if="invoices.length === 0" class="rounded-2xl bg-white ring-1 ring-slate-200 p-10 text-center">
            <h3 class="text-sm font-semibold text-slate-900">Nenhuma cobrança encontrada</h3>
            <p class="mt-1 text-sm text-slate-500">
                {{ filter_status ? 'Nenhuma cobrança com esse status.' : 'Gere cobranças a partir da página de assinatura de cada tenant.' }}
            </p>
        </div>

        <div v-else class="rounded-2xl bg-white ring-1 ring-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">#</th>
                            <th class="px-4 py-3 text-left font-medium">Tenant</th>
                            <th class="px-4 py-3 text-right font-medium">Valor</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-left font-medium hidden sm:table-cell">Emissão</th>
                            <th class="px-4 py-3 text-left font-medium">Vencimento</th>
                            <th class="px-4 py-3 text-left font-medium hidden md:table-cell">Pago em</th>
                            <th class="px-4 py-3 text-right font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="i in invoices" :key="i.id" class="hover:bg-slate-50/60">
                            <td class="px-4 py-3 font-mono text-xs text-slate-700">#{{ i.numero }}</td>
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('master.tenants.subscription.show', i.tenant_id)"
                                    class="text-slate-900 hover:underline font-medium"
                                >{{ i.tenant_nome }}</Link>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-900">{{ brl(i.valor) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1"
                                    :class="statusLabel[i.status]?.color || 'bg-slate-100 text-slate-700 ring-slate-200'"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusLabel[i.status]?.dot || 'bg-slate-400'"></span>
                                    {{ statusLabel[i.status]?.text || i.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 hidden sm:table-cell">{{ i.data_emissao }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ i.data_vencimento }}</td>
                            <td class="px-4 py-3 text-slate-600 hidden md:table-cell">{{ i.data_pagamento || '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="i.status !== 'paid'"
                                    @click="marcarPaga(i)"
                                    title="Marcar como paga"
                                    class="p-2 rounded-md hover:bg-emerald-50 text-slate-600 hover:text-emerald-700"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                                <button
                                    v-else
                                    @click="marcarPendente(i)"
                                    title="Reverter para pendente"
                                    class="p-2 rounded-md hover:bg-amber-50 text-slate-600 hover:text-amber-700"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MasterLayout>
</template>
