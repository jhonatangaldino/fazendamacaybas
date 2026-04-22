<script setup>
import { vMaska } from 'maska/vue';
const props = defineProps({
    modelValue: [String, Number],
    mask: { type: [String, Array], required: true },
    placeholder: String,
    autocomplete: String,
});
defineEmits(['update:modelValue']);
</script>

<template>
    <input
        v-maska :data-maska="mask"
        :value="modelValue"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        @input="$emit('update:modelValue', $event.target.value)"
        class="form-input"
    />
</template>
