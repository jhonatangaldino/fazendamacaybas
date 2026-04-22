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
    <div class="card overflow-hidden">
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

        <!-- =============== MOBILE + TABLET (< 1024px) =============== -->
        <div class="lg:hidden divide-y divide-slate-100">
            <div v-if="!rows.length" class="text-center text-slate-500 py-10">{{ emptyText }}</div>
            <div v-for="(row, i) in rows" :key="row.id ?? i"
                 :class="['p-4', rowClickable ? 'cursor-pointer active:bg-slate-50' : '']"
                 @click="rowClickable && $emit('row-click', row)">

                <!-- Primary no topo -->
                <div v-if="primaryCol" class="mb-3">
                    <slot :name="slotName(primaryCol.key)" :row="row" :value="row[primaryCol.key]">
                        <div class="text-base font-semibold text-slate-900 break-words">
                            {{ renderCell(primaryCol, row) }}
                        </div>
                    </slot>
                </div>

                <!-- Secundárias em pares label:valor -->
                <div class="space-y-1.5">
                    <div v-for="col in secondaryCols" :key="col.key"
                         class="flex items-start gap-3 text-sm">
                        <span class="text-[11px] uppercase tracking-wide text-slate-500 flex-shrink-0 w-24 pt-0.5">{{ col.label }}</span>
                        <span class="text-slate-800 break-words min-w-0 flex-1">
                            <slot :name="slotName(col.key)" :row="row" :value="row[col.key]">
                                {{ renderCell(col, row) }}
                            </slot>
                        </span>
                    </div>
                </div>

                <!-- Ações no rodapé -->
                <div v-if="hasActions"
                     class="flex flex-wrap items-center justify-end gap-3 mt-3 pt-3 border-t border-slate-100">
                    <slot name="cell-acoes" :row="row" :value="row['acoes']" />
                </div>
            </div>
        </div>
    </div>
</template>
