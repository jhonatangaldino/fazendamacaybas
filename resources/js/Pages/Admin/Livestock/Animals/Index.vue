<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { dataBR } from '@/utils/format.js';

const props = defineProps({ animals: Object, filters: Object, species: Array, lots: Array });
const filtros = reactive({ ...props.filters });
const confirmDelete = ref(null);

function filtrar() { router.get(route('admin.rebanho.animais.index'), filtros, { preserveState: true, replace: true }); }
function doDelete() {
    router.delete(route('admin.rebanho.animais.destroy', confirmDelete.value.id), {
        onSuccess: () => (confirmDelete.value = null),
    });
}

const statusBadge = (s) => ({
    ativo: 'badge-green', vendido: 'badge-blue', morto: 'badge-red',
    abatido: 'badge-slate', transferido: 'badge-yellow',
})[s] || 'badge-slate';
</script>

<template>
    <Head title="Rebanho — Animais" />
    <AdminLayout>
        <template #page-title>Rebanho</template>
        <PageHeader title="Animais" subtitle="Cadastro individual do rebanho">
            <template #actions>
                <Link :href="route('admin.rebanho.animais.create')" class="btn-primary">Novo animal</Link>
            </template>
        </PageHeader>

        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-4">
                <input v-model="filtros.search" @keyup.enter="filtrar" placeholder="Brinco ou nome" class="form-input">
                <select v-model="filtros.species_id" @change="filtrar" class="form-select">
                    <option value="">Todas as espécies</option>
                    <option v-for="s in species" :key="s.id" :value="s.id">{{ s.nome }}</option>
                </select>
                <select v-model="filtros.lot_id" @change="filtrar" class="form-select">
                    <option value="">Todos os lotes</option>
                    <option v-for="l in lots" :key="l.id" :value="l.id">{{ l.nome }}</option>
                </select>
                <select v-model="filtros.status" @change="filtrar" class="form-select">
                    <option value="">Todos os status</option>
                    <option value="ativo">Ativo</option>
                    <option value="vendido">Vendido</option>
                    <option value="morto">Morto</option>
                    <option value="abatido">Abatido</option>
                    <option value="transferido">Transferido</option>
                </select>
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'identificacao', label: 'Brinco' },
                { key: 'nome', label: 'Nome' },
                { key: 'species', label: 'Espécie' },
                { key: 'breed', label: 'Raça' },
                { key: 'sexo', label: 'Sexo' },
                { key: 'data_nascimento', label: 'Nascimento', format: dataBR },
                { key: 'lot', label: 'Lote' },
                { key: 'status', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="animals.data"
        >
            <template #cell-species="{ row }">{{ row.species?.nome ?? '—' }}</template>
            <template #cell-breed="{ row }">{{ row.breed?.nome ?? '—' }}</template>
            <template #cell-lot="{ row }">{{ row.lot?.nome ?? '—' }}</template>
            <template #cell-status="{ row }"><span :class="statusBadge(row.status)">{{ row.status }}</span></template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-2 justify-end">
                    <Link :href="route('admin.rebanho.animais.edit', row.id)" class="text-slate-500 hover:text-macaybas-primary">Editar</Link>
                    <button @click="confirmDelete = row" class="text-red-600 hover:underline">Excluir</button>
                </div>
            </template>
        </DataTable>

        <ConfirmModal :show="!!confirmDelete" title="Excluir animal"
                      :message="`Excluir ${confirmDelete?.identificacao}?`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
