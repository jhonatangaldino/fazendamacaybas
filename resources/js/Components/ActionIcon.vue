<script setup>
/**
 * Botão de ação icônico para colunas de ações em DataTables.
 *
 * F4.2 — MOBILE-FIRST:
 *   - Área de toque 44×44 em mobile (padrão WCAG 2.5.5)
 *   - 36×36 em desktop (md:) para densidade em tabelas grandes
 *   - Ícone SVG cresce junto: 20px mobile, 18px desktop
 *   - Tooltip nativo via `title` (mouse/teclado)
 *   - Label textual visível ao lado do ícone se `showLabel` ativo (mobile)
 *   - Variantes de cor por intenção (edit=slate, danger=red, success=green…)
 */
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, required: true }, // edit|delete|power-off|reactivate|pay|reset-password|view|download|upload|toggle-on|toggle-off|link|pdf|add|publish|copy|drag
    title: { type: String, required: true },
    variant: { type: String, default: 'auto' }, // auto|slate|primary|danger|success|warning|info
    disabled: { type: Boolean, default: false },
    size: { type: String, default: 'md' }, // sm|md
});
defineEmits(['click']);

const paths = {
    edit: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
    delete: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
    'power-off': 'M18.36 6.64a9 9 0 11-12.73 0M12 2v10',
    reactivate: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
    pay: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    'reset-password': 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
    view: 'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
    download: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4',
    upload: 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12',
    'toggle-on': 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    'toggle-off': 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    link: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
    pdf: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    add: 'M12 6v6m0 0v6m0-6h6m-6 0H6',
    publish: 'M7 11l5-5m0 0l5 5m-5-5v12',
    copy: 'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z',
    drag: 'M4 6h16M4 12h16M4 18h16',
    scale: 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3',
    syringe: 'M4.5 12.75l6 6 9-13.5',
    heart: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
    history: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    barcode: 'M4 4v16M8 4v16M12 4v8m0 4v4M16 4v16M20 4v16',
    camera: 'M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9zM15 13a3 3 0 11-6 0 3 3 0 016 0z',
};

const autoVariant = {
    edit: 'slate',
    delete: 'danger',
    'power-off': 'danger',
    reactivate: 'success',
    pay: 'success',
    'reset-password': 'warning',
    view: 'info',
    download: 'slate',
    upload: 'slate',
    'toggle-on': 'success',
    'toggle-off': 'slate',
    link: 'info',
    pdf: 'slate',
    add: 'primary',
    publish: 'primary',
    copy: 'slate',
    drag: 'slate',
    scale: 'info',
    syringe: 'success',
    heart: 'danger',
    history: 'slate',
    barcode: 'info',
    camera: 'info',
};

const variantClasses = {
    slate: 'text-slate-500 hover:text-slate-800 hover:bg-slate-100',
    primary: 'text-macaybas-primary-700 hover:text-macaybas-primary-900 hover:bg-macaybas-primary-50',
    danger: 'text-red-600 hover:text-red-800 hover:bg-red-50',
    success: 'text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50',
    warning: 'text-amber-600 hover:text-amber-800 hover:bg-amber-50',
    info: 'text-sky-600 hover:text-sky-800 hover:bg-sky-50',
};

const effectiveVariant = computed(() =>
    props.variant === 'auto' ? (autoVariant[props.type] || 'slate') : props.variant
);
const d = computed(() => paths[props.type] || paths.edit);
// F4.2: área de toque ≥40px em qualquer touch device (mobile + tablet),
// reduz só em desktop puro (lg=1024px+, sem touch). Padrão WCAG 2.5.5.
// Antes usava md: (≥768px) que pegava iPad como desktop e quebrava UX touch.
const sizeClass = computed(() =>
    props.size === 'sm'
        ? 'h-10 w-10 lg:h-8 lg:w-8'
        : 'h-11 w-11 lg:h-9 lg:w-9',
);
const iconSize = computed(() =>
    props.size === 'sm'
        ? 'h-5 w-5 lg:h-4 lg:w-4'
        : 'h-5 w-5 lg:h-[18px] lg:w-[18px]',
);
</script>

<template>
    <button
        type="button"
        :title="title"
        :aria-label="title"
        :disabled="disabled"
        @click.stop="!disabled && $emit('click', $event)"
        :class="[
            sizeClass,
            variantClasses[effectiveVariant],
            disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer',
        ]"
        class="inline-flex items-center justify-center rounded-lg transition-colors touch-manipulation"
    >
        <svg :class="iconSize" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="d" />
        </svg>
    </button>
</template>
