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
import InputMasked from '@/Components/InputMasked.vue';
import { brl, dataBR, cpfMask, telefoneMask } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ employees: Object, filters: Object, farms: Array, setores: Array });
useAutoReload(['employees'], 25000);

const filtros = reactive({ ...props.filters });
const editing = ref(null);
const confirmDelete = ref(null);

const form = useForm({
    farm_id: null, nome: '', cpf: '', rg: '', data_nascimento: '',
    telefone: '', celular: '', email: '',
    setor: '', funcao: '', salario: '',
    data_admissao: '', data_demissao: '',
    cep: '', endereco: '', numero: '', bairro: '', cidade: '', estado: '',
    observacoes: '', is_active: true,
});

function novo() { form.reset(); form.is_active = true; editing.value = 'new'; }
function editar(e) { Object.keys(form.data()).forEach(k => form[k] = e[k] ?? form[k]); editing.value = e.id; }
function filtrar() { router.get(route('admin.funcionarios.index'), filtros, { preserveState: true, replace: true }); }

function salvar() {
    const opts = { preserveScroll: true, only: ['employees'], onSuccess: () => editing.value = null };
    if (editing.value === 'new') form.post(route('admin.funcionarios.store'), opts);
    else form.put(route('admin.funcionarios.update', editing.value), opts);
}
function toggle(row) { router.post(route('admin.funcionarios.toggle', row.id), {}, { preserveScroll: true, only: ['employees'] }); }
function doDelete() {
    router.delete(route('admin.funcionarios.destroy', confirmDelete.value.id), {
        preserveScroll: true, only: ['employees'],
        onSuccess: () => confirmDelete.value = null,
    });
}
</script>

<template>
    <Head title="Funcionários" />
    <AdminLayout>
        <template #page-title>Funcionários</template>
        <PageHeader title="Funcionários" subtitle="Cadastro completo com contratos, setores e cargos">
            <template #actions>
                <button @click="novo" class="btn-primary">+ Novo funcionário</button>
            </template>
        </PageHeader>

        <div v-if="editing" class="card mb-6">
            <div class="card-header"><h2 class="card-title">{{ editing === 'new' ? 'Novo funcionário' : 'Editar funcionário' }}</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2"><InputLabel value="Nome completo" /><input v-model="form.nome" required class="form-input"></div>
                <div>
                    <InputLabel value="Fazenda" />
                    <select v-model="form.farm_id" class="form-select">
                        <option :value="null">—</option>
                        <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.nome }}</option>
                    </select>
                </div>
                <div><InputLabel value="CPF" /><InputMasked v-model="form.cpf" mask="###.###.###-##" /></div>
                <div><InputLabel value="RG" /><input v-model="form.rg" class="form-input"></div>
                <div><InputLabel value="Data nascimento" /><InputDate v-model="form.data_nascimento" /></div>
                <div><InputLabel value="Telefone" /><InputMasked v-model="form.telefone" :mask="['(##) ####-####', '(##) #####-####']" /></div>
                <div><InputLabel value="Celular" /><InputMasked v-model="form.celular" mask="(##) #####-####" /></div>
                <div><InputLabel value="E-mail" /><input type="email" v-model="form.email" class="form-input"></div>
                <div><InputLabel value="Setor" /><input v-model="form.setor" class="form-input" placeholder="Ex: Pecuária"></div>
                <div><InputLabel value="Função / cargo" /><input v-model="form.funcao" class="form-input" placeholder="Ex: Vaqueiro"></div>
                <div><InputLabel value="Salário" /><InputMoney v-model="form.salario" /></div>
                <div><InputLabel value="Admissão" /><InputDate v-model="form.data_admissao" /></div>
                <div><InputLabel value="Demissão" /><InputDate v-model="form.data_demissao" /></div>
                <div><InputLabel value="CEP" /><InputMasked v-model="form.cep" mask="#####-###" /></div>
                <div class="sm:col-span-2"><InputLabel value="Endereço" /><input v-model="form.endereco" class="form-input"></div>
                <div><InputLabel value="Número" /><input v-model="form.numero" class="form-input"></div>
                <div><InputLabel value="Bairro" /><input v-model="form.bairro" class="form-input"></div>
                <div><InputLabel value="Cidade" /><input v-model="form.cidade" class="form-input"></div>
                <div><InputLabel value="UF" /><input v-model="form.estado" maxlength="2" class="form-input uppercase"></div>
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
            <div class="card-body grid gap-3 sm:grid-cols-3">
                <input v-model="filtros.search" @keyup.enter="filtrar" placeholder="Nome ou CPF" class="form-input">
                <select v-model="filtros.setor" @change="filtrar" class="form-select">
                    <option value="">Todos os setores</option>
                    <option v-for="s in setores" :key="s" :value="s">{{ s }}</option>
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
                { key: 'cpf', label: 'CPF', format: cpfMask },
                { key: 'funcao', label: 'Cargo' },
                { key: 'setor', label: 'Setor' },
                { key: 'celular', label: 'Celular', format: telefoneMask },
                { key: 'data_admissao', label: 'Admissão', format: dataBR },
                { key: 'salario', label: 'Salário', align: 'right', format: brl },
                { key: 'is_active', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="employees.data"
        >
            <template #cell-is_active="{ row }">
                <button @click="toggle(row)" :class="row.is_active ? 'badge-green' : 'badge-slate'" class="cursor-pointer">{{ row.is_active ? 'Ativo' : 'Inativo' }}</button>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-2 justify-end">
                    <button @click="editar(row)" class="text-slate-500 hover:text-macaybas-primary">Editar</button>
                    <button @click="confirmDelete = row" class="text-red-600 hover:underline">Desligar</button>
                </div>
            </template>
        </DataTable>

        <ConfirmModal :show="!!confirmDelete" title="Desligar funcionário"
                      :message="`Desligar ${confirmDelete?.nome}? O registro será mantido, apenas marcado como inativo.`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
