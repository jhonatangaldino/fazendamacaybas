<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import { cpfCnpjMask, telefoneMask } from '@/utils/format.js';

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
                <div class="flex items-center gap-1 justify-end">
                    <Link :href="route('admin.parceiros.edit', row.id)" class="inline-flex">
                        <ActionIcon type="edit" title="Editar parceiro" />
                    </Link>
                    <ActionIcon type="delete" title="Excluir parceiro" @click="confirmDelete = row" />
                </div>
            </template>
        </DataTable>

        <ConfirmModal :show="!!confirmDelete" title="Excluir parceiro"
                      :message="`Excluir ${confirmDelete?.nome}?`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
