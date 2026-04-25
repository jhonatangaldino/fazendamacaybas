<script setup>
/**
 * PixQrCode — exibe um QR Code PIX (BR Code) + copia-e-cola + botão copiar.
 *
 * Recebe `payload` (string EMV gerada pelo PixPayloadGenerator no backend) e
 * desenha o QR no client via lib `qrcode`. Sem dependência PHP de imagem.
 *
 * UX:
 *  • QR centralizado, fundo branco com padding (legibilidade)
 *  • Botão copiar com feedback visual ("Copiado!" por 2s)
 *  • Mobile-first: layout em coluna única, botão grande (48px+)
 *
 * Quando `payload` é vazio (master ainda não configurou chave),
 * mostra estado vazio amigável instruindo a contatar o suporte.
 */
import { onMounted, ref, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    payload: { type: String, default: '' },
    size: { type: Number, default: 220 },
    referencia: { type: String, default: '' },  // ex: "MAC-0008" — mostrada para conciliação
});

const dataUrl = ref('');
const copiado = ref(false);
const erroCopia = ref(null);

async function gerarQr() {
    if (! props.payload) {
        dataUrl.value = '';
        return;
    }
    try {
        dataUrl.value = await QRCode.toDataURL(props.payload, {
            errorCorrectionLevel: 'M',
            margin: 1,
            width: props.size,
            color: { dark: '#0F172A', light: '#FFFFFF' },
        });
    } catch (e) {
        dataUrl.value = '';
    }
}

onMounted(gerarQr);
watch(() => props.payload, gerarQr);

async function copiar() {
    erroCopia.value = null;
    try {
        await navigator.clipboard.writeText(props.payload);
        copiado.value = true;
        setTimeout(() => { copiado.value = false; }, 2000);
    } catch (e) {
        // Fallback para navegadores sem clipboard API
        try {
            const ta = document.createElement('textarea');
            ta.value = props.payload;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            copiado.value = true;
            setTimeout(() => { copiado.value = false; }, 2000);
        } catch (e2) {
            erroCopia.value = 'Não foi possível copiar. Selecione o texto manualmente.';
        }
    }
}
</script>

<template>
    <div class="rounded-xl bg-white ring-1 ring-slate-200 p-4 sm:p-5">
        <!-- Estado vazio (chave PIX ainda não configurada pelo master) -->
        <div v-if="! payload" class="text-center py-6">
            <div class="h-12 w-12 mx-auto rounded-full bg-amber-100 text-amber-700 flex items-center justify-center mb-3">
                ⚠
            </div>
            <p class="text-sm font-medium text-slate-900">PIX ainda não disponível</p>
            <p class="mt-1 text-xs text-slate-500">
                Aguarde — a equipe vai habilitar o pagamento dessa fatura.
            </p>
        </div>

        <div v-else class="flex flex-col items-center gap-4">
            <!-- QR Code -->
            <div class="rounded-lg bg-white ring-1 ring-slate-200 p-2 inline-block">
                <img v-if="dataUrl" :src="dataUrl" alt="QR Code PIX"
                     :width="size" :height="size"
                     class="block rounded">
                <div v-else class="flex items-center justify-center text-slate-400 text-xs"
                     :style="{ width: size + 'px', height: size + 'px' }">
                    Gerando QR…
                </div>
            </div>

            <p class="text-xs text-slate-500 text-center">
                Aponte a câmera do app do seu banco para o QR Code,
                ou use o copia-e-cola abaixo.
            </p>

            <!-- Referência para conciliação manual: cliente menciona ao suporte
                 caso o pagamento não atualize automaticamente. -->
            <div v-if="referencia" class="w-full rounded-lg bg-amber-50 ring-1 ring-amber-200 p-3 text-center">
                <div class="text-[11px] uppercase tracking-wider text-amber-700 font-semibold">Referência da fatura</div>
                <div class="mt-0.5 font-mono text-base font-bold text-amber-900">{{ referencia }}</div>
                <p class="text-[11px] text-amber-700 mt-1.5">
                    Guarde este código. Se o pagamento não constar, informe esta referência ao suporte.
                </p>
            </div>

            <!-- Copia-e-cola -->
            <div class="w-full">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                    PIX copia-e-cola
                </label>
                <div class="rounded-lg bg-slate-50 ring-1 ring-slate-200 p-2.5 font-mono text-[11px] leading-relaxed text-slate-700 break-all max-h-24 overflow-y-auto">
                    {{ payload }}
                </div>
            </div>

            <!-- Botão copiar -->
            <button type="button" @click="copiar"
                :class="[
                    'w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-sm font-semibold transition shadow-sm',
                    copiado
                        ? 'bg-emerald-600 text-white'
                        : 'bg-macaybas-primary-700 text-white hover:bg-macaybas-primary-800',
                ]"
            >
                <svg v-if="copiado" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                {{ copiado ? 'Código copiado!' : 'Copiar código PIX' }}
            </button>

            <p v-if="erroCopia" class="text-xs text-rose-700 text-center">{{ erroCopia }}</p>
        </div>
    </div>
</template>
