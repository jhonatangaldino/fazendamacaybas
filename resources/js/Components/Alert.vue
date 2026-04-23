<script setup>
/**
 * Alert — alerta/callout inline com variantes semânticas.
 *
 * Uso:
 *   <Alert variant="success" title="Tudo certo">
 *     Alterações publicadas com sucesso.
 *   </Alert>
 *
 *   <Alert variant="warning" dismissible @dismiss="shown = false">
 *     Configuração incompleta — preencha o mapa.
 *   </Alert>
 *
 * Variantes:
 *   - success | error | warning | info
 *
 * Diferente do toast (transitório, canto superior direito), Alert é inline e
 * permanente enquanto não for dismissado. Para feedback de ação use toast;
 * para estado persistente da tela use Alert.
 */
import { computed } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'info',
        validator: (v) => ['success', 'error', 'warning', 'info'].includes(v),
    },
    title: { type: String, default: '' },
    dismissible: { type: Boolean, default: false },
});
defineEmits(['dismiss']);

const styles = computed(() => {
    switch (props.variant) {
        case 'success':
            return {
                wrapper: 'bg-emerald-50 ring-emerald-200',
                iconBg: 'bg-emerald-500 text-white',
                title: 'text-emerald-900',
                body: 'text-emerald-800',
                icon: 'check-circle',
                dismissHover: 'hover:bg-emerald-100 text-emerald-700',
            };
        case 'error':
            return {
                wrapper: 'bg-red-50 ring-red-200',
                iconBg: 'bg-red-500 text-white',
                title: 'text-red-900',
                body: 'text-red-800',
                icon: 'x-circle',
                dismissHover: 'hover:bg-red-100 text-red-700',
            };
        case 'warning':
            return {
                wrapper: 'bg-amber-50 ring-amber-200',
                iconBg: 'bg-amber-500 text-white',
                title: 'text-amber-900',
                body: 'text-amber-800',
                icon: 'alert-triangle',
                dismissHover: 'hover:bg-amber-100 text-amber-700',
            };
        case 'info':
        default:
            return {
                wrapper: 'bg-sky-50 ring-sky-200',
                iconBg: 'bg-sky-500 text-white',
                title: 'text-sky-900',
                body: 'text-sky-800',
                icon: 'info-circle',
                dismissHover: 'hover:bg-sky-100 text-sky-700',
            };
    }
});
</script>

<template>
    <div class="rounded-xl ring-1 p-4 flex items-start gap-3" :class="styles.wrapper">
        <div class="h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0" :class="styles.iconBg">
            <Icon :name="styles.icon" :size="16" stroke-width="2.5" />
        </div>

        <div class="flex-1 min-w-0 text-sm">
            <p v-if="title" class="font-semibold" :class="styles.title">{{ title }}</p>
            <div :class="[styles.body, title ? 'mt-0.5' : '']">
                <slot />
            </div>
        </div>

        <button
            v-if="dismissible"
            type="button"
            v-tooltip="'Fechar'"
            @click="$emit('dismiss')"
            class="h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0 transition"
            :class="styles.dismissHover"
            aria-label="Fechar aviso"
        >
            <Icon name="x" :size="16" />
        </button>
    </div>
</template>
