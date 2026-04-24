<script setup>
import { reactive, ref, onMounted, computed } from 'vue';
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
import { brl, dataBR, hojeBR } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ movements: Object, filters: Object, items: Array, warehouses: Array, partners: Array });

useAutoReload(['movements'], 15000);

const filtros = reactive({ ...props.filters });
const showForm = ref(false);
const confirmDelete = ref(null);

// ⚠ `data` colide com método .data() do useForm — usar data_movimento + transform
const form = useForm({
    item_id: '',
    warehouse_id: props.warehouses[0]?.id ?? '',
    partner_id: null,
    tipo: 'entrada',
    motivo: 'compra',
    data_movimento: hojeBR(),
    quantidade: '',
    valor_unitario: '',
    numero_documento: '',
    observacoes: '',
});

// Hub v3 — auto-abrir form quando vier do Hub com `?novo=1` + pré-selecionar tipo/motivo.
// Exemplo: /admin/estoque/movimentos?novo=1&tipo=entrada&motivo=compra
onMounted(() => {
    const qs = new URLSearchParams(window.location.search);
    if (qs.get('novo') === '1') {
        const tipo = qs.get('tipo');
        const motivo = qs.get('motivo');
        if (tipo && ['entrada', 'saida', 'ajuste', 'transferencia'].includes(tipo)) {
            form.tipo = tipo;
        }
        if (motivo) {
            form.motivo = motivo;
        }
        showForm.value = true;
    }
});

function filtrar() {
    router.get(route('admin.estoque.movimentos.index'), filtros, { preserveState: true, replace: true });
}

function save() {
    form.transform((d) => {
        // Renomeia data_movimento → data para o backend (que espera 'data')
        const { data_movimento, ...rest } = d;
        return { ...rest, data: data_movimento };
    }).post(route('admin.estoque.movimentos.store'), {
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

// Hub v4 · Modo contextual — quando o usuário chega pelo card "Registrar
// saída de estoque" (?novo=1&tipo=saida), a tela exibe título e banner
// apropriados em vez do genérico "Movimentações de estoque".
const modoContextual = computed(() => {
    if (props.filters?.novo !== '1' && !new URLSearchParams(window.location.search).has('novo')) return null;
    const qs = new URLSearchParams(window.location.search);
    const tipo = qs.get('tipo') || form.tipo;
    return tipo;
});
const tituloContextual = computed(() => ({
    entrada: 'Receber mercadoria',
    saida: 'Registrar saída de estoque',
    ajuste: 'Ajustar estoque',
    transferencia: 'Transferir entre armazéns',
})[modoContextual.value] ?? 'Movimentações de estoque');
const bannerContextual = computed(() => ({
    saida: {
        emoji: '📤',
        titulo: 'Registrar saída de estoque',
        texto: 'Quando você usa algum item (ração, vacina, adubo, peça...), anote aqui quanto saiu. O saldo do estoque vai cair automaticamente.',
        cor: 'amber',
    },
    entrada: {
        emoji: '📦',
        titulo: 'Receber mercadoria',
        texto: 'Quando chega um produto novo (compra, doação...), anote aqui quanto entrou. O saldo do estoque vai subir.',
        cor: 'emerald',
    },
    ajuste: {
        emoji: '🔢',
        titulo: 'Ajustar estoque',
        texto: 'Use quando a quantidade real no galpão é diferente do que está no sistema. Anote a diferença (pode ser negativa, se faltou).',
        cor: 'sky',
    },
})[modoContextual.value] ?? null);
</script>

<template>
    <Head :title="tituloContextual" />
    <AdminLayout>
        <template #page-title>Estoque</template>

        <PageHeader
            :title="tituloContextual"
            :subtitle="bannerContextual ? '' : 'Entradas, saídas, ajustes e transferências'"
        >
            <template #actions>
                <Link :href="route('admin.estoque.index')" class="btn-outline">Voltar</Link>
                <button @click="showForm = !showForm" class="btn-primary">
                    {{ showForm ? 'Fechar' : '+ Nova movimentação' }}
                </button>
            </template>
        </PageHeader>

        <!-- Banner contextual quando chega pelo card do Hub -->
        <div v-if="bannerContextual && showForm"
             class="mb-6 p-4 rounded-xl border-l-4 flex items-start gap-3"
             :class="{
                 'bg-amber-50 border-amber-400': bannerContextual.cor === 'amber',
                 'bg-emerald-50 border-emerald-400': bannerContextual.cor === 'emerald',
                 'bg-sky-50 border-sky-400': bannerContextual.cor === 'sky',
             }">
            <span class="text-2xl flex-shrink-0" aria-hidden="true">{{ bannerContextual.emoji }}</span>
            <div class="min-w-0 flex-1">
                <div class="font-semibold"
                     :class="{ 'text-amber-900': bannerContextual.cor === 'amber', 'text-emerald-900': bannerContextual.cor === 'emerald', 'text-sky-900': bannerContextual.cor === 'sky' }">
                    {{ bannerContextual.titulo }}
                </div>
                <div class="text-sm mt-0.5"
                     :class="{ 'text-amber-800': bannerContextual.cor === 'amber', 'text-emerald-800': bannerContextual.cor === 'emerald', 'text-sky-800': bannerContextual.cor === 'sky' }">
                    {{ bannerContextual.texto }}
                </div>
            </div>
        </div>

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
                    <InputDate v-model="form.data_movimento" />
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
