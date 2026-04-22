<script setup>
import { computed } from 'vue';
import dayjs from 'dayjs';
import { vMaska } from 'maska/vue';

const props = defineProps({
    modelValue: { type: [String, Date], default: '' }, // ISO (yyyy-mm-dd) ou Date
    placeholder: { type: String, default: 'dd/mm/aaaa' },
});
const emit = defineEmits(['update:modelValue']);

const display = computed({
    get() {
        if (!props.modelValue) return '';
        const d = dayjs(props.modelValue);
        return d.isValid() ? d.format('DD/MM/YYYY') : String(props.modelValue);
    },
    set(v) {
        const digits = String(v).replace(/\D/g, '');
        if (digits.length !== 8) { emit('update:modelValue', v); return; }
        const dd = digits.slice(0, 2), mm = digits.slice(2, 4), yy = digits.slice(4, 8);
        const iso = `${yy}-${mm}-${dd}`;
        const d = dayjs(iso);
        emit('update:modelValue', d.isValid() ? iso : null);
    },
});
</script>

<template>
    <input
        v-maska data-maska="##/##/####"
        :value="display"
        @input="display = $event.target.value"
        :placeholder="placeholder"
        inputmode="numeric"
        class="form-input"
    />
</template>
