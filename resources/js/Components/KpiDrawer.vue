<script setup>
/**
 * Painel de detalhamento de KPI.
 *  - Desktop (sm+): side-drawer direito, 520px de largura
 *  - Mobile (< sm): bottom-sheet que sobe só 80vh com handle no topo
 *
 * Fecha com ESC, swipe para baixo no handle (mobile), clique fora ou X.
 */
import { onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    fullLink: { type: Object, default: null }, // { href, label }
});
const emit = defineEmits(['close']);

function onKey(e) { if (e.key === 'Escape') emit('close'); }
onMounted(() => document.addEventListener('keydown', onKey));
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});

watch(() => props.open, (v) => {
    document.body.style.overflow = v ? 'hidden' : '';
});
</script>

<template>
    <Teleport to="body">
        <!-- Overlay escurecido -->
        <transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="open" class="fixed inset-0 z-50 bg-black/40" @click="$emit('close')"></div>
        </transition>

        <!-- Painel: bottom-sheet no mobile, side-drawer no desktop -->
        <transition
            :enter-active-class="'transition-transform duration-300 ease-out'"
            :enter-from-class="'translate-y-full sm:translate-y-0 sm:translate-x-full'"
            :enter-to-class="'translate-y-0 sm:translate-x-0'"
            :leave-active-class="'transition-transform duration-200 ease-in'"
            :leave-from-class="'translate-y-0 sm:translate-x-0'"
            :leave-to-class="'translate-y-full sm:translate-y-0 sm:translate-x-full'">
            <aside v-if="open"
                   class="fixed z-50 bg-white shadow-2xl flex flex-col
                          inset-x-0 bottom-0 max-h-[85vh] rounded-t-2xl
                          sm:inset-x-auto sm:inset-y-0 sm:right-0 sm:max-h-none sm:w-full sm:max-w-lg sm:rounded-none">
                <!-- Handle visível só no mobile -->
                <div class="sm:hidden flex justify-center pt-2 pb-1 cursor-pointer" @click="$emit('close')">
                    <div class="h-1.5 w-12 bg-slate-300 rounded-full"></div>
                </div>

                <!-- Header -->
                <header class="sticky top-0 z-10 flex items-start justify-between gap-3 px-5 py-3 sm:px-6 sm:py-4 bg-white border-b border-slate-200">
                    <div class="min-w-0">
                        <h2 class="text-base sm:text-lg font-semibold text-slate-900 truncate">{{ title }}</h2>
                        <p v-if="subtitle" class="text-xs sm:text-sm text-slate-500 truncate">{{ subtitle }}</p>
                    </div>
                    <button @click="$emit('close')"
                            class="text-slate-400 hover:text-slate-700 p-1 flex-shrink-0"
                            aria-label="Fechar">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </header>

                <!-- Corpo scrollável -->
                <div class="flex-1 overflow-y-auto overscroll-contain px-5 sm:px-6 py-3 sm:py-4">
                    <slot />
                </div>

                <!-- Footer opcional -->
                <footer v-if="fullLink" class="px-5 sm:px-6 py-3 border-t border-slate-200 bg-slate-50 safe-area-bottom">
                    <a :href="fullLink.href"
                       class="flex items-center justify-center gap-2 w-full btn-outline text-sm">
                        {{ fullLink.label || 'Ver lista completa' }}
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </footer>
            </aside>
        </transition>
    </Teleport>
</template>

<style>
/* Respeita a safe-area do iOS no rodapé (home indicator) */
.safe-area-bottom {
    padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0));
}
</style>
