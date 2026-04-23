<script setup>
import { computed, useSlots } from 'vue';

const props = defineProps({
    /**
     * Colunas da tabela. Opções por coluna:
     *   - key          (obrigatório)
     *   - label
     *   - align         'left' | 'right' | 'center'
     *   - format(val, row)  formatador para texto simples
     *   - primary       marca coluna como título do card em mobile (default: 1ª coluna não-ação)
     *   - hideOnMobile  oculta a coluna em tela < lg (tablet e mobile)
     *   - hideOnDesktop oculta na tabela (só aparece em card mobile)
     */
    columns: { type: Array, required: true },
    rows: { type: Array, required: true },
    emptyText: { type: String, default: 'Nenhum registro encontrado.' },
    rowClickable: { type: Boolean, default: false },
});

defineEmits(['row-click']);

const slots = useSlots();

const primaryCol = computed(() =>
    props.columns.find(c => c.primary) ?? props.columns.find(c => c.key !== 'acoes')
);

const secondaryCols = computed(() =>
    props.columns.filter(c => c.key !== primaryCol.value?.key && c.key !== 'acoes' && !c.hideOnMobile)
);

const hasActions = computed(() => props.columns.some(c => c.key === 'acoes'));
const desktopColumns = computed(() => props.columns.filter(c => !c.hideOnDesktop));

function slotName(key) { return `cell-${key}`; }
function renderCell(col, row) {
    if (col.format) return col.format(row[col.key], row);
    const v = row[col.key];
    return v === null || v === undefined || v === '' ? '—' : v;
}
</script>

<template>
    <div class="lg:card lg:overflow-hidden">
        <!-- =============== DESKTOP (≥ 1024px) =============== -->
        <div class="hidden lg:block overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr>
                        <th v-for="col in desktopColumns" :key="col.key"
                            :class="[col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : '']">
                            {{ col.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="!rows.length">
                        <td :colspan="desktopColumns.length" class="text-center text-slate-500 py-10">{{ emptyText }}</td>
                    </tr>
                    <tr v-for="(row, i) in rows" :key="row.id ?? i"
                        :class="[rowClickable ? 'cursor-pointer' : '']"
                        @click="rowClickable && $emit('row-click', row)">
                        <td v-for="col in desktopColumns" :key="col.key"
                            :class="[col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : '']">
                            <slot :name="slotName(col.key)" :row="row" :value="row[col.key]">
                                {{ renderCell(col, row) }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- =============== MOBILE + TABLET (< 1024px) — cards com respiro =============== -->
        <div class="lg:hidden p-3 space-y-3 bg-slate-50 w-full max-w-full overflow-hidden rounded-xl">
            <div v-if="!rows.length" class="text-center text-slate-500 py-10">{{ emptyText }}</div>
            <div v-for="(row, i) in rows" :key="row.id ?? i"
                 :class="['bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-4 w-full max-w-full overflow-hidden',
                          rowClickable ? 'cursor-pointer active:bg-slate-50 hover:shadow-md transition-shadow' : '']"
                 @click="rowClickable && $emit('row-click', row)">

                <!-- Primary no topo -->
                <div v-if="primaryCol" class="mb-3 pb-3 border-b border-slate-100">
                    <slot :name="slotName(primaryCol.key)" :row="row" :value="row[primaryCol.key]">
                        <div class="text-base font-semibold text-slate-900 break-words">
                            {{ renderCell(primaryCol, row) }}
                        </div>
                    </slot>
                </div>

                <!-- Secundárias em pares label:valor — F4.2: layout vertical em
                     telas ≤360px (label em cima, valor abaixo) para não espremer
                     valores longos. Horizontal a partir de sm:. -->
                <div class="space-y-2.5">
                    <div v-for="col in secondaryCols" :key="col.key"
                         class="flex flex-col sm:flex-row sm:items-start sm:gap-3 text-sm w-full max-w-full">
                        <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-400 flex-shrink-0 sm:w-24 sm:pt-0.5">{{ col.label }}</span>
                        <span class="text-slate-800 break-words min-w-0 flex-1 leading-relaxed overflow-hidden">
                            <slot :name="slotName(col.key)" :row="row" :value="row[col.key]">
                                {{ renderCell(col, row) }}
                            </slot>
                        </span>
                    </div>
                </div>

                <!-- Ações no rodapé -->
                <div v-if="hasActions"
                     class="flex flex-wrap items-center justify-end gap-4 mt-4 pt-3 border-t border-slate-100">
                    <slot name="cell-acoes" :row="row" :value="row['acoes']" />
                </div>
            </div>
        </div>

        <!-- Wrapper para o card não herdar o bg cinza mobile quando o .card externo cuidou disso -->
    </div>
</template>
