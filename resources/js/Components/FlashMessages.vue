<script setup>
import { computed, watch, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const flash = computed(() => page.props.flash || {});
const visible = ref(true);

watch(flash, () => { visible.value = true; setTimeout(() => (visible.value = false), 5000); });

const types = {
    success: { bg: 'bg-green-50', border: 'border-green-200', text: 'text-green-800' },
    error:   { bg: 'bg-red-50',   border: 'border-red-200',   text: 'text-red-800' },
    info:    { bg: 'bg-blue-50',  border: 'border-blue-200',  text: 'text-blue-800' },
    warning: { bg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-800' },
};
</script>

<template>
    <div v-if="visible" class="px-4 lg:px-8 pt-4 space-y-2">
        <div v-for="type in ['success', 'error', 'info', 'warning']" :key="type">
            <div v-if="flash[type]"
                 :class="[types[type].bg, types[type].border, types[type].text]"
                 class="border rounded-lg px-4 py-3 text-sm flex items-center justify-between">
                <span>{{ flash[type] }}</span>
                <button @click="visible = false" class="opacity-60 hover:opacity-100">&times;</button>
            </div>
        </div>
    </div>
</template>
