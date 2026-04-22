<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputMoney from '@/Components/InputMoney.vue';
import InputDate from '@/Components/InputDate.vue';
import { brl, dataBR } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ plantings: Object, filters: Object, fields: Array, crops: Array, seasons: Array });
useAutoReload(['plantings'], 20000);

const filtros = reactive({ ...props.filters });
const editing = ref(null);
const confirmDelete = ref(null);

const form = useForm({
    field_id: '', crop_id: '', season_id: null,
    data_plantio: new Date().toISOString().slice(0, 10),
    previsao_colheita: '',
    area_plantada_ha: '',
    custo_previsto: '',
    custo_real: '',
    status: 'em_andamento',
    observacoes: '',
});

function novo() { form.reset(); form.status = 'em_andamento'; form.data_plantio = new Date().toISOString().slice(0, 10); editing.value = 'new'; }
function editar(p) { Object.keys(form.data()).forEach(k => form[k] = p[k] ?? form[k]); editing.value = p.id; }
function filtrar() { router.get(route('admin.agricola.plantios.index'), filtros, { preserveState: true, replace: true }); }

function salvar() {
    const opts = { preserveScroll: true, only: ['plantings'], onSuccess: () => editing.value = null };
    if (editing.value === 'new') form.post(route('admin.agricola.plantios.store'), opts);
    else form.put(route('admin.agricola.plantios.update', editing.value), opts);
}
function doDelete() {
    router.delete(route('admin.agricola.plantios.destroy', confirmDelete.value.id), {
        preserveScroll: true, only: ['plantings'],
        onSuccess: () => confirmDelete.value = null,
    });
}

const statusBadge = (s) => ({
    em_andamento: 'badge-blue',
    colhido: 'badge-green',
    perdido: 'badge-red',
    cancelado: 'badge-slate',
})[s] || 'badge-slate';
</script>

<template>
    <Head title="Plantios" />
    <AdminLayout>
        <template #page-title>Agrícola</template>
        <PageHeader title="Plantios" subtitle="Registro de plantios por talhão, cultura e safra">
            <template #actions>
                <Link :href="route('admin.agricola.index')" class="btn-outline">Voltar</Link>
                <button @click="novo" class="btn-primary">+ Novo plantio</button>
            </template>
        </PageHeader>

        <div v-if="editing" class="card mb-6">
            <div class="card-header"><h2 class="card-title">{{ editing === 'new' ? 'Novo plantio' : 'Editar plantio' }}</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel value="Talhão" />
                    <select v-model="form.field_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <option v-for="f in fields" :key="f.id" :value="f.id">{{ f.nome }} ({{ f.area_ha }} ha)</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Cultura" />
                    <select v-model="form.crop_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <option v-for="c in crops" :key="c.id" :value="c.id">{{ c.nome }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Safra" />
                    <select v-model="form.season_id" class="form-select">
                        <option :value="null">—</option>
                        <option v-for="s in seasons" :key="s.id" :value="s.id">{{ s.nome }}</option>
                    </select>
                </div>
                <div><InputLabel value="Data do plantio" /><InputDate v-model="form.data_plantio" /></div>
                <div><InputLabel value="Previsão de colheita" /><InputDate v-model="form.previsao_colheita" /></div>
                <div><InputLabel value="Área plantada (ha)" /><input type="number" step="0.0001" v-model="form.area_plantada_ha" required class="form-input"></div>
                <div><InputLabel value="Custo previsto" /><InputMoney v-model="form.custo_previsto" /></div>
                <div><InputLabel value="Custo real" /><InputMoney v-model="form.custo_real" /></div>
                <div>
                    <InputLabel value="Status" />
                    <select v-model="form.status" class="form-select">
                        <option value="em_andamento">Em andamento</option>
                        <option value="colhido">Colhido</option>
                        <option value="perdido">Perdido</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
                <div class="sm:col-span-3"><InputLabel value="Observações" /><textarea v-model="form.observacoes" rows="2" class="form-textarea"></textarea></div>
                <div class="sm:col-span-3 flex justify-end gap-2">
                    <button @click="editing = null" class="btn-outline">Cancelar</button>
                    <button @click="salvar" :disabled="form.processing" class="btn-primary">Salvar</button>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-3">
                <select v-model="filtros.status" @change="filtrar" class="form-select">
                    <option value="">Todos</option>
                    <option value="em_andamento">Em andamento</option>
                    <option value="colhido">Colhido</option>
                    <option value="perdido">Perdido</option>
                    <option value="cancelado">Cancelado</option>
                </select>
                <select v-model="filtros.field_id" @change="filtrar" class="form-select">
                    <option value="">Todos os talhões</option>
                    <option v-for="f in fields" :key="f.id" :value="f.id">{{ f.nome }}</option>
                </select>
                <select v-model="filtros.season_id" @change="filtrar" class="form-select">
                    <option value="">Todas as safras</option>
                    <option v-for="s in seasons" :key="s.id" :value="s.id">{{ s.nome }}</option>
                </select>
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'data_plantio', label: 'Plantio', format: dataBR },
                { key: 'field', label: 'Talhão' },
                { key: 'crop', label: 'Cultura' },
                { key: 'season', label: 'Safra' },
                { key: 'area_plantada_ha', label: 'Área (ha)', align: 'right' },
                { key: 'custo_real', label: 'Custo real', align: 'right', format: brl },
                { key: 'status', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="plantings.data"
        >
            <template #cell-field="{ row }">{{ row.field?.nome ?? '—' }}</template>
            <template #cell-crop="{ row }">{{ row.crop?.nome ?? '—' }}</template>
            <template #cell-season="{ row }">{{ row.season?.nome ?? '—' }}</template>
            <template #cell-area_plantada_ha="{ row }">{{ Number(row.area_plantada_ha).toLocaleString('pt-BR', { maximumFractionDigits: 4 }) }}</template>
            <template #cell-status="{ row }"><span :class="statusBadge(row.status)">{{ row.status.replace('_', ' ') }}</span></template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-2 justify-end">
                    <button @click="editar(row)" class="text-slate-500 hover:text-macaybas-primary">Editar</button>
                    <button @click="confirmDelete = row" class="text-red-600 hover:underline">Excluir</button>
                </div>
            </template>
        </DataTable>

        <ConfirmModal :show="!!confirmDelete" title="Excluir plantio"
                      message="Excluir este plantio?"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
