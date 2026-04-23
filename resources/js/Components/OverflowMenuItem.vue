<script setup>
/**
 * OverflowMenuItem — item dentro de <OverflowMenu>.
 *
 * Uso (navegação):
 *   <OverflowMenuItem icon="edit" :href="route('x.edit', id)">Editar</OverflowMenuItem>
 *
 * Uso (ação):
 *   <OverflowMenuItem icon="trash" danger @click="remove()">Remover</OverflowMenuItem>
 *
 * Flags:
 *   - danger=true  → texto vermelho (para ações destrutivas)
 *   - success=true → texto verde   (para reativar etc.)
 *   - disabled=true → não acionável, opacidade reduzida
 *
 * Fecha o menu automaticamente ao ser acionado (via provide injetado pelo pai).
 */
import { inject } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from './Icon.vue';

const props = defineProps({
    icon: { type: String, default: null },
    href: { type: String, default: null },
    danger: { type: Boolean, default: false },
    success: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['click']);

const close = inject('overflowMenuClose', () => {});

function onClick(event) {
    if (props.disabled) {
        event.preventDefault();
        return;
    }
    close();
    emit('click', event);
}
</script>

<template>
    <!-- Modo link: usa Inertia Link (preserva SPA) -->
    <Link
        v-if="href && ! disabled"
        :href="href"
        role="menuitem"
        class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-slate-50 transition"
        :class="[
            danger ? 'text-red-600 hover:bg-red-50' : '',
            success ? 'text-emerald-700 hover:bg-emerald-50' : '',
            (! danger && ! success) ? 'text-slate-700' : '',
        ]"
        @click="onClick"
    >
        <Icon v-if="icon" :name="icon" :size="16"
              :class="danger ? 'text-red-400' : (success ? 'text-emerald-500' : 'text-slate-400')" />
        <span><slot /></span>
    </Link>

    <!-- Modo botão/ação -->
    <button
        v-else
        type="button"
        role="menuitem"
        :disabled="disabled"
        @click.stop="onClick"
        class="w-full text-left flex items-center gap-2 px-4 py-2 text-sm transition"
        :class="[
            disabled ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-50',
            disabled ? 'text-slate-700' : (danger ? 'text-red-600' : success ? 'text-emerald-700' : 'text-slate-700'),
        ]"
    >
        <Icon v-if="icon" :name="icon" :size="16"
              :class="danger ? 'text-red-400' : (success ? 'text-emerald-500' : 'text-slate-400')" />
        <span><slot /></span>
    </button>
</template>
