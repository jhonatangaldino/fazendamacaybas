<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ items: Object, filters: Object, categories: Array });
const filtros = reactive({ ...props.filters });
const confirmDelete = ref(null);

// Realtime: re-puxa a lista a cada 15s
useAutoReload(['items'], 15000);

function filtrar() {
    router.get(route('admin.estoque.itens.index'), filtros, { preserveState: true, replace: true });
}

function toggle(row) {
    router.post(route('admin.estoque.itens.toggle', row.id), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['items'],
    });
}

function doDelete() {
    router.delete(route('admin.estoque.itens.destroy', confirmDelete.value.id), {
        preserveScroll: true,
        only: ['items'],
        onSuccess: () => (confirmDelete.value = null),
    });
}

const tipoLabel = {
    insumo: 'Insumo',
    medicamento: 'Medicamento',
    racao: 'Ração',
    ferramenta: 'Ferramenta',
    peca: 'Peça',
    combustivel: 'Combustível',
    material: 'Material',
};
</script>

<template>
    <Head title="Estoque — Itens" />
    <AdminLayout>
        <template #page-title>Estoque</template>

        <PageHeader title="Itens de estoque" subtitle="Todos os itens controlados no almoxarifado">
            <template #actions>
                <Link :href="route('admin.estoque.index')" class="btn-outline">Voltar</Link>
                <Link :href="route('admin.estoque.itens.create')" class="btn-primary">Novo item</Link>
            </template>
        </PageHeader>

        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-4">
                <input v-model="filtros.search" @keyup.enter="filtrar" placeholder="Buscar por código ou nome" class="form-input sm:col-span-2">
                <select v-model="filtros.tipo" @change="filtrar" class="form-select">
                    <option value="">Todos os tipos</option>
                    <option value="insumo">Insumo</option>
                    <option value="medicamento">Medicamento</option>
                    <option value="racao">Ração</option>
                    <option value="ferramenta">Ferramenta</option>
                    <option value="peca">Peça</option>
                    <option value="combustivel">Combustível</option>
                    <option value="material">Material</option>
                </select>
                <select v-model="filtros.status" @change="filtrar" class="form-select">
                    <option value="ativos">Ativos</option>
                    <option value="inativos">Inativos</option>
                </select>
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'codigo', label: 'Código' },
                { key: 'nome', label: 'Nome' },
                { key: 'tipo', label: 'Tipo' },
                { key: 'saldo', label: 'Saldo', align: 'right' },
                { key: 'unidade', label: 'UN' },
                { key: 'estoque_minimo', label: 'Mínimo', align: 'right' },
                { key: 'is_active', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="items.data"
        >
            <template #cell-tipo="{ row }"><span class="badge-slate">{{ tipoLabel[row.tipo] ?? row.tipo }}</span></template>
            <template #cell-saldo="{ row }">
                <span :class="row.abaixo_minimo ? 'text-red-700 font-semibold' : ''">
                    {{ Number(row.saldo).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }}
                </span>
            </template>
            <template #cell-estoque_minimo="{ row }">
                {{ Number(row.estoque_minimo).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }}
            </template>
            <template #cell-is_active="{ row }">
                <button @click="toggle(row)" :class="row.is_active ? 'badge-green' : 'badge-slate'" class="cursor-pointer hover:opacity-80">
                    {{ row.is_active ? 'Ativo' : 'Inativo' }}
                </button>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-2 justify-end">
                    <Link :href="route('admin.estoque.itens.edit', row.id)" class="text-slate-500 hover:text-macaybas-primary">Editar</Link>
                    <button @click="confirmDelete = row" class="text-red-600 hover:underline">Excluir</button>
                </div>
            </template>
        </DataTable>

        <div v-if="items.links" class="mt-4 flex gap-2 flex-wrap justify-end">
            <Link v-for="link in items.links" :key="link.label"
                  :href="link.url ?? '#'"
                  v-html="link.label"
                  :class="['btn-outline btn-sm', link.active ? '!bg-macaybas-primary !text-white !border-transparent' : '', !link.url ? 'opacity-40 pointer-events-none' : '']" />
        </div>

        <ConfirmModal :show="!!confirmDelete" title="Excluir item"
                      :message="`Excluir ${confirmDelete?.nome}? Se houver movimentações, o item será apenas desativado.`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
