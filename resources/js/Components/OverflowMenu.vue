<script setup>
/**
 * OverflowMenu — menu "⋯" padronizado para ações secundárias.
 *
 * COMPORTAMENTO ADAPTATIVO:
 *   • Desktop (≥ 640px): popover via <Teleport to="body">
 *     - Mede o botão (getBoundingClientRect) + tamanho do menu pós-render
 *     - Decide top/bottom: se faltar espaço abaixo, abre PARA CIMA
 *     - Decide left/right: se ultrapassar a borda direita, alinha à esquerda do botão
 *     - Reage a scroll/resize: recalcula posição enquanto aberto
 *     - z-index 60 (acima de modais comuns) garante que nada da tabela corte o menu
 *
 *   • Mobile (< 640px): bottom sheet
 *     - Backdrop escurecido (toque fora fecha)
 *     - Sheet ancorado ao bottom, full width, rounded-t-2xl, slide-up
 *     - Itens com touch target 44px+
 *
 * API preservada — não muda nada para quem já usa:
 *   <OverflowMenu label="Mais ações para X">
 *     <OverflowMenuItem icon="edit" :href="route('...')">Editar</OverflowMenuItem>
 *     <OverflowMenuItem icon="trash" danger @click="remove()">Remover</OverflowMenuItem>
 *     <OverflowMenuDivider />
 *   </OverflowMenu>
 */
import { computed, nextTick, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    label: { type: String, default: 'Mais ações' },
    align: { type: String, default: 'right' },     // hint só usado no desktop como preferência
    sheetTitle: { type: String, default: null },   // título opcional do bottom sheet
});

const open = ref(false);
const trigger = ref(null);   // <button> que abre o menu
const popover = ref(null);   // <div> do menu (desktop)
const isMobile = ref(false);

// Coordenadas computadas para o popover desktop
const pos = ref({ top: 0, left: 0, ready: false });
const direction = ref('down'); // 'down' | 'up' — controla origem da animação

const MENU_GAP = 6;           // px entre botão e menu
const VIEWPORT_PAD = 12;      // padding mínimo da viewport
const MOBILE_BREAKPOINT = 640;

function detectMobile() {
    isMobile.value = typeof window !== 'undefined' && window.innerWidth < MOBILE_BREAKPOINT;
}

function close() { open.value = false; }
function toggle() {
    detectMobile();
    open.value = !open.value;
}

provide('overflowMenuClose', close);

/**
 * Calcula a posição do popover desktop com base no retângulo do botão e do menu.
 * Decisões:
 *   • top/bottom: se espaço abaixo do botão < altura do menu, alinha pelo topo do botão
 *   • esquerda/direita: por padrão alinha o lado direito do menu com o do botão
 *     (preferência "right"); se isso fizer left < pad, recoloca à direita do botão.
 *
 * Roda em nextTick após render para que o menu já tenha tamanho real.
 */
async function recompute() {
    if (! open.value || isMobile.value || ! trigger.value) return;
    await nextTick();
    if (! popover.value) return;

    const tRect = trigger.value.getBoundingClientRect();
    const pRect = popover.value.getBoundingClientRect();
    const vw = window.innerWidth;
    const vh = window.innerHeight;

    // Vertical
    const espacoAbaixo = vh - tRect.bottom - VIEWPORT_PAD;
    const espacoAcima = tRect.top - VIEWPORT_PAD;
    let top;
    if (pRect.height + MENU_GAP <= espacoAbaixo) {
        top = tRect.bottom + MENU_GAP;
        direction.value = 'down';
    } else if (pRect.height + MENU_GAP <= espacoAcima) {
        top = tRect.top - pRect.height - MENU_GAP;
        direction.value = 'up';
    } else {
        // Cabe em nenhum dos dois (raro): cola embaixo e clampa
        top = Math.max(VIEWPORT_PAD, vh - pRect.height - VIEWPORT_PAD);
        direction.value = 'down';
    }

    // Horizontal — alinhamento preferencial pelo final direito do botão
    let left;
    if (props.align === 'left') {
        left = tRect.left;
        if (left + pRect.width > vw - VIEWPORT_PAD) {
            left = vw - pRect.width - VIEWPORT_PAD;
        }
    } else {
        left = tRect.right - pRect.width;
        if (left < VIEWPORT_PAD) {
            left = tRect.left;
            if (left + pRect.width > vw - VIEWPORT_PAD) {
                left = vw - pRect.width - VIEWPORT_PAD;
            }
        }
    }
    if (left < VIEWPORT_PAD) left = VIEWPORT_PAD;

    pos.value = { top: Math.round(top), left: Math.round(left), ready: true };
}

// Recalcula sempre que o menu é aberto
watch(open, async (v) => {
    if (v) {
        pos.value.ready = false;
        await nextTick();
        await recompute();
    }
});

// Listeners globais — fecha em scroll/resize fora do menu, recalcula se ainda aberto
function onScroll() {
    if (! open.value) return;
    if (isMobile.value) return;          // bottom sheet não precisa
    recompute();
}
function onResize() {
    detectMobile();
    if (open.value) recompute();
}
function onDocumentClick(event) {
    if (! open.value) return;
    const t = event.target;
    if (trigger.value && trigger.value.contains(t)) return;
    if (popover.value && popover.value.contains(t)) return;
    close();
}
function onKeydown(event) {
    if (event.key === 'Escape' && open.value) close();
}

onMounted(() => {
    detectMobile();
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('scroll', onScroll, true); // capture: pega scroll de tabelas internas
    window.addEventListener('resize', onResize);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('scroll', onScroll, true);
    window.removeEventListener('resize', onResize);
});

const popoverStyle = computed(() => ({
    top: pos.value.top + 'px',
    left: pos.value.left + 'px',
    visibility: pos.value.ready ? 'visible' : 'hidden',
}));
</script>

<template>
    <button
        ref="trigger"
        type="button"
        v-tooltip="label"
        @click.stop="toggle"
        :aria-label="label"
        :aria-expanded="open"
        aria-haspopup="menu"
        :class="[
            'inline-flex items-center justify-center flex-shrink-0 h-10 w-10 rounded-lg transition',
            'ring-1 focus:outline-none focus:ring-2 focus:ring-macaybas-primary-400',
            open
                ? 'ring-macaybas-primary-300 bg-macaybas-primary-50 text-macaybas-primary-800'
                : 'ring-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 hover:ring-macaybas-primary-300',
        ]"
    >
        <Icon name="dots-vertical" :size="20" :stroke-width="2.5" />
    </button>

    <!-- DESKTOP: popover teleportado para <body>, posição calculada via JS -->
    <Teleport to="body">
        <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open && ! isMobile"
                ref="popover"
                role="menu"
                :style="popoverStyle"
                :class="[
                    'fixed w-56 bg-white rounded-xl shadow-xl ring-1 ring-slate-200 py-1 z-[60] text-left',
                    direction === 'up' ? 'origin-bottom' : 'origin-top',
                ]"
            >
                <slot />
            </div>
        </transition>

        <!-- MOBILE: bottom sheet -->
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open && isMobile"
                class="fixed inset-0 z-[60] bg-black/40"
                @click.self="close"
                aria-hidden="true"
            ></div>
        </transition>

        <transition
            enter-active-class="transition transform duration-200 ease-out"
            enter-from-class="translate-y-full"
            enter-to-class="translate-y-0"
            leave-active-class="transition transform duration-150 ease-in"
            leave-from-class="translate-y-0"
            leave-to-class="translate-y-full"
        >
            <div
                v-if="open && isMobile"
                role="menu"
                class="fixed inset-x-0 bottom-0 z-[61] bg-white rounded-t-2xl shadow-2xl pb-[env(safe-area-inset-bottom)] max-h-[85vh] overflow-y-auto"
            >
                <!-- Handle visual (drag indicator) -->
                <div class="flex justify-center pt-2 pb-1" aria-hidden="true">
                    <div class="h-1.5 w-12 rounded-full bg-slate-300"></div>
                </div>
                <div v-if="sheetTitle || label" class="px-5 pt-2 pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-semibold text-slate-900">{{ sheetTitle ?? label }}</h3>
                </div>
                <div class="py-1.5 sheet-items">
                    <slot />
                </div>
                <button
                    type="button"
                    @click="close"
                    class="w-full py-3.5 text-sm font-semibold text-slate-700 border-t border-slate-100 hover:bg-slate-50"
                >
                    Cancelar
                </button>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
/*
 * Touch-friendly: dentro do bottom sheet, força os <OverflowMenuItem> a ter
 * altura mínima de 48px e padding maior. O componente filho é stateless
 * quanto a contexto, então fazemos o ajuste por CSS escopado ao container.
 */
.sheet-items :deep(a),
.sheet-items :deep(button) {
    min-height: 48px;
    padding: 14px 20px !important;
    font-size: 0.95rem;
}
.sheet-items :deep(svg) {
    width: 20px !important;
    height: 20px !important;
}
</style>
