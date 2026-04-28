<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ fields: Object, filters: Object, farms: Array });
useAutoReload(['fields'], 20000);

const filtros = reactive({ ...props.filters });
const confirmDelete = ref(null);
const editing = ref(null);

const form = useForm({
    farm_id: props.farms[0]?.id ?? '',
    codigo: '',
    nome: '',
    area_ha: '',
    tipo_solo: '',
    descricao: '',
    localizacao: '',
    latitude: null,
    longitude: null,
    is_active: true,
});

function novo() { form.reset(); form.farm_id = props.farms[0]?.id; form.is_active = true; editing.value = 'new'; }
function editar(f) { Object.keys(form.data()).forEach(k => form[k] = f[k] ?? form[k]); editing.value = f.id; }
function filtrar() { router.get(route('admin.agricola.talhoes.index'), filtros, { preserveState: true, replace: true }); }

function salvar() {
    const opts = { preserveScroll: true, only: ['fields'], onSuccess: () => editing.value = null };
    if (editing.value === 'new') form.post(route('admin.agricola.talhoes.store'), opts);
    else form.put(route('admin.agricola.talhoes.update', editing.value), opts);
}

function toggle(row) { router.post(route('admin.agricola.talhoes.toggle', row.id), {}, { preserveScroll: true, only: ['fields'] }); }
function doDelete() {
    router.delete(route('admin.agricola.talhoes.destroy', confirmDelete.value.id), {
        preserveScroll: true, only: ['fields'],
        onSuccess: () => confirmDelete.value = null,
    });
}
</script>

<template>
    <Head title="Agrícola — Talhões" />
    <AdminLayout>
        <template #page-title>Agrícola</template>

        <PageHeader title="Talhões / áreas" subtitle="Áreas produtivas da fazenda">
            <template #actions>
                <Link :href="route('admin.agricola.index')" class="btn-outline">Voltar</Link>
                <button @click="novo" class="btn-primary">+ Novo talhão</button>
            </template>
        </PageHeader>

        <div v-if="editing" class="card mb-6">
            <div class="card-header"><h2 class="card-title">{{ editing === 'new' ? 'Novo talhão' : 'Editar talhão' }}</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div><InputLabel value="Código" /><input v-model="form.codigo" required class="form-input"></div>
                <div class="sm:col-span-2"><InputLabel value="Nome" /><input v-model="form.nome" required class="form-input"></div>
                <div>
                    <InputLabel value="Fazenda" />
                    <select v-model="form.farm_id" class="form-select" required>
                        <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.nome }}</option>
                    </select>
                </div>
                <div><InputLabel value="Área (ha)" /><input type="number" step="0.0001" v-model="form.area_ha" required class="form-input"></div>
                <div><InputLabel value="Tipo de solo" /><input v-model="form.tipo_solo" class="form-input" placeholder="Ex: Latossolo"></div>
                <div class="sm:col-span-3"><InputLabel value="Localização / referência" /><input v-model="form.localizacao" class="form-input"></div>
                <div><InputLabel value="Latitude" /><input type="number" step="0.0000001" v-model="form.latitude" class="form-input"></div>
                <div><InputLabel value="Longitude" /><input type="number" step="0.0000001" v-model="form.longitude" class="form-input"></div>
                <div class="sm:col-span-3"><InputLabel value="Descrição" /><textarea v-model="form.descricao" rows="2" class="form-textarea"></textarea></div>
                <div class="sm:col-span-3 flex justify-between items-center">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_active" class="rounded"> Ativo
                    </label>
                    <div class="flex gap-2">
                        <button @click="editing = null" class="btn-outline">Cancelar</button>
                        <button @click="salvar" :disabled="form.processing" class="btn-primary">Salvar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <input v-model="filtros.search" @keyup.enter="filtrar" placeholder="Buscar código ou nome" class="form-input sm:col-span-2">
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
                { key: 'farm', label: 'Fazenda' },
                { key: 'area_ha', label: 'Área (ha)', align: 'right' },
                { key: 'tipo_solo', label: 'Solo' },
                { key: 'is_active', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="fields.data"
        >
            <template #cell-farm="{ row }">{{ row.farm?.nome ?? '—' }}</template>
            <template #cell-area_ha="{ row }">{{ Number(row.area_ha).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 4 }) }}</template>
            <template #cell-is_active="{ row }">
                <button @click="toggle(row)" :class="row.is_active ? 'badge-toggle-green' : 'badge-toggle-slate'">{{ row.is_active ? 'Ativo' : 'Inativo' }}</button>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-1 justify-end">
                    <ActionIcon type="edit" title="Editar talhão" @click="editar(row)" />
                    <ActionIcon type="delete" title="Excluir talhão" @click="confirmDelete = row" />
                </div>
            </template>
        </DataTable>

        <ConfirmModal :show="!!confirmDelete" title="Excluir talhão"
                      :message="`Excluir ${confirmDelete?.nome}? Se houver plantios, será desativado.`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
