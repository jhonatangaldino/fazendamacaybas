import { ref, readonly } from 'vue';

const toasts = ref([]);
let nextId = 1;

/**
 * Sistema de notificações toast (canto superior direito).
 * Uso:
 *   const { toast } = useToast();
 *   toast.success('Salvo!');
 *   toast.error('Falha ao excluir');
 *   toast.info('Operação em andamento');
 *   toast.warning('Atenção');
 */
function push(type, message, options = {}) {
    const id = nextId++;
    const t = {
        id,
        type,           // success | error | warning | info
        message: String(message),
        title: options.title || null,
        duration: options.duration ?? (type === 'error' ? 7000 : 4000),
    };
    toasts.value.push(t);

    if (t.duration > 0) {
        setTimeout(() => dismiss(id), t.duration);
    }
    return id;
}

function dismiss(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

function clear() {
    toasts.value = [];
}

const toast = {
    success: (msg, opts) => push('success', msg, opts),
    error:   (msg, opts) => push('error', msg, opts),
    warning: (msg, opts) => push('warning', msg, opts),
    info:    (msg, opts) => push('info', msg, opts),
};

export function useToast() {
    return {
        toasts: readonly(toasts),
        toast,
        dismiss,
        clear,
    };
}
