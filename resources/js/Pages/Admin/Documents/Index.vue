<script setup>
import { reactive, ref, computed, watch, onMounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import { dataBR, brl, hojeBR } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';
import { useConfirm } from '@/composables/useConfirm.js';

const { confirm } = useConfirm();

const props = defineProps({
    documents: Object,
    filters: Object,
    categories: Array,
    linkables: Object,
});
useAutoReload(['documents'], 20000);

const filtros = reactive({ ...props.filters });
const showUpload = ref(false);
const confirmDelete = ref(null);

const form = useForm({
    category_id: null, titulo: '', descricao: '',
    arquivo: null, data_documento: '', data_vencimento: '',
    is_confidential: false, tags: [],
    // F3 · Vínculo polimórfico (D8)
    related_type: '',
    related_id: null,
});

const categoryForm = useForm({ nome: '', cor: '#64748b', icon: 'file' });
const showCategoryForm = ref(false);

// Hub v3 — auto-abrir upload ao chegar pelo Hub com `?novo=1`
onMounted(() => {
    const qs = new URLSearchParams(window.location.search);
    if (qs.get('novo') === '1') {
        showUpload.value = true;
    }
});

// ═════════════════════════════════════════════════════════════════════
// F3 · UX anti-erro orientada por domínio — DOCUMENTOS
//
// Espelho fiel da matriz D8 (DocumentController):
//
//   contrato     → Partner
//   nota_fiscal  → Partner  OU  FinancialTransaction
//   laudo        → Animal   OU  Planting  OU  Field
//   comprovante  → sem regra
//   (outras)     → sem regra
//
// O tipo é INFERIDO da categoria via palavra-chave (mesmo algoritmo do
// backend: str_contains case-insensitive em nome+slug).
// ═════════════════════════════════════════════════════════════════════

/** Espelho de TIPO_POR_PALAVRA_CHAVE. Ordem importa: primeiro match ganha. */
const TIPO_POR_PALAVRA_CHAVE = [
    ['contrato',    'contrato'],
    ['nota',        'nota_fiscal'],
    ['fiscal',      'nota_fiscal'],
    ['laudo',       'laudo'],
    ['comprovante', 'comprovante'],
];

/** Espelho de RELATED_POR_TIPO — FQCN idêntico ao que o backend aceita. */
const RELATED_POR_TIPO = {
    contrato:    ['App\\Models\\Partner'],
    nota_fiscal: ['App\\Models\\Partner', 'App\\Models\\Financial\\FinancialTransaction'],
    laudo:       ['App\\Models\\Livestock\\Animal', 'App\\Models\\Agricultural\\Planting', 'App\\Models\\Agricultural\\Field'],
    comprovante: [], // sem regra — ficará sem seção de vínculo
};

/** Tipos conceituais que obrigam vínculo (F3 é mais estrita que o backend D8). */
const TIPOS_COM_VINCULO_OBRIGATORIO = ['contrato', 'nota_fiscal', 'laudo'];

/** Rótulo humano de cada FQCN. */
const RELATED_LABEL = {
    'App\\Models\\Partner': 'Parceiro',
    'App\\Models\\Livestock\\Animal': 'Animal',
    'App\\Models\\Agricultural\\Planting': 'Plantio',
    'App\\Models\\Agricultural\\Field': 'Talhão',
    'App\\Models\\Financial\\FinancialTransaction': 'Lançamento financeiro',
    'App\\Models\\Vehicle\\Vehicle': 'Veículo',
};

/** Rótulo humano do tipo inferido. */
const TIPO_LABEL = {
    contrato: 'Contrato',
    nota_fiscal: 'Nota fiscal',
    laudo: 'Laudo técnico',
    comprovante: 'Comprovante',
};

/** Categoria selecionada. */
const categoriaSelecionada = computed(() =>
    props.categories.find((c) => c.id === form.category_id) ?? null,
);

/** Infere tipo conceitual da categoria (mesma lógica do backend). */
const tipoInferido = computed(() => {
    const cat = categoriaSelecionada.value;
    if (!cat) return null;
    const haystack = `${cat.nome ?? ''} ${cat.slug ?? ''}`.toLowerCase();
    for (const [palavra, tipo] of TIPO_POR_PALAVRA_CHAVE) {
        if (haystack.includes(palavra)) return tipo;
    }
    return null;
});

/** Tipo impõe vínculo obrigatório? */
const vinculoObrigatorio = computed(
    () => tipoInferido.value !== null && TIPOS_COM_VINCULO_OBRIGATORIO.includes(tipoInferido.value),
);

/** Tipos de vínculo permitidos para o tipo atual. */
const relatedTypesDisponiveis = computed(() => {
    const t = tipoInferido.value;
    if (!t) return [];
    return RELATED_POR_TIPO[t] ?? [];
});

/** Mostra a seção de vínculo quando o tipo é conhecido e tem ao menos uma opção. */
const showVinculoSection = computed(
    () => tipoInferido.value !== null && relatedTypesDisponiveis.value.length > 0,
);

/** Lista de entidades a mostrar para o related_type escolhido. */
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
        default:
            return [];
    }
});

/** Card azul de dica contextual. */
const dicaVinculo = computed(() => {
    const t = tipoInferido.value;
    if (!t) return null;
    const nome = TIPO_LABEL[t] ?? t;
    switch (t) {
        case 'contrato':
            return {
                titulo: `${nome} — vínculo obrigatório com parceiro`,
                texto: 'Contratos são acordos com terceiros (arrendamento, fornecimento, serviço, comodato). Por isso, todo contrato deve estar vinculado ao parceiro contratado para que fique rastreável no cadastro dele.',
                tone: 'amber',
            };
        case 'nota_fiscal':
            return {
                titulo: `${nome} — vincule ao parceiro OU ao lançamento`,
                texto: 'Notas fiscais documentam operações comerciais. Ideal vincular ao lançamento financeiro correspondente (contas a pagar/receber) — assim a NF aparece direto no detalhe da transação. Como alternativa, vincule ao parceiro emissor.',
                tone: 'amber',
            };
        case 'laudo':
            return {
                titulo: `${nome} — vincule ao contexto técnico`,
                texto: 'Laudos são diagnósticos emitidos por veterinários, agrônomos ou técnicos. Devem ser vinculados ao objeto examinado: o animal (laudo veterinário), o plantio (laudo fitossanitário) ou o talhão (análise de solo).',
                tone: 'amber',
            };
        case 'comprovante':
            return {
                titulo: `${nome} — vínculo opcional`,
                texto: 'Comprovantes são flexíveis: podem ser anexados a lançamentos, parceiros, animais — ou ficar soltos no acervo. Sem regra de vínculo obrigatório.',
                tone: 'slate',
            };
        default:
            return null;
    }
});

const dicaTone = computed(() => {
    const t = dicaVinculo.value?.tone;
    return {
        amber: 'border-amber-200 bg-amber-50 text-amber-900',
        slate: 'border-slate-200 bg-slate-50 text-slate-700',
    }[t] ?? 'border-blue-200 bg-blue-50 text-blue-900';
});

// ── Watchers: anti-erro ao trocar categoria ou related_type ──────────

watch(
    () => form.category_id,
    () => {
        // Se o related_type atual não está mais na lista permitida → limpa
        if (form.related_type && !relatedTypesDisponiveis.value.includes(form.related_type)) {
            form.related_type = '';
            form.related_id = null;
        }
        // Se tipo virou sem regra (sem seção de vínculo) → limpa
        if (!showVinculoSection.value) {
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

/** Botão "Salvar" — bloqueia quando vínculo obrigatório está incompleto. */
const podeSalvar = computed(() => {
    if (form.processing) return false;
    if (!form.titulo) return false;
    if (!form.arquivo) return false;
    if (vinculoObrigatorio.value && (!form.related_type || !form.related_id)) return false;
    return true;
});

function filtrar() {
    router.get(route('admin.documentos.index'), filtros, { preserveState: true, replace: true });
}

function salvar() {
    form.post(route('admin.documentos.store'), {
        preserveScroll: true, only: ['documents'],
        forceFormData: true,
        onSuccess: () => { showUpload.value = false; form.reset(); },
    });
}

function onFile(e) { form.arquivo = e.target.files?.[0]; }

function doDelete() {
    router.delete(route('admin.documentos.destroy', confirmDelete.value.id), {
        preserveScroll: true, only: ['documents'],
        onSuccess: () => confirmDelete.value = null,
    });
}

function createCategory() {
    categoryForm.post(route('admin.documentos.categorias.store'), {
        preserveScroll: true, only: ['categories'],
        onSuccess: () => { showCategoryForm.value = false; categoryForm.reset(); categoryForm.cor = '#64748b'; categoryForm.icon = 'file'; },
    });
}
async function delCategory(id) {
    const ok = await confirm({
        title: 'Excluir categoria',
        message: 'Tem certeza que deseja excluir esta categoria?',
        confirmText: 'Excluir',
        variant: 'danger',
    });
    if (ok) {
        router.delete(route('admin.documentos.categorias.destroy', id), { preserveScroll: true, only: ['categories'] });
    }
}

function formatSize(b) {
    if (!b) return '';
    const kb = b / 1024;
    return kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.round(kb)} KB`;
}

const hoje = hojeBR();
const isVencido = (d) => d && d < hoje;
const isProxVenc = (d) => {
    if (!d) return false;
    const dt = new Date(d);
    const daqui30 = new Date(); daqui30.setDate(daqui30.getDate() + 30);
    return dt <= daqui30 && dt >= new Date();
};
</script>

<template>
    <Head title="Documentos" />
    <AdminLayout>
        <template #page-title>Documentos</template>
        <PageHeader title="Documentos e arquivos" subtitle="Contratos, notas fiscais, comprovantes e documentos sanitários">
            <template #actions>
                <button @click="showCategoryForm = !showCategoryForm" class="btn-outline">Categorias</button>
                <button @click="showUpload = !showUpload" class="btn-primary">+ Upload</button>
            </template>
        </PageHeader>

        <!-- Form Upload -->
        <div v-if="showUpload" class="mb-6 space-y-4">
            <!-- Dica contextual por tipo (F3 — UX guiada) -->
            <div
                v-if="dicaVinculo"
                class="rounded-lg border px-4 py-3 text-sm"
                :class="dicaTone"
            >
                <div class="font-medium">{{ dicaVinculo.titulo }}</div>
                <div class="mt-1">{{ dicaVinculo.texto }}</div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Enviar documento</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><InputLabel value="Título" /><input v-model="form.titulo" required class="form-input" placeholder="Ex.: Contrato de arrendamento — Sr. José"></div>
                    <div>
                        <InputLabel value="Categoria" />
                        <select v-model="form.category_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.nome }}</option>
                        </select>
                        <p v-if="tipoInferido" class="text-xs text-slate-500 mt-1">
                            Detectado como <strong>{{ TIPO_LABEL[tipoInferido] ?? tipoInferido }}</strong> pela palavra-chave na categoria.
                        </p>
                    </div>
                    <div>
                        <InputLabel value="Arquivo (até 20 MB)" />
                        <input type="file" @change="onFile" class="form-input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt">
                    </div>
                    <div><InputLabel value="Data do documento" /><InputDate v-model="form.data_documento" /></div>
                    <div><InputLabel value="Data de vencimento" /><InputDate v-model="form.data_vencimento" /></div>
                    <div class="sm:col-span-2"><InputLabel value="Descrição" /><textarea v-model="form.descricao" rows="2" class="form-textarea"></textarea></div>

                    <!-- F3 · Seção de vínculo polimórfico (D8) -->
                    <div v-if="showVinculoSection" class="sm:col-span-2 p-4 rounded-lg border border-slate-200 bg-slate-50 space-y-3">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.828 10.172a4 4 0 015.656 0l1.415 1.414a4 4 0 01-5.657 5.657l-1.414-1.414m-1.415-4.243a4 4 0 00-5.656 0L5.343 12.9a4 4 0 005.657 5.657l1.414-1.414"/>
                            </svg>
                            <h3 class="text-sm font-semibold text-slate-700">
                                Vincular a uma entidade {{ vinculoObrigatorio ? '(obrigatório)' : '(opcional)' }}
                            </h3>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Tipo de vínculo" />
                                <select
                                    v-model="form.related_type"
                                    class="form-select"
                                    :required="vinculoObrigatorio"
                                >
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
                                    :required="vinculoObrigatorio"
                                    :disabled="entidadesDoTipo.length === 0"
                                >
                                    <option :value="null">— Escolha —</option>
                                    <option v-for="e in entidadesDoTipo" :key="e.id" :value="e.id">{{ e.label }}</option>
                                </select>
                                <p v-if="entidadesDoTipo.length === 0" class="text-xs text-amber-700 mt-1">
                                    Nenhum registro de {{ RELATED_LABEL[form.related_type]?.toLowerCase() }} ativo.
                                    Cadastre um antes de anexar o documento.
                                </p>
                                <p v-else-if="form.related_type === 'App\\Models\\Financial\\FinancialTransaction'" class="text-xs text-slate-500 mt-1">
                                    Exibindo 200 lançamentos mais recentes.
                                </p>
                            </div>
                        </div>

                        <p v-if="vinculoObrigatorio && (!form.related_type || !form.related_id)" class="text-xs text-amber-700">
                            ⚠ O vínculo é obrigatório para este tipo de documento. Escolha o tipo e a entidade acima antes de salvar.
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" v-model="form.is_confidential" class="rounded"> Documento confidencial
                        </label>
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-2">
                        <button @click="showUpload = false" class="btn-outline">Cancelar</button>
                        <button
                            @click="salvar"
                            :disabled="!podeSalvar"
                            class="btn-primary"
                            :title="!podeSalvar && !form.processing ? 'Preencha título, arquivo e vínculo obrigatório (se houver)' : ''"
                        >
                            {{ form.processing ? `Enviando... ${form.progress?.percentage ?? 0}%` : 'Enviar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gestão de categorias -->
        <div v-if="showCategoryForm" class="card mb-6">
            <div class="card-header"><h2 class="card-title">Categorias de documentos</h2></div>
            <div class="card-body space-y-3">
                <div class="flex gap-2 flex-wrap">
                    <span v-for="c in categories" :key="c.id"
                          class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm"
                          :style="{ background: (c.cor || '#64748b') + '20', color: c.cor || '#64748b' }">
                        {{ c.nome }}
                        <button @click="delCategory(c.id)" class="hover:opacity-70">×</button>
                    </span>
                </div>
                <div class="grid gap-3 sm:grid-cols-4 pt-3 border-t border-slate-100">
                    <div class="sm:col-span-2"><InputLabel value="Nome da categoria" /><input v-model="categoryForm.nome" class="form-input" placeholder="Ex.: Contrato de fornecimento"></div>
                    <div><InputLabel value="Cor (hex)" /><input type="color" v-model="categoryForm.cor" class="h-10 w-full rounded border border-slate-300"></div>
                    <div class="flex items-end"><button @click="createCategory" :disabled="categoryForm.processing" class="btn-primary w-full">Adicionar</button></div>
                </div>
                <p class="text-xs text-slate-500">
                    Dica: palavras-chave <strong>contrato</strong>, <strong>nota</strong>/<strong>fiscal</strong>, <strong>laudo</strong> e <strong>comprovante</strong>
                    no nome da categoria ativam a orientação de vínculo automática ao fazer upload.
                </p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-3">
                <input v-model="filtros.search" @keyup.enter="filtrar" placeholder="Buscar por título ou descrição" class="form-input">
                <select v-model="filtros.category_id" @change="filtrar" class="form-select">
                    <option value="">Todas as categorias</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.nome }}</option>
                </select>
                <select v-model="filtros.venc" @change="filtrar" class="form-select">
                    <option value="">Todos</option>
                    <option value="proximos">Vencem em 30 dias</option>
                    <option value="vencidos">Vencidos</option>
                </select>
            </div>
        </div>

        <!-- Grid de documentos -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="d in documents.data" :key="d.id" class="card">
                <div class="card-body">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <div v-if="d.category" class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold"
                                 :style="{ background: (d.category.cor || '#64748b') + '20', color: d.category.cor || '#64748b' }">
                                📄
                            </div>
                            <span v-if="d.category" class="text-xs text-slate-500">{{ d.category.nome }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <span v-if="d.is_confidential" title="Confidencial">🔒</span>
                            <span v-if="isVencido(d.data_vencimento)" class="badge-red text-xs">Vencido</span>
                            <span v-else-if="isProxVenc(d.data_vencimento)" class="badge-yellow text-xs">Próx. venc.</span>
                        </div>
                    </div>
                    <h3 class="font-semibold text-slate-900 mb-1 truncate" :title="d.titulo">{{ d.titulo }}</h3>
                    <p v-if="d.descricao" class="text-xs text-slate-500 line-clamp-2 mb-2">{{ d.descricao }}</p>
                    <div class="text-xs text-slate-500 space-y-1">
                        <div v-if="d.data_documento">📅 {{ dataBR(d.data_documento) }}</div>
                        <div v-if="d.data_vencimento">⏰ Vence em {{ dataBR(d.data_vencimento) }}</div>
                        <div>📎 {{ d.nome_arquivo }} ({{ formatSize(d.size) }})</div>
                    </div>
                    <div class="flex gap-1 mt-3 pt-3 border-t border-slate-100 justify-end">
                        <a :href="d.url" target="_blank" class="inline-flex">
                            <ActionIcon type="download" title="Baixar arquivo" />
                        </a>
                        <ActionIcon type="delete" title="Excluir documento" @click="confirmDelete = d" />
                    </div>
                </div>
            </div>

            <div v-if="!documents.data.length" class="sm:col-span-2 lg:col-span-3 card p-10 text-center text-slate-500">
                Nenhum documento encontrado.
            </div>
        </div>

        <ConfirmModal :show="!!confirmDelete" title="Excluir documento"
                      :message="`Excluir ${confirmDelete?.titulo}? O arquivo também será removido do servidor.`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
