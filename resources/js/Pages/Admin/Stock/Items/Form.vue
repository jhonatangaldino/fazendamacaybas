<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import InputMoney from '@/Components/InputMoney.vue';
import BarcodeScanner from '@/Components/BarcodeScanner.vue';
import { useToast } from '@/composables/useToast.js';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';
import InputDecimal from '@/Components/InputDecimal.vue';

const { toast } = useToast();
const props = defineProps({ item: Object, categories: Array });
const isEdit = !!props.item;

// Se viemos do fluxo "scan → não encontrado em nenhuma base → cadastrar",
// o código é pré-preenchido via query (?codigo_barras=XXX&scanned=1) e tratado
// como verificado. Aumenta a robustez da base interna.
const qs = new URLSearchParams(window.location.search);
const codigoBarrasFromScan = qs.get('codigo_barras') || '';
const scannedFlag = qs.get('scanned') === '1';
const codigoVerificado = ref(scannedFlag && !!codigoBarrasFromScan);

const form = useForm({
    category_id: props.item?.category_id ?? null,
    codigo: props.item?.codigo ?? '',
    codigo_barras: props.item?.codigo_barras ?? codigoBarrasFromScan,
    nome: props.item?.nome ?? '',
    descricao: props.item?.descricao ?? '',
    unidade: props.item?.unidade ?? 'un',
    marca: props.item?.marca ?? '',
    estoque_minimo: props.item?.estoque_minimo ?? 0,
    estoque_maximo: props.item?.estoque_maximo ?? null,
    custo_medio: props.item?.custo_medio ?? 0,
    tipo: props.item?.tipo ?? 'insumo',
    registro_ms: props.item?.registro_ms ?? '',
    is_active: props.item?.is_active ?? true,
});

// ═════════════════════════════════════════════════════════════════════
// F3 · UX anti-erro orientada por domínio — STOCK ITEM
//
// Espelha a matriz D3 (StockItemController::validateDomainCoherence):
//
//   medicamento → registro_ms OBRIGATÓRIO (rastreabilidade sanitária)
//   racao       → descrição contextual ≥ 10 chars (evitar item genérico)
//   insumo      → descrição contextual ≥ 10 chars (composição/cultura)
//   ferramenta  → campos simples, sem regulação
//   combustivel → unidade default litro, sem validade/registro
//   peca        → marca/modelo relevantes
//   material    → livre
//
// O backend D3 permanece como 2ª camada (nunca confiar só em JS).
// ═════════════════════════════════════════════════════════════════════

/** Espelho de TIPOS_EXIGEM_REGISTRO_MS. */
const TIPOS_EXIGEM_REGISTRO_MS = ['medicamento'];

/** Espelho de TIPOS_EXIGEM_DESCRICAO + DESCRICAO_MIN_CHARS. */
const TIPOS_EXIGEM_DESCRICAO = ['racao', 'insumo'];
const DESCRICAO_MIN_CHARS = 10;

/** Espelho de DICAS_DESCRICAO (mesmo texto do controller, para consistência). */
const DICAS_DESCRICAO = {
    racao: "Ex.: 'Ração 20% proteína para bezerros em desmame' ou 'Ração peletizada para postura fase 2'.",
    insumo: "Ex.: 'NPK 08-28-16 para plantio de milho' ou 'Calcário dolomítico para correção de solo'.",
};

/** Unidade default por tipo (UX: menos cliques). Sobrescreve apenas em cadastro novo. */
const UNIDADE_DEFAULT_POR_TIPO = {
    racao: 'sc',          // saca
    insumo: 'sc',
    medicamento: 'ml',
    combustivel: 'l',
    ferramenta: 'un',
    peca: 'un',
    material: 'un',
};

/** Rótulo legível do tipo (usado em mensagens). */
const TIPO_LABEL = {
    insumo: 'Insumo agrícola',
    medicamento: 'Medicamento veterinário',
    racao: 'Ração',
    ferramenta: 'Ferramenta',
    peca: 'Peça / componente',
    combustivel: 'Combustível',
    material: 'Material diverso',
};

/** Placeholder contextual da descrição por tipo. */
const PLACEHOLDER_DESCRICAO = {
    racao: 'Ração 20% proteína para bezerros em desmame',
    insumo: 'NPK 08-28-16 para plantio de milho',
    medicamento: 'Antibiótico injetável de amplo espectro',
    combustivel: 'Diesel S10 — uso em máquinas agrícolas',
    ferramenta: 'Enxada reforçada 3 libras, cabo longo',
    peca: 'Filtro de óleo Trator John Deere 5078E',
    material: 'Arame farpado zincado 500m',
};

/** Placeholder contextual do nome por tipo. */
const PLACEHOLDER_NOME = {
    racao: 'Ex.: Ração Bezerro Inicial 20%',
    insumo: 'Ex.: NPK 08-28-16',
    medicamento: 'Ex.: Oxitetraciclina 20% LA',
    combustivel: 'Ex.: Diesel S10',
    ferramenta: 'Ex.: Enxada 3lb cabo longo',
    peca: 'Ex.: Filtro de óleo JD 5078E',
    material: 'Ex.: Arame farpado 500m',
};

const showRegistroMs = computed(() => TIPOS_EXIGEM_REGISTRO_MS.includes(form.tipo));
const requiresRegistroMs = computed(() => showRegistroMs.value);
const requiresDescricao = computed(() => TIPOS_EXIGEM_DESCRICAO.includes(form.tipo));
const descricaoLength = computed(() => (form.descricao ?? '').trim().length);
const descricaoOk = computed(() =>
    !requiresDescricao.value || descricaoLength.value >= DESCRICAO_MIN_CHARS,
);

const tipoLabel = computed(() => TIPO_LABEL[form.tipo] ?? form.tipo);
const placeholderNome = computed(() => PLACEHOLDER_NOME[form.tipo] ?? '');
const placeholderDescricao = computed(() => PLACEHOLDER_DESCRICAO[form.tipo] ?? '');

/** Card azul de dica contextual por tipo. */
const dicaTipo = computed(() => {
    switch (form.tipo) {
        case 'medicamento':
            return {
                titulo: 'Medicamento veterinário',
                texto: 'Registro no MAPA/MS é obrigatório por lei para rastreabilidade sanitária. A unidade pode ser ml, dose ou un conforme a apresentação.',
            };
        case 'racao':
            return {
                titulo: 'Ração',
                texto: 'Evite cadastros genéricos tipo "Ração". Na descrição, informe para qual animal/fase se destina e a formulação (% proteína, peletizada, farelada, etc.).',
            };
        case 'insumo':
            return {
                titulo: 'Insumo agrícola',
                texto: 'Na descrição, informe composição (ex.: NPK 08-28-16), finalidade (plantio, cobertura, correção) e cultura. Isso permite rastrear o custo por safra.',
            };
        case 'combustivel':
            return {
                titulo: 'Combustível',
                texto: 'Unidade padrão: litros. Sem registro MS nem validade no cadastro — o controle é por lote de abastecimento na movimentação.',
            };
        case 'ferramenta':
            return {
                titulo: 'Ferramenta',
                texto: 'Ferramentas são bens reutilizáveis. Marca e modelo ajudam em reposição e manutenção futura.',
            };
        case 'peca':
            return {
                titulo: 'Peça / componente',
                texto: 'Na descrição, informe modelo/veículo compatível (ex.: "Filtro de óleo Trator JD 5078E"). Essencial para manutenção preventiva.',
            };
        default:
            return null;
    }
});

// ── Reset de campos incompatíveis ao trocar tipo ───────────────────
watch(
    () => form.tipo,
    (novo, antigo) => {
        if (novo === antigo) return;

        // Unidade default: só sobrescreve em cadastro novo e se usuário não mexeu
        if (!isEdit && form.unidade === (UNIDADE_DEFAULT_POR_TIPO[antigo] ?? 'un')) {
            form.unidade = UNIDADE_DEFAULT_POR_TIPO[novo] ?? 'un';
        }

        // registro_ms só faz sentido para medicamento — limpa ao sair
        if (!TIPOS_EXIGEM_REGISTRO_MS.includes(novo)) {
            form.registro_ms = '';
        }
    },
);

const showScanner = ref(false);
const lookupLoading = ref(false);
const produtoExistente = ref(null);
useBodyScrollLock(computed(() => !!produtoExistente.value));
const sugestaoPublica = ref(null);
const ultimaTentativa = ref(null);

async function onBarcodeDetected(code) {
    showScanner.value = false;
    form.codigo_barras = code;
    codigoVerificado.value = true;
    lookupLoading.value = true;
    sugestaoPublica.value = null;
    ultimaTentativa.value = null;

    try {
        const resp = await fetch(route('admin.estoque.itens.lookup-barcode') + '?code=' + encodeURIComponent(code), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const data = await resp.json();

        if (data.found && data.item) {
            if (!isEdit) {
                produtoExistente.value = data.item;
                toast.warning(`"${data.item.nome}" já está cadastrado.`);
                return;
            }
            toast.info(`Código já associado a "${data.item.nome}".`);
            return;
        }

        if (data.suggestion) {
            const s = data.suggestion;
            sugestaoPublica.value = {
                source: s.source,
                nome: s.nome,
                marca: s.marca,
                imagem_url: s.imagem_url || null,
                quantidade_embalagem: s.quantidade_embalagem || null,
            };
            if (!isEdit) {
                if (!form.nome && s.nome) form.nome = s.nome;
                if (!form.marca && s.marca) form.marca = s.marca;
            }
            toast.success(`Produto identificado: ${s.nome} (${s.source})`);
            return;
        }

        ultimaTentativa.value = {
            code,
            diagnostico: data.diagnostico || null,
            attempts: data.attempts || null,
        };
        toast.info(
            `Código ${code} lido, mas não consta em nenhuma base pública. Preencha manualmente — das próximas vezes será reconhecido localmente.`,
            { duration: 8000 }
        );
        requestAnimationFrame(() => {
            document.querySelector('input[required][class*="form-input"]:not([value])')?.focus();
        });
    } catch (e) {
        toast.warning('Falha de rede ao consultar produto. Preencha manualmente.');
        ultimaTentativa.value = { code, diagnostico: 'Erro de rede: ' + (e?.message || 'desconhecido'), attempts: null };
    } finally {
        lookupLoading.value = false;
    }
}

function irParaMovimentacao() {
    window.location.href = produtoExistente.value.movement_url;
}
function irParaEdicao() {
    window.location.href = produtoExistente.value.edit_url;
}

function submit() {
    if (isEdit) form.put(route('admin.estoque.itens.update', props.item.id));
    else form.post(route('admin.estoque.itens.store'));
}

const unidades = ['un', 'kg', 'g', 'l', 'ml', 'sc', 'cx', 'pc', 'm', 'm2', 'm3', 'dose'];
</script>

<template>
    <Head :title="isEdit ? 'Editar item' : 'Novo item'" />
    <AdminLayout>
        <template #page-title>{{ isEdit ? 'Editar item' : 'Novo item de estoque' }}</template>
        <PageHeader :title="isEdit ? 'Editar item de estoque' : 'Novo item de estoque'">
            <template #actions>
                <Link :href="route('admin.estoque.itens.index')" class="btn-outline">Voltar</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="space-y-6 max-w-3xl">
            <!-- Dica contextual por tipo (F3 — UX guiada) -->
            <div
                v-if="dicaTipo"
                class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900"
            >
                <div class="font-medium">{{ dicaTipo.titulo }}</div>
                <div class="mt-1 text-blue-800">{{ dicaTipo.texto }}</div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Identificação</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <!-- Tipo sempre primeiro: dirige toda a UX abaixo -->
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="form.tipo" class="form-select" required>
                            <option value="insumo">Insumo agrícola</option>
                            <option value="medicamento">Medicamento veterinário</option>
                            <option value="racao">Ração</option>
                            <option value="ferramenta">Ferramenta</option>
                            <option value="peca">Peça / componente</option>
                            <option value="combustivel">Combustível</option>
                            <option value="material">Material diverso</option>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Define os campos obrigatórios e o perfil de uso do item.</p>
                    </div>
                    <div>
                        <InputLabel value="Código interno" />
                        <input v-model="form.codigo" required class="form-input" placeholder="Ex: RAC-001">
                        <InputError :message="form.errors.codigo" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Código de barras (EAN/UPC)" />
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <input v-model="form.codigo_barras" class="form-input w-full font-mono"
                                       :class="codigoVerificado ? 'pl-9 bg-emerald-50 border-emerald-300' : ''"
                                       placeholder="Ex: 7891234567890">
                                <svg v-if="codigoVerificado" class="absolute left-2.5 top-1/2 -translate-y-1/2 h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <button type="button" @click="showScanner = true"
                                    class="btn-outline flex items-center gap-1.5 flex-shrink-0"
                                    :disabled="lookupLoading">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9zM15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Escanear
                            </button>
                        </div>
                        <p v-if="codigoVerificado" class="text-xs text-emerald-700 mt-1 flex items-center gap-1">
                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Código verificado via scanner — ajuda a fortalecer a base interna da fazenda
                        </p>
                        <p v-if="lookupLoading" class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                            <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/><path d="M12 2a10 10 0 0110 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                            Consultando bases públicas...
                        </p>
                        <div v-if="ultimaTentativa && !sugestaoPublica"
                             class="mt-2 p-3 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-900 flex items-start gap-2">
                            <svg class="h-5 w-5 flex-shrink-0 mt-0.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div class="flex-1">
                                <div class="font-medium">Produto não identificado</div>
                                <p class="text-xs text-amber-800 mt-0.5">
                                    Preencha o nome manualmente — nas próximas leituras, este código será reconhecido automaticamente.
                                </p>
                            </div>
                        </div>

                        <div v-if="sugestaoPublica" class="mt-2 p-3 rounded-lg bg-emerald-50 border border-emerald-200 flex items-start gap-3">
                            <img v-if="sugestaoPublica.imagem_url" :src="sugestaoPublica.imagem_url"
                                 class="h-14 w-14 rounded object-contain bg-white ring-1 ring-emerald-200 flex-shrink-0">
                            <div class="min-w-0 flex-1 text-sm">
                                <div class="font-medium text-emerald-900 truncate">{{ sugestaoPublica.nome }}</div>
                                <div v-if="sugestaoPublica.marca" class="text-xs text-emerald-700">{{ sugestaoPublica.marca }}</div>
                                <div v-if="sugestaoPublica.quantidade_embalagem" class="text-xs text-emerald-700">{{ sugestaoPublica.quantidade_embalagem }}</div>
                                <div class="text-[10px] uppercase tracking-wider text-emerald-600 mt-1">
                                    Identificado em {{ sugestaoPublica.source }} · revise e ajuste se necessário
                                </div>
                            </div>
                            <button type="button" @click="sugestaoPublica = null" class="text-emerald-600 hover:text-emerald-900 flex-shrink-0" aria-label="Fechar">&times;</button>
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Nome" />
                        <input v-model="form.nome" required class="form-input" :placeholder="placeholderNome">
                        <InputError :message="form.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Categoria" />
                        <select v-model="form.category_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.nome }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Marca" />
                        <input v-model="form.marca" class="form-input">
                    </div>

                    <!-- Descrição contextual: obrigatória para racao/insumo, opcional para outros -->
                    <div class="sm:col-span-2">
                        <div class="flex items-end justify-between">
                            <InputLabel :value="requiresDescricao ? 'Descrição (obrigatória)' : 'Descrição'" />
                            <span
                                v-if="requiresDescricao"
                                class="text-xs font-mono"
                                :class="descricaoOk ? 'text-emerald-600' : 'text-amber-600'"
                            >
                                {{ descricaoLength }}/{{ DESCRICAO_MIN_CHARS }}
                            </span>
                        </div>
                        <textarea
                            v-model="form.descricao"
                            rows="2"
                            class="form-textarea"
                            :class="requiresDescricao && !descricaoOk && descricaoLength > 0 ? 'border-amber-300 focus:ring-amber-500' : ''"
                            :required="requiresDescricao"
                            :placeholder="placeholderDescricao"
                            :minlength="requiresDescricao ? DESCRICAO_MIN_CHARS : undefined"
                        ></textarea>
                        <p
                            v-if="requiresDescricao"
                            class="text-xs mt-1"
                            :class="descricaoOk ? 'text-slate-400' : 'text-amber-700'"
                        >
                            {{ DICAS_DESCRICAO[form.tipo] }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Estoque</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel value="Unidade" />
                        <select v-model="form.unidade" class="form-select" required>
                            <option v-for="u in unidades" :key="u" :value="u">{{ u }}</option>
                        </select>
                        <p v-if="form.tipo === 'combustivel'" class="text-xs text-slate-400 mt-1">Padrão: litro.</p>
                    </div>
                    <div>
                        <InputLabel value="Estoque mínimo" />
                        <InputDecimal v-model="form.estoque_minimo" :decimals="3" :min="0" placeholder="0,000" />
                    </div>
                    <div>
                        <InputLabel value="Estoque máximo (opcional)" />
                        <InputDecimal v-model="form.estoque_maximo" :decimals="3" :min="0" placeholder="0,000" />
                    </div>
                    <div>
                        <InputLabel value="Custo médio (R$/un)" />
                        <InputMoney v-model="form.custo_medio" />
                        <p class="text-xs text-slate-400 mt-1">Recalculado automaticamente em cada entrada.</p>
                    </div>

                    <!-- Registro MS: só para medicamento (D3 regra R1) -->
                    <div v-if="showRegistroMs" class="sm:col-span-2">
                        <InputLabel :value="requiresRegistroMs ? 'Registro no MAPA/MS (obrigatório)' : 'Registro no MAPA/MS'" />
                        <input
                            v-model="form.registro_ms"
                            class="form-input"
                            :required="requiresRegistroMs"
                            placeholder="Ex.: MS 1.2345.6789"
                        >
                        <p class="text-xs text-slate-400 mt-1">
                            Rastreabilidade sanitária obrigatória por lei para medicamentos veterinários.
                        </p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_active" class="rounded">
                        Item ativo
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Link :href="route('admin.estoque.itens.index')" class="btn-outline">Cancelar</Link>
                <button
                    type="submit"
                    class="btn-primary"
                    :disabled="form.processing || (requiresDescricao && !descricaoOk) || (requiresRegistroMs && !form.registro_ms)"
                >
                    Salvar
                </button>
            </div>
        </form>

        <BarcodeScanner v-if="showScanner"
                        @detected="onBarcodeDetected"
                        @close="showScanner = false" />

        <!-- Modal: Produto já cadastrado -->
        <Teleport to="body">
            <div v-if="produtoExistente" class="fixed inset-0 z-[55] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="produtoExistente = null"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <div class="flex items-start gap-3 mb-4">
                        <div class="h-10 w-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-slate-900">Produto já cadastrado</h3>
                            <p class="text-sm text-slate-500 mt-1">Este código de barras já está vinculado a um item existente do seu estoque.</p>
                        </div>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-4 mb-4 space-y-1">
                        <div class="font-semibold text-slate-900">{{ produtoExistente.nome }}</div>
                        <div class="text-sm text-slate-600">
                            <span class="font-mono">{{ produtoExistente.codigo }}</span>
                            <span v-if="produtoExistente.marca"> · {{ produtoExistente.marca }}</span>
                            <span v-if="produtoExistente.category?.nome"> · {{ produtoExistente.category.nome }}</span>
                        </div>
                        <div class="text-sm pt-2 border-t border-slate-200">
                            Saldo atual:
                            <strong :class="produtoExistente.saldo_atual > 0 ? 'text-emerald-700' : 'text-slate-500'">
                                {{ Number(produtoExistente.saldo_atual).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }} {{ produtoExistente.unidade }}
                            </strong>
                        </div>
                    </div>
                    <p class="text-sm text-slate-600 mb-4">
                        Você só precisa <strong>dar entrada em estoque</strong>. Deseja ir direto para a tela de movimentação?
                    </p>
                    <div class="flex flex-col sm:flex-row justify-end gap-2">
                        <button @click="produtoExistente = null" class="btn-outline">Cancelar</button>
                        <button @click="irParaEdicao" class="btn-outline">Editar cadastro</button>
                        <button @click="irParaMovimentacao" class="btn-primary">Entrada em estoque →</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
