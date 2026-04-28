<script setup>
import { reactive, ref, onMounted } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputMoney from '@/Components/InputMoney.vue';
import InputDecimal from '@/Components/InputDecimal.vue';
import InputDate from '@/Components/InputDate.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import { brl, dataBR } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ orders: Object, filters: Object, vehicles: Array, partners: Array, accounts: Array });
useAutoReload(['orders'], 20000);

const filtros = reactive({ ...props.filters });
const editing = ref(null);
const confirmDelete = ref(null);

const form = useForm({
    vehicle_id: '', partner_id: null, tipo: 'preventiva', descricao: '',
    data_prevista: '', data_realizada: '', medidor: null,
    valor_pecas: '', valor_servico: '', status: 'agendada',
    observacoes: '', gerar_lancamento_financeiro: false, account_id: null,
});

function novo(tipoPreSelecionado = 'preventiva') {
    form.reset();
    form.tipo = ['preventiva', 'corretiva', 'revisao'].includes(tipoPreSelecionado) ? tipoPreSelecionado : 'preventiva';
    form.status = 'agendada';
    editing.value = 'new';
}

// Hub v3 — auto-abrir form com `?novo=1&tipo=corretiva|preventiva|revisao`
onMounted(() => {
    const qs = new URLSearchParams(window.location.search);
    if (qs.get('novo') === '1') {
        novo(qs.get('tipo') || 'preventiva');
    }
});
function editar(o) {
    Object.keys(form.data()).forEach(k => form[k] = o[k] ?? form[k]);
    form.gerar_lancamento_financeiro = false;
    editing.value = o.id;
}
function filtrar() { router.get(route('admin.maquinas.manutencoes.index'), filtros, { preserveState: true, replace: true }); }

function salvar() {
    const opts = { preserveScroll: true, only: ['orders'], onSuccess: () => editing.value = null };
    if (editing.value === 'new') form.post(route('admin.maquinas.manutencoes.store'), opts);
    else form.put(route('admin.maquinas.manutencoes.update', editing.value), opts);
}
function doDelete() {
    router.delete(route('admin.maquinas.manutencoes.destroy', confirmDelete.value.id), {
        preserveScroll: true, only: ['orders'],
        onSuccess: () => confirmDelete.value = null,
    });
}

const statusBadge = (s) => ({
    agendada: 'badge-blue', em_andamento: 'badge-yellow',
    concluida: 'badge-green', cancelada: 'badge-slate',
})[s] || 'badge-slate';
</script>

<template>
    <Head title="Manutenções" />
    <AdminLayout>
        <template #page-title>Máquinas</template>
        <PageHeader title="Ordens de manutenção" subtitle="Preventiva, corretiva e revisão — com custo integrado ao financeiro">
            <template #actions>
                <Link :href="route('admin.maquinas.index')" class="btn-outline">Voltar</Link>
                <button @click="novo" class="btn-primary">+ Nova manutenção</button>
            </template>
        </PageHeader>

        <div v-if="editing" class="card mb-6">
            <div class="card-header"><h2 class="card-title">{{ editing === 'new' ? 'Nova manutenção' : 'Editar manutenção' }}</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <InputLabel value="Veículo" />
                    <select v-model="form.vehicle_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.nome }} {{ v.placa ? `(${v.placa})` : '' }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Tipo" />
                    <select v-model="form.tipo" class="form-select">
                        <option value="preventiva">Preventiva</option>
                        <option value="corretiva">Corretiva</option>
                        <option value="revisao">Revisão</option>
                    </select>
                </div>
                <div class="sm:col-span-3"><InputLabel value="Descrição do serviço" /><input v-model="form.descricao" required class="form-input"></div>
                <div><InputLabel value="Data prevista" /><InputDate v-model="form.data_prevista" /></div>
                <div><InputLabel value="Data realizada" /><InputDate v-model="form.data_realizada" /></div>
                <div><InputLabel value="Leitura medidor" /><InputDecimal v-model="form.medidor" :decimals="2" :min="0" placeholder="0,00" /></div>
                <div><InputLabel value="Valor peças" /><InputMoney v-model="form.valor_pecas" /></div>
                <div><InputLabel value="Valor serviço" /><InputMoney v-model="form.valor_servico" /></div>
                <div>
                    <InputLabel value="Status" />
                    <select v-model="form.status" class="form-select">
                        <option value="agendada">Agendada</option>
                        <option value="em_andamento">Em andamento</option>
                        <option value="concluida">Concluída</option>
                        <option value="cancelada">Cancelada</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <InputLabel value="Oficina / fornecedor" />
                    <select v-model="form.partner_id" class="form-select">
                        <option :value="null">—</option>
                        <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.nome }}</option>
                    </select>
                </div>
                <div class="sm:col-span-3 p-3 rounded-lg bg-macaybas-primary-50 border border-macaybas-primary-200">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.gerar_lancamento_financeiro" class="rounded">
                        Gerar lançamento no financeiro automaticamente
                    </label>
                    <div v-if="form.gerar_lancamento_financeiro" class="mt-3">
                        <InputLabel value="Conta financeira" />
                        <select v-model="form.account_id" class="form-select" required>
                            <option :value="null">Selecione...</option>
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.nome }}</option>
                        </select>
                    </div>
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
                    <option value="agendada">Agendadas</option>
                    <option value="em_andamento">Em andamento</option>
                    <option value="concluida">Concluídas</option>
                    <option value="cancelada">Canceladas</option>
                </select>
                <select v-model="filtros.tipo" @change="filtrar" class="form-select">
                    <option value="">Todos os tipos</option>
                    <option value="preventiva">Preventiva</option>
                    <option value="corretiva">Corretiva</option>
                    <option value="revisao">Revisão</option>
                </select>
                <select v-model="filtros.vehicle_id" @change="filtrar" class="form-select">
                    <option value="">Todos os veículos</option>
                    <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.nome }}</option>
                </select>
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'data_prevista', label: 'Prevista', format: dataBR },
                { key: 'data_realizada', label: 'Realizada', format: dataBR },
                { key: 'vehicle', label: 'Veículo' },
                { key: 'tipo', label: 'Tipo' },
                { key: 'descricao', label: 'Descrição' },
                { key: 'valor_total', label: 'Total', align: 'right', format: brl },
                { key: 'status', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="orders.data"
        >
            <template #cell-vehicle="{ row }">{{ row.vehicle?.nome ?? '—' }}</template>
            <template #cell-tipo="{ row }"><span class="badge-slate">{{ row.tipo }}</span></template>
            <template #cell-status="{ row }"><span :class="statusBadge(row.status)">{{ row.status.replace('_', ' ') }}</span></template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-1 justify-end">
                    <ActionIcon type="edit" title="Editar manutenção" @click="editar(row)" />
                    <ActionIcon type="delete" title="Excluir manutenção" @click="confirmDelete = row" />
                </div>
            </template>
        </DataTable>

        <ConfirmModal :show="!!confirmDelete" title="Excluir manutenção"
                      message="Excluir esta manutenção?"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
