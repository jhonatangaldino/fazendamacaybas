<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ warehouses: Object, filters: Object, farms: Array });
useAutoReload(['warehouses'], 30000);

const confirmDelete = ref(null);
const editing = ref(null);

const form = useForm({
    farm_id: null,
    nome: '',
    localizacao: '',
    responsavel: '',
    is_active: true,
});

function novo() {
    form.reset();
    form.is_active = true;
    editing.value = 'new';
}

function editar(w) {
    form.farm_id = w.farm_id;
    form.nome = w.nome;
    form.localizacao = w.localizacao;
    form.responsavel = w.responsavel;
    form.is_active = w.is_active;
    editing.value = w.id;
}

function salvar() {
    const opts = {
        preserveScroll: true,
        only: ['warehouses'],
        onSuccess: () => (editing.value = null),
    };
    if (editing.value === 'new') form.post(route('admin.estoque.armazens.store'), opts);
    else form.put(route('admin.estoque.armazens.update', editing.value), opts);
}

function toggle(row) {
    router.post(route('admin.estoque.armazens.toggle', row.id), {}, {
        preserveScroll: true, only: ['warehouses'],
    });
}

function doDelete() {
    router.delete(route('admin.estoque.armazens.destroy', confirmDelete.value.id), {
        preserveScroll: true, only: ['warehouses'],
        onSuccess: () => (confirmDelete.value = null),
    });
}
</script>

<template>
    <Head title="Estoque — Armazéns" />
    <AdminLayout>
        <template #page-title>Estoque</template>

        <PageHeader title="Armazéns" subtitle="Locais físicos de estocagem">
            <template #actions>
                <Link :href="route('admin.estoque.index')" class="btn-outline">Voltar</Link>
                <button @click="novo" class="btn-primary">+ Novo armazém</button>
            </template>
        </PageHeader>

        <div v-if="editing" class="card mb-6">
            <div class="card-header"><h2 class="card-title">{{ editing === 'new' ? 'Novo armazém' : 'Editar armazém' }}</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel value="Nome" />
                    <input v-model="form.nome" required class="form-input">
                </div>
                <div>
                    <InputLabel value="Fazenda" />
                    <select v-model="form.farm_id" class="form-select">
                        <option :value="null">—</option>
                        <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.nome }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <InputLabel value="Localização" />
                    <input v-model="form.localizacao" class="form-input" placeholder="Ex: Galpão principal, próximo ao curral">
                </div>
                <div>
                    <InputLabel value="Responsável" />
                    <input v-model="form.responsavel" class="form-input">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_active" class="rounded">
                        Armazém ativo
                    </label>
                </div>
                <div class="sm:col-span-2 flex justify-end gap-2">
                    <button @click="editing = null" class="btn-outline">Cancelar</button>
                    <button @click="salvar" :disabled="form.processing" class="btn-primary">Salvar</button>
                </div>
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'nome', label: 'Nome' },
                { key: 'farm', label: 'Fazenda' },
                { key: 'localizacao', label: 'Localização' },
                { key: 'responsavel', label: 'Responsável' },
                { key: 'is_active', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="warehouses.data"
        >
            <template #cell-farm="{ row }">{{ row.farm?.nome ?? '—' }}</template>
            <template #cell-is_active="{ row }">
                <button @click="toggle(row)" :class="row.is_active ? 'badge-green' : 'badge-slate'" class="cursor-pointer hover:opacity-80">
                    {{ row.is_active ? 'Ativo' : 'Inativo' }}
                </button>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-2 justify-end">
                    <button @click="editar(row)" class="text-slate-500 hover:text-macaybas-primary">Editar</button>
                    <button @click="confirmDelete = row" class="text-red-600 hover:underline">Excluir</button>
                </div>
            </template>
        </DataTable>

        <ConfirmModal :show="!!confirmDelete" title="Excluir armazém"
                      :message="`Excluir ${confirmDelete?.nome}? Só é possível se não houver movimentações.`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
