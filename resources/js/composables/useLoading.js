import { ref, readonly } from 'vue';

/**
 * Estado de loading global compartilhado entre todos os componentes.
 * O AdminLayout e o LoadingOverlay observam `pending` para exibir o spinner.
 */
const pending = ref(0);           // contador de operações em andamento
const message = ref('Carregando...');
const progress = ref(null);       // 0..100 ou null

function start(msg = 'Carregando...') {
    pending.value++;
    message.value = msg;
}

function finish() {
    pending.value = Math.max(0, pending.value - 1);
    if (pending.value === 0) {
        progress.value = null;
        message.value = 'Carregando...';
    }
}

function reset() {
    pending.value = 0;
    progress.value = null;
    message.value = 'Carregando...';
}

function setProgress(value, msg) {
    progress.value = value;
    if (msg) message.value = msg;
}

/**
 * Envelopa uma promise, incrementando/decrementando o pending automaticamente.
 *   await withLoading(fetch(...), 'Enviando imagem...');
 */
async function withLoading(promise, msg) {
    start(msg);
    try {
        return await promise;
    } finally {
        finish();
    }
}

export function useLoading() {
    return {
        pending: readonly(pending),
        message: readonly(message),
        progress: readonly(progress),
        start,
        finish,
        reset,
        setProgress,
        withLoading,
    };
}
