<script setup>
/**
 * Validate.vue — double-check do master pra pagamento que o cliente afirmou
 * ter feito (status=paid_pending_review). Mostra o COMPROVANTE grande à
 * esquerda + dados da fatura à direita, e botões "Aprovar" / "Rejeitar".
 *
 * Fase 1: master compara visualmente.
 * Fase 2 (este commit): Tesseract.js no browser pré-extrai valor/data/E2E/CPF
 * do comprovante e mostra match/mismatch ao lado de cada campo da fatura.
 * Tesseract local no servidor não foi viável (Hostinger Business shared sem
 * binário tesseract). Usar tesseract.js (WebAssembly) é equivalente em
 * qualidade, roda no navegador do master, lazy-loaded só nesta tela.
 */
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import { useConfirm } from '@/composables/useConfirm.js';

const { confirm } = useConfirm();

const props = defineProps({
    invoice: { type: Object, required: true },
});

const dataPagamento = ref(new Date().toISOString().slice(0, 10));
const externalPaymentId = ref(props.invoice.external_payment_id || '');
const motivoRejeicao = ref('');
const aprovando = ref(false);
const rejeitando = ref(false);

const isPdf = computed(() => props.invoice.payment_proof_mime === 'application/pdf');
const isImage = computed(() => props.invoice.payment_proof_mime?.startsWith('image/'));

// ─── OCR (Tesseract.js no browser) ─────────────────────────────────────
const ocrLoading = ref(false);
const ocrError = ref(null);
const ocrResult = ref(null);
const ocrProgress = ref(0);
const ocrStatus = ref('');

function extractValor(text) {
    // R$ 1.234,56 / R$1234,56 / 1.234,56 / 1,00
    const matches = [...text.matchAll(/R?\$?\s*(\d{1,3}(?:\.\d{3})*,\d{2}|\d+,\d{2})/g)];
    if (matches.length === 0) return null;
    // Pega o MAIOR valor encontrado (geralmente é o do pagamento, não taxa nem saldo)
    const valores = matches.map(m => parseFloat(m[1].replace(/\./g, '').replace(',', '.')));
    return Math.max(...valores);
}
function extractData(text) {
    const m = text.match(/(\d{2})\/(\d{2})\/(\d{4})/);
    return m ? `${m[1]}/${m[2]}/${m[3]}` : null;
}
function extractE2e(text) {
    // Formato BACEN: E + ISPB(8) + AAAAMMDD + HHMM + 11 chars = 32 chars
    const m = text.match(/E[A-Z0-9]{31}/i);
    return m ? m[0].toUpperCase() : null;
}
function extractCpf(text) {
    const m = text.match(/(\d{3})\.?(\d{3})\.?(\d{3})-?(\d{2})/);
    return m ? `${m[1]}.${m[2]}.${m[3]}-${m[4]}` : null;
}

async function runOcr() {
    if (! isImage.value || ! props.invoice.payment_proof_url) return;
    ocrLoading.value = true;
    ocrError.value = null;
    ocrProgress.value = 0;
    ocrStatus.value = 'Carregando módulo de OCR...';
    try {
        const Tesseract = await import('tesseract.js');
        ocrStatus.value = 'Lendo o comprovante...';
        const { data } = await Tesseract.recognize(
            props.invoice.payment_proof_url,
            'por',
            {
                logger: (m) => {
                    if (m.status === 'recognizing text') {
                        ocrProgress.value = Math.round(m.progress * 100);
                        ocrStatus.value = `Lendo o comprovante... ${ocrProgress.value}%`;
                    } else if (m.status === 'loading language traineddata') {
                        ocrStatus.value = 'Baixando dicionário português (1ª vez)...';
                    }
                },
            }
        );
        const text = data.text || '';
        ocrResult.value = {
            rawText: text,
            valor: extractValor(text),
            data: extractData(text),
            e2e: extractE2e(text),
            cpf: extractCpf(text),
        };
        // Auto-preenche o E2E se OCR achou e o campo está vazio
        if (ocrResult.value.e2e && ! externalPaymentId.value) {
            externalPaymentId.value = ocrResult.value.e2e;
        }
    } catch (e) {
        ocrError.value = e.message || 'Falha ao processar OCR.';
    } finally {
        ocrLoading.value = false;
        ocrStatus.value = '';
    }
}

const valorMatch = computed(() => {
    if (! ocrResult.value?.valor) return null;
    return Math.abs(ocrResult.value.valor - props.invoice.valor) < 0.01;
});
const valorOcrFmt = computed(() => {
    if (! ocrResult.value?.valor) return null;
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(ocrResult.value.valor);
});

// Roda OCR automaticamente ao abrir a tela
onMounted(() => {
    if (isImage.value) runOcr();
});

function brl(v) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
}
function tamanhoArquivo(bytes) {
    if (! bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

async function aprovar() {
    const ok = await confirm({
        title: 'Aprovar pagamento',
        message: `Confirmar que o pagamento de ${brl(props.invoice.valor)} foi recebido? A fatura ${props.invoice.numero} será marcada como paga.`,
        confirmText: 'Aprovar',
        variant: 'primary',
        icon: 'question',
    });
    if (! ok) return;
    aprovando.value = true;
    router.post(route('master.cobrancas.approve-proof', props.invoice.id), {
        data_pagamento: dataPagamento.value,
        external_payment_id: externalPaymentId.value?.trim() || null,
    }, { onFinish: () => { aprovando.value = false; } });
}

async function rejeitar() {
    if (! motivoRejeicao.value.trim() || motivoRejeicao.value.trim().length < 5) {
        alert('Informe um motivo de pelo menos 5 caracteres.');
        return;
    }
    const ok = await confirm({
        title: 'Rejeitar comprovante',
        message: 'A fatura volta ao estado pendente, o comprovante é removido, e o cliente vê o motivo na próxima visita. Continuar?',
        confirmText: 'Rejeitar',
        variant: 'danger',
        icon: 'warning',
    });
    if (! ok) return;
    rejeitando.value = true;
    router.post(route('master.cobrancas.reject-proof', props.invoice.id), {
        motivo: motivoRejeicao.value.trim(),
    }, { onFinish: () => { rejeitando.value = false; } });
}
</script>

<template>
    <Head :title="`Validar pagamento · ${invoice.numero}`" />
    <MasterLayout>
        <template #page-title>Validar pagamento</template>

        <nav class="text-sm text-slate-500 mb-4 flex items-center gap-1.5 flex-wrap">
            <Link :href="route('master.cobrancas.index')" class="hover:text-slate-900">Cobranças</Link>
            <span>›</span>
            <Link :href="route('master.tenants.subscription.show', invoice.tenant_id)" class="hover:text-slate-900">{{ invoice.tenant_nome }}</Link>
            <span>›</span>
            <span class="text-slate-900 font-medium">Validar #{{ invoice.numero }}</span>
        </nav>

        <div class="grid lg:grid-cols-2 gap-6">
            <!-- COMPROVANTE -->
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Comprovante enviado</h3>
                    <a v-if="invoice.payment_proof_url" :href="invoice.payment_proof_url" target="_blank"
                       class="text-xs text-macaybas-primary-700 hover:underline">Abrir em nova aba ↗</a>
                </div>
                <div v-if="isImage" class="rounded-lg ring-1 ring-slate-200 overflow-hidden bg-slate-50">
                    <img :src="invoice.payment_proof_url" alt="Comprovante" class="w-full h-auto max-h-[80vh] object-contain">
                </div>
                <div v-else-if="isPdf" class="rounded-lg ring-1 ring-slate-200 overflow-hidden bg-slate-50">
                    <iframe :src="invoice.payment_proof_url" class="w-full h-[70vh]" />
                </div>
                <div v-else class="rounded-lg ring-1 ring-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-500">
                    Formato não pré-visualizável — clique em "Abrir em nova aba".
                </div>
                <div class="mt-2 text-xs text-slate-500">
                    {{ invoice.payment_proof_mime }} · {{ tamanhoArquivo(invoice.payment_proof_size) }}
                    · enviado em {{ invoice.payment_submitted_at }}
                </div>
            </div>

            <!-- DADOS DA FATURA + AÇÕES -->
            <div class="space-y-4">
                <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-5">
                    <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">Dados da fatura</h3>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-slate-500">Cliente</dt>
                            <dd class="font-semibold text-slate-900">{{ invoice.tenant_nome }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Referência</dt>
                            <dd class="font-mono text-slate-900">{{ invoice.referencia_curta }} · {{ invoice.numero }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Valor</dt>
                            <dd class="font-mono font-bold text-emerald-700 text-lg">{{ brl(invoice.valor) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Vencimento</dt>
                            <dd class="text-slate-900">{{ invoice.data_vencimento }}</dd>
                        </div>
                        <div v-if="invoice.pix_txid" class="col-span-2">
                            <dt class="text-xs text-slate-500">TXID PIX gerado</dt>
                            <dd class="font-mono text-xs text-slate-700 break-all">{{ invoice.pix_txid }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 p-3 rounded-lg bg-amber-50 ring-1 ring-amber-200 text-xs text-amber-900">
                        🔍 Confira no comprovante: <strong>valor</strong> bate com {{ brl(invoice.valor) }}?
                        <strong>recebedor</strong> é a sua conta? <strong>data</strong> faz sentido?
                    </div>
                </div>

                <!-- ANÁLISE AUTOMÁTICA (OCR) -->
                <div v-if="isImage" class="rounded-2xl bg-white ring-1 ring-slate-200 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">
                            🤖 Análise automática <span class="text-[10px] font-normal text-slate-400">OCR no navegador</span>
                        </h3>
                        <button v-if="!ocrLoading && ocrResult" @click="runOcr" type="button"
                                class="text-xs text-slate-500 hover:text-slate-900">↻ Re-analisar</button>
                    </div>

                    <div v-if="ocrLoading" class="text-sm text-slate-600">
                        <div class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-macaybas-primary-700" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span>{{ ocrStatus || 'Processando...' }}</span>
                        </div>
                        <div v-if="ocrProgress > 0" class="mt-2 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-macaybas-primary-600 transition-all" :style="`width: ${ocrProgress}%`"></div>
                        </div>
                    </div>

                    <div v-else-if="ocrError" class="text-sm text-rose-700 bg-rose-50 rounded-md p-2">
                        ⚠ {{ ocrError }} <button @click="runOcr" class="underline ml-1">Tentar de novo</button>
                    </div>

                    <div v-else-if="ocrResult" class="space-y-2 text-sm">
                        <!-- Valor -->
                        <div class="flex items-center justify-between p-2 rounded-lg"
                             :class="valorMatch === true ? 'bg-emerald-50 ring-1 ring-emerald-200' : valorMatch === false ? 'bg-rose-50 ring-1 ring-rose-200' : 'bg-slate-50'">
                            <span class="text-xs text-slate-600">Valor detectado</span>
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold" :class="valorMatch === true ? 'text-emerald-800' : valorMatch === false ? 'text-rose-800' : 'text-slate-700'">
                                    {{ valorOcrFmt || '—' }}
                                </span>
                                <span v-if="valorMatch === true" class="text-emerald-600">✓</span>
                                <span v-else-if="valorMatch === false" class="text-rose-600" title="Não bate com o valor da fatura">✗</span>
                            </div>
                        </div>
                        <!-- Data -->
                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50">
                            <span class="text-xs text-slate-600">Data detectada</span>
                            <span class="font-mono text-slate-700">{{ ocrResult.data || '—' }}</span>
                        </div>
                        <!-- E2E -->
                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50 gap-2">
                            <span class="text-xs text-slate-600 flex-shrink-0">E2E PIX</span>
                            <span class="font-mono text-[11px] text-slate-700 break-all text-right">{{ ocrResult.e2e || '—' }}</span>
                        </div>
                        <!-- CPF -->
                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50">
                            <span class="text-xs text-slate-600">CPF detectado</span>
                            <span class="font-mono text-slate-700">{{ ocrResult.cpf || '—' }}</span>
                        </div>

                        <p v-if="valorMatch === false" class="text-xs text-rose-700 mt-2">
                            ⚠ <strong>Atenção:</strong> o valor do comprovante não bate com o da fatura.
                            Confira com cuidado antes de aprovar.
                        </p>
                        <p v-else-if="valorMatch === true" class="text-xs text-emerald-700 mt-2">
                            ✓ Valor bate com a fatura. Restam: confirmar recebedor + data no comprovante.
                        </p>
                    </div>
                </div>

                <!-- PDF: OCR não disponível -->
                <div v-else-if="isPdf" class="rounded-2xl bg-white ring-1 ring-slate-200 p-5 text-sm text-slate-600">
                    🤖 <strong>OCR automático não disponível para PDFs</strong> nesta versão.
                    Confira manualmente os dados no documento ao lado.
                </div>

                <!-- APROVAR -->
                <div class="rounded-2xl bg-white ring-1 ring-emerald-200 p-5">
                    <h3 class="text-sm font-semibold text-emerald-800 uppercase tracking-wider mb-3">✓ Aprovar pagamento</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Data do pagamento</label>
                            <input type="date" v-model="dataPagamento" :max="new Date().toISOString().slice(0, 10)" class="form-input">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                                ID da transação bancária <span class="font-normal lowercase text-slate-400">(opcional)</span>
                            </label>
                            <input type="text" v-model="externalPaymentId" maxlength="50"
                                   placeholder="E2E PIX do comprovante"
                                   class="form-input font-mono text-xs">
                        </div>
                        <button type="button" @click="aprovar" :disabled="aprovando || rejeitando"
                                class="w-full btn-primary disabled:opacity-50">
                            {{ aprovando ? 'Aprovando…' : 'Aprovar e marcar como paga' }}
                        </button>
                    </div>
                </div>

                <!-- REJEITAR -->
                <div class="rounded-2xl bg-white ring-1 ring-rose-200 p-5">
                    <h3 class="text-sm font-semibold text-rose-800 uppercase tracking-wider mb-3">✗ Rejeitar comprovante</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                                Motivo (será mostrado ao cliente) <span class="text-red-500">*</span>
                            </label>
                            <textarea v-model="motivoRejeicao" rows="3" maxlength="500"
                                      placeholder="Ex.: O comprovante mostra R$ 99 mas a fatura é R$ 199. Confirme se você pagou o valor correto."
                                      class="form-textarea resize-none"></textarea>
                        </div>
                        <button type="button" @click="rejeitar" :disabled="aprovando || rejeitando || ! motivoRejeicao.trim()"
                                class="w-full btn-danger disabled:opacity-50">
                            {{ rejeitando ? 'Rejeitando…' : 'Rejeitar e devolver pro cliente' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </MasterLayout>
</template>
