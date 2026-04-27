<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ roles: Array, permissions: Array });
useAutoReload(['roles'], 20000);

const editing = ref(null); // 'new' | id
const confirmDelete = ref(null);

const form = useForm({
    name: '',
    short_name: '',
    description: '',
    permissions: [],
});

const moduleLabels = {
    dashboard: 'Painel',
    users: 'Usuários',
    roles: 'Perfis e permissões',
    cms: 'Site / Landing',
    financeiro: 'Financeiro',
    rebanho: 'Rebanho',
    agricola: 'Agrícola',
    estoque: 'Estoque',
    maquinas: 'Máquinas',
    funcionarios: 'Funcionários',
    documentos: 'Documentos',
    relatorios: 'Relatórios',
    parceiros: 'Parceiros',
    fazendas: 'Fazendas',
    geral: 'Geral',
};

function novo() {
    form.reset();
    editing.value = 'new';
}

function editar(role) {
    form.name = role.name;
    form.short_name = role.short_name || '';
    form.description = role.description;
    form.permissions = [...role.permissions];
    editing.value = role.id;
}

function salvar() {
    const opts = { preserveScroll: true, only: ['roles'], onSuccess: () => (editing.value = null) };
    if (editing.value === 'new') form.post(route('admin.roles.store'), opts);
    else form.put(route('admin.roles.update', editing.value), opts);
}

function doDelete() {
    router.delete(route('admin.roles.destroy', confirmDelete.value.id), {
        preserveScroll: true,
        only: ['roles'],
        onSuccess: () => (confirmDelete.value = null),
    });
}

function toggleAll(mod, checked) {
    const names = mod.items.map((p) => p.name);
    if (checked) {
        form.permissions = [...new Set([...form.permissions, ...names])];
    } else {
        form.permissions = form.permissions.filter((n) => !names.includes(n));
    }
}

function allChecked(mod) {
    return mod.items.every((p) => form.permissions.includes(p.name));
}
function someChecked(mod) {
    return mod.items.some((p) => form.permissions.includes(p.name)) && !allChecked(mod);
}

const isAdminMaster = computed(() => form.name === 'admin_master');
const isSystemEditing = computed(() => {
    if (editing.value === 'new') return false;
    return props.roles.find((r) => r.id === editing.value)?.is_system;
});
</script>

<template>
    <Head title="Perfis e permissões" />
    <AdminLayout>
        <template #page-title>Perfis e permissões</template>

        <PageHeader title="Perfis de acesso" subtitle="Defina o que cada tipo de usuário pode fazer no sistema">
            <template #actions>
                <button @click="novo" class="btn-primary">+ Novo perfil</button>
            </template>
        </PageHeader>

        <!-- Editor -->
        <div v-if="editing !== null" class="card mb-6">
            <div class="card-header">
                <h2 class="card-title">{{ editing === 'new' ? 'Novo perfil' : `Editando perfil: ${form.name}` }}</h2>
                <span v-if="isSystemEditing" class="badge-blue">Perfil de sistema</span>
            </div>
            <div class="card-body space-y-4">
                <div class="grid gap-4 sm:grid-cols-4">
                    <div>
                        <InputLabel value="Nome técnico" />
                        <input v-model="form.name"
                               :disabled="isSystemEditing"
                               required class="form-input"
                               placeholder="ex: gerente_financeiro">
                        <InputError :message="form.errors.name" />
                        <p class="form-help">Usado internamente. Só letras minúsculas, números e _.</p>
                    </div>
                    <div>
                        <InputLabel value="Nome curto" />
                        <input v-model="form.short_name" required class="form-input" placeholder="ex: Gerente">
                        <InputError :message="form.errors.short_name" />
                        <p class="form-help">Aparece nas tabelas/badges.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Descrição" />
                        <input v-model="form.description" required class="form-input"
                               placeholder="ex: Gerente com acesso financeiro completo">
                        <InputError :message="form.errors.description" />
                        <p class="form-help">Aparece em tooltip sobre o badge.</p>
                    </div>
                </div>

                <div v-if="isAdminMaster" class="rounded-lg bg-macaybas-primary-50 border border-macaybas-primary-200 p-3 text-sm text-macaybas-primary-900">
                    <strong>ℹ️ </strong>O perfil <code>admin_master</code> sempre tem TODAS as permissões, independente dos checkboxes.
                </div>

                <div>
                    <label class="form-label">Permissões</label>
                    <p class="form-help mb-3">
                        Escolha quais ações este perfil pode executar.
                        Marque o cabeçalho do módulo para conceder/revogar TUDO daquela área de uma vez.
                    </p>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div v-for="mod in permissions" :key="mod.module"
                             class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <label class="flex items-center gap-2 font-semibold text-sm text-slate-800 mb-3 pb-2 border-b border-slate-200 cursor-pointer">
                                <input type="checkbox"
                                       :checked="allChecked(mod)"
                                       :indeterminate.prop="someChecked(mod)"
                                       @change="toggleAll(mod, $event.target.checked)"
                                       :disabled="isAdminMaster"
                                       class="rounded">
                                {{ mod.module_label || moduleLabels[mod.module] || mod.module }}
                            </label>
                            <div class="space-y-1.5">
                                <label v-for="p in mod.items" :key="p.id"
                                       class="flex items-start gap-2 text-sm text-slate-700 cursor-pointer hover:bg-white hover:text-slate-900 rounded px-1 py-0.5"
                                       :title="p.description || p.name">
                                    <input type="checkbox" :value="p.name"
                                           v-model="form.permissions"
                                           :disabled="isAdminMaster"
                                           class="mt-1 rounded flex-shrink-0">
                                    <span class="flex-1 min-w-0">
                                        <span class="block">{{ p.label }}</span>
                                        <span v-if="p.description" class="block text-xs text-slate-500 mt-0.5">{{ p.description }}</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row justify-end gap-2 pt-4 border-t border-slate-100">
                    <button @click="editing = null" class="btn-outline">Cancelar</button>
                    <button @click="salvar" :disabled="form.processing" class="btn-primary">Salvar</button>
                </div>
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'short_name', label: 'Perfil', primary: true },
                { key: 'description', label: 'Descrição' },
                { key: 'name', label: 'Nome técnico' },
                { key: 'users_count', label: 'Usuários', align: 'right' },
                { key: 'permissions_count', label: 'Permissões', align: 'right' },
                { key: 'is_system', label: 'Tipo' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="roles"
        >
            <template #cell-short_name="{ row }">
                <div class="font-semibold text-slate-900">{{ row.short_name || row.name }}</div>
            </template>
            <template #cell-description="{ row }">
                <span class="text-sm text-slate-600">{{ row.description }}</span>
            </template>
            <template #cell-name="{ row }">
                <code class="text-xs text-slate-500">{{ row.name }}</code>
            </template>
            <template #cell-is_system="{ row }">
                <span :class="row.is_system ? 'badge-blue' : 'badge-slate'">
                    {{ row.is_system ? 'Sistema' : 'Customizado' }}
                </span>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-1 justify-end">
                    <ActionIcon type="edit" title="Editar perfil e permissões" @click="editar(row)" />
                    <ActionIcon v-if="!row.is_system"
                                type="delete"
                                title="Excluir perfil"
                                @click="confirmDelete = row" />
                </div>
            </template>
        </DataTable>

        <ConfirmModal :show="!!confirmDelete"
                      title="Excluir perfil"
                      :message="`Excluir ${confirmDelete?.description}? Só é possível se não houver usuários vinculados.`"
                      @cancel="confirmDelete = null"
                      @confirm="doDelete" />
    </AdminLayout>
</template>
