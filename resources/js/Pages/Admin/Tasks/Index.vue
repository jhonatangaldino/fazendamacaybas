<script setup>
import { reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import { dataBR } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ tasks: Object, filters: Object, employees: Array });
useAutoReload(['tasks'], 15000);

const filtros = reactive({ ...props.filters });
const editing = ref(null);
const confirmDelete = ref(null);
const newChecklistItem = ref('');

const form = useForm({
    titulo: '', descricao: '',
    prioridade: 'media', status: 'pendente',
    data_inicio: '', data_vencimento: '',
    modulo: 'geral', farm_id: null,
    assignees: [],
    checklist_items: [],
});

function novo() {
    form.reset();
    form.prioridade = 'media'; form.status = 'pendente'; form.modulo = 'geral';
    form.assignees = []; form.checklist_items = [];
    editing.value = 'new';
}

function editar(t) {
    form.titulo = t.titulo; form.descricao = t.descricao ?? '';
    form.prioridade = t.prioridade; form.status = t.status;
    form.data_inicio = t.data_inicio ?? ''; form.data_vencimento = t.data_vencimento ?? '';
    form.modulo = t.modulo ?? 'geral';
    form.assignees = t.assignees.map(a => a.id);
    form.checklist_items = [];
    editing.value = t.id;
}

function addChecklistItem() {
    if (!newChecklistItem.value.trim()) return;
    form.checklist_items.push(newChecklistItem.value);
    newChecklistItem.value = '';
}
function removeChecklistItem(i) { form.checklist_items.splice(i, 1); }

function filtrar() { router.get(route('admin.tarefas.index'), filtros, { preserveState: true, replace: true }); }
function salvar() {
    const opts = { preserveScroll: true, only: ['tasks'], onSuccess: () => editing.value = null };
    if (editing.value === 'new') form.post(route('admin.tarefas.store'), opts);
    else form.put(route('admin.tarefas.update', editing.value), opts);
}
function concluir(t) { router.post(route('admin.tarefas.complete', t.id), {}, { preserveScroll: true, only: ['tasks'] }); }
function reabrir(t) { router.post(route('admin.tarefas.reopen', t.id), {}, { preserveScroll: true, only: ['tasks'] }); }
function toggleItem(item) { router.post(route('admin.tarefas.checklist.toggle', item.id), {}, { preserveScroll: true, only: ['tasks'] }); }
function doDelete() {
    router.delete(route('admin.tarefas.destroy', confirmDelete.value.id), {
        preserveScroll: true, only: ['tasks'],
        onSuccess: () => confirmDelete.value = null,
    });
}

const prioridadeBadge = (p) => ({ baixa: 'badge-slate', media: 'badge-blue', alta: 'badge-yellow', urgente: 'badge-red' })[p] || 'badge-slate';
const statusBadge = (s) => ({ pendente: 'badge-yellow', em_andamento: 'badge-blue', concluida: 'badge-green', cancelada: 'badge-slate', atrasada: 'badge-red' })[s] || 'badge-slate';
</script>

<template>
    <Head title="Tarefas" />
    <AdminLayout>
        <template #page-title>Tarefas</template>
        <PageHeader title="Tarefas" subtitle="Gestão de atividades da operação com checklists e responsáveis">
            <template #actions>
                <button @click="novo" class="btn-primary">+ Nova tarefa</button>
            </template>
        </PageHeader>

        <div v-if="editing" class="card mb-6">
            <div class="card-header"><h2 class="card-title">{{ editing === 'new' ? 'Nova tarefa' : 'Editar tarefa' }}</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2"><InputLabel value="Título" /><input v-model="form.titulo" required class="form-input"></div>
                <div>
                    <InputLabel value="Módulo" />
                    <select v-model="form.modulo" class="form-select">
                        <option value="geral">Geral</option>
                        <option value="rebanho">Rebanho</option>
                        <option value="agricola">Agrícola</option>
                        <option value="estoque">Estoque</option>
                        <option value="maquinas">Máquinas</option>
                        <option value="financeiro">Financeiro</option>
                    </select>
                </div>
                <div class="sm:col-span-3"><InputLabel value="Descrição" /><textarea v-model="form.descricao" rows="2" class="form-textarea"></textarea></div>
                <div>
                    <InputLabel value="Prioridade" />
                    <select v-model="form.prioridade" class="form-select">
                        <option value="baixa">Baixa</option>
                        <option value="media">Média</option>
                        <option value="alta">Alta</option>
                        <option value="urgente">Urgente</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Status" />
                    <select v-model="form.status" class="form-select">
                        <option value="pendente">Pendente</option>
                        <option value="em_andamento">Em andamento</option>
                        <option value="concluida">Concluída</option>
                        <option value="cancelada">Cancelada</option>
                    </select>
                </div>
                <div><InputLabel value="Início" /><InputDate v-model="form.data_inicio" /></div>
                <div><InputLabel value="Vencimento" /><InputDate v-model="form.data_vencimento" /></div>

                <div class="sm:col-span-3">
                    <InputLabel value="Responsáveis" />
                    <div class="grid gap-2 sm:grid-cols-3">
                        <label v-for="e in employees" :key="e.id" class="flex items-center gap-2 text-sm rounded-md border border-slate-200 p-2 hover:border-macaybas-primary cursor-pointer">
                            <input type="checkbox" :value="e.id" v-model="form.assignees" class="rounded">
                            <span>{{ e.nome }}</span>
                        </label>
                    </div>
                </div>

                <div v-if="editing === 'new'" class="sm:col-span-3">
                    <InputLabel value="Checklist (opcional)" />
                    <div class="space-y-2">
                        <div v-for="(it, i) in form.checklist_items" :key="i" class="flex gap-2 items-center">
                            <span class="text-slate-600 text-sm flex-1">{{ i + 1 }}. {{ it }}</span>
                            <button type="button" @click="removeChecklistItem(i)" class="text-red-600 text-sm">remover</button>
                        </div>
                        <div class="flex gap-2">
                            <input v-model="newChecklistItem" @keyup.enter="addChecklistItem" placeholder="Adicionar item do checklist" class="form-input flex-1">
                            <button type="button" @click="addChecklistItem" class="btn-outline">+</button>
                        </div>
                    </div>
                </div>

                <div class="sm:col-span-3 flex justify-end gap-2">
                    <button @click="editing = null" class="btn-outline">Cancelar</button>
                    <button @click="salvar" :disabled="form.processing" class="btn-primary">Salvar</button>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-4">
                <select v-model="filtros.status" @change="filtrar" class="form-select">
                    <option value="">Todos os status</option>
                    <option value="pendente">Pendentes</option>
                    <option value="em_andamento">Em andamento</option>
                    <option value="concluida">Concluídas</option>
                </select>
                <select v-model="filtros.prioridade" @change="filtrar" class="form-select">
                    <option value="">Toda prioridade</option>
                    <option value="baixa">Baixa</option><option value="media">Média</option>
                    <option value="alta">Alta</option><option value="urgente">Urgente</option>
                </select>
                <select v-model="filtros.modulo" @change="filtrar" class="form-select">
                    <option value="">Todos os módulos</option>
                    <option value="geral">Geral</option>
                    <option value="rebanho">Rebanho</option>
                    <option value="agricola">Agrícola</option>
                    <option value="estoque">Estoque</option>
                    <option value="maquinas">Máquinas</option>
                    <option value="financeiro">Financeiro</option>
                </select>
                <select v-model="filtros.employee_id" @change="filtrar" class="form-select">
                    <option value="">Todos os responsáveis</option>
                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.nome }}</option>
                </select>
            </div>
        </div>

        <div class="space-y-3">
            <div v-for="t in tasks.data" :key="t.id" class="card">
                <div class="card-body">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span :class="prioridadeBadge(t.prioridade)">{{ t.prioridade }}</span>
                                <span :class="statusBadge(t.status)">{{ t.status.replace('_', ' ') }}</span>
                                <span v-if="t.modulo" class="badge-slate">{{ t.modulo }}</span>
                                <span v-if="t.data_vencimento" class="text-xs text-slate-500">
                                    vence em {{ dataBR(t.data_vencimento) }}
                                </span>
                            </div>
                            <h3 class="font-semibold text-slate-900">{{ t.titulo }}</h3>
                            <p v-if="t.descricao" class="text-sm text-slate-600 mt-1">{{ t.descricao }}</p>

                            <div v-if="t.assignees.length" class="mt-2 flex flex-wrap gap-1">
                                <span v-for="a in t.assignees" :key="a.id" class="badge-blue text-xs">👤 {{ a.nome }}</span>
                            </div>

                            <div v-for="cl in t.checklists" :key="cl.id" class="mt-3 space-y-1">
                                <div class="text-xs uppercase tracking-wide text-slate-500">{{ cl.titulo }}</div>
                                <label v-for="item in cl.items" :key="item.id" class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="checkbox" :checked="item.is_done" @change="toggleItem(item)" class="rounded">
                                    <span :class="item.is_done ? 'line-through text-slate-400' : ''">{{ item.descricao }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <button v-if="t.status !== 'concluida'" @click="concluir(t)" class="btn-primary btn-sm">Concluir</button>
                            <button v-else @click="reabrir(t)" class="btn-outline btn-sm">Reabrir</button>
                            <button @click="editar(t)" class="btn-outline btn-sm">Editar</button>
                            <button @click="confirmDelete = t" class="text-red-600 text-xs hover:underline">Excluir</button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!tasks.data.length" class="card p-10 text-center text-slate-500">
                Nenhuma tarefa encontrada com os filtros atuais.
            </div>
        </div>

        <ConfirmModal :show="!!confirmDelete" title="Excluir tarefa"
                      :message="`Excluir ${confirmDelete?.titulo}?`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
