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
    /** Tailwind grid classes para o layout em desktop. Default: 3 colunas. */
    cols: { type: String, default: 'sm:grid-cols-3' },
});

const slots = useSlots();
const mobileOpen = ref(false);

const hasAlways = computed(() => !!slots.always);
</script>

<template>
    <div class="card mb-4">
        <div class="card-body">
            <!-- Busca/filtros sempre visíveis -->
            <div v-if="hasAlways" class="mb-0" :class="mobileOpen ? 'mb-3' : ''">
                <slot name="always" />
            </div>

            <!-- Botão "Mostrar filtros" — só aparece em mobile -->
            <button
                v-if="$slots.default"
                type="button"
                @click="mobileOpen = !mobileOpen"
                class="md:hidden w-full flex items-center justify-center gap-2 text-sm font-medium text-slate-600 hover:text-macaybas-primary py-2 border-t border-slate-100 mt-2"
                :class="hasAlways ? 'mt-3 pt-3' : ''"
            >
                <svg class="h-4 w-4 transition-transform" :class="mobileOpen ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
                {{ mobileOpen ? 'Esconder filtros' : 'Mostrar filtros' }}
            </button>

            <!-- Filtros — escondidos em mobile por padrão, sempre visíveis em desktop -->
            <div
                v-if="$slots.default"
                class="grid gap-3"
                :class="[
                    cols,
                    mobileOpen ? 'mt-3' : 'hidden md:grid',
                ]"
            >
                <slot />
            </div>
        </div>
    </div>
</template>
