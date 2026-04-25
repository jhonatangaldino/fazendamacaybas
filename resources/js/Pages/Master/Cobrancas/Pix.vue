<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import PixQrCode from '@/Components/PixQrCode.vue';

const props = defineProps({
    invoice: { type: Object, required: true },
});

function brl(v) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
}

const statusLabel = {
    pending: { text: 'Pendente', color: 'bg-amber-50 text-amber-700 ring-amber-200', dot: 'bg-amber-500' },
    paid: { text: 'Paga', color: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
    overdue: { text: 'Vencida', color: 'bg-rose-50 text-rose-700 ring-rose-200', dot: 'bg-rose-500' },
};

const isPayable = computed(() => ['pending', 'overdue'].includes(props.invoice.status));

const copied = ref(false);
async function copiarPix() {
    if (! props.invoice.pix_payload) return;
    try {
        await navigator.clipboard.writeText(props.invoice.pix_payload);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch (e) {
        // Fallback para browsers antigos
        const ta = document.createElement('textarea');
        ta.value = props.invoice.pix_payload;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); copied.value = true; setTimeout(() => { copied.value = false; }, 2000); }
        finally { document.body.removeChild(ta); }
    }
}

function marcarPaga() {
    if (! confirm(`Confirma marcar cobrança #${props.invoice.numero} como PAGA?\n\nEssa ação atualiza a assinatura do cliente automaticamente se estava em atraso.`)) return;
    router.post(route('master.cobrancas.mark-paid', props.invoice.id));
}

function marcarPendente() {
    if (! confirm(`Reverter cobrança #${props.invoice.numero} para pendente?`)) return;
    router.post(route('master.cobrancas.mark-pending', props.invoice.id));
}
</script>

<template>
    <Head :title="`Cobrança #${invoice.numero} · PIX`" />
    <MasterLayout>
        <template #page-title>Cobrança #{{ invoice.numero }}</template>

        <!-- Breadcrumb -->
        <nav class="text-sm text-slate-500 mb-4 flex items-center gap-1.5">
            <Link :href="route('master.cobrancas.index')" class="hover:text-slate-900">Cobranças</Link>
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-900 font-medium">#{{ invoice.numero }} · {{ invoice.tenant_nome }}</span>
        </nav>

        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-6 mb-4">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div>
                        <div class="text-xs uppercase tracking-widest text-slate-500 font-semibold mb-1">Valor a pagar</div>
                        <div class="text-4xl font-serif font-bold text-slate-900">{{ brl(invoice.valor) }}</div>
                        <div v-if="invoice.referencia_curta" class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-macaybas-primary-50 text-macaybas-primary-800 ring-1 ring-macaybas-primary-200 font-mono text-sm font-bold">
                            {{ invoice.referencia_curta }}
                        </div>
                    </div>
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium ring-1"
                        :class="statusLabel[invoice.status]?.color"
                    >
                        <span class="h-2 w-2 rounded-full" :class="statusLabel[invoice.status]?.dot"></span>
                        {{ statusLabel[invoice.status]?.text }}
                    </span>
                </div>

                <!-- Bloco de instrução para conciliação manual -->
                <div v-if="isPayable && invoice.referencia_curta" class="mb-4 rounded-lg bg-amber-50 ring-1 ring-amber-200 p-3 text-sm">
                    <p class="text-amber-900">
                        💬 <strong>Instrução para o cliente:</strong>
                        ao pagar, peça que mencione a referência
                        <code class="px-1.5 py-0.5 rounded bg-white ring-1 ring-amber-300 font-mono font-bold">{{ invoice.referencia_curta }}</code>
                        no comentário do PIX. Se o pagamento não atualizar, busque por essa referência aqui no Master.
                    </p>
                </div>

                <!-- Metadados -->
                <dl class="grid sm:grid-cols-2 gap-3 text-sm pt-4 border-t border-slate-100">
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-slate-500">Cliente</dt>
                        <dd class="text-slate-900 font-medium">{{ invoice.tenant_nome }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-slate-500">Número</dt>
                        <dd class="text-slate-900 font-mono">#{{ invoice.numero }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-slate-500">Emissão</dt>
                        <dd class="text-slate-700">{{ invoice.data_emissao }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-slate-500">Vencimento</dt>
                        <dd class="text-slate-700">{{ invoice.data_vencimento }}</dd>
                    </div>
                    <div v-if="invoice.data_pagamento" class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wider text-slate-500">Pago em</dt>
                        <dd class="text-emerald-700 font-medium">{{ invoice.data_pagamento }}</dd>
                    </div>
                </dl>
            </div>

            <!-- QR Code (para mostrar presencialmente ao cliente). Reutiliza o
                 mesmo componente da tela do tenant — uma fonte de verdade só. -->
            <div v-if="invoice.pix_payload && isPayable" class="mb-4">
                <PixQrCode :payload="invoice.pix_payload" :referencia="invoice.referencia_curta" :size="220" />
            </div>

            <!-- Caixa do PIX -->
            <div v-if="invoice.pix_payload && isPayable" class="rounded-2xl bg-slate-900 text-white p-6 mb-4">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <div class="text-xs uppercase tracking-widest text-amber-400 font-semibold mb-1">PIX · Copia e Cola</div>
                        <h3 class="font-serif font-bold text-lg">Pagamento via PIX</h3>
                        <p class="text-sm text-slate-400 mt-1">
                            Envie o código abaixo ao cliente. Cole no app do banco → Pagar com PIX → Pix Copia e Cola.
                        </p>
                    </div>
                    <div class="text-xs text-slate-500 font-mono flex-shrink-0">
                        txid: {{ invoice.pix_txid }}
                    </div>
                </div>

                <!-- Payload -->
                <div class="relative">
                    <pre class="bg-slate-800 rounded-lg p-4 text-xs text-slate-200 font-mono overflow-x-auto whitespace-pre-wrap break-all select-all">{{ invoice.pix_payload }}</pre>
                    <button
                        @click="copiarPix"
                        class="absolute top-2 right-2 px-3 py-1.5 rounded-md text-xs font-medium transition"
                        :class="copied ? 'bg-emerald-500 text-white' : 'bg-amber-500 text-slate-900 hover:bg-amber-400'"
                    >
                        <span v-if="copied">✓ Copiado</span>
                        <span v-else>Copiar</span>
                    </button>
                </div>

                <p class="mt-3 text-xs text-slate-400">
                    <strong>Nota:</strong> esta cobrança usa chave PIX de simulação (sem gateway real).
                    Quando a integração com PSP estiver ativa, o mesmo código passará a processar pagamentos reais sem alteração.
                </p>
            </div>

            <!-- Ações -->
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Ações do master</h3>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        v-if="invoice.status !== 'paid'"
                        @click="marcarPaga"
                        class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700"
                    >
                        ✓ Marcar como paga
                    </button>

                    <button
                        v-if="invoice.status === 'paid'"
                        @click="marcarPendente"
                        class="px-4 py-2 rounded-lg bg-amber-50 text-amber-700 ring-1 ring-amber-200 text-sm font-medium hover:bg-amber-100"
                    >
                        ↻ Reverter para pendente
                    </button>

                    <Link
                        :href="route('master.tenants.subscription.show', invoice.tenant_id)"
                        class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm hover:bg-slate-200"
                    >Ver assinatura do cliente</Link>

                    <Link
                        :href="route('master.cobrancas.index')"
                        class="px-4 py-2 rounded-lg text-slate-600 text-sm hover:bg-slate-100"
                    >← Voltar à lista</Link>
                </div>

                <p v-if="isPayable" class="mt-4 text-xs text-slate-500">
                    Ao marcar como paga, se a assinatura do cliente estava em atraso, ela volta automaticamente para ativa.
                </p>
            </div>
        </div>
    </MasterLayout>
</template>
