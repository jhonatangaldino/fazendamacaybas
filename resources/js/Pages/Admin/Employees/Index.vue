<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
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
const desligamento = ref(null); // funcionário em processo de desligamento
const desligForm = useForm({ data_demissao: '', motivo_demissao: '' });

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

function abrirDesligamento(row) {
    desligamento.value = row;
    desligForm.reset();
    desligForm.clearErrors();
    desligForm.data_demissao = new Date().toISOString().slice(0, 10);
}
function confirmarDesligamento() {
    desligForm.delete(route('admin.funcionarios.destroy', desligamento.value.id), {
        preserveScroll: true, only: ['employees'],
        onSuccess: () => { desligamento.value = null; desligForm.reset(); },
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
                <div><InputLabel value="Admissão" /><InputDate v-model="form.data_admissao" :max="form.data_demissao || undefined" /></div>
                <div><InputLabel value="Demissão" /><InputDate v-model="form.data_demissao" :min="form.data_admissao || undefined" /></div>
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
                <span v-if="row.is_active" class="badge-green">Ativo</span>
                <span v-else class="badge-slate" :title="row.data_demissao ? `Desligado em ${dataBR(row.data_demissao)}` : 'Desligado'">
                    Inativo<span v-if="row.data_demissao" class="ml-1 text-[11px] text-slate-500">({{ dataBR(row.data_demissao) }})</span>
                </span>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-3 justify-end">
                    <button @click="editar(row)" class="text-slate-500 hover:text-macaybas-primary">Editar</button>
                    <button v-if="row.is_active"
                            @click="abrirDesligamento(row)"
                            class="text-red-600 hover:underline">Desligar</button>
                    <button v-else
                            @click="toggle(row)"
                            class="text-emerald-600 hover:underline">Reativar</button>
                </div>
            </template>
        </DataTable>

        <!-- Modal: Desligar funcionário com data obrigatória -->
        <Teleport to="body">
            <div v-if="desligamento" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="desligamento = null"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-slate-900">Desligar funcionário</h3>
                        <p class="text-sm text-slate-500 mt-1">Informe a data de desligamento de <strong>{{ desligamento.nome }}</strong>. O registro será mantido — apenas marcado como inativo.</p>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Data do desligamento *" />
                            <InputDate
                                v-model="desligForm.data_demissao"
                                :min="desligamento.data_admissao || null"
                                :max="new Date().toISOString().slice(0,10)"
                                required
                            />
                            <p v-if="desligForm.errors.data_demissao" class="text-xs text-red-600 mt-1">{{ desligForm.errors.data_demissao }}</p>
                            <p v-if="desligamento.data_admissao" class="text-xs text-slate-500 mt-1">
                                Admissão: {{ dataBR(desligamento.data_admissao) }} — a data de desligamento não pode ser anterior a ela nem futura.
                            </p>
                        </div>
                        <div>
                            <InputLabel value="Motivo (opcional)" />
                            <textarea v-model="desligForm.motivo_demissao" rows="2" class="form-textarea"
                                      placeholder="Ex: pedido de demissão, reestruturação, etc."></textarea>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="desligamento = null" class="btn-outline">Cancelar</button>
                        <button @click="confirmarDesligamento" :disabled="desligForm.processing"
                                class="btn-primary bg-red-600 hover:bg-red-700">
                            {{ desligForm.processing ? 'Desligando...' : 'Confirmar desligamento' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
