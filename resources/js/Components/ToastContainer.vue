<script setup>
import { useToast } from '@/composables/useToast.js';

const { toasts, dismiss } = useToast();

const iconPaths = {
    success: 'M5 13l4 4L19 7',
    error: 'M6 18L18 6M6 6l12 12',
    warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
};
const styles = {
    success: { bg: 'bg-green-50', border: 'border-green-200', iconBg: 'bg-green-500', text: 'text-green-900' },
    error:   { bg: 'bg-red-50',   border: 'border-red-200',   iconBg: 'bg-red-500',   text: 'text-red-900' },
    warning: { bg: 'bg-amber-50', border: 'border-amber-200', iconBg: 'bg-amber-500', text: 'text-amber-900' },
    info:    { bg: 'bg-blue-50',  border: 'border-blue-200',  iconBg: 'bg-blue-500',  text: 'text-blue-900' },
};
</script>

<template>
    <Teleport to="body">
        <!-- Acessibilidade · F7 (QA Deep 2026-04-29): live region NO CONTAINER
             (sempre presente no DOM) garante que screen readers anunciem
             toasts adicionados dinamicamente via TransitionGroup. Antes os
             aria-live estavam só nos filhos (criados ad-hoc), e axe-core
             reportava live-regions=0 em TODAS as rotas — toasts efetivamente
             invisíveis pra usuários NVDA/JAWS. -->
        <div class="fixed top-4 right-4 sm:top-6 sm:right-6 z-[75] flex flex-col gap-3 w-[calc(100vw-2rem)] sm:w-96 max-w-full pointer-events-none"
             role="status"
             aria-live="polite"
             aria-atomic="false"
             aria-relevant="additions">
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="transform translate-x-full opacity-0"
                enter-to-class="transform translate-x-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="transform translate-x-0 opacity-100"
                leave-to-class="transform translate-x-full opacity-0"
                move-class="transition-transform duration-200"
            >
                <div v-for="t in toasts" :key="t.id"
                     :class="[styles[t.type].bg, styles[t.type].border]"
                     class="pointer-events-auto rounded-xl border shadow-lg p-4 flex items-start gap-3 backdrop-blur">
                    <div :class="styles[t.type].iconBg"
                         class="h-8 w-8 rounded-full text-white flex items-center justify-center flex-shrink-0">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="iconPaths[t.type]"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p v-if="t.title" :class="styles[t.type].text" class="font-semibold text-sm mb-0.5">{{ t.title }}</p>
                        <p :class="styles[t.type].text" class="text-sm break-words">{{ t.message }}</p>
                    </div>
                    <button type="button" @click="dismiss(t.id)"
                            class="text-slate-400 hover:text-slate-600 flex-shrink-0"
                            aria-label="Fechar">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
