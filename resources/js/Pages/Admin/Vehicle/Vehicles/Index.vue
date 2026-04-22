<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputMoney from '@/Components/InputMoney.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMasked from '@/Components/InputMasked.vue';
import { brl, dataBR } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ vehicles: Object, filters: Object, farms: Array });
useAutoReload(['vehicles'], 20000);

const filtros = reactive({ ...props.filters });
const editing = ref(null);
const confirmDelete = ref(null);

const form = useForm({
    farm_id: null, tipo: 'trator', nome: '', marca: '', modelo: '',
    ano_fabricacao: null, ano_modelo: null, placa: '', renavam: '', chassi: '',
    cor: '', combustivel: '', medidor: 'km', medidor_atual: 0,
    valor_aquisicao: '', data_aquisicao: '', is_active: true, observacoes: '',
});

function novo() { form.reset(); form.tipo = 'trator'; form.medidor = 'km'; form.is_active = true; editing.value = 'new'; }
function editar(v) { Object.keys(form.data()).forEach(k => form[k] = v[k] ?? form[k]); editing.value = v.id; }
function filtrar() { router.get(route('admin.maquinas.veiculos.index'), filtros, { preserveState: true, replace: true }); }

function salvar() {
    const opts = { preserveScroll: true, only: ['vehicles'], onSuccess: () => editing.value = null };
    if (editing.value === 'new') form.post(route('admin.maquinas.veiculos.store'), opts);
    else form.put(route('admin.maquinas.veiculos.update', editing.value), opts);
}
function toggle(row) { router.post(route('admin.maquinas.veiculos.toggle', row.id), {}, { preserveScroll: true, only: ['vehicles'] }); }
function doDelete() {
    router.delete(route('admin.maquinas.veiculos.destroy', confirmDelete.value.id), {
        preserveScroll: true, only: ['vehicles'],
        onSuccess: () => confirmDelete.value = null,
    });
}

const tipoLabel = {
    trator: 'Trator', caminhao: 'Caminhão', pick_up: 'Pick-up',
    motocicleta: 'Motocicleta', implemento: 'Implemento',
    colheitadeira: 'Colheitadeira', outros: 'Outros',
};
</script>

<template>
    <Head title="Frota de veículos" />
    <AdminLayout>
        <template #page-title>Máquinas</template>
        <PageHeader title="Frota" subtitle="Veículos, implementos e máquinas agrícolas">
            <template #actions>
                <Link :href="route('admin.maquinas.index')" class="btn-outline">Voltar</Link>
                <button @click="novo" class="btn-primary">+ Novo veículo</button>
            </template>
        </PageHeader>

        <div v-if="editing" class="card mb-6">
            <div class="card-header"><h2 class="card-title">{{ editing === 'new' ? 'Novo veículo' : 'Editar veículo' }}</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel value="Tipo" />
                    <select v-model="form.tipo" class="form-select" required>
                        <option v-for="(l, v) in tipoLabel" :key="v" :value="v">{{ l }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2"><InputLabel value="Nome / apelido" /><input v-model="form.nome" required class="form-input"></div>
                <div><InputLabel value="Marca" /><input v-model="form.marca" class="form-input"></div>
                <div><InputLabel value="Modelo" /><input v-model="form.modelo" class="form-input"></div>
                <div><InputLabel value="Ano fab." /><input type="number" v-model="form.ano_fabricacao" class="form-input"></div>
                <div><InputLabel value="Placa (se houver)" /><InputMasked v-model="form.placa" mask="AAA#*##" placeholder="ABC-1234 ou ABC1D23" /></div>
                <div><InputLabel value="Renavam" /><input v-model="form.renavam" class="form-input"></div>
                <div><InputLabel value="Chassi" /><input v-model="form.chassi" class="form-input"></div>
                <div><InputLabel value="Cor" /><input v-model="form.cor" class="form-input"></div>
                <div>
                    <InputLabel value="Combustível" />
                    <select v-model="form.combustivel" class="form-select">
                        <option value="">—</option>
                        <option value="diesel">Diesel</option><option value="gasolina">Gasolina</option>
                        <option value="etanol">Etanol</option><option value="flex">Flex</option>
                        <option value="eletrico">Elétrico</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Medidor" />
                    <select v-model="form.medidor" class="form-select">
                        <option value="km">Quilometragem (km)</option>
                        <option value="h">Horímetro (h)</option>
                    </select>
                </div>
                <div><InputLabel value="Leitura atual" /><input type="number" step="0.01" v-model="form.medidor_atual" class="form-input"></div>
                <div>
                    <InputLabel value="Fazenda" />
                    <select v-model="form.farm_id" class="form-select">
                        <option :value="null">—</option>
                        <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.nome }}</option>
                    </select>
                </div>
                <div><InputLabel value="Valor de aquisição" /><InputMoney v-model="form.valor_aquisicao" /></div>
                <div><InputLabel value="Data de aquisição" /><InputDate v-model="form.data_aquisicao" /></div>
                <div class="sm:col-span-3"><InputLabel value="Observações" /><textarea v-model="form.observacoes" rows="2" class="form-textarea"></textarea></div>
                <div class="sm:col-span-3 flex justify-between items-center">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.is_active" class="rounded"> Ativo</label>
                    <div class="flex gap-2">
                        <button @click="editing = null" class="btn-outline">Cancelar</button>
                        <button @click="salvar" :disabled="form.processing" class="btn-primary">Salvar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-4">
                <input v-model="filtros.search" @keyup.enter="filtrar" placeholder="Nome, placa ou modelo" class="form-input sm:col-span-2">
                <select v-model="filtros.tipo" @change="filtrar" class="form-select">
                    <option value="">Todos os tipos</option>
                    <option v-for="(l, v) in tipoLabel" :key="v" :value="v">{{ l }}</option>
                </select>
                <select v-model="filtros.status" @change="filtrar" class="form-select">
                    <option value="ativos">Ativos</option>
                    <option value="inativos">Inativos</option>
                </select>
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'nome', label: 'Nome' },
                { key: 'tipo', label: 'Tipo' },
                { key: 'placa', label: 'Placa' },
                { key: 'marca', label: 'Marca/Modelo' },
                { key: 'ano_fabricacao', label: 'Ano' },
                { key: 'medidor_atual', label: 'Medidor' },
                { key: 'is_active', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="vehicles.data"
        >
            <template #cell-tipo="{ row }"><span class="badge-slate">{{ tipoLabel[row.tipo] ?? row.tipo }}</span></template>
            <template #cell-marca="{ row }">{{ row.marca }} {{ row.modelo ? '/ ' + row.modelo : '' }}</template>
            <template #cell-medidor_atual="{ row }">{{ Number(row.medidor_atual).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) }} {{ row.medidor }}</template>
            <template #cell-is_active="{ row }">
                <button @click="toggle(row)" :class="row.is_active ? 'badge-green' : 'badge-slate'" class="cursor-pointer">{{ row.is_active ? 'Ativo' : 'Inativo' }}</button>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-1 justify-end">
                    <ActionIcon type="edit" title="Editar veículo" @click="editar(row)" />
                    <ActionIcon type="delete" title="Excluir veículo" @click="confirmDelete = row" />
                </div>
            </template>
        </DataTable>

        <ConfirmModal :show="!!confirmDelete" title="Excluir veículo"
                      :message="`Excluir ${confirmDelete?.nome}? Se houver manutenções, será apenas desativado.`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
