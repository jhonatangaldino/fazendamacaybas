<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: String,
});
</script>

<template>
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ title }}</h1>
            <p v-if="subtitle" class="text-sm text-slate-500 mt-1">{{ subtitle }}</p>
        </div>
        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
