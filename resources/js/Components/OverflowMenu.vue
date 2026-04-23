<script setup>
/**
 * OverflowMenu — menu "⋯" padronizado para ações secundárias.
 *
 * Uso:
 *   <OverflowMenu label="Mais ações">
 *     <OverflowMenuItem icon="edit" :href="route('...')">Editar</OverflowMenuItem>
 *     <OverflowMenuItem icon="trash" danger @click="remove()">Remover</OverflowMenuItem>
 *     <OverflowMenuDivider />
 *     <OverflowMenuItem icon="power" disabled>...</OverflowMenuItem>
 *   </OverflowMenu>
 *
 * Estado interno próprio: abrir/fechar + click-outside. Sem dependência externa
 * e sem lift de estado — cada instância é independente (uma por linha da tabela).
 *
 * Fecha ao:
 *   - clicar fora
 *   - pressionar Esc
 *   - selecionar um item (auto-close)
 *
 * Posição: alinhado à direita por default (ações ficam no fim das linhas).
 */
import { onBeforeUnmount, onMounted, provide, ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    label: { type: String, default: 'Mais ações' },
    align: { type: String, default: 'right' }, // 'right' | 'left'
});

const open = ref(false);
const root = ref(null);

function toggle() {
    open.value = !open.value;
}
function close() {
    open.value = false;
}

// Expõe `close` para os itens filhos via provide — cada <OverflowMenuItem> chama
// close() quando acionado, fechando o menu automaticamente.
provide('overflowMenuClose', close);

function handleDocumentClick(event) {
    if (! open.value) return;
    if (root.value && ! root.value.contains(event.target)) close();
}
function handleEsc(event) {
    if (event.key === 'Escape' && open.value) close();
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleEsc);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleEsc);
});
</script>

<template>
    <div ref="root" class="relative inline-block">
        <button
            type="button"
            v-tooltip="label"
            @click.stop="toggle"
            :aria-expanded="open"
            aria-haspopup="menu"
            class="p-2 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-300"
        >
            <Icon name="dots-horizontal" :size="20" />
        </button>

        <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                role="menu"
                class="absolute top-full mt-1 w-56 bg-white rounded-xl shadow-lg ring-1 ring-slate-200 py-1 z-40 text-left origin-top"
                :class="align === 'left' ? 'left-0' : 'right-0'"
            >
                <slot />
            </div>
        </transition>
    </div>
</template>
