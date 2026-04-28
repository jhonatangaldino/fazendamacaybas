<script setup>
/**
 * F4.2 · Componente de filtros colapsáveis mobile-first.
 *
 * Por que existe:
 *   Listagens com 3-5 filtros (select/input) ficam empilhados em
 *   mobile ocupando uma tela inteira de altura ANTES da lista real.
 *   O usuário de 70 anos vê "uma tela de formulários sem nada" e
 *   desiste. O componente esconde os filtros atrás de um botão em
 *   mobile e mantém a busca por padrão (principal caso de uso).
 *
 * Comportamento:
 *   - Desktop (md+): filtros sempre visíveis no grid habitual.
 *   - Mobile (< md):
 *       → slot `always` aparece sempre (ex.: busca principal)
 *       → slot default fica escondido, revelado pelo botão
 *         "🔽 Mostrar filtros" / "🔼 Esconder filtros"
 *
 * Uso:
 *   <MobileFilters>
 *     <template #always>
 *       <input v-model="filtros.search" @keyup.enter="filtrar"
 *              placeholder="Buscar..." class="form-input">
 *     </template>
 *     <select v-model="filtros.tipo" @change="filtrar" class="form-select">...</select>
 *     <select v-model="filtros.status" @change="filtrar" class="form-select">...</select>
 *   </MobileFilters>
 */
import { ref, computed, useSlots } from 'vue';

const props = defineProps({
    /**
     * Tailwind grid classes para layout em desktop.
     * BLOCO 4.4 fix: novo default escalona gradualmente sem truncar texto.
     * sm:2 (640+), md:3 (768+), lg:4 (1024+), xl:5 (1280+).
     * Use a prop somente se precisar override explícito.
     */
    cols: { type: String, default: 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4' },
});

const slots = useSlots();
const mobileOpen = ref(false);

const hasAlways = computed(() => !!slots.always);
</script>

<template>
    <!-- Visual ajustado: busca destacada (ícone embutido + bg sutil),
         filtros separados visualmente pra não parecerem "grudados". -->
    <div class="bg-white rounded-xl ring-1 ring-slate-200 mb-4 overflow-hidden">
        <!-- BLOCO BUSCA — destacado, com lupa embutida e fundo sutil pra
             diferenciar dos filtros de refinamento abaixo. -->
        <div v-if="hasAlways" class="bg-slate-50 px-4 py-3 border-b border-slate-200">
            <div class="relative filter-search-slot">
                <!-- Ícone de busca abs à esquerda; o input filho ganha pl-10 via CSS local -->
                <svg class="h-5 w-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <slot name="always" />
            </div>
        </div>

        <!-- Botão "Mostrar filtros" mobile -->
        <button
            v-if="$slots.default"
            type="button"
            @click="mobileOpen = !mobileOpen"
            class="md:hidden w-full flex items-center justify-center gap-2 text-sm font-medium text-slate-600 hover:bg-slate-50 active:bg-slate-100 py-3 border-b border-slate-200 touch-manipulation"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            <span>{{ mobileOpen ? 'Esconder filtros' : 'Mostrar filtros' }}</span>
            <svg class="h-4 w-4 transition-transform" :class="mobileOpen ? 'rotate-180' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <!-- BLOCO FILTROS — refinamentos secundários. Padding interno respira
             sem precisar grudar com o input principal. -->
        <div
            v-if="$slots.default"
            class="grid gap-3 px-4 py-3"
            :class="[
                cols,
                mobileOpen ? '' : 'hidden md:grid',
            ]"
        >
            <slot />
        </div>
    </div>
</template>

<style scoped>
/* Busca: input filho recebe pl-10 (espaço pra ícone) automaticamente sem
   precisar mudar o consumidor. Também aumenta altura mobile pra 16px (anti-zoom). */
.filter-search-slot :deep(input) {
    padding-left: 2.5rem !important;
    background: white;
    border-color: #e2e8f0;
}
.filter-search-slot :deep(input:focus) {
    border-color: #15803d;
    box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.1);
}
</style>
