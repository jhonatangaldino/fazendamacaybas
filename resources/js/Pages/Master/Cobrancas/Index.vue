<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import MarkInvoicePaidModal from '@/Components/MarkInvoicePaidModal.vue';
import { useConfirm } from '@/composables/useConfirm.js';

const { confirm } = useConfirm();
const invoiceParaPagar = ref(null); // null = modal fechado

const props = defineProps({
    invoices: { type: Array, default: () => [] },
    filter_status: { type: String, default: null },
    filter_q: { type: String, default: '' },
    totals: { type: Object, default: () => ({}) },
});

const buscaQuery = ref(props.filter_q ?? '');

function buscar() {
    router.get(route('master.cobrancas.index'), {
        ...(props.filter_status ? { status: props.filter_status } : {}),
        ...(buscaQuery.value ? { q: buscaQuery.value } : {}),
    }, { preserveScroll: true });
}

function limparBusca() {
    buscaQuery.value = '';
    buscar();
}

function brl(v) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
}

const statusLabel = {
    pending: { text: 'Pendente', color: 'bg-amber-50 text-amber-700 ring-amber-200', dot: 'bg-amber-500' },
    paid: { text: 'Paga', color: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
    overdue: { text: 'Vencida', color: 'bg-rose-50 text-rose-700 ring-rose-200', dot: 'bg-rose-500' },
};

function filtrar(status) {
    router.get(route('master.cobrancas.index'), {
        ...(status ? { status } : {}),
        ...(buscaQuery.value ? { q: buscaQuery.value } : {}),
    }, {
        preserveScroll: true,
    });
}

function marcarPaga(invoice) {
    // BLOCO 4.3 — abre modal de processo (data + método + observação) em vez de confirm nativo
    invoiceParaPagar.value = invoice;
}

async function marcarPendente(invoice) {
    const ok = await confirm({
        title: 'Reverter para pendente',
        message: `Reverter cobrança ${invoice.referencia_curta || `#${invoice.numero}`} de "${invoice.tenant_nome}" para pendente? O registro de pagamento será removido. Use só em caso de estorno ou erro.`,
        confirmText: 'Reverter',
        variant: 'danger',
    });
    if (! ok) return;
    router.post(route('master.cobrancas.mark-pending', invoice.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Cobranças · Plataforma" />
    <MasterLayout>
        <template #page-title>Cobranças</template>

        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-serif font-bold text-slate-900">Cobranças emitidas</h2>
                <p class="mt-1 text-sm text-slate-600">Todas as invoices geradas para os clientes da plataforma.</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <Link
                    :href="route('master.cobrancas.configuracoes')"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-lg ring-1 ring-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 hover:ring-macaybas-primary-300 hover:text-macaybas-primary-800"
                    title="Chave PIX, nome e cidade"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span class="hidden sm:inline">Configurar PIX</span>
                </Link>
                <Link
                    :href="route('master.cobrancas.wizard.create')"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-macaybas-primary-700 text-white text-sm font-semibold hover:bg-macaybas-primary-800 shadow-sm"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Gerar faturas
                </Link>
            </div>
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

        <!-- Busca por referência (conciliação) -->
        <div class="mb-4 flex flex-wrap items-stretch gap-2">
            <form @submit.prevent="buscar" class="flex-1 min-w-[260px] flex gap-2">
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input
                        v-model="buscaQuery"
                        type="search"
                        placeholder="Buscar por MAC-0008, 0008 ou nome do cliente"
                        class="w-full pl-9 pr-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary-500 focus:outline-none text-sm"
                    >
                </div>
                <button type="submit" class="px-4 py-2 rounded-lg bg-macaybas-primary-700 text-white text-sm font-semibold hover:bg-macaybas-primary-800">
                    Buscar
                </button>
                <button v-if="filter_q" type="button" @click="limparBusca"
                    class="px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-100">
                    Limpar
                </button>
            </form>
        </div>

        <!-- Filtros -->
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <span class="text-xs uppercase tracking-wider text-slate-500 font-semibold mr-2">Status:</span>
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
            <span v-if="filter_q" class="ml-2 text-xs text-slate-600">
                Buscando "<strong>{{ filter_q }}</strong>" — {{ invoices.length }} resultado(s)
            </span>
        </div>

        <!-- Tabela -->
        <div v-if="invoices.length === 0" class="rounded-2xl bg-white ring-1 ring-slate-200 p-10 text-center">
            <h3 class="text-sm font-semibold text-slate-900">Nenhuma cobrança encontrada</h3>
            <p class="mt-1 text-sm text-slate-500">
                {{ filter_status ? 'Nenhuma cobrança com esse status.' : 'Gere cobranças a partir da página de assinatura de cada cliente.' }}
            </p>
        </div>

        <div v-else>
            <!-- BLOCO 4.3 — Lista refatorada:
                 DESKTOP: tabela limpa com ações com label visível
                 MOBILE:  cards verticais (ref grande, cliente, valor+status,
                          venc, ações com label) — nada some em mobile -->

            <!-- DESKTOP TABLE (xl+ · iPad usa cards) -->
            <div class="hidden xl:block rounded-2xl bg-white ring-1 ring-slate-200 overflow-hidden">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium whitespace-nowrap">Ref.</th>
                            <th class="px-4 py-3 text-left font-medium">Cliente</th>
                            <th class="px-4 py-3 text-right font-medium">Valor</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-left font-medium">Vencimento</th>
                            <th class="px-4 py-3 text-left font-medium">Pago em</th>
                            <th class="px-4 py-3 text-right font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="i in invoices" :key="i.id" class="hover:bg-slate-50/60">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-macaybas-primary-50 text-macaybas-primary-800 ring-1 ring-macaybas-primary-200 font-mono text-xs font-semibold">
                                    {{ i.referencia_curta }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <Link :href="route('master.tenants.subscription.show', i.tenant_id)"
                                      class="text-slate-900 hover:underline font-medium">{{ i.tenant_nome }}</Link>
                                <div class="text-xs text-slate-500 font-mono">#{{ i.numero }} · {{ i.data_emissao }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-900 font-semibold">{{ brl(i.valor) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1"
                                      :class="statusLabel[i.status]?.color || 'bg-slate-100 text-slate-700 ring-slate-200'">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusLabel[i.status]?.dot || 'bg-slate-400'"></span>
                                    {{ statusLabel[i.status]?.text || i.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ i.data_vencimento }}</td>
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ i.data_pagamento || '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <Link :href="route('master.cobrancas.pix', i.id)"
                                          class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                        Ver PIX
                                    </Link>
                                    <button v-if="i.status !== 'paid'" @click="marcarPaga(i)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium bg-emerald-600 text-white hover:bg-emerald-700">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Marcar paga
                                    </button>
                                    <button v-else @click="marcarPendente(i)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium bg-amber-50 text-amber-700 ring-1 ring-amber-200 hover:bg-amber-100">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                        Reverter
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- MOBILE/iPad CARDS (< xl) — todas as ações visíveis, sem ⋯ -->
            <div class="xl:hidden space-y-2.5">
                <div v-for="i in invoices" :key="i.id"
                     class="rounded-xl bg-white ring-1 ring-slate-200 p-3.5 shadow-sm">
                    <!-- linha 1: ref badge + status -->
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-macaybas-primary-50 text-macaybas-primary-800 ring-1 ring-macaybas-primary-200 font-mono text-xs font-semibold whitespace-nowrap">
                            {{ i.referencia_curta }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1 whitespace-nowrap"
                              :class="statusLabel[i.status]?.color || 'bg-slate-100 text-slate-700 ring-slate-200'">
                            <span class="h-1.5 w-1.5 rounded-full" :class="statusLabel[i.status]?.dot || 'bg-slate-400'"></span>
                            {{ statusLabel[i.status]?.text || i.status }}
                        </span>
                    </div>

                    <!-- linha 2: cliente nome -->
                    <Link :href="route('master.tenants.subscription.show', i.tenant_id)"
                          class="block text-slate-900 font-semibold hover:underline truncate">
                        {{ i.tenant_nome }}
                    </Link>

                    <!-- linha 3: valor + venc -->
                    <div class="mt-1.5 flex items-center justify-between gap-2 text-sm">
                        <span class="font-mono font-bold text-slate-900">{{ brl(i.valor) }}</span>
                        <span class="text-xs text-slate-500">venc. {{ i.data_vencimento }}</span>
                    </div>
                    <div v-if="i.data_pagamento" class="text-xs text-emerald-700 mt-0.5">Pago em {{ i.data_pagamento }}</div>

                    <!-- linha 4: ações com label -->
                    <div class="mt-3 flex items-center gap-2">
                        <Link :href="route('master.cobrancas.pix', i.id)"
                              class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 text-slate-700 hover:bg-slate-200">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            Ver PIX
                        </Link>
                        <button v-if="i.status !== 'paid'" @click="marcarPaga(i)"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Marcar paga
                        </button>
                        <button v-else @click="marcarPendente(i)"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-amber-50 text-amber-700 ring-1 ring-amber-200 hover:bg-amber-100">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                            Reverter
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- BLOCO 4.3 — modal processo seguro de marcar paga (substitui confirm nativo) -->
        <MarkInvoicePaidModal :invoice="invoiceParaPagar" @close="invoiceParaPagar = null" />
    </MasterLayout>
</template>
