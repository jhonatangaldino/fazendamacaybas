<script setup>
import { computed, reactive, ref, watch, onMounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import MobileFilters from '@/Components/MobileFilters.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import { dataBR, brl } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({
    tasks: Object,
    filters: Object,
    employees: Array,
    linkables: Object,
    resumo: { type: Object, default: () => ({ pendentes: 0, em_andamento: 0, atrasadas: 0, hoje: 0, concluidas_hoje: 0 }) },
});

// Atalhos para filtrar pelos cards de resumo
function filtrarPorStatus(status) {
    filtros.status = status;
    filtrar();
}
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
    // F3 · Vínculo polimórfico obrigatório
    related_type: '',
    related_id: null,
});

// ═════════════════════════════════════════════════════════════════════
// F3 · UX anti-erro — TAREFAS
//
// Regra de ouro: "Toda tarefa deve ter dono e propósito."
//
//   1. responsável (ao menos 1 employee em assignees)
//   2. vínculo polimórfico (related_type + related_id) a uma entidade
//      real do ecossistema (Animal, Veículo, Parceiro, Plantio, etc.)
//
// MAPEAMENTO MÓDULO → TIPOS DE VÍNCULO
//   O usuário escolhe o módulo da tarefa e a UI oferece SÓ os tipos
//   de entidade que fazem sentido naquele contexto:
//
//     rebanho    → Animal
//     agricola   → Plantio / Talhão
//     estoque    → Item de estoque
//     maquinas   → Veículo
//     financeiro → Lançamento financeiro / Parceiro
//     geral      → Parceiro (admin/gestão com terceiros)
//
// Backend aceita related_type/_id nullable — retrocompat com tarefas
// legadas e automações. A obrigatoriedade é imposta na UI.
// ═════════════════════════════════════════════════════════════════════

/** Rótulo humano de cada FQCN. */
const RELATED_LABEL = {
    'App\\Models\\Partner': 'Parceiro',
    'App\\Models\\Livestock\\Animal': 'Animal',
    'App\\Models\\Vehicle\\Vehicle': 'Veículo / máquina',
    'App\\Models\\Financial\\FinancialTransaction': 'Lançamento financeiro',
    'App\\Models\\Agricultural\\Planting': 'Plantio',
    'App\\Models\\Agricultural\\Field': 'Talhão',
    'App\\Models\\Stock\\StockItem': 'Item de estoque',
};

/** Módulo → tipos de entidade aceitáveis como vínculo. */
const RELATED_POR_MODULO = {
    rebanho:    ['App\\Models\\Livestock\\Animal'],
    agricola:   ['App\\Models\\Agricultural\\Planting', 'App\\Models\\Agricultural\\Field'],
    estoque:    ['App\\Models\\Stock\\StockItem'],
    maquinas:   ['App\\Models\\Vehicle\\Vehicle'],
    financeiro: ['App\\Models\\Financial\\FinancialTransaction', 'App\\Models\\Partner'],
    geral:      ['App\\Models\\Partner'], // tarefa administrativa / gestão
};

/** Card contextual por módulo — explica o propósito. */
const DICA_POR_MODULO = {
    rebanho: {
        titulo: 'Tarefa de REBANHO',
        texto: 'Atividade relacionada a um animal específico (pesagem, vacina, tratamento, transferência de lote). Vincule ao animal-alvo para aparecer no histórico dele.',
        tone: 'emerald',
    },
    agricola: {
        titulo: 'Tarefa AGRÍCOLA',
        texto: 'Atividade de plantio/colheita/manejo (aplicação de defensivo, irrigação, capina). Vincule ao plantio (se já iniciado) ou ao talhão.',
        tone: 'emerald',
    },
    estoque: {
        titulo: 'Tarefa de ESTOQUE',
        texto: 'Conferência, inventário, ou alerta de item. Vincule ao item de estoque específico (ex.: "comprar mais ração antes do fim do mês").',
        tone: 'slate',
    },
    maquinas: {
        titulo: 'Tarefa de MÁQUINAS',
        texto: 'Manutenção preventiva, abastecimento, conserto. Vincule ao veículo ou implemento específico — assim a tarefa aparece no histórico da máquina.',
        tone: 'amber',
    },
    financeiro: {
        titulo: 'Tarefa FINANCEIRA',
        texto: 'Cobrança, pagamento, conciliação, follow-up bancário. Vincule ao lançamento financeiro (ex.: "cobrar este boleto") ou ao parceiro (ex.: "renegociar com Sr. João").',
        tone: 'rose',
    },
    geral: {
        titulo: 'Tarefa GERAL',
        texto: 'Atividade administrativa ou gestão com terceiros. Vincule ao parceiro envolvido (fornecedor, cliente, prestador) para dar contexto e aparecer no histórico dele.',
        tone: 'indigo',
    },
};

/** Tipos disponíveis para o módulo atual. */
const relatedTypesDisponiveis = computed(
    () => RELATED_POR_MODULO[form.modulo] ?? RELATED_POR_MODULO.geral,
);

/** Lista de entidades para o related_type escolhido. */
const entidadesDoTipo = computed(() => {
    switch (form.related_type) {
        case 'App\\Models\\Partner':
            return (props.linkables?.partners ?? []).map((p) => ({
                id: p.id,
                label: `${p.nome}${p.pessoa === 'pj' ? ' (PJ)' : ' (PF)'}`,
            }));
        case 'App\\Models\\Livestock\\Animal':
            return (props.linkables?.animals ?? []).map((a) => ({
                id: a.id,
                label: `${a.identificacao}${a.nome ? ` — ${a.nome}` : ''}`,
            }));
        case 'App\\Models\\Vehicle\\Vehicle':
            return (props.linkables?.vehicles ?? []).map((v) => ({
                id: v.id,
                label: `${v.nome}${v.placa ? ` (${v.placa})` : ''} — ${v.tipo}`,
            }));
        case 'App\\Models\\Agricultural\\Planting':
            return (props.linkables?.plantings ?? []).map((p) => ({
                id: p.id,
                label: `${p.crop?.nome ?? 'Cultura'} @ ${p.field?.nome ?? 'talhão'}${p.data_plantio ? ` — ${p.data_plantio}` : ''}`,
            }));
        case 'App\\Models\\Agricultural\\Field':
            return (props.linkables?.fields ?? []).map((f) => ({
                id: f.id,
                label: f.nome,
            }));
        case 'App\\Models\\Financial\\FinancialTransaction':
            return (props.linkables?.transactions ?? []).map((t) => ({
                id: t.id,
                label: `${t.tipo === 'receita' ? '↑' : '↓'} ${t.descricao} — ${brl(t.valor)} (${t.data_vencimento ?? '?'})`,
            }));
        case 'App\\Models\\Stock\\StockItem':
            return (props.linkables?.stock_items ?? []).map((i) => ({
                id: i.id,
                label: `${i.nome} [${i.codigo}] — ${i.tipo}`,
            }));
        default:
            return [];
    }
});

const dicaModulo = computed(() => DICA_POR_MODULO[form.modulo] ?? null);
const dicaTone = computed(() => {
    const t = dicaModulo.value?.tone;
    return {
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-900',
        slate:   'border-slate-200 bg-slate-50 text-slate-700',
        amber:   'border-amber-200 bg-amber-50 text-amber-900',
        rose:    'border-rose-200 bg-rose-50 text-rose-900',
        indigo:  'border-indigo-200 bg-indigo-50 text-indigo-900',
    }[t] ?? 'border-blue-200 bg-blue-50 text-blue-900';
});

// ── Watchers: limpam vínculo incompatível ──────────────────────────

watch(
    () => form.modulo,
    (novo, antigo) => {
        if (novo === antigo) return;
        // Se o related_type atual não é válido para o novo módulo → limpa
        if (form.related_type && !relatedTypesDisponiveis.value.includes(form.related_type)) {
            form.related_type = '';
            form.related_id = null;
        }
    },
);

watch(
    () => form.related_type,
    (novo, antigo) => {
        if (novo === antigo) return;
        form.related_id = null; // troca de tipo zera a entidade escolhida
    },
);

/** Bloqueia submit sem responsável ou sem vínculo. */
const podeSalvar = computed(() => {
    if (form.processing) return false;
    if (!form.titulo) return false;
    if (form.assignees.length === 0) return false;           // ← "toda tarefa tem dono"
    if (!form.related_type || !form.related_id) return false; // ← "toda tarefa tem propósito"
    return true;
});

function novo() {
    form.reset();
    form.prioridade = 'media'; form.status = 'pendente'; form.modulo = 'geral';
    form.assignees = []; form.checklist_items = [];
    form.related_type = ''; form.related_id = null;
    editing.value = 'new';
}

// Hub v3 — auto-abrir form ao chegar pelo Hub com `?novo=1`
onMounted(() => {
    const qs = new URLSearchParams(window.location.search);
    if (qs.get('novo') === '1') {
        novo();
    }
});

function editar(t) {
    form.titulo = t.titulo; form.descricao = t.descricao ?? '';
    form.prioridade = t.prioridade; form.status = t.status;
    form.data_inicio = t.data_inicio ?? ''; form.data_vencimento = t.data_vencimento ?? '';
    form.modulo = t.modulo ?? 'geral';
    form.assignees = t.assignees.map((a) => a.id);
    form.checklist_items = [];
    form.related_type = t.related_type ?? '';
    form.related_id = t.related_id ?? null;
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
    const opts = { preserveScroll: true, only: ['tasks'], onSuccess: () => (editing.value = null) };
    if (editing.value === 'new') form.post(route('admin.tarefas.store'), opts);
    else form.put(route('admin.tarefas.update', editing.value), opts);
}
function concluir(t) { router.post(route('admin.tarefas.complete', t.id), {}, { preserveScroll: true, only: ['tasks'] }); }
function reabrir(t) { router.post(route('admin.tarefas.reopen', t.id), {}, { preserveScroll: true, only: ['tasks'] }); }
function toggleItem(item) { router.post(route('admin.tarefas.checklist.toggle', item.id), {}, { preserveScroll: true, only: ['tasks'] }); }
function doDelete() {
    router.delete(route('admin.tarefas.destroy', confirmDelete.value.id), {
        preserveScroll: true, only: ['tasks'],
        onSuccess: () => (confirmDelete.value = null),
    });
}

const prioridadeBadge = (p) => ({ baixa: 'badge-slate', media: 'badge-blue', alta: 'badge-yellow', urgente: 'badge-red' })[p] || 'badge-slate';
const statusBadge = (s) => ({ pendente: 'badge-yellow', em_andamento: 'badge-blue', concluida: 'badge-green', cancelada: 'badge-slate', atrasada: 'badge-red' })[s] || 'badge-slate';

// Label curto do vínculo para exibir no card da lista
function vinculoLabel(t) {
    if (!t.related_type || !t.related_id) return null;
    const tipo = RELATED_LABEL[t.related_type] ?? 'Entidade';
    return `${tipo} #${t.related_id}`;
}
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

        <!-- Resumo do dia — ajuda a decidir o que fazer AGORA.
             Cards clicáveis filtram a lista. Alerta vermelho quando há
             atrasadas (ação prioritária). -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <button
                @click="filtrarPorStatus('atrasada')"
                class="rounded-lg p-3 text-left border-2 transition-all"
                :class="Number(resumo.atrasadas) > 0
                    ? 'bg-red-50 border-red-300 hover:ring-2 hover:ring-red-200'
                    : 'bg-slate-50 border-slate-200 opacity-60 cursor-default'"
            >
                <div class="text-xs uppercase tracking-wider font-semibold"
                     :class="Number(resumo.atrasadas) > 0 ? 'text-red-700' : 'text-slate-500'">
                    ⚠ Atrasadas
                </div>
                <div class="text-2xl font-bold mt-1"
                     :class="Number(resumo.atrasadas) > 0 ? 'text-red-700' : 'text-slate-400'">
                    {{ resumo.atrasadas ?? 0 }}
                </div>
            </button>
            <button
                @click="filtros.status = 'pendente'; filtros.data_venc = 'hoje'; filtrar()"
                class="rounded-lg p-3 text-left border-2 bg-amber-50 border-amber-200 hover:ring-2 hover:ring-amber-200 transition-all"
            >
                <div class="text-xs uppercase tracking-wider font-semibold text-amber-700">
                    📅 Para hoje
                </div>
                <div class="text-2xl font-bold mt-1 text-amber-700">
                    {{ resumo.hoje ?? 0 }}
                </div>
            </button>
            <button
                @click="filtrarPorStatus('pendente')"
                class="rounded-lg p-3 text-left border-2 bg-white border-slate-200 hover:ring-2 hover:ring-slate-200 transition-all"
            >
                <div class="text-xs uppercase tracking-wider font-semibold text-slate-600">
                    📋 Pendentes
                </div>
                <div class="text-2xl font-bold mt-1 text-slate-700">
                    {{ resumo.pendentes ?? 0 }}
                </div>
            </button>
            <div class="rounded-lg p-3 text-left border-2 bg-emerald-50 border-emerald-200">
                <div class="text-xs uppercase tracking-wider font-semibold text-emerald-700">
                    ✓ Feitas hoje
                </div>
                <div class="text-2xl font-bold mt-1 text-emerald-700">
                    {{ resumo.concluidas_hoje ?? 0 }}
                </div>
            </div>
        </div>

        <div v-if="editing" class="mb-6 space-y-4">
            <!-- Dica contextual por módulo (F3 — UX guiada) -->
            <div
                v-if="dicaModulo"
                class="rounded-lg border px-4 py-3 text-sm"
                :class="dicaTone"
            >
                <div class="font-medium">{{ dicaModulo.titulo }}</div>
                <div class="mt-1">{{ dicaModulo.texto }}</div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ editing === 'new' ? 'Nova tarefa' : 'Editar tarefa' }}</h2>
                </div>
                <div class="card-body grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <InputLabel value="Título" />
                        <input v-model="form.titulo" required class="form-input" placeholder="Ex.: Vacinar lote da mangueira 3">
                    </div>
                    <div>
                        <InputLabel value="Módulo" />
                        <select v-model="form.modulo" class="form-select">
                            <option value="geral">Geral / gestão</option>
                            <option value="rebanho">Rebanho</option>
                            <option value="agricola">Agrícola</option>
                            <option value="estoque">Estoque</option>
                            <option value="maquinas">Máquinas</option>
                            <option value="financeiro">Financeiro</option>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Define os tipos de vínculo disponíveis abaixo.</p>
                    </div>
                    <div class="sm:col-span-3">
                        <InputLabel value="Descrição" />
                        <textarea v-model="form.descricao" rows="2" class="form-textarea"></textarea>
                    </div>

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

                    <!-- F3 · VÍNCULO OBRIGATÓRIO ───────────────────────── -->
                    <div class="sm:col-span-3 p-4 rounded-lg border border-slate-200 bg-slate-50 space-y-3">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.828 10.172a4 4 0 015.656 0l1.415 1.414a4 4 0 01-5.657 5.657l-1.414-1.414m-1.415-4.243a4 4 0 00-5.656 0L5.343 12.9a4 4 0 005.657 5.657l1.414-1.414"/>
                            </svg>
                            <h3 class="text-sm font-semibold text-slate-700">
                                Vínculo da tarefa (obrigatório) — a quê esta tarefa se refere?
                            </h3>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Tipo de vínculo" />
                                <select v-model="form.related_type" class="form-select" required>
                                    <option value="">— Escolha —</option>
                                    <option v-for="t in relatedTypesDisponiveis" :key="t" :value="t">
                                        {{ RELATED_LABEL[t] ?? t }}
                                    </option>
                                </select>
                            </div>
                            <div v-if="form.related_type">
                                <InputLabel :value="RELATED_LABEL[form.related_type]" />
                                <select
                                    v-model="form.related_id"
                                    class="form-select"
                                    required
                                    :disabled="entidadesDoTipo.length === 0"
                                >
                                    <option :value="null">— Escolha —</option>
                                    <option v-for="e in entidadesDoTipo" :key="e.id" :value="e.id">{{ e.label }}</option>
                                </select>
                                <p v-if="entidadesDoTipo.length === 0" class="text-xs text-amber-700 mt-1">
                                    Nenhum registro ativo de {{ RELATED_LABEL[form.related_type]?.toLowerCase() }}. Cadastre um antes de criar a tarefa.
                                </p>
                                <p v-else-if="form.related_type === 'App\\Models\\Financial\\FinancialTransaction'" class="text-xs text-slate-500 mt-1">
                                    Exibindo 200 lançamentos mais recentes.
                                </p>
                            </div>
                        </div>

                        <p v-if="!form.related_type || !form.related_id" class="text-xs text-amber-700">
                            ⚠ Toda tarefa precisa de propósito. Vincule a uma entidade antes de salvar.
                        </p>
                    </div>

                    <!-- F3 · RESPONSÁVEIS OBRIGATÓRIOS ──────────────────── -->
                    <div class="sm:col-span-3 p-4 rounded-lg border border-slate-200 bg-slate-50 space-y-3">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <h3 class="text-sm font-semibold text-slate-700">
                                Responsáveis (obrigatório ≥ 1) — quem vai executar?
                            </h3>
                        </div>

                        <div v-if="employees.length === 0" class="text-sm text-amber-700">
                            Nenhum funcionário ativo. Cadastre funcionários antes de criar tarefas.
                        </div>
                        <div v-else class="grid gap-2 sm:grid-cols-3">
                            <label v-for="e in employees" :key="e.id" class="flex items-center gap-2 text-sm rounded-md border border-slate-200 bg-white p-2 hover:border-macaybas-primary cursor-pointer">
                                <input type="checkbox" :value="e.id" v-model="form.assignees" class="rounded">
                                <span>{{ e.nome }}</span>
                            </label>
                        </div>
                        <p v-if="form.assignees.length === 0 && employees.length > 0" class="text-xs text-amber-700">
                            ⚠ Toda tarefa precisa de dono. Selecione ao menos um responsável.
                        </p>
                        <p v-else-if="form.assignees.length > 1" class="text-xs text-slate-500">
                            {{ form.assignees.length }} pessoas designadas — a tarefa aparecerá na lista de todas.
                        </p>
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
                        <button
                            @click="salvar"
                            :disabled="!podeSalvar"
                            class="btn-primary"
                            :title="!podeSalvar && !form.processing ? 'Preencha título, vínculo e ao menos um responsável' : ''"
                        >
                            Salvar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- F4.2 · Filtros colapsáveis em mobile -->
        <MobileFilters cols="sm:grid-cols-4">
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
        </MobileFilters>

        <div class="space-y-3">
            <div v-for="t in tasks.data" :key="t.id" class="card">
                <div class="card-body">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span :class="prioridadeBadge(t.prioridade)">{{ t.prioridade }}</span>
                                <span :class="statusBadge(t.status)">{{ t.status.replace('_', ' ') }}</span>
                                <span v-if="t.modulo" class="badge-slate">{{ t.modulo }}</span>
                                <span v-if="vinculoLabel(t)" class="badge-blue text-xs">🔗 {{ vinculoLabel(t) }}</span>
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

                        <!-- F4.2: ações com área de toque adequada em mobile -->
                        <div class="flex flex-row sm:flex-col gap-1.5 items-end flex-shrink-0">
                            <ActionIcon
                                v-if="t.status !== 'concluida'"
                                type="toggle-on"
                                title="Marcar como concluída"
                                variant="success"
                                @click="concluir(t)"
                            />
                            <ActionIcon
                                v-else
                                type="reactivate"
                                title="Reabrir tarefa"
                                variant="slate"
                                @click="reabrir(t)"
                            />
                            <ActionIcon type="edit" title="Editar tarefa" @click="editar(t)" />
                            <ActionIcon type="delete" title="Excluir tarefa" @click="confirmDelete = t" />
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
