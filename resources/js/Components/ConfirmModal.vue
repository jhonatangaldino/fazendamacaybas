<script setup>
defineProps({
    show: Boolean,
    title: { type: String, default: 'Confirmar' },
    message: { type: String, default: 'Tem certeza?' },
    confirmText: { type: String, default: 'Confirmar' },
    cancelText: { type: String, default: 'Cancelar' },
    variant: { type: String, default: 'danger' }, // danger | primary
});
defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="$emit('cancel')"></div>
            <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ title }}</h3>
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
