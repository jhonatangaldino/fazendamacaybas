<script setup>
/**
 * Campo de data brasileiro: máscara dd/mm/aaaa + ícone de calendário que abre o date-picker nativo.
 *
 * Props:
 *   modelValue: ISO yyyy-mm-dd (ou Date)
 *   min / max : ISO yyyy-mm-dd   (qualquer valor fora do intervalo é recortado)
 *   placeholder, disabled, required
 *
 * O componente garante que o v-model sempre seja uma data ISO válida dentro do range [min, max],
 * eliminando a possibilidade de "data máxima menor que a mínima" em formulários com período.
 */
import { computed, ref } from 'vue';
import dayjs from 'dayjs';
import { vMaska } from 'maska/vue';

const props = defineProps({
    modelValue: { type: [String, Date], default: '' },
    min: { type: String, default: null },
    max: { type: String, default: null },
    placeholder: { type: String, default: 'dd/mm/aaaa' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);
const nativeInput = ref(null);

function clamp(iso) {
    if (!iso) return iso;
    if (props.min && iso < props.min) return props.min;
    if (props.max && iso > props.max) return props.max;
    return iso;
}

const display = computed({
    get() {
        if (!props.modelValue) return '';
        const d = dayjs(props.modelValue);
        return d.isValid() ? d.format('DD/MM/YYYY') : String(props.modelValue);
    },
    set(v) {
        const digits = String(v).replace(/\D/g, '');
        if (digits.length < 8) { emit('update:modelValue', v); return; }
        const dd = digits.slice(0, 2), mm = digits.slice(2, 4), yy = digits.slice(4, 8);
        const iso = `${yy}-${mm}-${dd}`;
        const d = dayjs(iso);
        emit('update:modelValue', d.isValid() ? clamp(iso) : null);
    },
});

const isoValue = computed(() => {
    if (!props.modelValue) return '';
    const d = dayjs(props.modelValue);
    return d.isValid() ? d.format('YYYY-MM-DD') : '';
});

function onNativeChange(e) {
    const v = e.target.value;
    emit('update:modelValue', v ? clamp(v) : null);
}
</script>

<template>
    <div class="relative">
        <input
            v-maska data-maska="##/##/####"
            :value="display"
            @input="display = $event.target.value"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            inputmode="numeric"
            class="form-input pr-10"
        />
        <!-- Date-picker nativo invisível sobreposto ao ícone; clicar no ícone abre o calendário do SO. -->
        <input
            ref="nativeInput"
            type="date"
            :value="isoValue"
            :min="min || undefined"
            :max="max || undefined"
            :disabled="disabled"
            @change="onNativeChange"
            class="absolute right-0 top-0 h-full w-10 opacity-0 cursor-pointer"
            tabindex="-1"
            aria-hidden="true"
        />
        <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
    </div>
</template>
