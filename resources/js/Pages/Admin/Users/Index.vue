<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { dataHoraBR } from '@/utils/format.js';

const props = defineProps({
    users: Object,
    filters: Object,
    roles: Array,
});

const filtros = reactive({ ...props.filters });
const confirmDelete = ref(null);

function filtrar() {
    router.get(route('admin.users.index'), filtros, { preserveState: true, replace: true });
}

function askDelete(user) { confirmDelete.value = user; }
function doDelete() {
    router.delete(route('admin.users.destroy', confirmDelete.value.id), {
        onSuccess: () => (confirmDelete.value = null),
    });
}
function resetPassword(id) {
    if (confirm('Resetar senha deste usuário?')) {
        router.post(route('admin.users.reset-password', id));
    }
}
</script>

<template>
    <Head title="Usuários" />
    <AdminLayout>
        <template #page-title>Usuários</template>

        <PageHeader title="Usuários" subtitle="Gerencie o acesso e os perfis dos usuários do sistema">
            <template #actions>
                <Link :href="route('admin.users.create')" class="btn-primary">Novo usuário</Link>
            </template>
        </PageHeader>

        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-3">
                <input v-model="filtros.search" @keyup.enter="filtrar" placeholder="Buscar por nome ou e-mail" class="form-input">
                <select v-model="filtros.role" @change="filtrar" class="form-select">
                    <option value="">Todos os perfis</option>
                    <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.description || r.name }}</option>
                </select>
                <select v-model="filtros.status" @change="filtrar" class="form-select">
                    <option value="">Todos</option>
                    <option value="ativos">Ativos</option>
                    <option value="inativos">Inativos</option>
                </select>
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'name', label: 'Nome' },
                { key: 'email', label: 'E-mail' },
                { key: 'cargo', label: 'Cargo' },
                { key: 'roles', label: 'Perfis' },
                { key: 'last_login_at', label: 'Último acesso' },
                { key: 'is_active', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="users.data"
        >
            <template #cell-roles="{ row }">
                <div class="flex flex-wrap gap-1">
                    <span v-for="r in row.roles" :key="r.name" class="badge-blue">{{ r.description || r.name }}</span>
                </div>
            </template>
            <template #cell-last_login_at="{ row }">
                {{ dataHoraBR(row.last_login_at) }}
            </template>
            <template #cell-is_active="{ row }">
                <span :class="row.is_active ? 'badge-green' : 'badge-slate'">
                    {{ row.is_active ? 'Ativo' : 'Inativo' }}
                </span>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex items-center gap-2 justify-end">
                    <button @click="resetPassword(row.id)" class="text-slate-500 hover:text-amber-600" title="Resetar senha">🔑</button>
                    <Link :href="route('admin.users.edit', row.id)" class="text-slate-500 hover:text-macaybas-primary">Editar</Link>
                    <button @click="askDelete(row)" class="text-red-600 hover:underline">Excluir</button>
                </div>
            </template>
        </DataTable>

        <div v-if="users.links" class="mt-4 flex gap-2 justify-end">
            <Link v-for="link in users.links" :key="link.label"
                  :href="link.url ?? '#'"
                  v-html="link.label"
                  :class="['btn-outline btn-sm', link.active ? '!bg-macaybas-primary !text-white !border-transparent' : '', !link.url ? 'opacity-40 pointer-events-none' : '']" />
        </div>

        <ConfirmModal
            :show="!!confirmDelete"
            title="Excluir usuário"
            :message="`Tem certeza que deseja excluir ${confirmDelete?.name}? Esta ação não pode ser desfeita.`"
            confirm-text="Excluir"
            @cancel="confirmDelete = null"
            @confirm="doDelete"
        />
    </AdminLayout>
</template>
