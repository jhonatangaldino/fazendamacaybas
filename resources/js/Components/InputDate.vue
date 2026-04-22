<script setup>
/**
 * Campo de data brasileiro com calendário flatpickr (pt-BR) + máscara dd/mm/aaaa.
 *
 * Props:
 *   modelValue: ISO yyyy-mm-dd
 *   min / max : ISO yyyy-mm-dd  → clamping automático do valor e bloqueio visual no calendário
 *   placeholder, disabled, required
 *
 * Garante:
 *  - Formato pt-BR exibido (dd/mm/aaaa)
 *  - Navegação por mês/ano no calendário
 *  - Respeita min/max evitando inconsistências entre campos de período
 *  - v-model sempre ISO (yyyy-mm-dd) ou null
 */
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import flatpickr from 'flatpickr';
import { Portuguese } from 'flatpickr/dist/l10n/pt.js';
import 'flatpickr/dist/flatpickr.min.css';
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

const textInput = ref(null);
const anchor = ref(null);
let fp = null;

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
        if (digits.length < 8) { emit('update:modelValue', v || null); return; }
        const dd = digits.slice(0, 2), mm = digits.slice(2, 4), yy = digits.slice(4, 8);
        const iso = `${yy}-${mm}-${dd}`;
        const d = dayjs(iso);
        const next = d.isValid() ? clamp(iso) : null;
        emit('update:modelValue', next);
        if (fp && next) fp.setDate(next, false);
    },
});

function openPicker() {
    if (props.disabled) return;
    fp?.open();
}

onMounted(() => {
    fp = flatpickr(anchor.value, {
        locale: Portuguese,
        dateFormat: 'Y-m-d',
        allowInput: false,
        disableMobile: false,        // usa UI do flatpickr mesmo em mobile (consistente)
        minDate: props.min || undefined,
        maxDate: props.max || undefined,
        defaultDate: props.modelValue || undefined,
        positionElement: textInput.value,
        onChange: (selected) => {
            if (!selected.length) { emit('update:modelValue', null); return; }
            const iso = dayjs(selected[0]).format('YYYY-MM-DD');
            emit('update:modelValue', clamp(iso));
        },
    });
});

watch(() => props.modelValue, (v) => {
    if (!fp) return;
    if (!v) { fp.clear(); return; }
    const current = fp.selectedDates[0] ? dayjs(fp.selectedDates[0]).format('YYYY-MM-DD') : null;
    if (current !== v) fp.setDate(v, false);
});
watch(() => props.min, (v) => fp?.set('minDate', v || undefined));
watch(() => props.max, (v) => fp?.set('maxDate', v || undefined));

onBeforeUnmount(() => { fp?.destroy(); fp = null; });
</script>

<template>
    <div class="relative">
        <input
            ref="textInput"
            v-maska data-maska="##/##/####"
            :value="display"
            @input="display = $event.target.value"
            @focus="openPicker"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            inputmode="numeric"
            class="form-input pr-10"
        />
        <!-- âncora invisível só para o flatpickr posicionar o popup -->
        <input ref="anchor" type="text" class="sr-only" tabindex="-1" aria-hidden="true" />
        <button type="button" @click="openPicker" :disabled="disabled"
                class="absolute right-0 top-0 h-full w-10 flex items-center justify-center text-slate-400 hover:text-macaybas-primary transition-colors"
                :aria-label="'Abrir calendário'"
                tabindex="-1">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </button>
    </div>
</template>

<style>
/* Tema Macaybas aplicado ao flatpickr */
.flatpickr-calendar {
    border-radius: 0.75rem;
    box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.15), 0 10px 10px -5px rgb(0 0 0 / 0.08);
    border: 1px solid rgb(226 232 240);
    font-family: inherit;
    padding: 0.25rem;
}
.flatpickr-day.selected,
.flatpickr-day.selected:hover,
.flatpickr-day.startRange,
.flatpickr-day.endRange {
    background: #166534 !important;
    border-color: #166534 !important;
    color: #fff !important;
}
.flatpickr-day.today {
    border-color: #166534;
    color: #166534;
}
.flatpickr-day:hover { background: rgb(236 253 245); }
.flatpickr-months .flatpickr-month,
.flatpickr-current-month .flatpickr-monthDropdown-months,
.flatpickr-current-month input.cur-year {
    color: rgb(30 41 59);
    font-weight: 600;
}
.flatpickr-weekday { color: rgb(100 116 139); font-weight: 600; }
</style>
