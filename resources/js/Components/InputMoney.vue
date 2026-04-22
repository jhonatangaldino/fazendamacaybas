<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    placeholder: { type: String, default: 'R$ 0,00' },
});

const emit = defineEmits(['update:modelValue']);

/**
 * Máscara monetária BR: tudo que digitado é convertido para centavos.
 * 12345 → R$ 123,45
 * 1234567 → R$ 12.345,67
 */
const display = computed({
    get() {
        if (props.modelValue === '' || props.modelValue === null || props.modelValue === undefined) return '';
        const n = typeof props.modelValue === 'number' ? props.modelValue : parseFloat(String(props.modelValue).replace(',', '.'));
        if (Number.isNaN(n)) return '';
        return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    set(v) {
        const digits = String(v).replace(/\D/g, '');
        if (!digits) { emit('update:modelValue', null); return; }
        const float = parseInt(digits, 10) / 100;
        emit('update:modelValue', float);
    },
});
</script>

<template>
    <input
        :value="display"
        @input="display = $event.target.value"
        :placeholder="placeholder"
        inputmode="numeric"
        class="form-input text-right font-mono"
    />
</template>
