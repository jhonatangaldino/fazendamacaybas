<script setup>
/**
 * Icon — ícone SVG decorativo padronizado (Heroicons outline).
 *
 * Diferença em relação a ActionIcon.vue: ActionIcon é um <button> acionável
 * com variantes de cor semântica. Icon é puramente visual — para uso em
 * headers, breadcrumbs, barras de sub-navegação, rótulos de botões texto+ícone.
 *
 * Vocabulário de `name` é deliberadamente estável — se um ícone for substituído,
 * muda-se o path aqui e todas as telas refletem automaticamente.
 *
 * Uso:
 *   <Icon name="edit" />
 *   <Icon name="external-link" :size="20" class="text-slate-400" />
 *
 * Tamanho default: 16px (w-4 h-4) — alinha com texto de botão sm.
 * Paths seguem o padrão Heroicons v2 outline (stroke 1.5-2, viewBox 0 0 24 24).
 */
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: [String, Number], default: 16 },
    strokeWidth: { type: [String, Number], default: 2 },
});

// Paths Heroicons outline — todos stroke, viewBox 24 24.
// Para acrescentar um ícone novo: heroicons.com → copiar o `<path d="..."/>`.
const PATHS = {
    // Navegação
    'arrow-left':        'M10 19l-7-7m0 0l7-7m-7 7h18',
    'chevron-left':      'M15 19l-7-7 7-7',
    'chevron-right':     'M9 5l7 7-7 7',
    'chevron-down':      'M19 9l-7 7-7-7',
    'chevron-up':        'M5 15l7-7 7 7',
    'external-link':     'M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14',
    'home':              'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',

    // Ações comuns
    'edit':              'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
    'trash':             'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
    'plus':              'M12 4v16m8-8H4',
    'eye':               'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
    'copy':              'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z',
    'x':                 'M6 18L18 6M6 6l12 12',
    'check':             'M5 13l4 4L19 7',
    'dots-horizontal':   'M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z',

    // Status
    'check-circle':      'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    'x-circle':          'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    'alert-triangle':    'M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.667 1.73-3L13.73 4c-.77-1.333-2.69-1.333-3.46 0L3.34 16c-.77 1.333.19 3 1.73 3z',
    'info-circle':       'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    'power':             'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
    'bolt':              'M13 10V3L4 14h7v7l9-11h-7z',

    // Domínio do sistema
    'dashboard':         'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z',
    'building':          'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    'card':              'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    'invoice':           'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    'cog':               'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    'menu':              'M4 6h16M4 12h16M4 18h16',
    'document':          'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    'user':              'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    'envelope':          'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    'map-pin':           'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
    'globe':             'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
};

// O path pode ter 2 segmentos (ex. map-pin) — separa em múltiplos <path>.
const segments = computed(() => {
    const p = PATHS[props.name];
    if (! p) {
        // fallback: "?" visual — evita crash mas torna ausência visível
        return ['M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.5M12 18h.01'];
    }
    // separa por " M " para permitir paths compostos (evita bounding incorreto)
    return p.split(/\sM\s/).map((s, i) => (i === 0 ? s : 'M ' + s));
});
</script>

<template>
    <svg
        :width="size"
        :height="size"
        fill="none"
        stroke="currentColor"
        :stroke-width="strokeWidth"
        viewBox="0 0 24 24"
        aria-hidden="true"
        :class="['inline-block flex-shrink-0']"
    >
        <path
            v-for="(d, i) in segments"
            :key="i"
            stroke-linecap="round"
            stroke-linejoin="round"
            :d="d"
        />
    </svg>
</template>
