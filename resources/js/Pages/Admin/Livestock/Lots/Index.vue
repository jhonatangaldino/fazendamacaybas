<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ActionIcon from '@/Components/ActionIcon.vue';

const props = defineProps({
    lots: Object,
    filters: Object,
    finalidades: Object,
});

const filtros = reactive({ ...props.filters });

function filtrar() {
    router.get(route('admin.rebanho.lotes.index'), filtros, { preserveState: true, replace: true });
}

function excluir(l) {
    if (!confirm(`Excluir o lote "${l.nome}"?`)) return;
    router.delete(route('admin.rebanho.lotes.destroy', l.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Lotes (grupos de animais)" />
    <AdminLayout>
        <template #page-title>Rebanho · Lotes</template>

        <PageHeader
            title="Lotes (grupos lógicos de animais)"
            subtitle="Um lote é um grupo com critério comum — não é um lugar físico."
        >
            <template #actions>
                <Link :href="route('admin.rebanho.locais.index')" class="btn-outline">Ver locais</Link>
                <Link :href="route('admin.rebanho.lotes.create')" class="btn-primary">+ Novo lote</Link>
            </template>
        </PageHeader>

        <div class="card mb-6 bg-slate-50 border border-slate-200">
            <div class="card-body text-sm text-slate-700 space-y-1">
                <div>🐄 <strong>Lote</strong> = grupo lógico: "vacas leiteiras", "engorda 2026/Q1", "descarte"…</div>
                <div>📍 <strong>Local</strong> = lugar físico (pasto, piquete): abra em <Link :href="route('admin.rebanho.locais.index')" class="underline">Locais</Link>.</div>
                <div class="text-slate-500 text-xs mt-1">
                    Um mesmo lote pode estar em locais diferentes. Um local pode receber animais de vários lotes.
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <input v-model="filtros.q" @keyup.enter="filtrar"
                       placeholder="Buscar por nome ou código…"
                       class="form-input">
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'nome', label: 'Nome' },
                { key: 'finalidade', label: 'Finalidade' },
                { key: 'codigo', label: 'Código' },
                { key: 'animais_count', label: 'Animais', align: 'right' },
                { key: 'status', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="lots.data"
            empty-text="Nenhum lote cadastrado ainda. Cadastre o primeiro para agrupar seus animais por finalidade."
        >
            <template #cell-finalidade="{ row }">
                <span v-if="row.finalidade" class="badge-blue">{{ finalidades[row.finalidade] ?? row.finalidade }}</span>
                <span v-else class="text-slate-400">—</span>
            </template>
            <template #cell-animais_count="{ row }">
                <span class="font-mono">{{ row.animais_count ?? 0 }}</span>
            </template>
            <template #cell-status="{ row }">
                <span :class="row.is_active ? 'badge-green' : 'badge-slate'">
                    {{ row.is_active ? 'ativo' : 'inativo' }}
                </span>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-1 justify-end">
                    <Link :href="route('admin.rebanho.lotes.edit', row.id)" class="inline-flex">
                        <ActionIcon type="edit" title="Editar" />
                    </Link>
                    <ActionIcon type="delete" title="Excluir" @click="excluir(row)" />
                </div>
            </template>
        </DataTable>
    </AdminLayout>
</template>
