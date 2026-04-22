<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import { dataBR } from '@/utils/format.js';
import { tableActionsFor, isLoteSpecies, EVENT_CATALOG, allowedEventsFor } from '@/utils/animalProfile.js';

const props = defineProps({ animals: Object, filters: Object, species: Array, lots: Array, categorias: Array });
const filtros = reactive({
    search: props.filters?.search ?? '',
    species_id: props.filters?.species_id ?? '',
    lot_id: props.filters?.lot_id ?? '',
    status: props.filters?.status ?? '',
    categoria: props.filters?.categoria ?? '',
});
const confirmDelete = ref(null);
const pesagem = ref(null); // animal em processo de pesagem (ou evento rápido equivalente)
const pesagemTipo = ref('pesagem'); // tipo selecionado (pesagem, ordenha, biometria, etc.)
const pesagemForm = useForm({
    tipo: 'pesagem', data: '', peso: '', observacoes: '',
    litros: '', qtd_ovos: '',
});

// Retorna ações contextuais da espécie do animal (respeita peixe≠pesagem etc.)
function acoesDoAnimal(row) {
    return tableActionsFor(row.species, row.categoria);
}

function filtrar() { router.get(route('admin.rebanho.animais.index'), filtros, { preserveState: true, replace: true }); }
function doDelete() {
    router.delete(route('admin.rebanho.animais.destroy', confirmDelete.value.id), {
        onSuccess: () => (confirmDelete.value = null),
    });
}

function abrirEventoRapido(row, tipo = 'pesagem') {
    pesagem.value = row;
    pesagemTipo.value = tipo;
    pesagemForm.reset();
    pesagemForm.tipo = tipo;
    pesagemForm.data = new Date().toISOString().slice(0, 10);
}
function confirmarPesagem() {
    pesagemForm.post(route('admin.rebanho.animais.eventos.store', pesagem.value.id), {
        preserveScroll: true, only: ['animals', 'flash'],
        onSuccess: () => { pesagem.value = null; pesagemForm.reset(); },
    });
}

function labelEvento(tipo) { return EVENT_CATALOG[tipo]?.label || tipo; }

const statusBadge = (s) => ({
    ativo: 'badge-green', vendido: 'badge-blue', morto: 'badge-red',
    abatido: 'badge-slate', transferido: 'badge-yellow',
})[s] || 'badge-slate';

const categoriaBadge = (c) => ({
    leite: 'badge-blue',
    corte: 'badge-red',
    reproducao: 'badge-yellow',
    misto: 'badge-slate',
    pet: 'badge-green',
    servico: 'badge-slate',
})[c] || 'badge-slate';

const categoriaLabel = (c) => props.categorias.find(x => x.value === c)?.label || c || '—';
</script>

<template>
    <Head title="Rebanho — Animais" />
    <AdminLayout>
        <template #page-title>Rebanho</template>
        <PageHeader title="Animais" subtitle="Cadastro individual com histórico incremental de pesagens, vacinas e eventos">
            <template #actions>
                <Link :href="route('admin.rebanho.animais.create')" class="btn-primary">Novo animal</Link>
            </template>
        </PageHeader>

        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-5">
                <input v-model="filtros.search" @keyup.enter="filtrar" placeholder="Brinco ou nome" class="form-input">
                <select v-model="filtros.categoria" @change="filtrar" class="form-select">
                    <option value="">Todas as categorias</option>
                    <option v-for="c in categorias" :key="c.value" :value="c.value">{{ c.label }}</option>
                </select>
                <select v-model="filtros.species_id" @change="filtrar" class="form-select">
                    <option value="">Todas as espécies</option>
                    <option v-for="s in species" :key="s.id" :value="s.id">{{ s.nome }}</option>
                </select>
                <select v-model="filtros.lot_id" @change="filtrar" class="form-select">
                    <option value="">Todos os lotes</option>
                    <option v-for="l in lots" :key="l.id" :value="l.id">{{ l.nome }}</option>
                </select>
                <select v-model="filtros.status" @change="filtrar" class="form-select">
                    <option value="">Todos os status</option>
                    <option value="ativo">Ativo</option>
                    <option value="vendido">Vendido</option>
                    <option value="morto">Morto</option>
                    <option value="abatido">Abatido</option>
                    <option value="transferido">Transferido</option>
                </select>
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'foto', label: '' },
                { key: 'identificacao', label: 'Brinco' },
                { key: 'nome', label: 'Nome' },
                { key: 'categoria', label: 'Categoria' },
                { key: 'species', label: 'Espécie' },
                { key: 'sexo', label: 'Sexo' },
                { key: 'peso_atual', label: 'Peso atual', align: 'right' },
                { key: 'lot', label: 'Lote' },
                { key: 'status', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="animals.data"
        >
            <template #cell-foto="{ row }">
                <img v-if="row.photo_url" :src="row.photo_url"
                     class="h-9 w-9 rounded-full object-cover ring-1 ring-slate-200">
                <div v-else class="h-9 w-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            </template>
            <template #cell-species="{ row }">{{ row.species?.nome ?? '—' }}</template>
            <template #cell-lot="{ row }">{{ row.lot?.nome ?? '—' }}</template>
            <template #cell-categoria="{ row }">
                <span v-if="row.categoria" :class="categoriaBadge(row.categoria)">{{ categoriaLabel(row.categoria) }}</span>
                <span v-else class="text-slate-400">—</span>
            </template>
            <template #cell-peso_atual="{ row }">
                <span v-if="row.peso_atual" class="font-mono text-slate-700">{{ Number(row.peso_atual).toLocaleString('pt-BR', { minimumFractionDigits: 1 }) }} kg</span>
                <span v-else class="text-slate-400">—</span>
            </template>
            <template #cell-status="{ row }"><span :class="statusBadge(row.status)">{{ row.status }}</span></template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-1 justify-end">
                    <!-- Ações contextuais: só aparecem se fazem sentido para a espécie/categoria -->
                    <ActionIcon v-for="ac in acoesDoAnimal(row)" :key="ac.key"
                                :type="ac.icon" :title="ac.title"
                                @click="abrirEventoRapido(row, ac.key)" />
                    <Link :href="route('admin.rebanho.animais.show', row.id)" class="inline-flex">
                        <ActionIcon type="history" title="Histórico completo" />
                    </Link>
                    <Link :href="route('admin.rebanho.animais.edit', row.id)" class="inline-flex">
                        <ActionIcon type="edit" title="Editar animal" />
                    </Link>
                    <ActionIcon type="delete" title="Excluir animal" @click="confirmDelete = row" />
                </div>
            </template>
        </DataTable>

        <ConfirmModal :show="!!confirmDelete" title="Excluir animal"
                      :message="`Excluir ${confirmDelete?.identificacao}?`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />

        <!-- Modal: Evento rápido contextual (pesagem / ordenha / biometria / postura) -->
        <Teleport to="body">
            <div v-if="pesagem" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="pesagem = null"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <div class="flex items-start gap-4 mb-4">
                        <img v-if="pesagem.photo_url" :src="pesagem.photo_url" class="h-14 w-14 rounded-lg object-cover ring-1 ring-slate-200 flex-shrink-0">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Registrar {{ labelEvento(pesagemTipo).toLowerCase() }}</h3>
                            <p class="text-sm text-slate-500">
                                <span v-if="pesagem.species?.gestao === 'lote'">Lote</span>
                                <span v-else>Brinco</span>
                                <strong> {{ pesagem.identificacao }}</strong><span v-if="pesagem.nome"> · {{ pesagem.nome }}</span>
                            </p>
                            <p v-if="pesagemTipo === 'pesagem' && pesagem.peso_atual" class="text-xs text-slate-400 mt-1">Peso anterior: {{ Number(pesagem.peso_atual).toLocaleString('pt-BR', { minimumFractionDigits: 1 }) }} kg</p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <InputLabel :value="`Data da ${labelEvento(pesagemTipo).toLowerCase()} *`" />
                            <InputDate v-model="pesagemForm.data" :max="new Date().toISOString().slice(0,10)" required />
                            <p v-if="pesagemForm.errors.data" class="text-xs text-red-600 mt-1">{{ pesagemForm.errors.data }}</p>
                        </div>

                        <!-- Pesagem ou biometria amostral -->
                        <div v-if="pesagemTipo === 'pesagem' || pesagemTipo === 'biometria_amostral'">
                            <InputLabel :value="pesagemTipo === 'biometria_amostral' ? 'Peso médio do lote (kg) *' : 'Peso (kg) *'" />
                            <input type="number" step="0.01" min="0" max="9999.99"
                                   v-model="pesagemForm.peso" class="form-input"
                                   placeholder="Ex: 420.5" required>
                            <p v-if="pesagemForm.errors.peso" class="text-xs text-red-600 mt-1">{{ pesagemForm.errors.peso }}</p>
                        </div>

                        <!-- Ordenha: armazena volume em 'peso' pra reaproveitar coluna -->
                        <div v-if="pesagemTipo === 'ordenha'">
                            <InputLabel value="Volume (litros) *" />
                            <input type="number" step="0.01" min="0" max="999.99"
                                   v-model="pesagemForm.peso" class="form-input"
                                   placeholder="Ex: 12.5" required>
                            <p class="text-xs text-slate-400 mt-1">Total ordenhado no dia (soma manhã + tarde).</p>
                        </div>

                        <!-- Postura de ovos -->
                        <div v-if="pesagemTipo === 'postura_diaria'">
                            <InputLabel value="Ovos coletados *" />
                            <input type="number" step="1" min="0"
                                   v-model="pesagemForm.peso" class="form-input"
                                   placeholder="Ex: 45" required>
                            <p class="text-xs text-slate-400 mt-1">Quantidade total de ovos do dia.</p>
                        </div>

                        <div>
                            <InputLabel value="Observações" />
                            <textarea v-model="pesagemForm.observacoes" rows="2" class="form-textarea"
                                      :placeholder="pesagemTipo === 'pesagem' ? 'Ex: condição corporal 3.5, escore de 1-5...' : ''"></textarea>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="pesagem = null" class="btn-outline">Cancelar</button>
                        <button @click="confirmarPesagem" :disabled="pesagemForm.processing" class="btn-primary">
                            {{ pesagemForm.processing ? 'Salvando...' : `Registrar ${labelEvento(pesagemTipo).toLowerCase()}` }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
