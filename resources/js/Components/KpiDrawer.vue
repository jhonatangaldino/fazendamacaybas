<script setup>
/**
 * Modal de detalhamento de KPI.
 *  - Desktop: modal centralizado com max-w-2xl, max-h-[90vh]
 *  - Mobile: modal que ocupa quase a tela toda (95vh) com header/footer sticky
 *
 * Sempre centralizado — listas longas ganham altura sem virar drawer estreito.
 * Fecha com ESC, clique no overlay ou X.
 */
import { onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    fullLink: { type: Object, default: null },
});
const emit = defineEmits(['close']);

function onKey(e) { if (e.key === 'Escape') emit('close'); }
onMounted(() => document.addEventListener('keydown', onKey));
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});
watch(() => props.open, (v) => { document.body.style.overflow = v ? 'hidden' : ''; });
</script>

<template>
    <Teleport to="body">
        <transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="open" class="fixed inset-0 z-50 bg-black/50" @click="$emit('close')"></div>
        </transition>

        <transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 scale-95 sm:scale-100 sm:translate-y-4"
            enter-to-class="opacity-100 scale-100 sm:translate-y-0"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95">
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 pointer-events-none">
                <div class="relative bg-white rounded-xl sm:rounded-2xl shadow-2xl flex flex-col pointer-events-auto
                            w-full max-w-2xl h-[95vh] sm:h-auto sm:max-h-[90vh]"
                     @click.stop>
                    <!-- Header sticky -->
                    <header class="flex items-start justify-between gap-3 px-5 py-4 border-b border-slate-200 flex-shrink-0">
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-slate-900 truncate">{{ title }}</h2>
                            <p v-if="subtitle" class="text-sm text-slate-500 truncate">{{ subtitle }}</p>
                        </div>
                        <button @click="$emit('close')"
                                class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg p-1.5 flex-shrink-0 transition-colors"
                                aria-label="Fechar">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </header>

                    <!-- Corpo scrollável -->
                    <div class="flex-1 overflow-y-auto overscroll-contain px-5 py-4">
                        <slot />
                    </div>

                    <!-- Footer opcional -->
                    <footer v-if="fullLink" class="px-5 py-3 border-t border-slate-200 bg-slate-50 flex-shrink-0 rounded-b-xl sm:rounded-b-2xl">
                        <a :href="fullLink.href"
                           class="flex items-center justify-center gap-2 w-full btn-outline text-sm">
                            {{ fullLink.label || 'Ver lista completa' }}
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </footer>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
