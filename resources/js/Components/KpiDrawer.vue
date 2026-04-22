<script setup>
/**
 * Drawer lateral leve — aparece ao clicar em um bullet/KPI mostrando o detalhamento
 * do que compõe aquele número. Fecha com ESC, clique fora, ou botão X.
 *
 * UX: usuários leigos precisam ver "o que é esse número" sem perder contexto.
 * Redirecionar pra outra página é ruim (perde-se o estado anterior).
 */
import { onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    fullLink: { type: Object, default: null }, // { href, label } — "Ver tudo" no rodapé
});
const emit = defineEmits(['close']);

function onKey(e) { if (e.key === 'Escape') emit('close'); }
onMounted(() => document.addEventListener('keydown', onKey));
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));

watch(() => props.open, (v) => {
    document.body.style.overflow = v ? 'hidden' : '';
});
</script>

<template>
    <Teleport to="body">
        <transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="open" class="fixed inset-0 z-50 bg-black/40" @click="$emit('close')"></div>
        </transition>

        <transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="translate-x-full" enter-to-class="translate-x-0"
            leave-active-class="transition-transform duration-200 ease-in"
            leave-from-class="translate-x-0" leave-to-class="translate-x-full">
            <aside v-if="open"
                   class="fixed inset-y-0 right-0 z-50 w-full max-w-lg bg-white shadow-2xl flex flex-col">
                <!-- Header sticky -->
                <header class="sticky top-0 z-10 flex items-start justify-between gap-3 px-6 py-4 bg-white border-b border-slate-200">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-slate-900 truncate">{{ title }}</h2>
                        <p v-if="subtitle" class="text-sm text-slate-500 truncate">{{ subtitle }}</p>
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
                <div class="flex-1 overflow-y-auto px-6 py-4">
                    <slot />
                </div>

                <!-- Footer com link opcional "Ver tudo" -->
                <footer v-if="fullLink" class="sticky bottom-0 px-6 py-3 border-t border-slate-200 bg-slate-50">
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
