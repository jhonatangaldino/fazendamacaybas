<script setup>
import { onMounted, onBeforeUnmount, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useLoading } from '@/composables/useLoading.js';

const { pending, message, progress, start, finish, setProgress, reset } = useLoading();

let removeStart, removeProgress, removeFinish, removeError, removeInvalid;

function messageForMethod(method, url) {
    const m = (method || 'get').toLowerCase();
    // Inertia pode passar URL object em vez de string — coerce defensivamente
    const u = url == null ? '' : (typeof url === 'string' ? url : String(url));
    if (u.includes('/concluir') || u.includes('/complete')) return 'Concluindo...';
    if (u.includes('/reabrir') || u.includes('/reopen')) return 'Reabrindo...';
    if (u.includes('/publicar') || u.includes('/publish')) return 'Publicando...';
    if (u.includes('/toggle')) return 'Atualizando...';
    if (u.includes('/pagar') || u.includes('/pay')) return 'Quitando...';
    if (u.includes('/resetar-senha') || u.includes('/reset-password')) return 'Resetando senha...';
    if (u.includes('/login')) return 'Entrando...';
    if (u.includes('/logout')) return 'Saindo...';
    if (u.includes('/upload')) return 'Enviando arquivo...';
    if (u.includes('/rascunho') || u.includes('/draft')) return 'Salvando rascunho...';
    return {
        get: 'Carregando...',
        post: 'Salvando...',
        put: 'Salvando...',
        patch: 'Salvando...',
        delete: 'Excluindo...',
    }[m] || 'Processando...';
}

onMounted(() => {
    removeStart = router.on('start', (event) => {
        if (event.detail.visit?.__silent) return;
        const { method, url } = event.detail.visit || {};
        start(messageForMethod(method, url));
    });

    removeProgress = router.on('progress', (event) => {
        if (event.detail.visit?.__silent) return;
        const p = event.detail.progress;
        if (p?.percentage !== undefined) {
            setProgress(p.percentage, 'Enviando arquivos...');
        }
    });

    removeFinish = router.on('finish', (event) => {
        if (event.detail.visit?.__silent) return;
        finish();
    });

    removeError = router.on('error', () => reset());
    removeInvalid = router.on('invalid', () => reset());
});

onBeforeUnmount(() => {
    removeStart?.();
    removeProgress?.();
    removeFinish?.();
    removeError?.();
    removeInvalid?.();
});

const show = computed(() => pending.value > 0);
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-150"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="show"
             class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/30 backdrop-blur-[2px] pointer-events-auto"
             role="status"
             aria-live="polite">
            <div class="bg-white rounded-xl shadow-2xl px-6 py-5 flex flex-col items-center gap-3 max-w-xs mx-4">
                <svg class="animate-spin h-10 w-10 text-macaybas-primary" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-sm font-medium text-slate-900 text-center">{{ message }}</p>
                <div v-if="progress !== null" class="w-full">
                    <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-1.5 bg-macaybas-primary transition-all duration-150"
                             :style="{ width: progress + '%' }"></div>
                    </div>
                    <p class="text-xs text-slate-500 mt-1 text-right">{{ progress }}%</p>
                </div>
            </div>
        </div>
    </Transition>
</template>
