<script setup>
import { useConfirm } from '@/composables/useConfirm.js';

const { state, handleConfirm, handleCancel } = useConfirm();

const iconPaths = {
    warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    danger: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    question: 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
};
const iconColors = {
    warning: { bg: 'bg-amber-100', text: 'text-amber-600' },
    danger: { bg: 'bg-red-100', text: 'text-red-600' },
    question: { bg: 'bg-blue-100', text: 'text-blue-600' },
    info: { bg: 'bg-macaybas-primary-100', text: 'text-macaybas-primary-700' },
};

function onKey(e) {
    if (!state.value.open) return;
    if (e.key === 'Escape') handleCancel();
    else if (e.key === 'Enter') handleConfirm();
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="state.open"
                 class="fixed inset-0 z-[80] flex items-center justify-center p-4"
                 role="dialog"
                 aria-modal="true"
                 @keydown="onKey"
                 tabindex="-1">
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px]" @click="handleCancel"></div>

                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <div class="flex items-start gap-4">
                        <div :class="['h-12 w-12 rounded-full flex items-center justify-center flex-shrink-0',
                                      iconColors[state.icon]?.bg ?? 'bg-slate-100']">
                            <svg :class="['h-7 w-7', iconColors[state.icon]?.text ?? 'text-slate-600']"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="iconPaths[state.icon] ?? iconPaths.info"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-lg font-semibold text-slate-900">{{ state.title }}</h3>
                            <p class="mt-1 text-sm text-slate-600 whitespace-pre-line">{{ state.message }}</p>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 mt-6">
                        <button type="button" @click="handleCancel" class="btn-outline">
                            {{ state.cancelText }}
                        </button>
                        <button type="button" @click="handleConfirm"
                                :class="state.variant === 'danger' ? 'btn-danger' : 'btn-primary'"
                                autofocus>
                            {{ state.confirmText }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
