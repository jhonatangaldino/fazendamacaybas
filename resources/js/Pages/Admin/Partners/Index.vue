<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { cpfCnpjMask, telefoneMask } from '@/utils/format.js';

function cpfCnpjMask(v) {
    if (!v) return '—';
    const d = String(v).replace(/\D/g, '');
    if (d.length === 14) return `${d.slice(0,2)}.${d.slice(2,5)}.${d.slice(5,8)}/${d.slice(8,12)}-${d.slice(12,14)}`;
    if (d.length === 11) return `${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6,9)}-${d.slice(9,11)}`;
    return v;
}

const props = defineProps({ partners: Object, filters: Object });
const filtros = reactive({ ...props.filters });
const confirmDelete = ref(null);

function filtrar() {
    router.get(route('admin.parceiros.index'), filtros, { preserveState: true, replace: true });
}

function doDelete() {
    router.delete(route('admin.parceiros.destroy', confirmDelete.value.id), {
        onSuccess: () => (confirmDelete.value = null),
    });
}
</script>

<template>
    <Head title="Parceiros" />
    <AdminLayout>
        <template #page-title>Parceiros</template>

        <PageHeader title="Fornecedores e clientes" subtitle="Cadastro unificado de parceiros comerciais">
            <template #actions>
                <Link :href="route('admin.parceiros.create')" class="btn-primary">Novo parceiro</Link>
            </template>
        </PageHeader>

        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-3">
                <input v-model="filtros.search" @keyup.enter="filtrar" placeholder="Nome, CPF/CNPJ ou e-mail" class="form-input sm:col-span-2">
                <select v-model="filtros.tipo" @change="filtrar" class="form-select">
                    <option value="">Todos</option>
                    <option value="fornecedor">Fornecedores</option>
                    <option value="cliente">Clientes</option>
                    <option value="ambos">Ambos</option>
                </select>
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'nome', label: 'Nome' },
                { key: 'tipo', label: 'Tipo' },
                { key: 'documento', label: 'CPF/CNPJ', format: cpfCnpjMask },
                { key: 'email', label: 'E-mail' },
                { key: 'telefone', label: 'Telefone', format: telefoneMask },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="partners.data"
        >
            <template #cell-tipo="{ row }">
                <span :class="{'badge-blue': row.tipo === 'fornecedor', 'badge-green': row.tipo === 'cliente', 'badge-yellow': row.tipo === 'ambos'}">
                    {{ row.tipo }}
                </span>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex items-center gap-2 justify-end">
                    <Link :href="route('admin.parceiros.edit', row.id)" class="text-slate-500 hover:text-macaybas-primary">Editar</Link>
                    <button @click="confirmDelete = row" class="text-red-600 hover:underline">Excluir</button>
                </div>
            </template>
        </DataTable>

        <ConfirmModal :show="!!confirmDelete" title="Excluir parceiro"
                      :message="`Excluir ${confirmDelete?.nome}?`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
