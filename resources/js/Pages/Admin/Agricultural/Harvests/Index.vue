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

const props = defineProps({ harvests: Object, filters: Object, plantings: Array });
useAutoReload(['harvests'], 20000);

const filtros = reactive({ ...props.filters });
const showForm = ref(false);
const confirmDelete = ref(null);

const form = useForm({
    planting_id: '', data_colheita: new Date().toISOString().slice(0, 10),
    quantidade_colhida: '', unidade: 'kg', valor_total: '', observacoes: '',
});

function salvar() {
    form.post(route('admin.agricola.colheitas.store'), {
        preserveScroll: true, only: ['harvests'],
        onSuccess: () => { showForm.value = false; form.reset(); form.unidade = 'kg'; form.data_colheita = new Date().toISOString().slice(0, 10); },
    });
}
function filtrar() { router.get(route('admin.agricola.colheitas.index'), filtros, { preserveState: true, replace: true }); }
function doDelete() {
    router.delete(route('admin.agricola.colheitas.destroy', confirmDelete.value.id), {
        preserveScroll: true, only: ['harvests'],
        onSuccess: () => confirmDelete.value = null,
    });
}
</script>

<template>
    <Head title="Colheitas" />
    <AdminLayout>
        <template #page-title>Agrícola</template>
        <PageHeader title="Colheitas" subtitle="Registro de colheita com produtividade automática por hectare">
            <template #actions>
                <Link :href="route('admin.agricola.index')" class="btn-outline">Voltar</Link>
                <button @click="showForm = !showForm" class="btn-primary">{{ showForm ? 'Fechar' : '+ Nova colheita' }}</button>
            </template>
        </PageHeader>

        <div v-if="showForm" class="card mb-6">
            <div class="card-header"><h2 class="card-title">Registrar colheita</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <InputLabel value="Plantio" />
                    <select v-model="form.planting_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <option v-for="p in plantings" :key="p.id" :value="p.id">
                            {{ p.field?.nome }} — {{ p.crop?.nome }} ({{ dataBR(p.data_plantio) }})
                        </option>
                    </select>
                </div>
                <div><InputLabel value="Data da colheita" /><InputDate v-model="form.data_colheita" /></div>
                <div><InputLabel value="Quantidade colhida" /><input type="number" step="0.0001" v-model="form.quantidade_colhida" required class="form-input"></div>
                <div>
                    <InputLabel value="Unidade" />
                    <select v-model="form.unidade" class="form-select">
                        <option value="kg">kg</option><option value="ton">ton</option><option value="sc">sc</option>
                    </select>
                </div>
                <div><InputLabel value="Valor total" /><InputMoney v-model="form.valor_total" /></div>
                <div class="sm:col-span-3"><InputLabel value="Observações" /><textarea v-model="form.observacoes" rows="2" class="form-textarea"></textarea></div>
                <div class="sm:col-span-3 flex justify-end gap-2">
                    <button @click="showForm = false" class="btn-outline">Cancelar</button>
                    <button @click="salvar" :disabled="form.processing" class="btn-primary">Registrar</button>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-2">
                <input type="date" v-model="filtros.from" @change="filtrar" class="form-input">
                <input type="date" v-model="filtros.to" @change="filtrar" class="form-input">
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'data_colheita', label: 'Data', format: dataBR },
                { key: 'campo', label: 'Talhão / Cultura' },
                { key: 'quantidade_colhida', label: 'Quantidade', align: 'right' },
                { key: 'produtividade_por_ha', label: 'Prod./ha', align: 'right' },
                { key: 'valor_total', label: 'Valor total', align: 'right', format: brl },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="harvests.data"
        >
            <template #cell-campo="{ row }">
                {{ row.planting?.field?.nome }} — {{ row.planting?.crop?.nome }}
            </template>
            <template #cell-quantidade_colhida="{ row }">
                {{ Number(row.quantidade_colhida).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }} {{ row.unidade }}
            </template>
            <template #cell-produtividade_por_ha="{ row }">
                {{ Number(row.produtividade_por_ha).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) }} {{ row.unidade }}/ha
            </template>
            <template #cell-acoes="{ row }">
                <button @click="confirmDelete = row" class="text-red-600 hover:underline">Excluir</button>
            </template>
        </DataTable>

        <ConfirmModal :show="!!confirmDelete" title="Excluir colheita"
                      message="Excluir esta colheita?"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
