<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
});

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) input.value.focus();
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        ref="input"
        :type="type"
        class="form-input"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
