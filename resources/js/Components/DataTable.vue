<script setup>
import { useSlots } from 'vue';

const props = defineProps({
    columns: { type: Array, required: true }, // [{ key, label, align?, format?: fn, hideOnMobile?: bool }]
    rows: { type: Array, required: true },
    emptyText: { type: String, default: 'Nenhum registro encontrado.' },
    rowClickable: { type: Boolean, default: false },
    mobileCards: { type: Boolean, default: true },
});

defineEmits(['row-click']);

const slots = useSlots();

function slotName(key) { return `cell-${key}`; }
function renderCell(col, row) {
    if (col.format) return col.format(row[col.key], row);
    return row[col.key] ?? '—';
}
</script>

<template>
    <div class="card overflow-hidden">
        <!-- =============== DESKTOP: TABELA =============== -->
        <div class="overflow-x-auto hidden sm:block">
            <table class="table-base">
                <thead>
                    <tr>
                        <th v-for="col in columns" :key="col.key"
                            :class="[col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : '']">
                            {{ col.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="!rows.length">
                        <td :colspan="columns.length" class="text-center text-slate-500 py-10">{{ emptyText }}</td>
                    </tr>
                    <tr v-for="(row, i) in rows" :key="row.id ?? i"
                        :class="[rowClickable ? 'cursor-pointer' : '']"
                        @click="rowClickable && $emit('row-click', row)">
                        <td v-for="col in columns" :key="col.key"
                            :class="[col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : '']">
                            <slot :name="slotName(col.key)" :row="row" :value="row[col.key]">
                                {{ renderCell(col, row) }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- =============== MOBILE: CARDS =============== -->
        <div v-if="mobileCards" class="sm:hidden divide-y divide-slate-100">
            <div v-if="!rows.length" class="text-center text-slate-500 py-10">{{ emptyText }}</div>
            <div v-for="(row, i) in rows" :key="row.id ?? i"
                 :class="['p-4 space-y-2', rowClickable ? 'cursor-pointer active:bg-slate-50' : '']"
                 @click="rowClickable && $emit('row-click', row)">
                <div v-for="col in columns.filter(c => !c.hideOnMobile && c.key !== 'acoes')" :key="col.key"
                     class="flex items-start justify-between gap-3 text-sm">
                    <span class="text-xs uppercase tracking-wide text-slate-500 flex-shrink-0">{{ col.label }}</span>
                    <span class="text-slate-800 text-right break-words min-w-0">
                        <slot :name="slotName(col.key)" :row="row" :value="row[col.key]">
                            {{ renderCell(col, row) }}
                        </slot>
                    </span>
                </div>
                <div v-if="columns.find(c => c.key === 'acoes')"
                     class="flex flex-wrap items-center justify-end gap-3 pt-2 border-t border-slate-100">
                    <slot name="cell-acoes" :row="row" :value="row['acoes']" />
                </div>
            </div>
        </div>

        <!-- Fallback: scroll horizontal no mobile se mobileCards = false -->
        <div v-else class="overflow-x-auto sm:hidden">
            <table class="table-base">
                <thead>
                    <tr>
                        <th v-for="col in columns" :key="col.key"
                            :class="[col.align === 'right' ? 'text-right' : '']">{{ col.label }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="!rows.length">
                        <td :colspan="columns.length" class="text-center text-slate-500 py-10">{{ emptyText }}</td>
                    </tr>
                    <tr v-for="(row, i) in rows" :key="row.id ?? i">
                        <td v-for="col in columns" :key="col.key"
                            :class="[col.align === 'right' ? 'text-right' : '']">
                            <slot :name="slotName(col.key)" :row="row" :value="row[col.key]">
                                {{ renderCell(col, row) }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
