<script setup>
import { reactive, ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMoney from '@/Components/InputMoney.vue';
import { dataBR, brl } from '@/utils/format.js';
import { tableActionsFor, EVENT_CATALOG, vendaConfigFor } from '@/utils/animalProfile.js';

const props = defineProps({ animals: Object, filters: Object, species: Array, lots: Array, categorias: Array, partners: Array });
const filtros = reactive({
    search: props.filters?.search ?? '',
    species_id: props.filters?.species_id ?? '',
    lot_id: props.filters?.lot_id ?? '',
    status: props.filters?.status ?? '',
    categoria: props.filters?.categoria ?? '',
});

function filtrar() { router.get(route('admin.rebanho.animais.index'), filtros, { preserveState: true, replace: true }); }

const statusBadge = (s) => ({ ativo: 'badge-green', vendido: 'badge-blue', morto: 'badge-red', abatido: 'badge-slate', transferido: 'badge-yellow' })[s] || 'badge-slate';
const categoriaBadge = (c) => ({ leite: 'badge-blue', corte: 'badge-red', reproducao: 'badge-yellow', misto: 'badge-slate', pet: 'badge-green', servico: 'badge-slate' })[c] || 'badge-slate';
const categoriaLabel = (c) => props.categorias.find(x => x.value === c)?.label || c || '—';
const labelEvento = (tipo) => EVENT_CATALOG[tipo]?.label || tipo;
const acoesDoAnimal = (row) => tableActionsFor(row.species, row.categoria);

// =========================================================================
// Seleção múltipla para ações em lote (Vender, Medicar, Transferir, etc.)
// =========================================================================
const selecionados = ref(new Set()); // Set<id>
const selecionadosAtivos = computed(() =>
    (props.animals?.data || []).filter(a => selecionados.value.has(a.id) && a.status === 'ativo')
);
const especieSelecionada = computed(() => {
    const primeiro = selecionadosAtivos.value[0];
    return primeiro?.species ?? null;
});
const misturouEspecies = computed(() => {
    if (selecionadosAtivos.value.length <= 1) return false;
    const ids = new Set(selecionadosAtivos.value.map(a => a.species?.id));
    return ids.size > 1;
});

function toggle(row) {
    if (row.status !== 'ativo') return;
    const next = new Set(selecionados.value);
    // Restrição de mesma espécie — se tentar adicionar de outra espécie, troca tudo
    if (!next.has(row.id) && selecionadosAtivos.value.length && especieSelecionada.value?.id !== row.species?.id) {
        next.clear();
    }
    next.has(row.id) ? next.delete(row.id) : next.add(row.id);
    selecionados.value = next;
}
function selecionarTodos() {
    // Seleciona todos os ATIVOS da mesma espécie do primeiro item da página
    const ativos = (props.animals?.data || []).filter(a => a.status === 'ativo');
    if (!ativos.length) return;
    const primeiraEspecieId = ativos[0].species?.id;
    selecionados.value = new Set(
        ativos.filter(a => a.species?.id === primeiraEspecieId).map(a => a.id)
    );
}
function limparSelecao() { selecionados.value = new Set(); }
const todosSelecionados = computed(() => {
    const ativos = (props.animals?.data || []).filter(a => a.status === 'ativo');
    return ativos.length > 0 && ativos.every(a => selecionados.value.has(a.id));
});

// =========================================================================
// Ação rápida: pesagem / vacinação / ordenha / biometria / postura
// =========================================================================
const eventoRapido = ref(null);
const eventoTipo = ref('pesagem');
const eventoForm = useForm({
    tipo: 'pesagem', data: '', peso: '', vacina: '', medicamento: '',
    dose: '', via_aplicacao: '', observacoes: '',
});

function abrirEventoRapido(row, tipo = 'pesagem') {
    eventoRapido.value = row;
    eventoTipo.value = tipo;
    eventoForm.reset();
    eventoForm.tipo = tipo;
    eventoForm.data = new Date().toISOString().slice(0, 10);
}
function confirmarEventoRapido() {
    eventoForm.post(route('admin.rebanho.animais.eventos.store', eventoRapido.value.id), {
        preserveScroll: true, only: ['animals', 'flash'],
        onSuccess: () => { eventoRapido.value = null; eventoForm.reset(); },
    });
}

// =========================================================================
// Venda em lote contextual (arroba/kg/unidade/cabeça)
// =========================================================================
const vendaAberta = ref(false);
const vendaForm = useForm({
    animal_ids: [], data: '', partner_id: null, observacoes: '',
    unidade: 'cabeca', quantidade: '', peso_medio: '', valor_unitario: '',
});
const vendaCfg = computed(() => {
    if (!especieSelecionada.value) return null;
    const categ = selecionadosAtivos.value[0]?.categoria;
    return vendaConfigFor(especieSelecionada.value, categ);
});

function abrirVenda() {
    if (!selecionadosAtivos.value.length || misturouEspecies.value) return;
    vendaForm.reset();
    vendaForm.animal_ids = selecionadosAtivos.value.map(a => a.id);
    vendaForm.data = new Date().toISOString().slice(0, 10);
    vendaForm.unidade = vendaCfg.value?.unidadePadrao || 'cabeca';
    vendaAberta.value = true;
}

// Cálculos contextuais:
// - Quando unidade tem kg definido (arroba=15kg, kg=1kg) e pergunta peso médio → quantidade total em unidades vem do peso_medio × n_cabecas
// - Quando não (cabeça, unidade de peixe, dúzia) → usuário informa quantidade direta
const nCabecas = computed(() => selecionadosAtivos.value.length);
const kgPorUnidade = computed(() => vendaCfg.value?.kgPorUnidade?.[vendaForm.unidade] || null);
const perguntaPesoMedio = computed(() => vendaCfg.value?.perguntaPesoMedio && kgPorUnidade.value);

const quantidadeCalculada = computed(() => {
    if (perguntaPesoMedio.value) {
        const pm = parseFloat(vendaForm.peso_medio) || 0;
        return (pm * nCabecas.value) / kgPorUnidade.value;
    }
    if (vendaForm.unidade === 'cabeca') return nCabecas.value;
    return parseFloat(vendaForm.quantidade) || 0;
});
const totalVenda = computed(() => {
    const vu = parseFloat(vendaForm.valor_unitario) || 0;
    return quantidadeCalculada.value * vu;
});
const pesoTotalKg = computed(() => {
    if (!kgPorUnidade.value) return null;
    return quantidadeCalculada.value * kgPorUnidade.value;
});
const valorPorCabeca = computed(() => nCabecas.value ? totalVenda.value / nCabecas.value : 0);

function confirmarVenda() {
    vendaForm.transform((d) => ({
        ...d,
        quantidade: quantidadeCalculada.value,
        valor_total: totalVenda.value,
    })).post(route('admin.rebanho.animais.vender-lote'), {
        preserveScroll: true, only: ['animals', 'flash'],
        onSuccess: () => { vendaAberta.value = false; limparSelecao(); vendaForm.reset(); },
    });
}

// Excluir (continua individual)
const confirmDelete = ref(null);
function doDelete() {
    router.delete(route('admin.rebanho.animais.destroy', confirmDelete.value.id), {
        onSuccess: () => (confirmDelete.value = null),
    });
}
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

        <!-- Filtros -->
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

        <!-- Barra sticky de ações em lote (aparece quando há seleção) -->
        <transition
            enter-active-class="transition-all duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2">
            <div v-if="selecionadosAtivos.length"
                 class="sticky top-16 z-20 mb-4 p-3 rounded-xl bg-macaybas-primary-900 text-white shadow-lg flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <span class="h-8 w-8 rounded-full bg-white/20 flex items-center justify-center text-sm font-bold flex-shrink-0">
                        {{ selecionadosAtivos.length }}
                    </span>
                    <span class="text-sm">
                        <strong>{{ selecionadosAtivos.length }}</strong> {{ especieSelecionada?.nome?.toLowerCase() || 'animal' }}{{ selecionadosAtivos.length === 1 ? '' : 's' }} selecionado{{ selecionadosAtivos.length === 1 ? '' : 's' }}
                    </span>
                    <span v-if="misturouEspecies" class="text-xs bg-amber-400/20 text-amber-200 px-2 py-0.5 rounded">Mistura de espécies — não pode vender</span>
                </div>
                <button @click="limparSelecao" class="text-sm text-white/70 hover:text-white">Limpar</button>
                <button @click="abrirVenda" :disabled="misturouEspecies"
                        class="btn bg-emerald-600 hover:bg-emerald-700 text-white focus:ring-emerald-500 gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
                    Vender selecionados
                </button>
            </div>
        </transition>

        <!-- Tabela custom com checkbox (desktop) -->
        <div class="hidden lg:block card overflow-hidden">
            <table class="table-base">
                <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" :checked="todosSelecionados" @change="todosSelecionados ? limparSelecao() : selecionarTodos()"
                                   class="rounded border-slate-300" title="Selecionar todos os ativos desta página">
                        </th>
                        <th></th>
                        <th>Brinco</th>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Espécie</th>
                        <th>Sexo</th>
                        <th class="text-right">Peso atual</th>
                        <th>Lote</th>
                        <th>Status</th>
                        <th class="text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="!animals.data.length">
                        <td colspan="11" class="text-center text-slate-500 py-10">Nenhum animal cadastrado.</td>
                    </tr>
                    <tr v-for="row in animals.data" :key="row.id"
                        :class="[selecionados.has(row.id) ? 'bg-macaybas-primary-50' : '']">
                        <td>
                            <input type="checkbox" :checked="selecionados.has(row.id)" @change="toggle(row)"
                                   :disabled="row.status !== 'ativo'"
                                   class="rounded border-slate-300 disabled:opacity-30">
                        </td>
                        <td>
                            <img v-if="row.photo_url" :src="row.photo_url" class="h-9 w-9 rounded-full object-cover ring-1 ring-slate-200">
                            <div v-else class="h-9 w-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-xs">—</div>
                        </td>
                        <td class="font-medium">{{ row.identificacao }}</td>
                        <td>{{ row.nome || '—' }}</td>
                        <td>
                            <span v-if="row.categoria" :class="categoriaBadge(row.categoria)">{{ categoriaLabel(row.categoria) }}</span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td>{{ row.species?.nome || '—' }}</td>
                        <td>{{ row.sexo }}</td>
                        <td class="text-right font-mono">{{ row.peso_atual ? Number(row.peso_atual).toLocaleString('pt-BR', {minimumFractionDigits: 1}) + ' kg' : '—' }}</td>
                        <td>{{ row.lot?.nome || '—' }}</td>
                        <td><span :class="statusBadge(row.status)">{{ row.status }}</span></td>
                        <td class="text-right">
                            <div class="flex gap-1 justify-end">
                                <ActionIcon v-for="ac in acoesDoAnimal(row)" :key="ac.key"
                                            :type="ac.icon" :title="ac.title" @click="abrirEventoRapido(row, ac.key)" />
                                <Link :href="route('admin.rebanho.animais.show', row.id)" class="inline-flex">
                                    <ActionIcon type="history" title="Histórico completo" />
                                </Link>
                                <Link :href="route('admin.rebanho.animais.edit', row.id)" class="inline-flex">
                                    <ActionIcon type="edit" title="Editar animal" />
                                </Link>
                                <ActionIcon type="delete" title="Excluir animal" @click="confirmDelete = row" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Cards mobile -->
        <div class="lg:hidden space-y-3">
            <div v-for="row in animals.data" :key="row.id"
                 :class="[selecionados.has(row.id) ? 'ring-2 ring-macaybas-primary-300 bg-macaybas-primary-50' : 'bg-white ring-1 ring-slate-200']"
                 class="rounded-xl shadow-sm p-4">
                <div class="flex items-start gap-3">
                    <input type="checkbox" :checked="selecionados.has(row.id)" @change="toggle(row)"
                           :disabled="row.status !== 'ativo'"
                           class="mt-1 rounded border-slate-300 disabled:opacity-30">
                    <img v-if="row.photo_url" :src="row.photo_url" class="h-12 w-12 rounded-lg object-cover ring-1 ring-slate-200 flex-shrink-0">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <div class="font-semibold text-slate-900 truncate">{{ row.identificacao }}<span v-if="row.nome" class="font-normal text-slate-500"> · {{ row.nome }}</span></div>
                            <span :class="statusBadge(row.status)" class="flex-shrink-0">{{ row.status }}</span>
                        </div>
                        <div class="text-xs text-slate-500 mt-0.5">{{ row.species?.nome }} · {{ row.sexo }}<span v-if="row.lot"> · lote {{ row.lot.nome }}</span></div>
                        <div v-if="row.peso_atual" class="text-xs text-slate-600 mt-1">{{ Number(row.peso_atual).toLocaleString('pt-BR', {minimumFractionDigits: 1}) }} kg</div>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex flex-wrap gap-2 justify-end">
                    <ActionIcon v-for="ac in acoesDoAnimal(row)" :key="ac.key"
                                :type="ac.icon" :title="ac.title" @click="abrirEventoRapido(row, ac.key)" />
                    <Link :href="route('admin.rebanho.animais.show', row.id)" class="inline-flex">
                        <ActionIcon type="history" title="Histórico" />
                    </Link>
                    <Link :href="route('admin.rebanho.animais.edit', row.id)" class="inline-flex">
                        <ActionIcon type="edit" title="Editar" />
                    </Link>
                    <ActionIcon type="delete" title="Excluir" @click="confirmDelete = row" />
                </div>
            </div>
        </div>

        <ConfirmModal :show="!!confirmDelete" title="Excluir animal"
                      :message="`Excluir ${confirmDelete?.identificacao}?`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />

        <!-- Modal: evento rápido (pesagem/vacinação/ordenha/biometria/postura) -->
        <Teleport to="body">
            <div v-if="eventoRapido" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="eventoRapido = null"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-slate-900 mb-1">Registrar {{ labelEvento(eventoTipo).toLowerCase() }}</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        <span v-if="eventoRapido.species?.gestao === 'lote'">Lote</span>
                        <span v-else>Brinco</span>
                        <strong> {{ eventoRapido.identificacao }}</strong>
                    </p>
                    <div class="space-y-3">
                        <div>
                            <InputLabel :value="`Data *`" />
                            <InputDate v-model="eventoForm.data" :max="new Date().toISOString().slice(0,10)" required />
                        </div>
                        <div v-if="eventoTipo === 'pesagem' || eventoTipo === 'biometria_amostral'">
                            <InputLabel :value="eventoTipo === 'biometria_amostral' ? 'Peso médio do lote (kg) *' : 'Peso (kg) *'" />
                            <input type="number" step="0.01" min="0" v-model="eventoForm.peso" class="form-input" required>
                        </div>
                        <div v-if="eventoTipo === 'ordenha'">
                            <InputLabel value="Volume (litros) *" />
                            <input type="number" step="0.01" min="0" v-model="eventoForm.peso" class="form-input" required>
                        </div>
                        <div v-if="eventoTipo === 'postura_diaria'">
                            <InputLabel value="Ovos coletados *" />
                            <input type="number" step="1" min="0" v-model="eventoForm.peso" class="form-input" required>
                        </div>
                        <template v-if="eventoTipo === 'vacinacao'">
                            <div>
                                <InputLabel value="Vacina *" />
                                <input v-model="eventoForm.vacina" class="form-input" placeholder="Ex: Febre Aftosa" required>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div><InputLabel value="Dose (ml)" /><input type="number" step="0.01" v-model="eventoForm.dose" class="form-input"></div>
                                <div><InputLabel value="Via" /><input v-model="eventoForm.via_aplicacao" class="form-input" placeholder="subcutânea"></div>
                            </div>
                        </template>
                        <div>
                            <InputLabel value="Observações" />
                            <textarea v-model="eventoForm.observacoes" rows="2" class="form-textarea"></textarea>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="eventoRapido = null" class="btn-outline">Cancelar</button>
                        <button @click="confirmarEventoRapido" :disabled="eventoForm.processing" class="btn-primary">
                            {{ eventoForm.processing ? 'Salvando...' : 'Registrar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal: venda em lote contextual (arroba/kg/unidade/cabeça) -->
        <Teleport to="body">
            <div v-if="vendaAberta" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="vendaAberta = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-xl w-full p-6 max-h-[90vh] overflow-y-auto">
                    <h3 class="text-lg font-semibold text-slate-900 mb-1">
                        Venda — {{ nCabecas }} {{ especieSelecionada?.nome?.toLowerCase() }}{{ nCabecas === 1 ? '' : 's' }}
                    </h3>
                    <p class="text-sm text-slate-500 mb-4">{{ vendaCfg?.label }}</p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Data da venda *" />
                            <InputDate v-model="vendaForm.data" :max="new Date().toISOString().slice(0,10)" required />
                        </div>
                        <div>
                            <InputLabel value="Comprador" />
                            <select v-model="vendaForm.partner_id" class="form-select">
                                <option :value="null">—</option>
                                <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.nome }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Unidade de venda *" />
                            <select v-model="vendaForm.unidade" class="form-select">
                                <option v-for="u in vendaCfg?.unidades || []" :key="u.v" :value="u.v">{{ u.l }}</option>
                            </select>
                        </div>

                        <!-- Campo peso médio por cabeça (bovino, suíno, ovino) -->
                        <div v-if="perguntaPesoMedio">
                            <InputLabel value="Peso médio por cabeça (kg) *" />
                            <input type="number" step="0.01" min="0" v-model="vendaForm.peso_medio" class="form-input" placeholder="Ex: 480">
                            <p v-if="vendaForm.peso_medio" class="text-xs text-slate-500 mt-1">
                                Total estimado: {{ (Number(vendaForm.peso_medio) * nCabecas).toLocaleString('pt-BR', {maximumFractionDigits: 2}) }} kg
                            </p>
                        </div>
                        <!-- Campo quantidade direta (peixe, aves, ovos) -->
                        <div v-else-if="vendaForm.unidade !== 'cabeca'">
                            <InputLabel :value="`Quantidade (${vendaForm.unidade}) *`" />
                            <input type="number" step="0.001" min="0" v-model="vendaForm.quantidade" class="form-input">
                        </div>
                        <!-- Venda por cabeça fixa: só precisa valor por cabeça -->
                        <div v-else>
                            <div class="form-input bg-slate-50 text-slate-600 flex items-center">
                                {{ nCabecas }} cabeça{{ nCabecas === 1 ? '' : 's' }} (da seleção)
                            </div>
                        </div>

                        <div>
                            <InputLabel :value="`Valor por ${vendaForm.unidade} *`" />
                            <InputMoney v-model="vendaForm.valor_unitario" />
                        </div>

                        <!-- Resumo calculado -->
                        <div class="sm:col-span-2 p-4 rounded-lg bg-emerald-50 border border-emerald-200">
                            <div class="text-xs uppercase tracking-wider text-emerald-700 mb-2">Resumo da venda</div>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>Quantidade:</div>
                                <div class="text-right font-mono">{{ Number(quantidadeCalculada).toLocaleString('pt-BR', {maximumFractionDigits: 3}) }} {{ vendaForm.unidade }}</div>
                                <template v-if="pesoTotalKg">
                                    <div>Peso total:</div>
                                    <div class="text-right font-mono">{{ Number(pesoTotalKg).toLocaleString('pt-BR', {maximumFractionDigits: 2}) }} kg</div>
                                </template>
                                <div>Valor por cabeça:</div>
                                <div class="text-right font-mono">{{ brl(valorPorCabeca) }}</div>
                                <div class="font-semibold text-emerald-900">Total:</div>
                                <div class="text-right font-mono font-bold text-emerald-900">{{ brl(totalVenda) }}</div>
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <InputLabel value="Observações" />
                            <textarea v-model="vendaForm.observacoes" rows="2" class="form-textarea"></textarea>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="vendaAberta = false" class="btn-outline">Cancelar</button>
                        <button @click="confirmarVenda" :disabled="vendaForm.processing || !totalVenda"
                                class="btn-primary bg-emerald-600 hover:bg-emerald-700">
                            {{ vendaForm.processing ? 'Salvando...' : `Confirmar venda de ${brl(totalVenda)}` }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
