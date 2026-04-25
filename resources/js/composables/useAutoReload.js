import { onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Polling leve que mantém a listagem atualizada sem F5.
 *
 * OTIMIZAÇÃO Hostinger (500 conn/h):
 *   - Intervalo mínimo elevado para 60s (antes aceitava 15s — 240 req/h por aba).
 *   - Pausa automática após 5min sem interação do usuário.
 *   - Cap de 60 reloads totais por montagem (1h de atividade; depois para).
 *   - Pausa quando tab não está ativa (document.hidden) — já existia.
 *
 * IMPORTANTE: esse reload é marcado como `__silent` no objeto visit
 * para o componente GlobalLoading ignorá-lo — não queremos overlay
 * aparecendo periodicamente.
 */
const MIN_INTERVAL_MS = 60_000;    // piso de 60s (protege contra caller pedindo 15s)
const IDLE_AFTER_MS = 5 * 60_000;  // pausa após 5min sem atividade
const MAX_RELOADS = 60;            // cap de 60 reloads (≈ 1h ativo, depois para)

export function useAutoReload(props = [], intervalMs = 60_000, options = {}) {
    const onlyProps = Array.isArray(props) ? props : [props];
    const effectiveInterval = Math.max(intervalMs, MIN_INTERVAL_MS);

    let intervalId = null;
    let isPaused = false;
    let reloadCount = 0;
    let lastActivity = Date.now();

    const markActivity = () => { lastActivity = Date.now(); };

    const doReload = () => {
        if (isPaused || document.hidden) return;
        if (reloadCount >= MAX_RELOADS) {
            clearInterval(intervalId); intervalId = null;
            return;
        }
        // Idle detection: se usuário não interagiu nos últimos 5min, pausa
        if (Date.now() - lastActivity > IDLE_AFTER_MS) return;

        reloadCount++;
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
        if (!document.hidden) markActivity(); // voltar à aba conta como atividade
    };

    onMounted(() => {
        if (effectiveInterval > 0) {
            intervalId = setInterval(doReload, effectiveInterval);
            document.addEventListener('visibilitychange', onVisibilityChange);
            // Tracking mínimo de atividade — sem overhead
            ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(ev =>
                document.addEventListener(ev, markActivity, { passive: true })
            );
        }
    });

    onBeforeUnmount(() => {
        if (intervalId) clearInterval(intervalId);
        document.removeEventListener('visibilitychange', onVisibilityChange);
        ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(ev =>
            document.removeEventListener(ev, markActivity)
        );
    });

    return {
        reload: doReload,
        pause: () => (isPaused = true),
        resume: () => { isPaused = false; markActivity(); },
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
