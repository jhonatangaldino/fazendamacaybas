<script setup>
/**
 * InputDecimal — campo numérico decimal pt-BR (aceita vírgula E ponto).
 *
 * Por que existe: <input type="number"> rejeita vírgula em quase todos os
 * navegadores (US-centric). Usuário BR digita "12,5" e o campo bloqueia.
 * Bug reportado pelo PO em 2026-04-28.
 *
 * Como funciona:
 *   • type="text" + inputmode="decimal" → no mobile abre o teclado numérico
 *     do iOS/Android (inclui a tecla vírgula no PT-BR)
 *   • aceita vírgula OU ponto durante digitação
 *   • mostra valor com vírgula (formato BR)
 *   • emite Number via update:modelValue (compat com v-model.number antigo,
 *     o backend continua recebendo número, não string)
 *   • prop `decimals` força N casas decimais no blur (ex.: peso "1,5" vira
 *     "1,50" se decimals=2)
 *
 * Uso:
 *   <InputDecimal v-model="form.peso" :decimals="2" placeholder="0,00" />
 *   <InputDecimal v-model="form.area_ha" :decimals="4" :min="0" />
 */
import { ref, watch, computed } from 'vue';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    placeholder: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    min: { type: [Number, String], default: null },
    max: { type: [Number, String], default: null },
    /** Força N casas decimais no display ao perder foco. Null = livre. */
    decimals: { type: Number, default: null },
    /** Sobrescreve a classe (default: form-input) */
    inputClass: { type: String, default: 'form-input' },
});
const emit = defineEmits(['update:modelValue', 'blur']);

const localValue = ref('');

function numberToBR(n) {
    if (n === null || n === undefined || n === '') return '';
    const num = typeof n === 'number' ? n : parseFloat(String(n).replace(',', '.'));
    if (Number.isNaN(num)) return '';
    if (props.decimals !== null) return num.toFixed(props.decimals).replace('.', ',');
    return String(num).replace('.', ',');
}

function brToNumber(s) {
    if (s === '' || s === null || s === undefined) return null;
    const n = parseFloat(String(s).replace(',', '.'));
    return Number.isNaN(n) ? null : n;
}

// Sincroniza modelValue → localValue, mas SÓ se o número mudou de fato
// (preserva digitação intermediária tipo "1," sem reformatar pra "1").
watch(() => props.modelValue, (newVal) => {
    const novaForma = numberToBR(newVal);
    const atualNum = brToNumber(localValue.value);
    const novoNum = newVal === '' || newVal === null ? null : (typeof newVal === 'number' ? newVal : brToNumber(newVal));
    if (atualNum !== novoNum) {
        localValue.value = novaForma;
    }
}, { immediate: true });

function onInput(e) {
    let v = e.target.value;
    // Permite só dígitos, vírgula, ponto e sinal de menos
    v = v.replace(/[^\d,.\-]/g, '');
    // Apenas o primeiro - (sinal negativo) é válido
    v = v.replace(/(?!^)-/g, '');
    // Se tem vírgula E ponto, padroniza pra vírgula (BR) removendo pontos
    if (v.includes(',') && v.includes('.')) {
        v = v.replace(/\./g, '');
    }
    // Apenas um separador decimal — extras viram dígitos
    const parts = v.split(/[,.]/);
    if (parts.length > 2) {
        v = parts[0] + ',' + parts.slice(1).join('');
    }
    localValue.value = v;
    e.target.value = v;
    emit('update:modelValue', brToNumber(v));
}

function onBlur(e) {
    // Reformata com N casas decimais se a prop foi passada
    if (props.decimals !== null && props.modelValue !== null && props.modelValue !== '') {
        const num = typeof props.modelValue === 'number' ? props.modelValue : brToNumber(props.modelValue);
        if (num !== null) {
            localValue.value = num.toFixed(props.decimals).replace('.', ',');
            emit('update:modelValue', parseFloat(num.toFixed(props.decimals)));
        }
    }
    emit('blur', e);
}
</script>

<template>
    <input
        type="text"
        inputmode="decimal"
        :value="localValue"
        @input="onInput"
        @blur="onBlur"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :class="inputClass"
    />
</template>
