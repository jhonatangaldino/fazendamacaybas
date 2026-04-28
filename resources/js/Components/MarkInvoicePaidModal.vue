<script setup>
/**
 * MarkInvoicePaidModal — processo seguro p/ marcar cobrança como paga.
 *
 * Antes (B4.2): window.confirm() nativo, sem contexto rico, sem dados operacionais.
 * Agora (B4.3): modal Vue com:
 *   - Contexto da cobrança (cliente, valor, ref MAC, vencimento)
 *   - Data de pagamento (default: hoje)
 *   - Método (PIX, transferência, dinheiro, boleto, cartão, outro)
 *   - Observação opcional (comprovante, nº transação, etc.)
 *   - Loading state + bloqueio de duplo clique
 *   - Cancelar visível
 *
 * Backend recebe os 3 campos extras via mark-paid endpoint.
 *
 * Uso (pai):
 *   <MarkInvoicePaidModal :invoice="invoiceToMark" @close="invoiceToMark=null" />
 */
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';

const props = defineProps({
    invoice: { type: Object, default: null }, // null = fechado
});
const emit = defineEmits(['close', 'success']);

const aberto = computed(() => props.invoice !== null);
useBodyScrollLock(aberto);

const dataPagamento = ref(new Date().toISOString().slice(0, 10));
const metodoPagamento = ref('pix');
const observacao = ref('');
const enviando = ref(false);

const METODOS = [
    { value: 'pix',           label: 'PIX' },
    { value: 'transferencia', label: 'Transferência' },
    { value: 'dinheiro',      label: 'Dinheiro' },
    { value: 'boleto',        label: 'Boleto' },
    { value: 'cartao',        label: 'Cartão' },
    { value: 'outro',         label: 'Outro' },
];

watch(() => props.invoice, (n) => {
    if (n) {
        // reset ao abrir
        dataPagamento.value = new Date().toISOString().slice(0, 10);
        metodoPagamento.value = 'pix';
        observacao.value = '';
        enviando.value = false;
    }
});

function brl(v) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
}

function fechar() {
    if (enviando.value) return;
    emit('close');
}

function confirmar() {
    if (! props.invoice || enviando.value) return;
    enviando.value = true;
    router.post(route('master.cobrancas.mark-paid', props.invoice.id), {
        data_pagamento: dataPagamento.value,
        metodo_pagamento: metodoPagamento.value,
        observacao_pagamento: observacao.value?.trim() || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            enviando.value = false;
            emit('success');
            emit('close');
        },
        onError: () => { enviando.value = false; },
        onFinish: () => { enviando.value = false; },
    });
}
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="aberto" class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center p-0 sm:p-4">
                <!-- backdrop -->
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="fechar"></div>

                <!-- modal -->
                <div class="relative w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl ring-1 ring-slate-200 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <!-- header -->
                    <div class="px-5 pt-5 pb-3 border-b border-slate-100">
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                <svg class="h-5 w-5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h2 class="text-base font-semibold text-slate-900">Marcar cobrança como paga</h2>
                                <p class="text-xs text-slate-500 mt-0.5">Registra o recebimento e regulariza a assinatura do cliente automaticamente.</p>
                            </div>
                        </div>
                    </div>

                    <!-- contexto da cobrança -->
                    <div v-if="invoice" class="px-5 py-3 bg-slate-50 border-b border-slate-100 text-xs">
                        <dl class="grid grid-cols-2 gap-y-1.5 gap-x-3">
                            <dt class="text-slate-500">Cliente</dt>
                            <dd class="text-slate-900 font-medium text-right truncate">{{ invoice.tenant_nome }}</dd>

                            <dt class="text-slate-500">Referência</dt>
                            <dd class="text-slate-900 font-mono text-right">{{ invoice.referencia_curta || `#${invoice.numero}` }}</dd>

                            <dt class="text-slate-500">Valor</dt>
                            <dd class="text-slate-900 font-mono font-bold text-right">{{ brl(invoice.valor) }}</dd>

                            <dt class="text-slate-500">Vencimento</dt>
                            <dd class="text-slate-700 text-right">{{ invoice.data_vencimento }}</dd>
                        </dl>
                    </div>

                    <!-- form processo -->
                    <div class="px-5 py-4 space-y-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Data do pagamento</label>
                            <input type="date" v-model="dataPagamento" :max="new Date().toISOString().slice(0,10)"
                                   class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500 focus:outline-none text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Método de pagamento</label>
                            <div class="grid grid-cols-3 gap-1.5">
                                <button v-for="m in METODOS" :key="m.value" type="button"
                                        @click="metodoPagamento = m.value"
                                        :class="metodoPagamento === m.value
                                            ? 'bg-emerald-600 text-white ring-emerald-600'
                                            : 'bg-white text-slate-700 ring-slate-200 hover:ring-emerald-300'"
                                        class="px-2 py-2 text-xs font-medium rounded-lg ring-1 transition">
                                    {{ m.label }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Observação <span class="font-normal lowercase text-slate-400">(opcional)</span></label>
                            <textarea v-model="observacao" rows="2" maxlength="500"
                                      placeholder="Nº transação, comprovante, banco recebedor..."
                                      class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500 focus:outline-none text-sm resize-none"></textarea>
                        </div>

                        <div class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-md px-2.5 py-1.5">
                            ℹ Se a assinatura estava em atraso e esta era a única cobrança vencida, ela voltará para <strong>ativa</strong> automaticamente.
                        </div>
                    </div>

                    <!-- ações -->
                    <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" @click="fechar" :disabled="enviando"
                                class="px-4 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-200 disabled:opacity-50">
                            Cancelar
                        </button>
                        <button type="button" @click="confirmar" :disabled="enviando"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-wait">
                            <svg v-if="enviando" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span>{{ enviando ? 'Registrando…' : 'Registrar pagamento' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
