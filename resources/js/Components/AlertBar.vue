<script setup>
/**
 * AlertBar — barra de alertas globais no topo do AdminLayout.
 *
 * Filosofia: o cliente não pode precisar entrar no Dashboard para descobrir
 * que tem coisa quebrando. Esta barra fica visível em TODAS as telas /admin/*.
 *
 * Estado:
 *   • Lê `usePage().props.alerts` (vem do HandleInertiaRequests).
 *   • Cada alerta dispensável fica oculto via Set local (não persiste — recompõe
 *     no próximo carregamento se ainda válido).
 *   • Se vazia, não renderiza nada (sem espaço fantasma).
 *
 * UX:
 *   • Crítico (vermelho) > Atenção (âmbar) > Info (azul)
 *   • CTA com Inertia <Link> levando direto à tela que resolve
 *   • Mobile: ocupa largura total, ícone + título principal + CTA — descrição esconde
 *   • Múltiplos alertas: empilha verticalmente
 */
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const dismissed = ref(new Set());

const alerts = computed(() => {
    const list = page.props.alerts ?? [];
    return list.filter(a => ! dismissed.value.has(a.id));
});

function dismiss(id) {
    dismissed.value.add(id);
    dismissed.value = new Set(dismissed.value);  // força reatividade
}

const SEV_STYLE = {
    critico: {
        bar: 'bg-rose-50 border-l-4 border-rose-500 text-rose-900',
        dot: 'bg-rose-500',
        cta: 'bg-rose-600 hover:bg-rose-700 text-white',
        icon: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    },
    atencao: {
        bar: 'bg-amber-50 border-l-4 border-amber-500 text-amber-900',
        dot: 'bg-amber-500',
        cta: 'bg-amber-600 hover:bg-amber-700 text-white',
        icon: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    },
    info: {
        bar: 'bg-sky-50 border-l-4 border-sky-500 text-sky-900',
        dot: 'bg-sky-500',
        cta: 'bg-sky-600 hover:bg-sky-700 text-white',
        icon: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z',
    },
};

function styleOf(sev) {
    return SEV_STYLE[sev] ?? SEV_STYLE.info;
}
</script>

<template>
    <div v-if="alerts.length > 0"
         class="space-y-1.5 mb-4"
         role="region"
         aria-label="Alertas do sistema">
        <div v-for="a in alerts" :key="a.id"
             :class="[
                'rounded-lg shadow-sm overflow-hidden',
                styleOf(a.severidade).bar,
             ]"
        >
            <div class="px-3 sm:px-4 py-2.5 sm:py-3 flex items-center gap-3">
                <!-- Ícone severidade -->
                <svg class="h-5 w-5 sm:h-6 sm:w-6 flex-shrink-0" fill="none" stroke="currentColor"
                     stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="styleOf(a.severidade).icon"/>
                </svg>

                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-sm sm:text-base leading-tight">{{ a.titulo }}</div>
                    <p class="text-xs sm:text-sm opacity-80 mt-0.5 hidden sm:block">{{ a.descricao }}</p>
                </div>

                <Link
                    :href="a.href"
                    :class="['inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-xs sm:text-sm font-semibold whitespace-nowrap shadow-sm',
                             styleOf(a.severidade).cta]"
                >
                    {{ a.cta }}
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </Link>

                <button type="button" @click="dismiss(a.id)"
                        :aria-label="`Dispensar alerta: ${a.titulo}`"
                        class="p-1 rounded hover:bg-black/10 flex-shrink-0">
                    <svg class="h-4 w-4 opacity-60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
