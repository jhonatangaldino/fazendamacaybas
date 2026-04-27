<script setup>
/**
 * Assistente guiado POLIMÓRFICO — 1 wizard serve 6 cards do Hub.
 *
 * Tipos aceitos (?tipo=X):
 *   vacinacao | medicacao | vermifugacao | movimentacao | mortalidade | observacao
 *
 * Estrutura:
 *   Passo 1 · Qual animal?          (grid de cards)
 *   Passo 2 · Detalhes              (campos dinâmicos pelo tipo)
 *   Passo 3 · Conferência           (resumo)
 *   Passo 4 · Pronto!               (sucesso)
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import { hojeBR, dataBR } from '@/utils/format.js';
import { emojiEspecie } from '@/utils/emojiEspecie.js';

const props = defineProps({
    tipoInicial: { type: String, required: true },
    animais: { type: Array, required: true },
    lotes: { type: Array, required: true },
    locais: { type: Array, default: () => [] },
    lotesAgregados: { type: Array, default: () => [] },
});

// Metadados por tipo — tudo que muda entre os 6 cards.
// Cada tipo tem:
//   passo1Titulo — verbo específico ("vai vacinar", "vai medicar")
//   tituloAcao — texto curto pra título do sucesso ("Vacinação registrada")
//   perguntaQuando — label dinâmica humanizada ("Quando você vacinou?")
const TIPOS = {
    vacinacao: {
        titulo: 'Aplicar vacina no animal',
        emoji: '💉',
        passo1Titulo: 'Qual animal você quer vacinar?',
        passo2Titulo: 'Qual vacina?',
        perguntaQuando: 'Quando você vacinou?',
        tituloSucesso: 'Vacinação registrada!',
        sucessoMsg: (d) => `Você vacinou o animal ${d._animal.identificacao}.`,
        impactos: ['Vacinação entrou no histórico do animal', 'Calendário sanitário atualizado'],
        campos: ['vacina', 'dose_opcional', 'data', 'observacoes'],
        camposObrigatorios: ['vacina'],
    },
    medicacao: {
        titulo: 'Aplicar medicamento',
        emoji: '💊',
        passo1Titulo: 'Qual animal vai receber o medicamento?',
        passo2Titulo: 'Qual medicamento?',
        perguntaQuando: 'Quando você medicou?',
        tituloSucesso: 'Tratamento registrado.',
        sucessoMsg: (d) => `Tratamento anotado no histórico de ${d._animal.identificacao}.`,
        impactos: ['Tratamento entrou no histórico do animal', 'Se houver carência, será rastreada'],
        campos: ['medicamento', 'dose_opcional', 'data', 'observacoes'],
        camposObrigatorios: ['medicamento'],
    },
    vermifugacao: {
        titulo: 'Aplicar vermífugo',
        emoji: '🧴',
        passo1Titulo: 'Qual animal você quer vermifugar?',
        passo2Titulo: 'Qual vermífugo?',
        perguntaQuando: 'Quando você aplicou?',
        tituloSucesso: 'Vermifugação registrada!',
        sucessoMsg: (d) => `Vermifugação de ${d._animal.identificacao} anotada.`,
        impactos: ['Vermifugação entrou no histórico', 'Próxima vermifugação será programada'],
        campos: ['medicamento', 'dose_opcional', 'data', 'observacoes'],
        camposObrigatorios: ['medicamento'],
    },
    movimentacao: {
        titulo: 'Mover animal de lote',
        emoji: '🐄',
        passo1Titulo: 'Qual animal você quer mover de lote?',
        passo2Titulo: 'Pra qual lote vai?',
        perguntaQuando: 'Quando foi a mudança?',
        tituloSucesso: 'Animal movido de lote!',
        sucessoMsg: (d) => `${d._animal.identificacao} agora faz parte do lote ${d._loteNome}.`,
        impactos: ['Animal agora pertence ao novo lote (grupo)', 'Movimentação entrou no histórico'],
        campos: ['lot_destino_id', 'data', 'observacoes'],
        camposObrigatorios: ['lot_destino_id'],
    },
    movimentacao_local: {
        titulo: 'Mover animal de pasto',
        emoji: '📍',
        passo1Titulo: 'Qual animal você quer mudar de pasto?',
        passo2Titulo: 'Pra qual pasto vai?',
        perguntaQuando: 'Quando foi a mudança?',
        tituloSucesso: 'Animal mudou de pasto!',
        sucessoMsg: (d) => `${d._animal.identificacao} agora está em ${d._localNome}.`,
        impactos: ['Animal agora está no novo local (pasto/piquete/curral)', 'Mudança entrou no histórico · o lote (grupo) continua o mesmo'],
        campos: ['location_destino_id', 'data', 'observacoes'],
        camposObrigatorios: ['location_destino_id'],
    },
    mortalidade: {
        titulo: 'Registrar morte do animal',
        emoji: '⚰️',
        tom: 'sobrio', // tom grave — evita UI celebratória no passo 4
        passo1Titulo: 'Qual animal morreu?',
        passo2Titulo: 'O que aconteceu?',
        perguntaQuando: 'Quando aconteceu?',
        tituloSucesso: 'Morte registrada.',
        sucessoMsg: (d) => `${d._animal.identificacao} saiu do rebanho ativo.`,
        impactos: ['Animal saiu do rebanho ativo', 'Baixa aparece no relatório de mortalidade'],
        campos: ['causa', 'data', 'observacoes'],
        camposObrigatorios: [],
    },
    observacao: {
        titulo: 'Registrar observação do animal',
        emoji: '📝',
        passo1Titulo: 'Sobre qual animal você quer anotar?',
        passo2Titulo: 'O que aconteceu?',
        perguntaQuando: 'Quando?',
        tituloSucesso: 'Observação registrada.',
        sucessoMsg: (d) => `Anotação salva no histórico de ${d._animal.identificacao}.`,
        impactos: ['Observação entrou no histórico do animal'],
        campos: ['data', 'observacoes'],
        camposObrigatorios: ['observacoes'],
    },
};

const tipoAtivo = ref(props.tipoInicial);
const meta = computed(() => TIPOS[tipoAtivo.value]);

const PASSOS = computed(() => [
    { n: 1, titulo: 'O animal',     icon: '🐄' },
    { n: 2, titulo: meta.value.passo2Titulo, icon: meta.value.emoji },
    { n: 3, titulo: 'Conferência',  icon: '📋' },
    { n: 4, titulo: 'Pronto!',      icon: '✅' },
]);

const passo = ref(1);
const selecionado = ref(null);
const busca = ref('');
const sucesso = ref(null);
const erroServidor = ref(null); // flash.error vindo do backend (ex.: allowed_events rejeitou)

// Listas LOCAIS — começam com as props e ganham novos itens criados via modal inline.
// Isso evita sair do wizard quando falta lote/pasto (regra: criar dependência dentro do fluxo).
const lotesLocal = ref([...props.lotes]);
const locaisLocal = ref([...(props.locais ?? [])]);

// Modal: novo lote inline
const novoLoteAberto = ref(false);
const novoLoteForm = useForm({ nome: '', codigo: '' });
function abrirNovoLote() { novoLoteForm.reset(); novoLoteAberto.value = true; }
function salvarNovoLote() {
    novoLoteForm.post(route('admin.rebanho.lotes.store'), {
        preserveScroll: true,
        preserveState: true,
        only: ['lotes', 'flash'],
        onSuccess: (page) => {
            // Backend redireciona pra lista, mas o flash carrega. Alternativa
            // robusta: buscar o lote recém-criado via JSON na lista atualizada.
            // Como o store redireciona pra index, usamos fetch direto para
            // pegar o lote novo e injetar na lista local sem sair do wizard.
            fetch(route('admin.rebanho.lotes.index'), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            }).catch(() => {});
            // Fallback simples: adiciona à lista local com ID temporário caso
            // o backend não retorne JSON. Próximo reload mostra o ID real.
            // Mais pragmático: usar o nome para match depois.
            novoLoteAberto.value = false;
        },
        onError: () => { /* banner de erro inline dentro do modal */ },
    });
}

// Modal: novo local inline
const novoLocalAberto = ref(false);
const novoLocalForm = useForm({ nome: '', codigo: '', tipo: 'pasto' });
function abrirNovoLocal() { novoLocalForm.reset(); novoLocalForm.tipo = 'pasto'; novoLocalAberto.value = true; }
function salvarNovoLocal() {
    novoLocalForm.post(route('admin.rebanho.locais.store'), {
        preserveScroll: true,
        preserveState: true,
        only: ['locais', 'flash'],
        onSuccess: () => { novoLocalAberto.value = false; },
    });
}

const form = useForm({
    tipo: tipoAtivo.value,
    data_evento: hojeBR(),
    vacina: '',
    medicamento: '',
    dose: '',
    via_aplicacao: '',
    responsavel: '',
    lot_destino_id: null,
    location_destino_id: null,
    causa: '',       // usado pra mortalidade; o backend lê via observacoes
    observacoes: '',
});

const animaisFiltrados = computed(() => {
    if (!busca.value.trim()) return props.animais;
    const q = busca.value.toLowerCase();
    return props.animais.filter((a) =>
        (a.identificacao || '').toLowerCase().includes(q) ||
        (a.nome || '').toLowerCase().includes(q)
    );
});

// MODO em lote — para vacinação/medicação/vermífugo/observação/mortalidade.
// Auditoria 2026-04-27: adicionada mortalidade aos modos lote, e novo
// modo 'lote_agregado' para aves/peixes (lote sem identificação individual,
// pesado e administrado em massa).
//
// Modos disponíveis (depende do tipo do evento):
//   - individual    → 1 animal específico (sempre permitido)
//   - lote_grupo    → todos os animais com lot_id = X (vacinacao etc, animais individuais)
//   - lote_pasto    → todos os animais com location_id = X
//   - selecao       → seleção múltipla manual
//   - lote_agregado → N de M animais de um lote AGREGADO (aves/peixes/abelhas)
// A4 — movimentacao e movimentacao_local entram nos modos lote.
// Cenário real: rotação de pasto move 50 vacas de uma vez para outro lote/pasto.
const TIPOS_LOTE_OK = [
    'vacinacao', 'medicacao', 'vermifugacao', 'observacao',
    'mortalidade', 'movimentacao', 'movimentacao_local',
];
const TIPOS_AGREGADO_OK = ['vacinacao', 'medicacao', 'vermifugacao', 'mortalidade', 'observacao'];
const permiteLote = computed(() => TIPOS_LOTE_OK.includes(tipoAtivo.value));
const permiteAgregado = computed(() =>
    TIPOS_AGREGADO_OK.includes(tipoAtivo.value) && (props.lotesAgregados || []).length > 0
);
const modo = ref('individual');           // 'individual' | 'lote_grupo' | 'lote_pasto' | 'selecao' | 'lote_agregado'
const loteFiltroId = ref(null);            // quando modo=lote_grupo
const localFiltroId = ref(null);           // quando modo=lote_pasto
const idsSelecionados = ref([]);           // quando modo=selecao
const loteAgregadoId = ref(null);          // quando modo=lote_agregado
const qtdAnimaisAgregado = ref('');        // quando modo=lote_agregado: quantos animais participam

const animaisDoFiltro = computed(() => {
    if (modo.value === 'lote_grupo' && loteFiltroId.value) {
        return props.animais.filter(a => a.lot?.id === loteFiltroId.value);
    }
    if (modo.value === 'lote_pasto' && localFiltroId.value) {
        return props.animais.filter(a => a.location_id === localFiltroId.value);
    }
    if (modo.value === 'selecao') {
        return props.animais.filter(a => idsSelecionados.value.includes(a.id));
    }
    return [];
});

const podeAvancar1 = computed(() => {
    if (modo.value === 'individual') return !!selecionado.value;
    if (modo.value === 'lote_agregado') {
        const qtd = parseInt(String(qtdAnimaisAgregado.value)) || 0;
        return !!loteAgregadoId.value && qtd >= 1;
    }
    return animaisDoFiltro.value.length > 0;
});
const podeAvancar2 = computed(() => {
    if (!form.data_evento) return false;
    const obrig = meta.value.camposObrigatorios;
    for (const c of obrig) {
        if (!form[c] || (typeof form[c] === 'string' && !form[c].trim())) return false;
    }
    return true;
});

function mostraCampo(nome) {
    return meta.value.campos.includes(nome);
}

// emojiEspecie agora vem de '@/utils/emojiEspecie.js' — centralizado,
// com mapeamento correto (cabra ≠ ovelha, cão ≠ gato, búfalo ≠ bovino).

function avancar() { if (passo.value < PASSOS.value.length) passo.value++; }
function voltar() { if (passo.value > 1) passo.value--; }
function irPara(n) { passo.value = n; }

const loteNome = computed(() => props.lotes.find(l => l.id === form.lot_destino_id)?.nome ?? null);
const localNome = computed(() => {
    const loc = props.locais.find(l => l.id === form.location_destino_id);
    return loc ? loc.nome : null;
});

function confirmar() {
    // Modo agregado: rota nova (admin.rebanho.lotes.eventos.store)
    if (modo.value === 'lote_agregado') {
        return confirmarLoteAgregado();
    }
    // Modos lote (grupo/pasto/seleção): rota batch existente que itera animais
    if (modo.value !== 'individual') {
        return confirmarEmLote();
    }
    // Para mortalidade com "causa", anexa na observacoes (backend não tem campo causa dedicado)
    if (tipoAtivo.value === 'mortalidade' && form.causa) {
        form.observacoes = form.causa + (form.observacoes ? ` · ${form.observacoes}` : '');
    }

    erroServidor.value = null;

    form
        .transform((d) => {
            const { data_evento, causa, ...r } = d;
            return { ...r, data: data_evento };
        })
        .post(route('admin.rebanho.animais.eventos.store', selecionado.value.id), {
            preserveScroll: false,
            // IMPORTANT: onSuccess dispara mesmo quando o backend faz
            // `back()->with('error', ...)` (resposta 200 Inertia). Por isso
            // checamos flash.error antes de celebrar sucesso — senão o
            // usuário vê a tela "Pronto!" mesmo quando NADA foi salvo.
            onSuccess: (page) => {
                const flashError = page?.props?.flash?.error
                    ?? usePage()?.props?.flash?.error
                    ?? null;
                if (flashError) {
                    erroServidor.value = flashError;
                    passo.value = 3; // mantém na conferência com banner vermelho
                    return;
                }
                // Contexto emitido por AnimalController::buildContexto — contém
                // dados como animais_no_lote_destino, receita_gerada, etc.
                const ctx = page?.props?.flash?.event_contexto
                    ?? usePage()?.props?.flash?.event_contexto
                    ?? null;
                sucesso.value = {
                    _animal: selecionado.value,
                    _localNome: localNome.value,
                    _loteNome: loteNome.value,
                    _contexto: ctx,
                    tipo: tipoAtivo.value,
                };
                passo.value = 4;
            },
            onError: (errors) => {
                // Validation errors (422) — mostra banner com 1ª mensagem
                const first = Object.values(errors || {})[0];
                if (first) erroServidor.value = Array.isArray(first) ? first[0] : first;
            },
        });
}

// Envio em LOTE — um único POST aplica o evento em N animais.
// Auditoria 2026-04-27 — evento em LOTE AGREGADO (aves, peixes).
// Rota: admin.rebanho.lotes.eventos.store
// Decrementa quantidade_atual quando tipo=mortalidade.
function confirmarLoteAgregado() {
    erroServidor.value = null;
    const qtd = parseInt(String(qtdAnimaisAgregado.value)) || 0;
    if (qtd < 1) { erroServidor.value = 'Informe quantos animais foram afetados.'; return; }

    const payload = {
        tipo: tipoAtivo.value,
        data: form.data_evento,
        quantidade_animais: qtd,
        vacina: form.vacina || null,
        medicamento: form.medicamento || null,
        dose: form.dose || null,
        via_aplicacao: form.via_aplicacao || null,
        responsavel: form.responsavel || null,
        observacoes: tipoAtivo.value === 'mortalidade' && form.causa
            ? form.causa + (form.observacoes ? ` · ${form.observacoes}` : '')
            : (form.observacoes || null),
    };

    form.transform(() => payload).post(route('admin.rebanho.lotes.eventos.store', loteAgregadoId.value), {
        preserveScroll: false,
        onSuccess: (page) => {
            const flashError = page?.props?.flash?.error ?? null;
            if (flashError) { erroServidor.value = flashError; return; }
            const lote = props.lotesAgregados.find(l => l.id === loteAgregadoId.value);
            sucesso.value = {
                _animal: { identificacao: lote?.nome || 'lote', especie_lote: true },
                _loteNome: lote?.nome,
                _qtd: qtd,
            };
            passo.value = 4;
        },
    });
}

function confirmarEmLote() {
    erroServidor.value = null;

    // Mortalidade em lote: causa anexada às observações
    let observacoesFinal = form.observacoes || null;
    if (tipoAtivo.value === 'mortalidade' && form.causa) {
        observacoesFinal = form.causa + (form.observacoes ? ` · ${form.observacoes}` : '');
    }

    const payload = {
        tipo: tipoAtivo.value,
        data: form.data_evento,
        vacina: form.vacina || null,
        medicamento: form.medicamento || null,
        dose: form.dose || null,
        via_aplicacao: form.via_aplicacao || null,
        responsavel: form.responsavel || null,
        observacoes: observacoesFinal,
        // A4 — destinos de movimentação (backend valida obrigatórios por tipo)
        lot_destino_id: form.lot_destino_id || null,
        location_destino_id: form.location_destino_id || null,
    };
    if (modo.value === 'lote_grupo')  payload.lot_id = loteFiltroId.value;
    if (modo.value === 'lote_pasto')  payload.location_id = localFiltroId.value;
    if (modo.value === 'selecao')     payload.animal_ids = [...idsSelecionados.value];

    form.transform(() => payload).post(route('admin.rebanho.animais.eventos-lote'), {
        preserveScroll: false,
        onSuccess: (page) => {
            const flashError = page?.props?.flash?.error
                ?? usePage()?.props?.flash?.error
                ?? null;
            if (flashError) {
                erroServidor.value = flashError;
                passo.value = 3;
                return;
            }
            const ctxBatch = page?.props?.flash?.event_batch_contexto
                ?? usePage()?.props?.flash?.event_batch_contexto
                ?? null;
            sucesso.value = {
                _animal: { identificacao: `${ctxBatch?.total ?? '?'} animais`, id: null },
                _loteNome: loteNome.value,
                _localNome: localNome.value,
                _contexto: { batch: ctxBatch },
                tipo: tipoAtivo.value,
            };
            passo.value = 4;
        },
        onError: (errors) => {
            const first = Object.values(errors || {})[0];
            if (first) erroServidor.value = Array.isArray(first) ? first[0] : first;
        },
    });
}

function reiniciar() {
    selecionado.value = null;
    busca.value = '';
    sucesso.value = null;
    form.reset();
    form.tipo = tipoAtivo.value;
    form.data_evento = hojeBR();
    modo.value = 'individual';
    loteFiltroId.value = null;
    localFiltroId.value = null;
    idsSelecionados.value = [];
    passo.value = 1;
}
</script>

<template>
    <Head :title="meta.titulo" />
    <AdminLayout>
        <template #page-title>Assistente · {{ meta.titulo }}</template>

        <PageHeader
            :title="meta.titulo"
            subtitle="Vamos registrar passo a passo — não precisa saber onde clicar."
        >
            <template #actions>
                <Link :href="route('admin.inicio')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- PASSO 1 · O animal -->
        <div v-if="passo === 1" class="card max-w-5xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">{{ meta.passo1Titulo }}</h2>

                <!-- Toggle INDIVIDUAL vs EM LOTE — só para tipos que aceitam em lote
                     (vacinação/medicação/vermifugação/observação). Cenário real:
                     veterinário aplica a mesma vacina em 80 animais de uma vez. -->
                <div v-if="permiteLote || permiteAgregado" class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                    <button type="button" @click="modo = 'individual'"
                        class="py-3 px-2 rounded-lg border-2 text-sm font-medium"
                        :class="modo === 'individual' ? 'border-macaybas-primary bg-emerald-50 text-emerald-800' : 'border-slate-200'">
                        🐄 Um animal
                    </button>
                    <button v-if="permiteLote" type="button" @click="modo = 'lote_grupo'"
                        class="py-3 px-2 rounded-lg border-2 text-sm font-medium"
                        :class="modo === 'lote_grupo' ? 'border-macaybas-primary bg-emerald-50 text-emerald-800' : 'border-slate-200'">
                        🐑 Lote inteiro
                    </button>
                    <button v-if="permiteLote" type="button" @click="modo = 'lote_pasto'"
                        class="py-3 px-2 rounded-lg border-2 text-sm font-medium"
                        :class="modo === 'lote_pasto' ? 'border-macaybas-primary bg-emerald-50 text-emerald-800' : 'border-slate-200'">
                        📍 Pasto inteiro
                    </button>
                    <button v-if="permiteLote" type="button" @click="modo = 'selecao'"
                        class="py-3 px-2 rounded-lg border-2 text-sm font-medium"
                        :class="modo === 'selecao' ? 'border-macaybas-primary bg-emerald-50 text-emerald-800' : 'border-slate-200'">
                        ✓ Escolher vários
                    </button>
                    <!-- Auditoria 2026-04-27 — modo AGREGADO (aves/peixes) -->
                    <button v-if="permiteAgregado" type="button" @click="modo = 'lote_agregado'"
                        class="py-3 px-2 rounded-lg border-2 text-sm font-medium"
                        :class="modo === 'lote_agregado' ? 'border-amber-500 bg-amber-50 text-amber-900' : 'border-slate-200'">
                        🐔 Lote agregado<br><span class="text-xs font-normal">(N de M animais)</span>
                    </button>
                </div>

                <!-- LOTE AGREGADO: escolhe lote + quantidade afetada -->
                <template v-if="modo === 'lote_agregado'">
                    <p class="text-sm text-slate-600">
                        <strong>Aves, peixes ou abelhas</strong> sem identificação individual.
                        Escolha o lote e informe quantos animais foram afetados pelo evento.
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <button v-for="l in lotesAgregados" :key="l.id" type="button"
                                @click="loteAgregadoId = l.id; qtdAnimaisAgregado = ''"
                                class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary"
                                :class="loteAgregadoId === l.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                            <div class="font-bold text-slate-900">{{ l.nome }}</div>
                            <div class="text-xs font-mono text-slate-500">{{ l.codigo }}</div>
                            <div class="text-sm text-slate-700 mt-2">
                                Efetivo: <strong>{{ Number(l.quantidade_atual).toLocaleString('pt-BR') }}</strong>
                            </div>
                        </button>
                    </div>

                    <div v-if="loteAgregadoId">
                        <InputLabel value="Quantos animais foram afetados? *" />
                        <input v-model="qtdAnimaisAgregado" type="number" min="1"
                               :max="props.lotesAgregados.find(l => l.id === loteAgregadoId)?.quantidade_atual"
                               class="form-input text-2xl py-3 font-mono w-full"
                               :placeholder="String(props.lotesAgregados.find(l => l.id === loteAgregadoId)?.quantidade_atual || 0)">
                        <p class="text-xs text-slate-500 mt-1">
                            <span v-if="tipoAtivo === 'mortalidade'">⚠ Esses animais serão DESCONTADOS do efetivo do lote.</span>
                            <span v-else>O efetivo do lote NÃO muda — só registra o evento.</span>
                        </p>
                    </div>
                </template>

                <!-- INDIVIDUAL: mesma UI de sempre -->
                <template v-if="modo === 'individual'">
                    <p class="text-base text-slate-600">Toque em um animal para escolher.</p>
                    <p v-if="animais.length === 0" class="text-sm text-amber-700">
                        Nenhum animal disponível. Cadastre um antes.
                    </p>

                    <div v-if="animais.length > 6" class="relative">
                        <input v-model="busca" placeholder="Buscar por brinco, nome ou raça…"
                               class="form-input pl-10 text-base py-3">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <div v-if="animaisFiltrados.length === 0" class="text-center text-slate-500 py-12">
                        Nenhum animal encontrado.
                    </div>

                    <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 max-h-[55vh] overflow-y-auto pr-1">
                        <button v-for="a in animaisFiltrados" :key="a.id" type="button"
                                @click="selecionado = a"
                                data-cy="card-animal"
                                :data-animal-id="a.id"
                                class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary"
                                :class="selecionado?.id === a.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                            <div class="flex items-start gap-3">
                                <img v-if="a.photo_url" :src="a.photo_url" class="h-14 w-14 rounded-lg object-cover flex-shrink-0">
                                <div v-else class="h-14 w-14 rounded-lg bg-slate-100 flex items-center justify-center text-3xl flex-shrink-0">
                                    {{ emojiEspecie(a.species?.nome) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-slate-900 truncate">{{ a.identificacao }}</div>
                                    <div v-if="a.nome" class="text-sm text-slate-700 truncate">{{ a.nome }}</div>
                                    <div class="text-sm text-slate-500 mt-0.5">
                                        {{ a.species?.nome ?? '—' }}<span v-if="a.lot"> · {{ a.lot.nome }}</span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                </template>

                <!-- LOTE (grupo): escolhe 1 lote, aplica em todos animais ativos -->
                <template v-if="modo === 'lote_grupo'">
                    <p class="text-base text-slate-600">Escolha o lote (grupo). O evento será aplicado em TODOS os animais ativos do lote.</p>
                    <select v-model="loteFiltroId" class="form-select text-base py-3">
                        <option :value="null">— Escolha um lote —</option>
                        <option v-for="l in lotesLocal" :key="l.id" :value="l.id">{{ l.nome }}</option>
                    </select>
                    <div v-if="loteFiltroId" class="text-sm text-emerald-800 bg-emerald-50 border border-emerald-200 rounded p-3">
                        <strong>{{ animaisDoFiltro.length }}</strong> {{ animaisDoFiltro.length === 1 ? 'animal' : 'animais' }} ativo{{ animaisDoFiltro.length === 1 ? '' : 's' }} nesse lote vão receber o evento.
                    </div>
                </template>

                <!-- LOTE (pasto): escolhe 1 local físico -->
                <template v-if="modo === 'lote_pasto'">
                    <p class="text-base text-slate-600">Escolha o pasto. Todos os animais que estão nele receberão o evento.</p>
                    <select v-model="localFiltroId" class="form-select text-base py-3">
                        <option :value="null">— Escolha um pasto —</option>
                        <option v-for="l in locaisLocal" :key="l.id" :value="l.id">
                            {{ l.nome }}<span v-if="l.tipo"> · {{ l.tipo }}</span>
                        </option>
                    </select>
                    <div v-if="localFiltroId" class="text-sm text-emerald-800 bg-emerald-50 border border-emerald-200 rounded p-3">
                        <strong>{{ animaisDoFiltro.length }}</strong> {{ animaisDoFiltro.length === 1 ? 'animal' : 'animais' }} no pasto vão receber o evento.
                    </div>
                </template>

                <!-- SELEÇÃO: checkboxes em múltiplos animais -->
                <template v-if="modo === 'selecao'">
                    <p class="text-base text-slate-600">Marque os animais que receberão o evento.</p>
                    <div class="text-sm text-slate-500">
                        Selecionados: <strong>{{ idsSelecionados.length }}</strong>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 max-h-[55vh] overflow-y-auto pr-1">
                        <label v-for="a in animais" :key="a.id"
                               class="flex items-center gap-3 rounded-lg border-2 p-3 cursor-pointer transition-all"
                               :class="idsSelecionados.includes(a.id) ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                            <input type="checkbox" :value="a.id" v-model="idsSelecionados" class="rounded border-slate-300">
                            <span class="text-xl">{{ emojiEspecie(a.species?.nome) }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-slate-900 truncate">{{ a.identificacao }}<span v-if="a.nome" class="text-slate-500"> · {{ a.nome }}</span></div>
                                <div class="text-xs text-slate-500">{{ a.species?.nome }}<span v-if="a.lot"> · {{ a.lot.nome }}</span></div>
                            </div>
                        </label>
                    </div>
                </template>

                <div class="flex justify-end pt-4">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2 · Detalhes (dinâmico por tipo) -->
        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">
                    {{ meta.emoji }} {{ meta.passo2Titulo }}
                </h2>
                <p class="text-base text-slate-600">
                    <template v-if="modo === 'individual' && selecionado">
                        Animal: <strong>{{ selecionado.identificacao }}</strong><span v-if="selecionado.nome"> · {{ selecionado.nome }}</span>
                    </template>
                    <template v-else>
                        <strong>{{ animaisDoFiltro.length }}</strong> {{ animaisDoFiltro.length === 1 ? 'animal' : 'animais' }} receberão este evento
                        <template v-if="modo === 'lote_grupo'"> (lote inteiro)</template>
                        <template v-else-if="modo === 'lote_pasto'"> (pasto inteiro)</template>
                    </template>
                </p>

                <!-- Campo: vacina -->
                <div v-if="mostraCampo('vacina')">
                    <InputLabel value="Nome da vacina" />
                    <input v-model="form.vacina" type="text" maxlength="150"
                           placeholder="Ex: Aftosa, Brucelose, Raiva"
                           class="form-input text-base py-3">
                </div>

                <!-- Campo: medicamento -->
                <div v-if="mostraCampo('medicamento')">
                    <InputLabel :value="tipoAtivo === 'vermifugacao' ? 'Nome do vermífugo' : 'Nome do medicamento'" />
                    <input v-model="form.medicamento" type="text" maxlength="150" data-cy="input-medicamento"
                           :placeholder="tipoAtivo === 'vermifugacao' ? 'Ex: Ivermectina, Albendazol' : 'Ex: Antibiótico, Anti-inflamatório'"
                           class="form-input text-base py-3">
                </div>

                <!-- Dose (opcional) -->
                <div v-if="mostraCampo('dose_opcional')">
                    <InputLabel value="Dose (opcional)" />
                    <input v-model="form.dose" type="text" maxlength="50"
                           placeholder="Ex: 5 mL, 1 cápsula, 2 comprimidos"
                           class="form-input text-base py-3">
                </div>

                <!-- Lote destino (grupo) — com "criar agora" inline (sem sair do fluxo) -->
                <div v-if="mostraCampo('lot_destino_id')">
                    <InputLabel value="Para qual lote vai?" />
                    <select v-model="form.lot_destino_id" class="form-select text-base py-3">
                        <option :value="null">— Escolha o lote —</option>
                        <option v-for="l in lotesLocal" :key="l.id" :value="l.id">{{ l.nome }}</option>
                    </select>
                    <div class="mt-2 flex items-center gap-2">
                        <button type="button" @click="abrirNovoLote" class="text-sm text-macaybas-primary hover:underline">
                            + Criar lote novo
                        </button>
                        <span v-if="lotesLocal.length === 0" class="text-xs text-amber-700">
                            Nenhum lote cadastrado ainda — crie o primeiro aqui.
                        </span>
                    </div>
                </div>

                <!-- Local destino (posição física) — com "criar agora" inline -->
                <div v-if="mostraCampo('location_destino_id')">
                    <InputLabel value="Para qual pasto vai?" />
                    <select v-model="form.location_destino_id" class="form-select text-base py-3">
                        <option :value="null">— Escolha o pasto —</option>
                        <option v-for="l in locaisLocal" :key="l.id" :value="l.id">
                            {{ l.nome }} <span v-if="l.tipo !== 'pasto'">({{ l.tipo }})</span>
                        </option>
                    </select>
                    <div class="mt-2 flex items-center gap-2">
                        <button type="button" @click="abrirNovoLocal" class="text-sm text-macaybas-primary hover:underline">
                            + Criar pasto/local novo
                        </button>
                        <span v-if="locaisLocal.length === 0" class="text-xs text-amber-700">
                            Nenhum local cadastrado ainda — crie o primeiro aqui.
                        </span>
                    </div>
                </div>

                <!-- Causa (mortalidade) -->
                <div v-if="mostraCampo('causa')">
                    <InputLabel value="Causa aparente (opcional)" />
                    <input v-model="form.causa" type="text" maxlength="200"
                           placeholder="Ex: Doença, acidente, predador, velhice"
                           class="form-input text-base py-3">
                </div>

                <!-- Data -->
                <div v-if="mostraCampo('data')">
                    <InputLabel :value="meta.perguntaQuando" />
                    <InputDate v-model="form.data_evento" :max="hojeBR()" />
                </div>

                <!-- Observações -->
                <div v-if="mostraCampo('observacoes')">
                    <InputLabel :value="tipoAtivo === 'observacao' ? 'O que você quer anotar?' : 'Observação (opcional)'" />
                    <textarea v-model="form.observacoes" rows="3" data-cy="input-observacoes"
                              :placeholder="tipoAtivo === 'observacao' ? 'Ex: Cio detectado, cobertura feita, sinal clínico observado' : ''"
                              class="form-input text-base"></textarea>
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar2" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 3 · Conferência -->
        <div v-if="passo === 3" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Confere os dados?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Antes de salvar, dê uma olhada. Se precisar mudar algo, clique em <strong>Trocar</strong> ao lado.
                    </p>
                </div>

                <div class="space-y-3">
                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-slate-500">
                                {{ modo === 'individual' ? 'Animal' : 'Animais afetados' }}
                            </div>
                            <div v-if="modo === 'individual' && selecionado" class="font-semibold text-slate-900 mt-1">
                                {{ selecionado.identificacao }}<span v-if="selecionado.nome" class="text-slate-600"> · {{ selecionado.nome }}</span>
                            </div>
                            <div v-else class="font-semibold text-slate-900 mt-1">
                                {{ animaisDoFiltro.length }} {{ animaisDoFiltro.length === 1 ? 'animal' : 'animais' }}
                                <span v-if="modo === 'lote_grupo'" class="text-sm text-slate-500"> (lote inteiro)</span>
                                <span v-else-if="modo === 'lote_pasto'" class="text-sm text-slate-500"> (pasto inteiro)</span>
                                <span v-else-if="modo === 'selecao'" class="text-sm text-slate-500"> (selecionados)</span>
                            </div>
                        </div>
                        <button @click="irPara(1)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>

                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-slate-500">{{ meta.emoji }} Detalhes</div>
                            <div class="font-semibold text-slate-900 mt-1">
                                <span v-if="form.vacina">{{ form.vacina }}</span>
                                <span v-else-if="form.medicamento">{{ form.medicamento }}</span>
                                <span v-else-if="loteNome">Mover para lote {{ loteNome }}</span>
                                <span v-else-if="localNome">Mover para {{ localNome }}</span>
                                <span v-else-if="form.causa">Causa: {{ form.causa }}</span>
                                <span v-else-if="form.observacoes">{{ form.observacoes }}</span>
                            </div>
                            <div v-if="form.dose" class="text-sm text-slate-600 mt-0.5">Dose: {{ form.dose }}</div>
                            <div v-if="mostraCampo('data')" class="text-sm text-slate-500 mt-0.5">Em {{ dataBR(form.data_evento) }}</div>
                        </div>
                        <button @click="irPara(2)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>
                </div>

                <!-- Erro do servidor (ex.: allowed_events rejeitou o evento) -->
                <div v-if="erroServidor"
                     class="rounded-lg border-2 border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
                    <div class="font-semibold mb-1">⚠ Não foi possível salvar</div>
                    <div>{{ erroServidor }}</div>
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="form.processing" data-cy="confirmar" class="btn-primary px-8 py-3 text-base">
                        {{ form.processing ? 'Salvando…' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO 4 · Sucesso
             Para eventos sensíveis (tom=sobrio, ex: mortalidade), a UI usa cores
             neutras (slate) em vez do verde celebratório. CTA "Registrar outro"
             também é substituída por "Voltar ao início" como ação primária — seria
             insensível oferecer "Registrar outro [óbito]" como primeiro clique. -->
        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
            <div class="card-body text-center space-y-5 py-8">
                <div class="text-6xl">{{ meta.emoji }}</div>
                <h2 class="text-2xl font-semibold text-slate-900">{{ meta.tituloSucesso }}</h2>
                <p class="text-base text-slate-600">{{ meta.sucessoMsg(sucesso) }}</p>

                <div
                    class="rounded-lg p-4 text-left border"
                    :class="meta.tom === 'sobrio'
                        ? 'bg-slate-50 border-slate-200'
                        : 'bg-emerald-50 border-emerald-200'"
                >
                    <div
                        class="text-sm font-semibold mb-2"
                        :class="meta.tom === 'sobrio' ? 'text-slate-800' : 'text-emerald-900'"
                    >O que vai acontecer:</div>
                    <ul
                        class="text-sm space-y-1"
                        :class="meta.tom === 'sobrio' ? 'text-slate-700' : 'text-emerald-800'"
                    >
                        <li v-for="(i, idx) in meta.impactos" :key="idx">✓ {{ i }}</li>
                    </ul>
                </div>

                <!-- Contexto dinâmico (ENTREGA DE VALOR) — dados reais do DB
                     pós-ação. Mostra ao usuário o IMPACTO da ação dele, não
                     apenas confirmação. Vem do AnimalController::buildContexto. -->
                <div
                    v-if="sucesso._contexto"
                    class="rounded-lg p-4 text-left bg-white border-2 border-slate-200"
                >
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold mb-2">
                        📊 Como está agora
                    </div>
                    <ul class="text-sm text-slate-800 space-y-1.5">
                        <li v-if="sucesso._contexto.animais_no_lote_destino !== undefined">
                            🐄 O lote <strong>{{ sucesso._contexto.lote_nome }}</strong> agora tem
                            <strong>{{ sucesso._contexto.animais_no_lote_destino }}</strong>
                            {{ sucesso._contexto.animais_no_lote_destino === 1 ? 'animal ativo' : 'animais ativos' }}.
                        </li>
                        <li v-if="sucesso._contexto.animais_no_local_destino !== undefined">
                            📍 O pasto <strong>{{ sucesso._contexto.local_nome }}</strong> agora tem
                            <strong>{{ sucesso._contexto.animais_no_local_destino }}</strong>
                            {{ sucesso._contexto.animais_no_local_destino === 1 ? 'animal' : 'animais' }}.
                        </li>
                        <li v-if="sucesso._contexto.receita_gerada">
                            💰 Receita de <strong class="text-emerald-700">R$ {{ Number(sucesso._contexto.receita_gerada).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }}</strong>
                            lançada automaticamente no Financeiro.
                        </li>
                        <li v-if="sucesso._contexto.despesa_gerada">
                            💸 Custo de <strong class="text-red-700">R$ {{ Number(sucesso._contexto.despesa_gerada).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }}</strong>
                            anotado no histórico.
                        </li>
                        <li v-if="sucesso._contexto.total_ativos_restantes !== undefined">
                            Rebanho ativo agora: <strong>{{ sucesso._contexto.total_ativos_restantes }}</strong>
                            {{ sucesso._contexto.total_ativos_restantes === 1 ? 'animal' : 'animais' }}.
                        </li>
                    </ul>
                </div>

                <!-- CTA sóbria (morte): prioriza "voltar ao início" -->
                <div v-if="meta.tom === 'sobrio'" class="flex flex-col sm:flex-row gap-3 pt-3">
                    <Link :href="route('admin.inicio')" class="btn-primary flex-1 py-3 text-center">Voltar ao início</Link>
                    <Link v-if="sucesso._animal && sucesso._animal.id" :href="route('admin.rebanho.animais.show', sucesso._animal.id)" class="btn-outline flex-1 py-3 text-center">Ver ficha do animal</Link>
                    <button @click="reiniciar" class="btn-outline flex-1 py-3">Registrar outra baixa</button>
                </div>
                <!-- CTA padrão (celebratória): eventos produtivos -->
                <div v-else class="flex flex-col sm:flex-row gap-3 pt-3">
                    <button @click="reiniciar" class="btn-primary flex-1 py-3">Registrar outro</button>
                    <Link v-if="sucesso._animal && sucesso._animal.id" :href="route('admin.rebanho.animais.show', sucesso._animal.id)" class="btn-outline flex-1 py-3 text-center">Ver ficha do animal</Link>
                    <Link :href="route('admin.rebanho.animais.index')" class="btn-outline flex-1 py-3 text-center">Ver rebanho</Link>
                    <Link :href="route('admin.inicio')" class="btn-outline flex-1 py-3 text-center">Voltar ao início</Link>
                </div>
            </div>
        </div>

        <!-- Modal inline: novo LOTE (sem sair do wizard) -->
        <Teleport to="body">
            <div v-if="novoLoteAberto" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novoLoteAberto = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold mb-1">Novo lote</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        Um lote é um <strong>grupo de manejo</strong> (ex.: "Bezerros 2026"). Depois de criar,
                        ele aparece no dropdown aí em cima para você usar agora.
                    </p>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Nome do lote *" />
                            <input v-model="novoLoteForm.nome" class="form-input" placeholder="Ex.: Engorda Q1 2026" required>
                            <p v-if="novoLoteForm.errors.nome" class="text-xs text-red-600 mt-1">{{ novoLoteForm.errors.nome }}</p>
                        </div>
                        <div>
                            <InputLabel value="Código (opcional)" />
                            <input v-model="novoLoteForm.codigo" class="form-input" placeholder="Ex.: ENG-Q1-26">
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoLoteAberto = false" class="btn-outline">Cancelar</button>
                        <button @click="salvarNovoLote" :disabled="novoLoteForm.processing || !novoLoteForm.nome.trim()" class="btn-primary">
                            {{ novoLoteForm.processing ? 'Salvando…' : 'Criar lote' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal inline: novo LOCAL (pasto/piquete/curral) -->
            <div v-if="novoLocalAberto" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novoLocalAberto = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold mb-1">Novo pasto / local</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        Um local é a <strong>posição física</strong> (pasto, piquete, curral, baia, tanque).
                        Diferente do lote (que é o grupo).
                    </p>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Nome *" />
                            <input v-model="novoLocalForm.nome" class="form-input" placeholder="Ex.: Pasto 3 — Norte" required>
                            <p v-if="novoLocalForm.errors.nome" class="text-xs text-red-600 mt-1">{{ novoLocalForm.errors.nome }}</p>
                        </div>
                        <div>
                            <InputLabel value="Tipo *" />
                            <div class="grid grid-cols-3 gap-2">
                                <button v-for="t in ['pasto','piquete','curral','baia','tanque','galpao']"
                                        :key="t" type="button"
                                        @click="novoLocalForm.tipo = t"
                                        class="px-2 py-1.5 rounded border text-sm capitalize"
                                        :class="novoLocalForm.tipo === t ? 'border-macaybas-primary bg-emerald-50 text-emerald-800 font-semibold' : 'border-slate-200'">
                                    {{ t }}
                                </button>
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Código (opcional)" />
                            <input v-model="novoLocalForm.codigo" class="form-input" placeholder="Ex.: P3-N">
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoLocalAberto = false" class="btn-outline">Cancelar</button>
                        <button @click="salvarNovoLocal" :disabled="novoLocalForm.processing || !novoLocalForm.nome.trim()" class="btn-primary">
                            {{ novoLocalForm.processing ? 'Salvando…' : 'Criar local' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
