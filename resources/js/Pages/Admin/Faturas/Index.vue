<script setup>
/**
 * Faturas (lado do cliente/tenant) — visualização + pagamento PIX.
 *
 * Mostra:
 *   - Estado da assinatura (vigência, status, alerta de carência)
 *   - Totais (em aberto, pago)
 *   - Lista filtrada por aparece_em <= today (master controla quando vira visível)
 *   - Para faturas pendentes/vencidas: linha expansível com QR PIX + copia-e-cola
 */
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PixQrCode from '@/Components/PixQrCode.vue';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';
import { brl } from '@/utils/format.js';

defineProps({
    invoices: { type: Array, default: () => [] },
    subscription: { type: Object, default: null },
    totals: { type: Object, default: () => ({}) },
});

// Auto-abre o PIX da primeira fatura pendente — usuário não precisa clicar
// para ver o que pagar. Demais ficam fechadas até clicar em "Pagar".
const expanded = ref(new Set());

function isPagavel(inv) {
    return inv.status === 'pending' || inv.status === 'overdue';
}

function togglePix(invoiceId) {
    if (expanded.value.has(invoiceId)) {
        expanded.value.delete(invoiceId);
    } else {
        expanded.value.add(invoiceId);
    }
    // Vue 3 reactivity em Set: precisa criar novo
    expanded.value = new Set(expanded.value);
}

const statusLabel = {
    pending:              { text: 'Pendente',              cls: 'bg-amber-50 text-amber-700 ring-amber-200', dot: 'bg-amber-500' },
    paid:                 { text: 'Paga',                  cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
    overdue:              { text: 'Vencida',               cls: 'bg-rose-50 text-rose-700 ring-rose-200',     dot: 'bg-rose-500' },
    paid_pending_review:  { text: 'Aguardando validação', cls: 'bg-sky-50 text-sky-700 ring-sky-200',         dot: 'bg-sky-500' },
};

// Modal de envio de comprovante
const uploadModal = ref({ open: false, invoice: null });
const uploadFile = ref(null);
const uploadE2e = ref('');
const uploading = ref(false);
const uploadError = ref(null);

useBodyScrollLock(() => uploadModal.value.open);

function abrirUpload(invoice) {
    uploadModal.value = { open: true, invoice };
    uploadFile.value = null;
    uploadE2e.value = '';
    uploadError.value = null;
}

function fecharUpload() {
    if (uploading.value) return;
    uploadModal.value = { open: false, invoice: null };
}

function onFileChange(e) {
    const f = e.target.files?.[0] ?? null;
    if (! f) { uploadFile.value = null; return; }
    if (f.size > 5 * 1024 * 1024) {
        uploadError.value = 'Arquivo maior que 5 MB.';
        e.target.value = '';
        return;
    }
    uploadFile.value = f;
    uploadError.value = null;
}

function enviarComprovante() {
    if (! uploadFile.value || ! uploadModal.value.invoice) return;
    uploading.value = true;
    uploadError.value = null;
    const fd = new FormData();
    fd.append('comprovante', uploadFile.value);
    if (uploadE2e.value.trim()) fd.append('external_payment_id', uploadE2e.value.trim());
    router.post(route('admin.faturas.submit-payment', uploadModal.value.invoice.id), fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploading.value = false;
            uploadModal.value = { open: false, invoice: null };
        },
        onError: (errors) => {
            uploading.value = false;
            uploadError.value = errors.comprovante || errors.external_payment_id || 'Não foi possível enviar.';
        },
        onFinish: () => { uploading.value = false; },
    });
}

const subStatusLabel = {
    active:   { text: 'Em dia',          cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    trial:    { text: 'Período de teste', cls: 'bg-sky-50 text-sky-700 ring-sky-200' },
    overdue:  { text: 'Em atraso',       cls: 'bg-rose-50 text-rose-700 ring-rose-200' },
    grace:    { text: 'Em carência',     cls: 'bg-amber-50 text-amber-700 ring-amber-200' },
    blocked:  { text: 'Bloqueado',       cls: 'bg-slate-200 text-slate-700 ring-slate-300' },
    canceled: { text: 'Cancelado',       cls: 'bg-slate-100 text-slate-600 ring-slate-200' },
};
</script>

<template>
    <Head title="Faturas" />
    <AdminLayout>
        <template #page-title>Faturas</template>

        <div class="max-w-5xl mx-auto">
            <div class="mb-6">
                <h2 class="text-xl font-serif font-bold text-slate-900">Suas faturas</h2>
                <p class="mt-1 text-sm text-slate-600">Acompanhe a vigência da sua assinatura e os pagamentos.</p>
            </div>

            <!-- Card de assinatura -->
            <div v-if="subscription" class="rounded-2xl bg-white ring-1 ring-slate-200 p-5 mb-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Assinatura</div>
                        <div class="text-lg font-semibold text-slate-900 mt-1">
                            {{ subscription.plano_nome ?? 'Sem plano' }}
                        </div>
                        <div v-if="subscription.plano_preco" class="text-sm text-slate-600 mt-0.5">
                            {{ brl(subscription.plano_preco) }} / mês
                        </div>
                    </div>
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold ring-1"
                        :class="subStatusLabel[subscription.status]?.cls ?? 'bg-slate-100 text-slate-700 ring-slate-200'"
                    >
                        {{ subStatusLabel[subscription.status]?.text ?? subscription.status }}
                    </span>
                </div>

                <div v-if="subscription.current_period_end" class="mt-4 text-sm text-slate-700">
                    <span class="text-slate-500">Vigência:</span>
                    <strong>{{ subscription.current_period_start ?? '—' }}</strong>
                    a
                    <strong>{{ subscription.current_period_end }}</strong>
                </div>
                <div v-if="subscription.grace_until" class="mt-2 text-sm text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-lg p-3">
                    ⚠ Vigência expirada — você está em carência até <strong>{{ subscription.grace_until }}</strong>.
                    Regularize uma fatura para evitar bloqueio.
                </div>
            </div>

            <!-- Totais -->
            <div class="grid sm:grid-cols-2 gap-3 mb-5">
                <div class="rounded-xl bg-white ring-1 ring-slate-200 p-4">
                    <div class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Em aberto</div>
                    <div class="mt-1 text-2xl font-serif font-bold text-amber-700">{{ brl(totals.pendente) }}</div>
                    <div class="text-xs text-slate-500">{{ totals.count_pendente ?? 0 }} fatura(s)</div>
                </div>
                <div class="rounded-xl bg-white ring-1 ring-slate-200 p-4">
                    <div class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Total pago</div>
                    <div class="mt-1 text-2xl font-serif font-bold text-emerald-700">{{ brl(totals.pago_total) }}</div>
                </div>
            </div>

            <!-- Lista -->
            <div v-if="invoices.length === 0" class="rounded-2xl bg-white ring-1 ring-slate-200 p-10 text-center">
                <div class="h-12 w-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h3 class="mt-3 text-sm font-semibold text-slate-900">Nenhuma fatura para exibir</h3>
                <p class="mt-1 text-sm text-slate-500">Quando suas faturas estiverem disponíveis, elas aparecerão aqui.</p>
            </div>

            <!-- Lista de faturas — cada uma vira um "card" expansível para mostrar PIX -->
            <div v-else class="space-y-3">
                <div v-for="i in invoices" :key="i.id"
                     class="rounded-2xl bg-white ring-1 ring-slate-200 overflow-hidden">
                    <!-- Linha resumo -->
                    <div class="p-4 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-macaybas-primary-50 text-macaybas-primary-800 ring-1 ring-macaybas-primary-200 font-mono text-xs font-semibold">
                                        {{ i.referencia_curta }}
                                    </span>
                                    <span class="font-mono text-[10px] text-slate-400">{{ i.numero }}</span>
                                    <span
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1"
                                        :class="statusLabel[i.status]?.cls ?? 'bg-slate-100 text-slate-700 ring-slate-200'"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="statusLabel[i.status]?.dot ?? 'bg-slate-400'"></span>
                                        {{ statusLabel[i.status]?.text ?? i.status }}
                                    </span>
                                </div>
                                <h3 class="mt-1.5 text-base font-semibold text-slate-900">
                                    <span v-if="i.tipo === 'mensal' && i.referencia_label">{{ i.referencia_label }}</span>
                                    <span v-else-if="i.tipo === 'unica'">
                                        Período {{ i.periodo_inicio }} → {{ i.periodo_fim }}
                                    </span>
                                    <span v-else>Fatura</span>
                                </h3>
                                <div class="mt-1 text-xs text-slate-500 flex flex-wrap gap-x-3">
                                    <span>Vencimento: <strong class="text-slate-700">{{ i.data_vencimento }}</strong></span>
                                    <span v-if="i.data_pagamento">Pago em: <strong class="text-slate-700">{{ i.data_pagamento }}</strong></span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xl font-serif font-bold text-slate-900">{{ brl(i.valor) }}</div>
                                <button v-if="isPagavel(i)" type="button" @click="togglePix(i.id)"
                                    :class="[
                                        'mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                                        expanded.has(i.id)
                                            ? 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                            : 'bg-macaybas-primary-700 text-white hover:bg-macaybas-primary-800 shadow-sm',
                                    ]"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="expanded.has(i.id) ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'"/>
                                    </svg>
                                    {{ expanded.has(i.id) ? 'Fechar' : 'Pagar com PIX' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Banner: rejeição anterior do master (cliente vê o motivo) -->
                    <div v-if="isPagavel(i) && i.payment_review_reason"
                         class="mx-4 sm:mx-5 mb-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900">
                        <div class="font-semibold">⚠ Comprovante anterior rejeitado pelo administrador</div>
                        <div class="mt-1 text-xs">{{ i.payment_review_reason }}</div>
                        <div class="mt-1 text-xs text-rose-700">Envie um novo comprovante abaixo.</div>
                    </div>

                    <!-- Banner: aguardando validação master -->
                    <div v-if="i.status === 'paid_pending_review'"
                         class="mx-4 sm:mx-5 mb-3 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">
                        <div class="font-semibold">⏳ Comprovante recebido — aguardando confirmação</div>
                        <div class="mt-1 text-xs">
                            Enviado em {{ i.payment_submitted_at }}. O administrador vai conferir e atualizar
                            o status em breve.
                        </div>
                    </div>

                    <!-- PIX expandido -->
                    <div v-if="isPagavel(i) && expanded.has(i.id)" class="border-t border-slate-100 bg-slate-50/50 p-4 sm:p-5 space-y-4">
                        <PixQrCode :payload="i.pix_payload ?? ''" :size="220" :referencia="i.referencia_curta" />
                        <div class="border-t border-slate-200 pt-3">
                            <p class="text-xs text-slate-600 mb-2">
                                Já fez o PIX? Envie o comprovante e o admin confirma rapidinho —
                                a fatura é dada como paga assim que ele aprovar.
                            </p>
                            <button type="button" @click="abrirUpload(i)"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                                ✓ Já paguei — enviar comprovante
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal: upload de comprovante -->
            <Teleport to="body">
                <div v-if="uploadModal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-slate-900/60" @click="fecharUpload"></div>
                    <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                        <h3 class="text-lg font-semibold text-slate-900 mb-1">Enviar comprovante</h3>
                        <p class="text-sm text-slate-600 mb-4">
                            Fatura <strong>{{ uploadModal.invoice?.referencia_curta }}</strong>
                            · {{ brl(uploadModal.invoice?.valor) }}
                        </p>

                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                                    Comprovante <span class="text-red-500">*</span>
                                </label>
                                <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp"
                                       @change="onFileChange"
                                       class="block w-full text-sm text-slate-700 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:text-sm file:font-medium hover:file:bg-emerald-100" />
                                <p class="text-xs text-slate-500 mt-1">PDF, JPG, PNG ou WEBP até 5 MB.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                                    ID da transação <span class="font-normal lowercase text-slate-400">(opcional)</span>
                                </label>
                                <input type="text" v-model="uploadE2e" maxlength="50"
                                       placeholder="Cole o ID PIX (E2E) do comprovante"
                                       class="form-input font-mono text-xs">
                            </div>
                            <div v-if="uploadError" class="text-sm text-red-700 bg-red-50 ring-1 ring-red-200 rounded-md px-3 py-2">
                                {{ uploadError }}
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" @click="fecharUpload" :disabled="uploading"
                                    class="btn-outline">Cancelar</button>
                            <button type="button" @click="enviarComprovante"
                                    :disabled="uploading || ! uploadFile"
                                    class="btn-primary disabled:opacity-50">
                                {{ uploading ? 'Enviando…' : 'Enviar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>

            <p class="mt-4 text-xs text-slate-500">
                💬 Pagou pelo banco e a fatura ainda não atualizou? Aguarde alguns minutos
                ou entre em contato com o suporte.
            </p>
        </div>
    </AdminLayout>
</template>
