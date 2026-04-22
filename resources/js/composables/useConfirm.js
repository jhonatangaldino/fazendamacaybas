import { ref, readonly } from 'vue';

const state = ref({
    open: false,
    title: 'Confirmar',
    message: '',
    confirmText: 'Confirmar',
    cancelText: 'Cancelar',
    variant: 'danger', // danger | primary
    icon: 'warning',   // warning | danger | question | info
    resolver: null,
});

/**
 * Abre um modal customizado de confirmação.
 * Uso: const ok = await confirm({ message: 'Excluir item?' })
 * Retorna Promise<boolean>.
 */
function confirm(options = {}) {
    return new Promise((resolve) => {
        state.value = {
            open: true,
            title: options.title || 'Confirmar ação',
            message: options.message || 'Tem certeza?',
            confirmText: options.confirmText || 'Confirmar',
            cancelText: options.cancelText || 'Cancelar',
            variant: options.variant || 'danger',
            icon: options.icon || (options.variant === 'primary' ? 'question' : 'warning'),
            resolver: resolve,
        };
    });
}

function handleConfirm() {
    state.value.resolver?.(true);
    state.value.open = false;
}
function handleCancel() {
    state.value.resolver?.(false);
    state.value.open = false;
}

export function useConfirm() {
    return {
        state: readonly(state),
        confirm,
        handleConfirm,
        handleCancel,
    };
}
