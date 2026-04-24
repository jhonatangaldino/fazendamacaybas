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
import ActionIcon from '@/Components/ActionIcon.vue';
import { brl, dataBR, hojeBR } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ applications: Object, filters: Object, fields: Array, plantings: Array });
useAutoReload(['applications'], 20000);

const filtros = reactive({ ...props.filters });
const showForm = ref(false);
const confirmDelete = ref(null);

const form = useForm({
    field_id: '', planting_id: null, data_aplicacao: hojeBR(),
    tipo: 'adubacao', produto: '', quantidade: '', unidade: 'kg',
    valor_total: '', responsavel: '', observacoes: '',
});

function salvar() {
    form.post(route('admin.agricola.aplicacoes.store'), {
        preserveScroll: true, only: ['applications'],
        onSuccess: () => { showForm.value = false; form.reset(); form.tipo = 'adubacao'; form.unidade = 'kg'; form.data_aplicacao = hojeBR(); },
    });
}
function filtrar() { router.get(route('admin.agricola.aplicacoes.index'), filtros, { preserveState: true, replace: true }); }
function doDelete() {
    router.delete(route('admin.agricola.aplicacoes.destroy', confirmDelete.value.id), {
        preserveScroll: true, only: ['applications'],
        onSuccess: () => confirmDelete.value = null,
    });
}

const tipoLabel = {
    adubacao: 'Adubação', herbicida: 'Herbicida', fungicida: 'Fungicida',
    inseticida: 'Inseticida', calagem: 'Calagem', irrigacao: 'Irrigação', outros: 'Outros',
};
</script>

<template>
    <Head title="Aplicações" />
    <AdminLayout>
        <template #page-title>Agrícola</template>
        <PageHeader title="Aplicações de insumos" subtitle="Adubação, herbicida, fungicida, calagem, irrigação">
            <template #actions>
                <Link :href="route('admin.agricola.index')" class="btn-outline">Voltar</Link>
                <button @click="showForm = !showForm" class="btn-primary">{{ showForm ? 'Fechar' : '+ Nova aplicação' }}</button>
            </template>
        </PageHeader>

        <div v-if="showForm" class="card mb-6">
            <div class="card-header"><h2 class="card-title">Registrar aplicação</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel value="Talhão" />
                    <select v-model="form.field_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <option v-for="f in fields" :key="f.id" :value="f.id">{{ f.nome }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Plantio (opcional)" />
                    <select v-model="form.planting_id" class="form-select">
                        <option :value="null">—</option>
                        <option v-for="p in plantings" :key="p.id" :value="p.id">{{ p.field?.nome }} · {{ p.crop?.nome }}</option>
                    </select>
                </div>
                <div><InputLabel value="Data" /><InputDate v-model="form.data_aplicacao" /></div>
                <div>
                    <InputLabel value="Tipo" />
                    <select v-model="form.tipo" class="form-select">
                        <option v-for="(label, val) in tipoLabel" :key="val" :value="val">{{ label }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2"><InputLabel value="Produto" /><input v-model="form.produto" required class="form-input"></div>
                <div><InputLabel value="Quantidade" /><input type="number" step="0.0001" v-model="form.quantidade" required class="form-input"></div>
                <div>
                    <InputLabel value="Unidade" />
                    <select v-model="form.unidade" class="form-select">
                        <option value="kg">kg</option><option value="g">g</option><option value="l">l</option><option value="ml">ml</option><option value="t">ton</option>
                    </select>
                </div>
                <div><InputLabel value="Valor total" /><InputMoney v-model="form.valor_total" /></div>
                <div class="sm:col-span-2"><InputLabel value="Responsável" /><input v-model="form.responsavel" class="form-input"></div>
                <div class="sm:col-span-3"><InputLabel value="Observações" /><textarea v-model="form.observacoes" rows="2" class="form-textarea"></textarea></div>
                <div class="sm:col-span-3 flex justify-end gap-2">
                    <button @click="showForm = false" class="btn-outline">Cancelar</button>
                    <button @click="salvar" :disabled="form.processing" class="btn-primary">Salvar</button>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-2">
                <select v-model="filtros.tipo" @change="filtrar" class="form-select">
                    <option value="">Todos os tipos</option>
                    <option v-for="(label, val) in tipoLabel" :key="val" :value="val">{{ label }}</option>
                </select>
                <select v-model="filtros.field_id" @change="filtrar" class="form-select">
                    <option value="">Todos os talhões</option>
                    <option v-for="f in fields" :key="f.id" :value="f.id">{{ f.nome }}</option>
                </select>
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'data_aplicacao', label: 'Data', format: dataBR },
                { key: 'tipo', label: 'Tipo' },
                { key: 'produto', label: 'Produto' },
                { key: 'field', label: 'Talhão' },
                { key: 'quantidade', label: 'Qtd.', align: 'right' },
                { key: 'valor_total', label: 'Valor', align: 'right', format: brl },
                { key: 'responsavel', label: 'Responsável' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="applications.data"
        >
            <template #cell-tipo="{ row }"><span class="badge-blue">{{ tipoLabel[row.tipo] ?? row.tipo }}</span></template>
            <template #cell-field="{ row }">{{ row.field?.nome ?? '—' }}</template>
            <template #cell-quantidade="{ row }">{{ Number(row.quantidade).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }} {{ row.unidade }}</template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-1 justify-end">
                    <ActionIcon type="delete" title="Excluir aplicação" @click="confirmDelete = row" />
                </div>
            </template>
        </DataTable>

        <ConfirmModal :show="!!confirmDelete" title="Excluir aplicação"
                      message="Excluir esta aplicação?"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
