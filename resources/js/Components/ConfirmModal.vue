<script setup>
import { toRef, onMounted, onBeforeUnmount } from 'vue';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';

const props = defineProps({
    show: Boolean,
    title: { type: String, default: 'Confirmar' },
    message: { type: String, default: 'Tem certeza?' },
    confirmText: { type: String, default: 'Confirmar' },
    cancelText: { type: String, default: 'Cancelar' },
    variant: { type: String, default: 'danger' }, // danger | primary
});
const emit = defineEmits(['confirm', 'cancel']);
useBodyScrollLock(toRef(props, 'show'));

function handleEsc(e) {
    if (e.key === 'Escape' && props.show) { e.stopPropagation(); emit('cancel'); }
}
onMounted(() => window.addEventListener('keydown', handleEsc));
onBeforeUnmount(() => window.removeEventListener('keydown', handleEsc));
</script>

<template>
    <Teleport to="body">
        <!-- F9 a11y · role="dialog" + aria-modal pra screen readers detectarem.
             aria-labelledby aponta pro h3 do título. Antes o modal era invisível
             pra NVDA/JAWS — usuário com deficiência visual não sabia que abriu. -->
        <div v-if="show"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="confirm-modal-title">
            <div class="absolute inset-0 bg-black/40" @click="$emit('cancel')"></div>
            <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                <h3 id="confirm-modal-title" class="text-lg font-semibold text-slate-900 mb-2">{{ title }}</h3>
                <p class="text-sm text-slate-600 mb-6">{{ message }}</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-outline" @click="$emit('cancel')">{{ cancelText }}</button>
                    <button type="button" :class="variant === 'danger' ? 'btn-danger' : 'btn-primary'" @click="$emit('confirm')">
                        {{ confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
