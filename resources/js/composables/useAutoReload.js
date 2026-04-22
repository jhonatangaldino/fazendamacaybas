import { onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Polling leve que mantém a listagem atualizada sem F5.
 *
 * IMPORTANTE: esse reload é marcado como `__silent` no objeto visit
 * para o componente GlobalLoading ignorá-lo — não queremos overlay
 * aparecendo a cada 15 segundos.
 */
export function useAutoReload(props = [], intervalMs = 15000, options = {}) {
    const onlyProps = Array.isArray(props) ? props : [props];
    let intervalId = null;
    let isPaused = false;

    const doReload = () => {
        if (isPaused || document.hidden) return;
        router.reload({
            only: onlyProps,
            preserveScroll: true,
            preserveState: true,
            ...options,
            onBefore: (visit) => {
                visit.__silent = true;
                if (options.onBefore) options.onBefore(visit);
            },
        });
    };

    const onVisibilityChange = () => {
        isPaused = document.hidden;
    };

    onMounted(() => {
        if (intervalMs > 0) {
            intervalId = setInterval(doReload, intervalMs);
            document.addEventListener('visibilitychange', onVisibilityChange);
        }
    });

    onBeforeUnmount(() => {
        if (intervalId) clearInterval(intervalId);
        document.removeEventListener('visibilitychange', onVisibilityChange);
    });

    return {
        reload: doReload,
        pause: () => (isPaused = true),
        resume: () => (isPaused = false),
    };
}

/**
 * Helper para submissões de form que precisam atualizar só uma prop após salvar.
 */
export function submitForm(form, url, onlyProps = [], method = 'post') {
    return form[method](url, {
        preserveScroll: true,
        only: onlyProps,
    });
}
