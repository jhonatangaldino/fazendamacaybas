<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    columns: { type: Array, required: true }, // [{ key, label, align?, format?: fn }]
    rows: { type: Array, required: true },
    emptyText: { type: String, default: 'Nenhum registro encontrado.' },
    rowClickable: { type: Boolean, default: false },
});

defineEmits(['row-click']);
</script>

<template>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr>
                        <th v-for="col in columns" :key="col.key" :class="[col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : '']">
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
                            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                                {{ col.format ? col.format(row[col.key], row) : (row[col.key] ?? '—') }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
