import { onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Composable que mantém a listagem atualizada sem F5.
 *
 * - Após cada ação (submit de form, delete, toggle), use router.post/put/delete
 *   com opções { preserveScroll: true, only: [...props para recarregar] } — o Inertia
 *   já busca apenas as props alteradas e re-hidrata a tela automaticamente.
 *
 * - Este composable adiciona um polling leve que dá `router.reload` na propriedade
 *   informada a cada N segundos, garantindo que várias abas/usuários vejam a mesma coisa.
 *
 * Uso:
 *   import { useAutoReload } from '@/composables/useAutoReload.js';
 *   useAutoReload(['items'], 15000); // reload de `items` a cada 15s
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
 *
 * Ex.: `submitForm(form, route('admin.items.store'), ['items'])`
 */
export function submitForm(form, url, onlyProps = [], method = 'post') {
    return form[method](url, {
        preserveScroll: true,
        only: onlyProps,
    });
}
