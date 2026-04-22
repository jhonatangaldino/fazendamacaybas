<script setup>
/**
 * Leitor de código de barras via câmera (EAN-13, UPC, Code128, QR).
 * Usa @zxing/browser — decodificação 100% client-side.
 *
 * Uso:
 *   <BarcodeScanner @detected="onDetected" @close="showScanner = false" v-if="showScanner" />
 *
 * Emite 'detected' com o texto do código.
 * Para em qualquer um: sucesso, botão fechar, ou tecla ESC.
 */
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { BrowserMultiFormatReader } from '@zxing/browser';

const emit = defineEmits(['detected', 'close']);

const video = ref(null);
const status = ref('Iniciando câmera...');
const error = ref(null);
const camerasDisponiveis = ref([]);
const cameraAtualId = ref(null);
let reader = null;
let controls = null;

async function iniciarCamera(deviceId = null) {
    try {
        status.value = 'Acessando câmera...';
        if (controls) controls.stop();

        reader = reader || new BrowserMultiFormatReader();

        // Lista câmeras na primeira execução
        if (!camerasDisponiveis.value.length) {
            const list = await BrowserMultiFormatReader.listVideoInputDevices();
            camerasDisponiveis.value = list;
            // Preferir traseira em mobile
            const traseira = list.find(d => /back|traseira|rear|environment/i.test(d.label));
            deviceId = deviceId || traseira?.deviceId || list[0]?.deviceId;
        }
        cameraAtualId.value = deviceId;

        status.value = 'Aponte a câmera para o código';

        controls = await reader.decodeFromVideoDevice(
            deviceId,
            video.value,
            (result, err, ctl) => {
                if (result) {
                    const texto = result.getText();
                    status.value = `✓ Lido: ${texto}`;
                    ctl?.stop();
                    emit('detected', texto);
                }
                // Ignora erros de frames sem código
            }
        );
    } catch (e) {
        error.value = e?.message || 'Não foi possível acessar a câmera. Verifique as permissões do navegador.';
        status.value = '';
    }
}

function trocarCamera() {
    if (camerasDisponiveis.value.length < 2) return;
    const idxAtual = camerasDisponiveis.value.findIndex(d => d.deviceId === cameraAtualId.value);
    const proximo = camerasDisponiveis.value[(idxAtual + 1) % camerasDisponiveis.value.length];
    iniciarCamera(proximo.deviceId);
}

function onKeydown(e) {
    if (e.key === 'Escape') emit('close');
}

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    iniciarCamera();
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    try { controls?.stop(); } catch (_) {}
});
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/80">
            <div class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Escanear código de barras</h3>
                    <button @click="$emit('close')" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
                </div>
                <div class="relative bg-black aspect-video">
                    <video ref="video" class="w-full h-full object-cover" autoplay muted playsinline></video>
                    <!-- Guias visuais -->
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="w-4/5 h-1/2 border-2 border-white/70 rounded-lg relative">
                            <div class="absolute left-0 right-0 top-1/2 h-[2px] bg-red-500 animate-pulse"></div>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3 border-t border-slate-200 flex items-center justify-between gap-3">
                    <div class="text-sm flex-1 min-w-0">
                        <p v-if="error" class="text-red-600 truncate">{{ error }}</p>
                        <p v-else class="text-slate-600 truncate">{{ status }}</p>
                    </div>
                    <button v-if="camerasDisponiveis.length > 1"
                            @click="trocarCamera"
                            class="btn-outline text-sm">
                        Trocar câmera
                    </button>
                    <button @click="$emit('close')" class="btn-outline text-sm">Cancelar</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
