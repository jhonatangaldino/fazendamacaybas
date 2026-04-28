<script setup>
import { reactive, ref, computed } from 'vue';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import MobileFilters from '@/Components/MobileFilters.vue';
import BarcodeScanner from '@/Components/BarcodeScanner.vue';
import { useAutoReload } from '@/composables/useAutoReload.js';
import { useToast } from '@/composables/useToast.js';

const { toast } = useToast();
const props = defineProps({ items: Object, filters: Object, categories: Array, resumo: { type: Object, default: () => ({ total_itens: 0, abaixo_minimo: 0, sem_estoque: 0 }) } });
// B4.4 fix · defaults '' garantem que option value="" seja auto-selecionado
const filtros = reactive({
    search: '',
    tipo: '',
    status: 'ativos',
    ...props.filters,
});
const confirmDelete = ref(null);

// Scanner a partir da listagem: se achar, abre o item; se não, oferece cadastro.
const showScanner = ref(false);
const naoEncontrado = ref(null); // { code } → modal oferecendo cadastro com scan
useBodyScrollLock(computed(() => !!naoEncontrado.value));

async function onBarcodeDetectedFromList(code) {
    showScanner.value = false;
    try {
        const resp = await fetch(route('admin.estoque.itens.lookup-barcode') + '?code=' + encodeURIComponent(code), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const data = await resp.json();

        if (data.found && data.item) {
            toast.success(`"${data.item.nome}" identificado. Abrindo edição...`);
            setTimeout(() => { window.location.href = data.item.edit_url; }, 400);
            return;
        }
        // Não encontrado em base nenhuma — oferece cadastrar com o código já verificado
        naoEncontrado.value = { code, suggestion: data.suggestion || null };
    } catch (e) {
        toast.warning('Falha na consulta. Tente novamente.');
    }
}

function cadastrarNovoComCodigo() {
    const url = route('admin.estoque.itens.create') + `?codigo_barras=${encodeURIComponent(naoEncontrado.value.code)}&scanned=1`;
    window.location.href = url;
}

// Realtime: re-puxa a lista a cada 15s
useAutoReload(['items'], 15000);

function filtrar() {
    router.get(route('admin.estoque.itens.index'), filtros, { preserveState: true, replace: true });
}

function toggle(row) {
    router.post(route('admin.estoque.itens.toggle', row.id), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['items'],
    });
}

function doDelete() {
    router.delete(route('admin.estoque.itens.destroy', confirmDelete.value.id), {
        preserveScroll: true,
        only: ['items'],
        onSuccess: () => (confirmDelete.value = null),
    });
}

const tipoLabel = {
    insumo: 'Insumo',
    medicamento: 'Medicamento',
    racao: 'Ração',
    ferramenta: 'Ferramenta',
    peca: 'Peça',
    combustivel: 'Combustível',
    material: 'Material',
};
</script>

<template>
    <Head title="Estoque — Itens" />
    <AdminLayout>
        <template #page-title>Estoque</template>

        <PageHeader title="Itens de estoque" subtitle="Todos os itens controlados no almoxarifado">
            <template #actions>
                <Link :href="route('admin.estoque.index')" class="btn-outline">Voltar</Link>
                <button @click="showScanner = true" class="btn-outline flex items-center gap-1.5">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9zM15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Escanear
                </button>
                <Link :href="route('admin.estoque.itens.create')" class="btn-primary">Novo item</Link>
            </template>
        </PageHeader>

        <!-- Resumo de valor — ajuda o dono a DECIDIR o que repor hoje.
             Só aparece quando há pelo menos 1 item abaixo do mínimo — caso
             contrário é só poluição visual. -->
        <div v-if="resumo.abaixo_minimo > 0"
             class="mb-5 rounded-xl border-l-4 border-amber-500 bg-amber-50 p-4 flex flex-wrap items-center gap-3">
            <span class="text-3xl flex-shrink-0" aria-hidden="true">⚠️</span>
            <div class="flex-1 min-w-0">
                <div class="font-semibold text-amber-900">
                    {{ resumo.abaixo_minimo }} {{ resumo.abaixo_minimo === 1 ? 'item precisa' : 'itens precisam' }} ser reposto{{ resumo.abaixo_minimo === 1 ? '' : 's' }}
                </div>
                <div class="text-sm text-amber-800 mt-0.5">
                    Saldo abaixo do mínimo definido.<span v-if="resumo.sem_estoque > 0"> <strong>{{ resumo.sem_estoque }} {{ resumo.sem_estoque === 1 ? 'está' : 'estão' }} zerado{{ resumo.sem_estoque === 1 ? '' : 's' }}.</strong></span>
                </div>
            </div>
            <div class="text-xs text-amber-700 bg-amber-100 px-2 py-1 rounded">
                Total: {{ resumo.total_itens }} {{ resumo.total_itens === 1 ? 'item ativo' : 'itens ativos' }}
            </div>
        </div>

        <!-- F4.2 · Filtros colapsáveis em mobile (busca sempre visível) -->
        <MobileFilters cols="sm:grid-cols-2 lg:grid-cols-3">
            <template #always>
                <input v-model="filtros.search" @keyup.enter="filtrar"
                       placeholder="Buscar por código ou nome…" class="form-input">
            </template>
            <select v-model="filtros.tipo" @change="filtrar" class="form-select">
                <option value="">Todos os tipos</option>
                <option value="insumo">Insumo</option>
                <option value="medicamento">Medicamento</option>
                <option value="racao">Ração</option>
                <option value="ferramenta">Ferramenta</option>
                <option value="peca">Peça</option>
                <option value="combustivel">Combustível</option>
                <option value="material">Material</option>
            </select>
            <select v-model="filtros.status" @change="filtrar" class="form-select">
                <option value="ativos">Ativos</option>
                <option value="inativos">Inativos</option>
            </select>
        </MobileFilters>

        <DataTable
            :columns="[
                { key: 'codigo', label: 'Código' },
                { key: 'nome', label: 'Nome' },
                { key: 'tipo', label: 'Tipo' },
                { key: 'saldo', label: 'Saldo', align: 'right' },
                { key: 'unidade', label: 'UN' },
                { key: 'estoque_minimo', label: 'Mínimo', align: 'right' },
                { key: 'is_active', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="items.data"
        >
            <template #cell-tipo="{ row }"><span class="badge-slate">{{ tipoLabel[row.tipo] ?? row.tipo }}</span></template>
            <template #cell-saldo="{ row }">
                <span :class="row.abaixo_minimo ? 'text-red-700 font-semibold' : ''">
                    {{ Number(row.saldo).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }}
                </span>
            </template>
            <template #cell-estoque_minimo="{ row }">
                {{ Number(row.estoque_minimo).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }}
            </template>
            <template #cell-is_active="{ row }">
                <button @click="toggle(row)" :class="row.is_active ? 'badge-toggle-green' : 'badge-toggle-slate'">
                    {{ row.is_active ? 'Ativo' : 'Inativo' }}
                </button>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-1 justify-end">
                    <Link :href="route('admin.estoque.itens.edit', row.id)" class="inline-flex">
                        <ActionIcon type="edit" title="Editar item" />
                    </Link>
                    <ActionIcon type="delete" title="Excluir item" @click="confirmDelete = row" />
                </div>
            </template>
        </DataTable>

        <div v-if="items.links" class="mt-4 flex gap-2 flex-wrap justify-end">
            <Link v-for="link in items.links" :key="link.label"
                  :href="link.url ?? '#'"
                  v-html="link.label"
                  :class="['btn-outline btn-sm', link.active ? '!bg-macaybas-primary !text-white !border-transparent' : '', !link.url ? 'opacity-40 pointer-events-none' : '']" />
        </div>

        <ConfirmModal :show="!!confirmDelete" title="Excluir item"
                      :message="`Excluir ${confirmDelete?.nome}? Se houver movimentações, o item será apenas desativado.`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />

        <BarcodeScanner v-if="showScanner"
                        @detected="onBarcodeDetectedFromList"
                        @close="showScanner = false" />

        <!-- Modal: código não encontrado → oferece cadastrar agora -->
        <Teleport to="body">
            <div v-if="naoEncontrado" class="fixed inset-0 z-[55] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="naoEncontrado = null"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <div class="flex items-start gap-3 mb-4">
                        <div class="h-10 w-10 rounded-full bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center flex-shrink-0">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Produto novo</h3>
                            <p class="text-sm text-slate-500">Código <span class="font-mono">{{ naoEncontrado.code }}</span> ainda não está na nossa base.</p>
                        </div>
                    </div>
                    <p class="text-sm text-slate-700 mb-4">
                        Vamos cadastrar agora? Seu cadastro fortalece a base interna da fazenda —
                        das próximas vezes que alguém escanear este mesmo código, o sistema reconhecerá
                        automaticamente sem precisar consultar fontes externas.
                    </p>
                    <div class="flex flex-col sm:flex-row justify-end gap-2">
                        <button @click="naoEncontrado = null" class="btn-outline">Cancelar</button>
                        <button @click="cadastrarNovoComCodigo" class="btn-primary flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            Cadastrar produto
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
