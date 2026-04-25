<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import { useConfirm } from '@/composables/useConfirm.js';

const { confirm } = useConfirm();

const props = defineProps({
    locations: Object,
    filters: Object,
    tipos: Object,
});

const filtros = reactive({ ...props.filters });

function filtrar() {
    router.get(route('admin.rebanho.locais.index'), filtros, { preserveState: true, replace: true });
}

function toggle(loc) {
    router.post(route('admin.rebanho.locais.toggle', loc.id), {}, { preserveScroll: true });
}

async function excluir(loc) {
    const ok = await confirm({
        title: 'Excluir local',
        message: `Excluir o local "${loc.nome}"? Animais alocados não serão excluídos, mas perdem o vínculo com este local físico.`,
        confirmText: 'Excluir local',
        variant: 'danger',
    });
    if (! ok) return;
    router.delete(route('admin.rebanho.locais.destroy', loc.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Locais (pastos e piquetes)" />
    <AdminLayout>
        <template #page-title>Rebanho · Locais</template>

        <PageHeader
            title="Locais (pastos, piquetes, currais…)"
            subtitle="Onde os animais ficam. Um local é um lugar físico — não confunda com lote."
        >
            <template #actions>
                <Link :href="route('admin.rebanho.lotes.index')" class="btn-outline">Ver lotes</Link>
                <Link :href="route('admin.rebanho.locais.create')" class="btn-primary">+ Novo local</Link>
            </template>
        </PageHeader>

        <!-- Explicação pedagógica pra reforçar a separação -->
        <div class="card mb-6 bg-slate-50 border border-slate-200">
            <div class="card-body text-sm text-slate-700 space-y-1">
                <div>📍 <strong>Local</strong> = lugar físico: pasto, piquete, curral, tanque…</div>
                <div>🐄 <strong>Lote</strong> = grupo lógico: "vacas leiteiras", "engorda 2026"…</div>
                <div class="text-slate-500 text-xs mt-1">
                    Um mesmo lote pode mudar de local (rotação de pasto) sem perder identidade.
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
                { key: 'tipo', label: 'Tipo' },
                { key: 'codigo', label: 'Código' },
                { key: 'area_ha', label: 'Área (ha)', align: 'right' },
                { key: 'animais_count', label: 'Animais', align: 'right' },
                { key: 'status', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="locations.data"
            empty-text="Nenhum local cadastrado ainda. Cadastre o primeiro pasto ou piquete."
        >
            <template #cell-tipo="{ row }">
                <span class="badge-slate">{{ tipos[row.tipo] ?? row.tipo }}</span>
            </template>
            <template #cell-area_ha="{ row }">
                <span v-if="row.area_ha">{{ Number(row.area_ha).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }}</span>
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
                    <Link :href="route('admin.rebanho.locais.edit', row.id)" class="inline-flex">
                        <ActionIcon type="edit" title="Editar" />
                    </Link>
                    <ActionIcon type="delete" title="Excluir" @click="excluir(row)" />
                </div>
            </template>
        </DataTable>
    </AdminLayout>
</template>
