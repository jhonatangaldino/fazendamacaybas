<script setup>
import { computed } from 'vue';
import { vMaska } from 'maska/vue';

const props = defineProps({
    modelValue: [String, Number],
    /**
     * Máscara:
     *  - String: máscara única (ex: '###.###.###-##')
     *  - Array: máscara dinâmica (ex: ['(##) ####-####', '(##) #####-####'])
     *    o maska escolhe a correta com base no número de caracteres digitados
     *
     * Tokens do maska 3.x:
     *  #  = dígito numérico
     *  @  = letra
     *  *  = alfanumérico
     *  H  = hexadecimal
     */
    mask: { type: [String, Array], required: true },
    placeholder: String,
    autocomplete: String,
    tokens: { type: Object, default: null },
});

defineEmits(['update:modelValue']);

/**
 * Maska 3.x aceita máscara dinâmica quando o atributo data-maska é um JSON array.
 * Se passamos Array como atributo Vue normal, ele serializa como "a,b" (vírgula-separated),
 * o que o maska interpreta como UMA máscara com vírgula literal — exatamente o bug reportado.
 */
const maskAttr = computed(() => {
    if (Array.isArray(props.mask)) return JSON.stringify(props.mask);
    return props.mask;
});

/**
 * F4.2: inputmode adequado por tipo de máscara.
 * Máscaras contendo @ (letra) ou * (alfanumérico) usam teclado alfabético.
 * Máscaras somente numéricas (# = dígito) mostram teclado numérico em mobile.
 */
const inputModeComputed = computed(() => {
    const masks = Array.isArray(props.mask) ? props.mask : [props.mask];
    const hasLetters = masks.some((m) => /[@*]/.test(m));
    return hasLetters ? 'text' : 'numeric';
});
</script>

<template>
    <input
        v-maska
        :data-maska="maskAttr"
        :data-maska-tokens="tokens ? JSON.stringify(tokens) : null"
        :value="modelValue"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        :inputmode="inputModeComputed"
        @input="$emit('update:modelValue', $event.target.value)"
        class="form-input"
    />
</template>
