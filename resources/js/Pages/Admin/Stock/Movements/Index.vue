<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import InputMoney from '@/Components/InputMoney.vue';
import InputDate from '@/Components/InputDate.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import { brl, dataBR } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ movements: Object, filters: Object, items: Array, warehouses: Array, partners: Array });

useAutoReload(['movements'], 15000);

const filtros = reactive({ ...props.filters });
const showForm = ref(false);
const confirmDelete = ref(null);

const form = useForm({
    item_id: '',
    warehouse_id: props.warehouses[0]?.id ?? '',
    partner_id: null,
    tipo: 'entrada',
    motivo: 'compra',
    data: new Date().toISOString().slice(0, 10),
    quantidade: '',
    valor_unitario: '',
    numero_documento: '',
    observacoes: '',
});

function filtrar() {
    router.get(route('admin.estoque.movimentos.index'), filtros, { preserveState: true, replace: true });
}

function save() {
    form.post(route('admin.estoque.movimentos.store'), {
        preserveScroll: true,
        only: ['movements'],
        onSuccess: () => {
            showForm.value = false;
            form.reset('quantidade', 'valor_unitario', 'numero_documento', 'observacoes');
        },
    });
}

function doDelete() {
    router.delete(route('admin.estoque.movimentos.destroy', confirmDelete.value.id), {
        preserveScroll: true,
        only: ['movements'],
        onSuccess: () => (confirmDelete.value = null),
    });
}

const tipoBadge = (t) => ({
    entrada: 'badge-green',
    saida: 'badge-red',
    ajuste: 'badge-blue',
    transferencia: 'badge-yellow',
})[t] || 'badge-slate';
</script>

<template>
    <Head title="Estoque — Movimentações" />
    <AdminLayout>
        <template #page-title>Estoque</template>

        <PageHeader title="Movimentações de estoque" subtitle="Entradas, saídas, ajustes e transferências">
            <template #actions>
                <Link :href="route('admin.estoque.index')" class="btn-outline">Voltar</Link>
                <button @click="showForm = !showForm" class="btn-primary">
                    {{ showForm ? 'Fechar' : '+ Nova movimentação' }}
                </button>
            </template>
        </PageHeader>

        <!-- Form inline (toggle) -->
        <div v-if="showForm" class="card mb-6">
            <div class="card-header"><h2 class="card-title">Nova movimentação</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-4">
                <div>
                    <InputLabel value="Tipo" />
                    <select v-model="form.tipo" class="form-select">
                        <option value="entrada">Entrada</option>
                        <option value="saida">Saída</option>
                        <option value="ajuste">Ajuste</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Motivo" />
                    <select v-model="form.motivo" class="form-select">
                        <option value="compra">Compra</option>
                        <option value="uso">Uso operacional</option>
                        <option value="perda">Perda</option>
                        <option value="vencimento">Vencimento</option>
                        <option value="inventario">Ajuste de inventário</option>
                        <option value="devolucao">Devolução</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Data" />
                    <InputDate v-model="form.data" />
                </div>
                <div>
                    <InputLabel value="Armazém" />
                    <select v-model="form.warehouse_id" class="form-select" required>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.nome }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <InputLabel value="Item" />
                    <select v-model="form.item_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <option v-for="i in items" :key="i.id" :value="i.id">{{ i.nome }} ({{ i.unidade }})</option>
                    </select>
                    <InputError :message="form.errors.item_id" />
                </div>
                <div>
                    <InputLabel value="Quantidade" />
                    <input type="number" step="0.001" min="0" v-model="form.quantidade" class="form-input" required>
                    <InputError :message="form.errors.quantidade" />
                </div>
                <div>
                    <InputLabel value="Valor unitário" />
                    <InputMoney v-model="form.valor_unitario" />
                </div>
                <div class="sm:col-span-2">
                    <InputLabel value="Fornecedor (opcional)" />
                    <select v-model="form.partner_id" class="form-select">
                        <option :value="null">—</option>
                        <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.nome }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Nº documento" />
                    <input v-model="form.numero_documento" class="form-input">
                </div>
                <div class="sm:col-span-4">
                    <InputLabel value="Observações" />
                    <textarea v-model="form.observacoes" rows="2" class="form-textarea"></textarea>
                </div>
                <div class="sm:col-span-4 flex justify-end gap-2">
                    <button type="button" @click="showForm = false" class="btn-outline">Cancelar</button>
                    <button type="button" @click="save" :disabled="form.processing" class="btn-primary">Registrar</button>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-5">
                <select v-model="filtros.tipo" @change="filtrar" class="form-select">
                    <option value="">Todos os tipos</option>
                    <option value="entrada">Entradas</option>
                    <option value="saida">Saídas</option>
                    <option value="ajuste">Ajustes</option>
                </select>
                <select v-model="filtros.item_id" @change="filtrar" class="form-select">
                    <option value="">Todos os itens</option>
                    <option v-for="i in items" :key="i.id" :value="i.id">{{ i.nome }}</option>
                </select>
                <select v-model="filtros.warehouse_id" @change="filtrar" class="form-select">
                    <option value="">Todos os armazéns</option>
                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.nome }}</option>
                </select>
                <InputDate v-model="filtros.from" :max="filtros.to || undefined" @update:modelValue="filtrar" />
                <InputDate v-model="filtros.to" :min="filtros.from || undefined" @update:modelValue="filtrar" />
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'data', label: 'Data', format: dataBR },
                { key: 'tipo', label: 'Tipo' },
                { key: 'item', label: 'Item' },
                { key: 'warehouse', label: 'Armazém' },
                { key: 'quantidade', label: 'Qtd.', align: 'right' },
                { key: 'valor_total', label: 'Valor total', align: 'right', format: brl },
                { key: 'partner', label: 'Fornecedor' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="movements.data"
        >
            <template #cell-tipo="{ row }"><span :class="tipoBadge(row.tipo)">{{ row.tipo }}</span></template>
            <template #cell-item="{ row }">{{ row.item?.nome ?? '—' }}</template>
            <template #cell-warehouse="{ row }">{{ row.warehouse?.nome ?? '—' }}</template>
            <template #cell-quantidade="{ row }">{{ Number(row.quantidade).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }} {{ row.item?.unidade }}</template>
            <template #cell-partner="{ row }">{{ row.partner?.nome ?? '—' }}</template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-1 justify-end">
                    <ActionIcon type="delete" title="Excluir movimentação" @click="confirmDelete = row" />
                </div>
            </template>
        </DataTable>

        <div v-if="movements.links" class="mt-4 flex gap-2 flex-wrap justify-end">
            <Link v-for="link in movements.links" :key="link.label"
                  :href="link.url ?? '#'"
                  v-html="link.label"
                  :class="['btn-outline btn-sm', link.active ? '!bg-macaybas-primary !text-white !border-transparent' : '', !link.url ? 'opacity-40 pointer-events-none' : '']" />
        </div>

        <ConfirmModal :show="!!confirmDelete" title="Excluir movimentação"
                      message="Excluir esta movimentação? O saldo será recalculado automaticamente."
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
