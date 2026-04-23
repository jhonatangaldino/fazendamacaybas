<script setup>
/**
 * Admin/PagamentoPendente.vue — tela de bloqueio por inadimplência.
 *
 * Standalone (sem AdminLayout) porque o tenant bloqueado não deve ter
 * sidebar, menu, nem dashboard — só o necessário para regularizar
 * o pagamento e voltar a operar.
 *
 * Contexto:
 *   - Exibida via redirect do middleware EnforceSubscriptionStatus
 *   - Rota: /admin/pagamento-pendente
 *   - Whitelistada (não entra em loop com o próprio middleware)
 */

import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import GlobalLoading from '@/Components/GlobalLoading.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import ToastContainer from '@/Components/ToastContainer.vue';

const props = defineProps({
    subscription: { type: Object, default: null },
    invoices: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const settings = computed(() => page.props.settings ?? {});
const logo = computed(() => settings.value?.logo ? `/storage/${settings.value.logo}` : null);
const siteNome = computed(() => settings.value?.nome || 'Fazenda Macaybas');

function brl(v) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
}

const statusLabel = {
    pending: { text: 'Pendente', color: 'bg-amber-50 text-amber-700 ring-amber-200', dot: 'bg-amber-500' },
    overdue: { text: 'Vencida', color: 'bg-rose-50 text-rose-700 ring-rose-200', dot: 'bg-rose-500' },
};

const copiedId = ref(null);
async function copiarPix(invoice) {
    if (! invoice.pix_payload) return;
    try {
        await navigator.clipboard.writeText(invoice.pix_payload);
    } catch (e) {
        const ta = document.createElement('textarea');
        ta.value = invoice.pix_payload;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } finally { document.body.removeChild(ta); }
    }
    copiedId.value = invoice.id;
    setTimeout(() => {
        if (copiedId.value === invoice.id) copiedId.value = null;
    }, 2500);
}

function logout() {
    router.post(route('logout'));
}

// Totais
const totalDevido = computed(() => props.invoices.reduce((acc, i) => acc + (i.valor || 0), 0));
const qtdOverdue = computed(() => props.invoices.filter(i => i.status === 'overdue').length);
</script>

<template>
    <Head title="Pagamento pendente" />
    <GlobalLoading />
    <ToastContainer />
    <FlashMessages />

    <div class="min-h-screen bg-slate-50 flex flex-col">
        <!-- Header minimalista -->
        <header class="bg-white border-b border-slate-200 px-4 lg:px-8 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img v-if="logo" :src="logo" class="h-9 w-9 rounded-full object-cover ring-1 ring-slate-200" alt="logo">
                <div v-else class="h-9 w-9 rounded-full bg-macaybas-primary-900 text-white flex items-center justify-center font-serif text-lg font-bold">M</div>
                <div class="min-w-0">
                    <div class="font-serif font-semibold text-slate-900 leading-tight truncate">{{ siteNome }}</div>
                    <div class="text-xs text-slate-500">{{ user?.name }}</div>
                </div>
            </div>
            <button
                @click="logout"
                class="text-sm text-slate-600 hover:text-red-600 px-3 py-1.5 rounded-md hover:bg-slate-100"
            >Sair</button>
        </header>

        <main class="flex-1 py-8 lg:py-12 px-4 lg:px-8">
            <div class="max-w-3xl mx-auto">
                <!-- Aviso principal -->
                <div class="rounded-2xl bg-white ring-1 ring-rose-200 shadow-sm p-6 lg:p-8 mb-6">
                    <div class="flex items-start gap-4">
                        <div class="h-12 w-12 flex-shrink-0 rounded-full bg-rose-50 flex items-center justify-center ring-1 ring-rose-200">
                            <svg class="h-6 w-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h1 class="text-xl lg:text-2xl font-serif font-bold text-slate-900">
                                Acesso bloqueado por inadimplência
                            </h1>
                            <p class="mt-2 text-sm lg:text-base text-slate-600 leading-relaxed">
                                Identificamos cobranças em aberto na sua conta. O acesso às áreas operacionais
                                ficou temporariamente suspenso. Para regularizar, pague a(s) cobrança(s) abaixo
                                via PIX — assim que o pagamento for confirmado, o sistema é liberado
                                automaticamente.
                            </p>

                            <!-- Resumo rápido -->
                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div class="bg-slate-50 rounded-lg p-3">
                                    <div class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Cobranças em aberto</div>
                                    <div class="mt-0.5 text-lg font-serif font-bold text-slate-900">{{ invoices.length }}</div>
                                    <div class="text-xs text-slate-500">{{ qtdOverdue }} vencida(s)</div>
                                </div>
                                <div class="bg-rose-50 rounded-lg p-3 ring-1 ring-rose-100">
                                    <div class="text-[10px] uppercase tracking-widest text-rose-700 font-semibold">Total devido</div>
                                    <div class="mt-0.5 text-lg font-serif font-bold text-rose-900">{{ brl(totalDevido) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de invoices com PIX -->
                <div v-if="invoices.length === 0" class="rounded-2xl bg-white ring-1 ring-slate-200 p-10 text-center">
                    <h3 class="text-sm font-semibold text-slate-900">Nenhuma cobrança em aberto encontrada</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        Se você acredita que isso é um engano, entre em contato com o suporte.
                        O acesso será liberado assim que a administração regularizar.
                    </p>
                </div>

                <div
                    v-for="inv in invoices"
                    :key="inv.id"
                    class="rounded-2xl bg-white ring-1 ring-slate-200 p-5 lg:p-6 mb-4"
                >
                    <!-- Header da invoice -->
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs uppercase tracking-widest text-slate-500 font-semibold">
                                    Cobrança #{{ inv.numero }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1"
                                    :class="statusLabel[inv.status]?.color"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusLabel[inv.status]?.dot"></span>
                                    {{ statusLabel[inv.status]?.text || inv.status }}
                                </span>
                            </div>
                            <div class="text-3xl font-serif font-bold text-slate-900">{{ brl(inv.valor) }}</div>
                            <div class="mt-1 text-sm text-slate-500">
                                Vencimento: <strong class="text-slate-700">{{ inv.data_vencimento }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- PIX copia-e-cola -->
                    <div v-if="inv.pix_payload" class="bg-slate-900 rounded-xl p-4">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div>
                                <div class="text-xs uppercase tracking-widest text-amber-400 font-semibold mb-0.5">PIX · Copia e Cola</div>
                                <p class="text-xs text-slate-400">
                                    Copie o código e cole no app do seu banco → Pagar com PIX → Pix Copia e Cola.
                                </p>
                            </div>
                        </div>

                        <div class="relative">
                            <pre class="bg-slate-800 rounded-lg p-3 text-xs text-slate-200 font-mono overflow-x-auto whitespace-pre-wrap break-all select-all">{{ inv.pix_payload }}</pre>
                            <button
                                @click="copiarPix(inv)"
                                class="absolute top-2 right-2 px-3 py-1.5 rounded-md text-xs font-medium transition"
                                :class="copiedId === inv.id ? 'bg-emerald-500 text-white' : 'bg-amber-500 text-slate-900 hover:bg-amber-400'"
                            >
                                <span v-if="copiedId === inv.id">✓ Copiado</span>
                                <span v-else>Copiar</span>
                            </button>
                        </div>
                    </div>

                    <div v-else class="text-sm text-slate-500 bg-slate-50 rounded-lg p-3">
                        Código PIX não disponível para esta cobrança.
                        Entre em contato com o suporte para gerar o pagamento.
                    </div>
                </div>

                <!-- Rodapé -->
                <div class="mt-8 text-center text-xs text-slate-500">
                    Após o pagamento, a administração confirma o recebimento e o sistema é liberado
                    automaticamente. Em caso de dúvida, entre em contato com o suporte.
                </div>
            </div>
        </main>
    </div>
</template>
