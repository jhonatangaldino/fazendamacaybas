<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useConfirm } from '@/composables/useConfirm.js';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';
import InputDecimal from '@/Components/InputDecimal.vue';

const { confirm: confirmModal } = useConfirm();
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMoney from '@/Components/InputMoney.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import AvatarUpload from '@/Components/AvatarUpload.vue';
import { dataBR, brl, hojeBR } from '@/utils/format.js';
import {
    Chart as ChartJS, LineElement, PointElement, LinearScale, TimeScale,
    CategoryScale, Tooltip, Legend, Title, Filler,
} from 'chart.js';
import { Line } from 'vue-chartjs';
ChartJS.register(LineElement, PointElement, LinearScale, CategoryScale, TimeScale, Tooltip, Legend, Title, Filler);

const props = defineProps({ animal: Object, events: Array, pesagens: Array, partners: Array, lots: Array, locations: { type: Array, default: () => [] } });

// Idade amigável em anos/meses/dias
const idadeFormatada = computed(() => {
    if (!props.animal?.data_nascimento) return '—';
    const nasc = new Date(props.animal.data_nascimento);
    const agora = new Date();
    const diffMs = agora - nasc;
    if (diffMs < 0) return '—';
    const dias = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    if (dias < 30) return `${dias} dia${dias === 1 ? '' : 's'}`;
    const meses = Math.floor(dias / 30.44);
    if (meses < 12) return `${meses} ${meses === 1 ? 'mês' : 'meses'}`;
    const anos = Math.floor(meses / 12);
    const mesesRestantes = meses % 12;
    if (mesesRestantes === 0) return `${anos} ano${anos === 1 ? '' : 's'}`;
    return `${anos} ano${anos === 1 ? '' : 's'} e ${mesesRestantes} ${mesesRestantes === 1 ? 'mês' : 'meses'}`;
});

const activeTab = ref('timeline'); // timeline | grafico | dados

// Modal de novo evento
//
// ⚠ IMPORTANTE: useForm do Inertia tem método interno chamado .data() que
// retorna o payload. NÃO podemos criar um campo chamado `data` porque ele
// sobrescreve o método → `TypeError: this.data is not a function` no submit.
// Renomeamos para `data_evento` e usamos transform() para enviar ao backend
// com a chave esperada (`data`).
const novoEvento = ref(false);
useBodyScrollLock(novoEvento);
const eventForm = useForm({
    tipo: 'pesagem',
    data_evento: hojeBR(),
    peso: '',
    vacina: '',
    medicamento: '',
    dose: '',
    via_aplicacao: '',
    responsavel: '',
    valor: '',
    partner_id: null,
    lot_origem_id: null,
    lot_destino_id: null,
    // LOCATION (pasto/piquete/curral) — evento separado de movimentação de LOTE
    location_origem_id: null,
    location_destino_id: null,
    observacoes: '',
    // Ordenha: array dinâmico de ordenhas {hora, litros} + total auto
    ordenhas: [{ hora: '08:00', litros: '' }],
    producao_litros: '',
    // Exame de toque
    gestacao_status: '',
    gestacao_dias: 0,
    data_prevista_parto: '',
});

// Helper para o modal de ordenha
const HORAS_PADRAO_MODAL = ['08:00', '15:00', '20:00', '04:00', '12:00', '18:00'];
const LABELS_ORDENHA = ['1ª', '2ª', '3ª', '4ª', '5ª', '6ª'];

function adicionarOrdenhaModal() {
    if (eventForm.ordenhas.length >= 6) return;
    eventForm.ordenhas.push({
        hora: HORAS_PADRAO_MODAL[eventForm.ordenhas.length] || '',
        litros: '',
    });
}
function removerOrdenhaModal(idx) {
    if (eventForm.ordenhas.length <= 1) return;
    eventForm.ordenhas.splice(idx, 1);
}
const totalOrdenhasModal = computed(() =>
    eventForm.ordenhas.reduce((acc, o) => acc + (parseFloat(o.litros) || 0), 0)
);

function abrirEvento(tipo = 'pesagem') {
    eventForm.reset();
    eventForm.tipo = tipo;
    eventForm.data_evento = hojeBR();
    // Ordenha inicia com 1ª ordenha às 08:00
    eventForm.ordenhas = [{ hora: '08:00', litros: '' }];
    novoEvento.value = true;
}

// Hub v3 — quando o usuário chega via Hub (ex: card "Registrar peso"),
// a URL vem com `?acao=pesar`. Auto-abrimos o modal de evento com o tipo correto.
// Mapeamento ação → tipo de evento (tipos aceitos pelo backend em
// AnimalController::storeEvent validator):
const MAPA_ACAO_HUB = {
    pesar:       'pesagem',
    vacinar:     'vacinacao',
    medicar:     'medicacao',
    vermifugar:  'vermifugacao',
    observar:    'observacao',
    mover:       'movimentacao',        // muda de LOTE (grupo lógico)
    mover_pasto: 'movimentacao_local',  // muda de PASTO (local físico)
    morte:       'mortalidade',
    ordenha:     'ordenha',
    alimentar:   'alimentacao',
};

onMounted(() => {
    const qs = new URLSearchParams(window.location.search);
    const acao = qs.get('acao');
    if (acao && MAPA_ACAO_HUB[acao]) {
        abrirEvento(MAPA_ACAO_HUB[acao]);
    }
    // Atalho ESC fecha o modal — UX padrão de qualquer modal
    window.addEventListener('keydown', handleEscape);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleEscape);
});

function handleEscape(e) {
    if (e.key === 'Escape' && novoEvento.value) {
        novoEvento.value = false;
    }
}

function salvarEvento() {
    eventForm
        .transform((d) => {
            // Renomeia data_evento → data para o backend (que espera 'data')
            const { data_evento, ...rest } = d;
            const payload = { ...rest, data: data_evento };

            // Tipo ordenha: filtra ordenhas com litros > 0, calcula total auto
            if (rest.tipo === 'ordenha') {
                const ordenhasValidas = rest.ordenhas
                    .map((o, idx) => ({
                        label: LABELS_ORDENHA[idx] || `${idx+1}ª`,
                        hora: o.hora || null,
                        litros: parseFloat(o.litros) || 0,
                    }))
                    .filter(o => o.litros > 0);
                payload.ordenhas = ordenhasValidas;
                payload.producao_litros = ordenhasValidas.reduce((acc, o) => acc + o.litros, 0).toFixed(2);
            } else {
                // Não-ordenha: limpa campos pra não enviar lixo
                delete payload.ordenhas;
                delete payload.producao_litros;
            }

            // Não-exame_toque: limpa campos de gestação
            if (rest.tipo !== 'exame_toque') {
                delete payload.gestacao_status;
                delete payload.gestacao_dias;
                delete payload.data_prevista_parto;
            }

            return payload;
        })
        .post(route('admin.rebanho.animais.eventos.store', props.animal.id), {
            preserveScroll: true,
            onSuccess: () => { novoEvento.value = false; eventForm.reset(); },
        });
}

async function removerEvento(event) {
    // "Apagar registro de X" funciona pra TODOS os tipos:
    //   pesagem, vacinação, mortalidade, venda, ordenha, postura, exame de toque…
    // "Remover pesagem" soava como reverter o ATO; aqui é claro que apaga
    // só o REGISTRO no histórico.
    const dataLegivel = event.data ? dataBR(event.data) : 'data desconhecida';
    const labelEvento = EVENT_CATALOG[event.tipo]?.label || event.tipo || 'evento';
    const ok = await confirmModal({
        title: `Apagar registro de ${labelEvento.toLowerCase()}?`,
        message: `Você vai apagar o registro de ${labelEvento.toLowerCase()} do dia ${dataLegivel}.\n\nIsso é permanente. Indicadores que dependem deste registro (peso atual, ganho médio, produção do mês, etc.) serão recalculados automaticamente.`,
        confirmText: 'Sim, apagar registro',
        cancelText: 'Cancelar',
        variant: 'danger',
    });
    if (! ok) return;
    router.delete(route('admin.rebanho.animais.eventos.destroy', [props.animal.id, event.id]), {
        preserveScroll: true,
    });
}

// Métricas · ordenação DEFENSIVA (não confia que props.pesagens já veio ordenado)
// Empate de data → desempata por id (pesagem cadastrada depois fica depois)
const pesagensOrdenadas = computed(() => {
    return [...props.pesagens].sort((a, b) => {
        if (a.data !== b.data) return a.data < b.data ? -1 : 1;
        return (a.id ?? 0) - (b.id ?? 0);
    });
});

const totalPesagens = computed(() => pesagensOrdenadas.value.length);

const primeiraPesagem = computed(() => pesagensOrdenadas.value[0] ?? null);
const ultimaPesagem = computed(() =>
    pesagensOrdenadas.value[pesagensOrdenadas.value.length - 1] ?? null,
);

const ganhoTotal = computed(() => {
    if (totalPesagens.value < 2) return null;
    // Diferença entre MAIS RECENTE e MAIS ANTIGA (pode ser negativa se animal perdeu peso)
    return ultimaPesagem.value.peso - primeiraPesagem.value.peso;
});

const ganhoMedioDiario = computed(() => {
    if (totalPesagens.value < 2) return null;
    const first = primeiraPesagem.value, last = ultimaPesagem.value;
    const dias = (new Date(last.data) - new Date(first.data)) / (1000 * 60 * 60 * 24);
    if (dias <= 0) return null;
    return (last.peso - first.peso) / dias;
});

const ganhoSinal = computed(() => {
    const g = ganhoTotal.value;
    if (g === null) return 'neutro';
    if (g > 0.01) return 'positivo';
    if (g < -0.01) return 'negativo';
    return 'neutro';
});

const diasEntrePesagens = computed(() => {
    if (totalPesagens.value < 2) return 0;
    const first = primeiraPesagem.value, last = ultimaPesagem.value;
    const dias = Math.round((new Date(last.data) - new Date(first.data)) / (1000 * 60 * 60 * 24));
    return dias > 0 ? dias : 0;
});

/**
 * Veredito de desempenho baseado em GMD + espécie + categoria.
 * Benchmarks realistas para bovino de corte em pastagem (referência:
 * EMBRAPA, 2019 — ganho médio diário esperado por fase).
 *
 * Para outras espécies/categorias, devolve classificação genérica
 * baseada no sinal (ganha/perde).
 */
const interpretacao = computed(() => {
    if (totalPesagens.value < 2) {
        return {
            tipo: 'info',
            titulo: 'Ainda sem avaliação',
            texto: 'Registre ao menos 2 pesagens em datas diferentes para o sistema avaliar o ganho.',
        };
    }

    const gmd = ganhoMedioDiario.value;
    if (gmd === null) return null;

    const especie = (props.animal?.species?.nome ?? '').toLowerCase();
    const categoria = (props.animal?.categoria ?? '').toLowerCase();
    const isBovino = especie.includes('bovino') || especie.includes('búfalo');
    const isCorte = categoria === 'corte' || categoria === 'misto';

    // PERDA (qualquer espécie)
    if (gmd < -0.01) {
        return {
            tipo: 'alerta',
            titulo: 'O animal está perdendo peso',
            texto: 'Isso é sinal de problema. Verifique alimentação, saúde e condições do pasto. Se continuar, chame o veterinário.',
        };
    }

    // GANHO - benchmarks por espécie
    if (isBovino && isCorte) {
        if (gmd < 0.3) {
            return {
                tipo: 'aviso',
                titulo: 'Ganho abaixo do esperado',
                texto: `${gmd.toFixed(3)} kg/dia é pouco para boi de corte. O ideal é acima de 0,7 kg/dia. Avalie a qualidade do pasto e a suplementação.`,
            };
        }
        if (gmd < 0.7) {
            return {
                tipo: 'regular',
                titulo: 'Ganho regular',
                texto: `${gmd.toFixed(3)} kg/dia — está ganhando peso, mas dá para melhorar. Boi de corte saudável em boa pastagem faz entre 0,7 e 1,2 kg/dia.`,
            };
        }
        if (gmd < 1.2) {
            return {
                tipo: 'bom',
                titulo: 'Ganho bom',
                texto: `${gmd.toFixed(3)} kg/dia está dentro do esperado para boi de corte em pastagem. Continue o manejo atual.`,
            };
        }
        if (gmd < 1.8) {
            return {
                tipo: 'excelente',
                titulo: 'Ganho excelente',
                texto: `${gmd.toFixed(3)} kg/dia é um ritmo ótimo — típico de confinamento ou pastagem de alta qualidade.`,
            };
        }
        return {
            tipo: 'excelente',
            titulo: 'Ganho excepcional',
            texto: `${gmd.toFixed(3)} kg/dia é muito alto. Confirme se as pesagens foram feitas corretamente — pode haver erro de medição.`,
        };
    }

    // Outras espécies — genérico positivo
    return {
        tipo: 'bom',
        titulo: 'O animal está ganhando peso',
        texto: `Ganhou em média ${gmd.toFixed(3)} kg por dia. Para ${especie || 'esta espécie'}, acompanhe a evolução ao longo do tempo.`,
    };
});

const interpretacaoClass = computed(() => ({
    alerta:    'bg-red-50 border-red-200 text-red-900',
    aviso:     'bg-amber-50 border-amber-200 text-amber-900',
    regular:   'bg-amber-50 border-amber-200 text-amber-900',
    bom:       'bg-emerald-50 border-emerald-200 text-emerald-900',
    excelente: 'bg-emerald-50 border-emerald-300 text-emerald-900',
    info:      'bg-slate-50 border-slate-200 text-slate-700',
}[interpretacao.value?.tipo] ?? 'bg-slate-50 border-slate-200 text-slate-700'));

const interpretacaoIcone = computed(() => ({
    alerta:    '🚨',
    aviso:     '⚠️',
    regular:   '⚖️',
    bom:       '✅',
    excelente: '🌟',
    info:      'ℹ️',
}[interpretacao.value?.tipo] ?? 'ℹ️'));

// ═══════ TABELA DROVET — estilos do card de avaliação ═══════
// status vem do backend: 'ok' | 'aviso' | 'alerta'
const drovetClass = computed(() => ({
    alerta: 'bg-red-50 border-red-300 text-red-900',
    aviso:  'bg-amber-50 border-amber-300 text-amber-900',
    ok:     'bg-emerald-50 border-emerald-300 text-emerald-900',
}[props.animal?.crescimento_drovet?.status] ?? 'bg-slate-50 border-slate-200 text-slate-700'));

const drovetIcone = computed(() => ({
    alerta: '🚨',
    aviso:  '⚠️',
    ok:     '🐄',
}[props.animal?.crescimento_drovet?.status] ?? 'ℹ️'));

const drovetBadgeClass = computed(() => ({
    alerta: 'bg-red-200 text-red-900',
    aviso:  'bg-amber-200 text-amber-900',
    ok:     'bg-emerald-200 text-emerald-900',
}[props.animal?.crescimento_drovet?.status] ?? 'bg-slate-200 text-slate-700'));

// Dados do gráfico — destaca último ponto (maior) + cor da linha conforme tendência
const chartData = computed(() => {
    const ps = pesagensOrdenadas.value;
    const pesos = ps.map(p => p.peso);
    const maior = Math.max(...pesos);
    const menor = Math.min(...pesos);
    const ultimo = ps.length - 1;

    // Tendência: linha verde se ganhando, vermelha se perdendo, slate se neutro
    const cor = ganhoSinal.value === 'negativo'
        ? { borda: '#b91c1c', fundo: 'rgba(185, 28, 28, 0.08)' }
        : { borda: '#166534', fundo: 'rgba(22, 101, 52, 0.1)' };

    return {
        labels: ps.map(p => dataBR(p.data)),
        datasets: [{
            label: 'Peso (kg)',
            data: pesos,
            borderColor: cor.borda,
            backgroundColor: cor.fundo,
            borderWidth: 2.5,
            tension: 0.25,
            fill: true,
            // Destaque do ponto: último = maior (verde/vermelho), maior = dourado, menor = laranja
            pointRadius: ps.map((p, i) => i === ultimo ? 9 : (p.peso === maior ? 7 : 5)),
            pointHoverRadius: 10,
            pointBackgroundColor: ps.map((p, i) =>
                i === ultimo ? cor.borda :
                (p.peso === maior ? '#f59e0b' :
                 p.peso === menor && p.peso !== maior ? '#f97316' : '#ffffff')
            ),
            pointBorderColor: ps.map((p, i) =>
                i === ultimo ? '#ffffff' : cor.borda
            ),
            pointBorderWidth: 2,
        }],
    };
});

const chartOptions = computed(() => {
    const ps = pesagensOrdenadas.value;
    const pesos = ps.map(p => p.peso);
    const maior = Math.max(...pesos);
    const menor = Math.min(...pesos);
    const ultimo = ps.length - 1;

    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (ctx) => {
                        const peso = ctx.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 1 });
                        const i = ctx.dataIndex;
                        let tag = '';
                        if (i === ultimo) tag = ' · ÚLTIMA pesagem';
                        else if (pesos[i] === maior) tag = ' · 🏆 maior peso';
                        else if (pesos[i] === menor) tag = ' · ⚠ menor peso';
                        return `${peso} kg${tag}`;
                    },
                },
            },
        },
        scales: {
            y: {
                ticks: { callback: (v) => `${v} kg` },
                grid: { color: 'rgba(148, 163, 184, 0.15)' },
            },
            x: { grid: { display: false } },
        },
    };
});

// ═══════ EVOLUÇÃO LEITEIRA · gráfico de produção ao longo do tempo ═══════
//
// Pega eventos tipo controle_leiteiro/ordenha com producao_litros e plota
// linha temporal. Só faz sentido pra fêmeas em manejo leiteiro.
const ordenhasOrdenadas = computed(() => {
    return [...props.events]
        .filter(e => (e.tipo === 'controle_leiteiro' || e.tipo === 'ordenha') && (e.producao_litros || (e.ordenhas && e.ordenhas.length)))
        .map(e => {
            // Soma litros: prefere producao_litros (cache); fallback array de ordenhas
            let litros = parseFloat(e.producao_litros || 0);
            if (! litros && Array.isArray(e.ordenhas)) {
                litros = e.ordenhas.reduce((acc, o) => acc + parseFloat(o.litros || 0), 0);
            }
            return { data: e.data, litros, tipo: e.tipo };
        })
        .filter(e => e.litros > 0)
        .sort((a, b) => a.data < b.data ? -1 : 1);
});

const totalOrdenhas = computed(() => ordenhasOrdenadas.value.length);
const totalLitrosTotal = computed(() => ordenhasOrdenadas.value.reduce((acc, e) => acc + e.litros, 0));
const mediaLitros = computed(() =>
    totalOrdenhas.value > 0 ? totalLitrosTotal.value / totalOrdenhas.value : 0
);

// Mostra a tab leiteira só pra fêmea com pelo menos 1 controle/ordenha
const temEvolucaoLeiteira = computed(() =>
    props.animal?.sexo === 'F' && totalOrdenhas.value >= 1
);

const chartLeiteData = computed(() => {
    const ords = ordenhasOrdenadas.value;
    const litros = ords.map(o => o.litros);
    const maior = Math.max(...litros, 0);
    const menor = Math.min(...litros, 0);
    const ultimo = ords.length - 1;

    return {
        labels: ords.map(o => dataBR(o.data)),
        datasets: [{
            label: 'Litros produzidos',
            data: litros,
            borderColor: '#0891b2',  // ciano (associado a leite/líquido)
            backgroundColor: 'rgba(8, 145, 178, 0.1)',
            borderWidth: 2.5,
            tension: 0.25,
            fill: true,
            pointRadius: ords.map((o, i) => i === ultimo ? 9 : (o.litros === maior ? 7 : 5)),
            pointHoverRadius: 10,
            pointBackgroundColor: ords.map((o, i) =>
                i === ultimo ? '#0891b2' :
                (o.litros === maior ? '#f59e0b' :
                 o.litros === menor && o.litros !== maior ? '#f97316' : '#ffffff')
            ),
            pointBorderColor: ords.map((o, i) => i === ultimo ? '#ffffff' : '#0891b2'),
            pointBorderWidth: 2,
        }],
    };
});

const chartLeiteOptions = computed(() => {
    const ords = ordenhasOrdenadas.value;
    const litros = ords.map(o => o.litros);
    const maior = Math.max(...litros, 0);
    const menor = Math.min(...litros, 0);
    const ultimo = ords.length - 1;
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (ctx) => {
                        const v = ctx.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 1 });
                        const i = ctx.dataIndex;
                        let tag = '';
                        if (i === ultimo) tag = ' · ÚLTIMO controle';
                        else if (litros[i] === maior) tag = ' · 🏆 maior produção';
                        else if (litros[i] === menor) tag = ' · ⚠ menor produção';
                        return `${v} L${tag}`;
                    },
                },
            },
        },
        scales: {
            y: {
                ticks: { callback: (v) => `${v} L` },
                grid: { color: 'rgba(148, 163, 184, 0.15)' },
            },
            x: { grid: { display: false } },
        },
    };
});

const eventoIcone = (tipo) => ({
    pesagem: '⚖️',
    vacinacao: '💉',
    medicacao: '💊',
    vermifugacao: '🧴',
    reproducao: '❤️',
    exame_toque: '🩺',
    secagem: '💧',
    controle_leiteiro: '🥛',
    ordenha: '🥛',
    ferrageamento: '🐎',
    tosquia: '✂️',
    castracao: '🔪',
    biometria_amostral: '🐟',
    qualidade_agua: '💧',
    alimentacao: '🌾',
    postura_diaria: '🥚',
    movimentacao: '🔄',         // mudança de LOTE (grupo)
    movimentacao_local: '📍',   // mudança de PASTO (local físico)
    observacao: '📝',
    nascimento: '🐣',
    morte: '⚰️',
    mortalidade: '⚰️',
    compra: '🛒',
    venda: '💰',
})[tipo] || '📌';

// Humaniza tipo técnico (snake_case) → label pt-BR. Antes a timeline mostrava
// "exame_toque", "controle_leiteiro", "vermifugacao" crus quando o tipo não
// estava mapeado — bug detectado pelo PO em ficha do animal.
const eventoLabel = (tipo) => ({
    pesagem: 'Pesagem',
    vacinacao: 'Vacinação',
    medicacao: 'Medicação',
    vermifugacao: 'Vermifugação',
    reproducao: 'Cobertura (cruzamento ou IA)',
    exame_toque: 'Exame de toque',
    secagem: 'Secagem',
    controle_leiteiro: 'Controle leiteiro',
    ordenha: 'Ordenha',
    ferrageamento: 'Ferrageamento',
    tosquia: 'Tosquia',
    castracao: 'Castração',
    biometria_amostral: 'Biometria amostral',
    qualidade_agua: 'Qualidade da água',
    alimentacao: 'Alimentação',
    postura_diaria: 'Postura',
    movimentacao: 'Mudança de lote',
    movimentacao_local: 'Mudança de pasto',
    observacao: 'Observação',
    nascimento: 'Nascimento',
    morte: 'Morte',
    mortalidade: 'Mortalidade',
    compra: 'Compra',
    venda: 'Venda',
})[tipo] || tipo.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

// ═══════ TIPOS DE EVENTO INTELIGENTES NO MODAL ═══════
//
// O backend já calcula `animal.acoes_rapidas` filtrado por:
//   • allowed_events da espécie (peixe não vacina, ave_postura não pesa)
//   • sexo do animal (macho não tem ordenha/exame_toque/secagem)
//   • idade reprodutiva (futuramente)
//
// Aqui combinamos:
//   1. tipos de acoes_rapidas (já filtrados — fonte da verdade do domínio)
//   2. + universais (ciclo de vida e logística — qualquer animal pode)
//
// Resultado: select do modal mostra APENAS o que faz sentido pra ESTE animal,
// nem 1 opção a mais, nem 1 a menos. Espelha o ACOES_CATALOGO do backend.
const TIPOS_UNIVERSAIS = [
    'observacao', 'movimentacao', 'movimentacao_local',
    'compra', 'venda', 'mortalidade', 'nascimento',
];

// Catálogo visual completo (label + emoji). Refletir aqui qualquer adição em ACOES_CATALOGO do backend.
const CATALOGO_TIPOS = {
    pesagem:             { emoji: '⚖️', label: 'Pesagem' },
    ordenha:             { emoji: '🥛', label: 'Ordenha (litros produzidos)' },
    vacinacao:           { emoji: '💉', label: 'Vacinação' },
    medicacao:           { emoji: '💊', label: 'Medicação' },
    vermifugacao:        { emoji: '🧴', label: 'Vermifugação' },
    reproducao:          { emoji: '❤️', label: 'Cobertura (cruzamento ou IA)' },
    exame_toque:         { emoji: '🩺', label: 'Exame de toque (palpação)' },
    secagem:             { emoji: '💧', label: 'Secagem' },
    ferrageamento:       { emoji: '🐎', label: 'Ferrageamento' },
    tosquia:             { emoji: '✂️', label: 'Tosquia' },
    castracao:           { emoji: '🔪', label: 'Castração' },
    biometria_amostral:  { emoji: '🐟', label: 'Biometria amostral' },
    qualidade_agua:      { emoji: '💧', label: 'Qualidade da água' },
    alimentacao:         { emoji: '🌾', label: 'Alimentação' },
    postura_diaria:      { emoji: '🥚', label: 'Postura' },
    movimentacao:        { emoji: '🔄', label: 'Mudar de lote (grupo)' },
    movimentacao_local:  { emoji: '📍', label: 'Mover de pasto (local físico)' },
    venda:               { emoji: '💰', label: 'Venda (encerra ciclo · gera receita)' },
    compra:              { emoji: '🛒', label: 'Compra' },
    mortalidade:         { emoji: '⚰️', label: 'Mortalidade (encerra ciclo)' },
    nascimento:          { emoji: '🐣', label: 'Nascimento' },
    observacao:          { emoji: '📝', label: 'Observação' },
};

// Ordem visual do select (mais usados primeiro, ciclo de vida no final)
const ORDEM_PREFERIDA = [
    'pesagem', 'ordenha', 'vacinacao', 'medicacao', 'vermifugacao',
    'reproducao', 'exame_toque', 'secagem',
    'ferrageamento', 'tosquia', 'castracao',
    'biometria_amostral', 'qualidade_agua', 'alimentacao', 'postura_diaria',
    'movimentacao', 'movimentacao_local',
    'venda', 'mortalidade', 'observacao',
];

const tiposPermitidosNoModal = computed(() => {
    const tiposEspecificos = (props.animal.acoes_rapidas || []).map(a => a.tipo);
    const set = new Set([...tiposEspecificos, ...TIPOS_UNIVERSAIS]);
    return ORDEM_PREFERIDA
        .filter(t => set.has(t) && CATALOGO_TIPOS[t])
        .map(t => ({ value: t, ...CATALOGO_TIPOS[t] }));
});

// Se o tipo selecionado deixar de fazer sentido (ex.: usuário trocou animal
// e o tipo atual não está mais na lista), fallback pra primeira opção válida.
watch(tiposPermitidosNoModal, (lista) => {
    if (! lista.length) return;
    const valido = lista.some(o => o.value === eventForm.tipo);
    if (! valido) eventForm.tipo = lista[0].value;
});
</script>

<template>
    <Head :title="`${animal.identificacao} — ${animal.nome || 'Animal'}`" />
    <AdminLayout>
        <template #page-title>Rebanho</template>

        <PageHeader :title="`${animal.identificacao}${animal.nome ? ' — ' + animal.nome : ''}`"
                    subtitle="Histórico completo e evolução do animal">
            <template #actions>
                <!-- Voltar respeita o filtro de espécie (lista filtrada) e Editar avisa
                     ao Form que veio do show (?from=show) → Cancel volta pro show. -->
                <Link :href="route('admin.rebanho.animais.index', { species_id: animal.species_id })" class="btn-outline">← Voltar</Link>
                <Link :href="route('admin.rebanho.animais.edit', { animal: animal.id, from: 'show' })" class="btn-outline">Editar cadastro</Link>
                <button @click="abrirEvento('pesagem')" class="btn-primary">+ Novo evento</button>
            </template>
        </PageHeader>

        <!-- Cabeçalho com foto editável + dados principais -->
        <div class="card mb-6">
            <div class="card-body flex flex-col sm:flex-row gap-6">
                <!-- Foto clicável: clique na imagem abre o seletor (mesmo componente do form) -->
                <div class="flex-shrink-0">
                    <AvatarUpload
                        :url="animal.photo_url"
                        :name="animal.identificacao"
                        size="h-40 w-40"
                        shape="square"
                        layout="stacked"
                        :upload-url="route('admin.rebanho.animais.foto.upload', animal.id)"
                        :remove-url="route('admin.rebanho.animais.foto.remove', animal.id)"
                    />
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 flex-1">
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Espécie</div><div class="font-medium">{{ animal.species?.nome || '—' }}</div></div>
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Raça</div><div class="font-medium">{{ animal.breed?.nome || '—' }}</div></div>
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Sexo</div><div class="font-medium">{{ animal.sexo === 'M' ? 'Macho' : 'Fêmea' }}</div></div>
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Idade</div><div class="font-medium">{{ idadeFormatada }}</div></div>
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Peso atual</div><div class="font-medium">{{ animal.peso_atual ? Number(animal.peso_atual).toLocaleString('pt-BR', { minimumFractionDigits: 1 }) + ' kg' : '—' }}</div></div>
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Categoria</div><div class="font-medium">{{ animal.categoria || '—' }}</div></div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-500">🐄 Lote <span class="normal-case text-slate-400">(grupo)</span></div>
                        <div class="font-medium">{{ animal.lot?.nome || '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-500">📍 Pasto <span class="normal-case text-slate-400">(local)</span></div>
                        <div class="font-medium">
                            <template v-if="animal.location">
                                {{ animal.location.nome }}
                                <span v-if="animal.location.tipo" class="text-xs text-slate-400">· {{ animal.location.tipo }}</span>
                            </template>
                            <template v-else>—</template>
                        </div>
                    </div>
                    <div><div class="text-xs uppercase tracking-wide text-slate-500">Status</div><div class="font-medium">{{ animal.status }}</div></div>
                </div>
            </div>
        </div>

        <!-- ═══ BADGES REPRODUTIVO/PRODUTIVO · prenhe, seca, produção ═══ -->
        <div v-if="animal.status_reprodutivo && (animal.status_reprodutivo.prenhe || animal.status_reprodutivo.secagem || animal.status_reprodutivo.producao_recente)"
             class="mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">

            <!-- Prenhez -->
            <div v-if="animal.status_reprodutivo.prenhe"
                 class="rounded-xl ring-2 ring-emerald-300 bg-gradient-to-br from-emerald-50 to-emerald-100 p-4">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">🤰</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-bold uppercase tracking-wider text-emerald-800">Prenhe</div>
                        <div class="text-sm font-semibold text-emerald-900 mt-0.5">
                            Parto previsto: <strong>{{ animal.status_reprodutivo.prenhe.dpp_br }}</strong>
                        </div>
                        <div class="text-xs text-emerald-700 mt-1">
                            <template v-if="animal.status_reprodutivo.prenhe.dias_para_parto > 0">
                                em <strong>{{ animal.status_reprodutivo.prenhe.dias_para_parto }} dias</strong>
                            </template>
                            <template v-else-if="animal.status_reprodutivo.prenhe.dias_para_parto === 0">
                                <strong>HOJE!</strong>
                            </template>
                            <template v-else>
                                parto previsto há {{ Math.abs(animal.status_reprodutivo.prenhe.dias_para_parto) }} dias — atrasado
                            </template>
                            · exame em {{ animal.status_reprodutivo.prenhe.data_exame_br }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vazia ou em dúvida -->
            <div v-else-if="animal.status_reprodutivo.ultimo_toque?.status === 'vazia'"
                 class="rounded-xl ring-1 ring-slate-200 bg-slate-50 p-4">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">⚪</span>
                    <div class="flex-1">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-700">Vazia</div>
                        <div class="text-sm text-slate-700 mt-0.5">
                            Último toque: {{ animal.status_reprodutivo.ultimo_toque.data_br }}
                        </div>
                    </div>
                </div>
            </div>
            <div v-else-if="animal.status_reprodutivo.ultimo_toque?.status === 'duvida'"
                 class="rounded-xl ring-2 ring-amber-300 bg-amber-50 p-4">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">⚠️</span>
                    <div class="flex-1">
                        <div class="text-xs font-bold uppercase tracking-wider text-amber-800">Em dúvida</div>
                        <div class="text-sm text-amber-900 mt-0.5">Refazer exame</div>
                        <div class="text-xs text-amber-700 mt-1">{{ animal.status_reprodutivo.ultimo_toque.data_br }}</div>
                    </div>
                </div>
            </div>

            <!-- Secagem ativa -->
            <div v-if="animal.status_reprodutivo.secagem"
                 class="rounded-xl ring-2 ring-sky-300 bg-gradient-to-br from-sky-50 to-sky-100 p-4">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">💧</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-bold uppercase tracking-wider text-sky-800">Vaca seca</div>
                        <div class="text-sm font-semibold text-sky-900 mt-0.5">
                            Secagem em <strong>{{ animal.status_reprodutivo.secagem.data_br }}</strong>
                        </div>
                        <div class="text-xs text-sky-700 mt-1">
                            há {{ animal.status_reprodutivo.secagem.dias_atras }} dia(s)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Produção recente (só se NÃO está seca) -->
            <div v-if="animal.status_reprodutivo.producao_recente && !animal.status_reprodutivo.secagem"
                 class="rounded-xl ring-2 ring-amber-200 bg-gradient-to-br from-amber-50 to-yellow-50 p-4">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">🥛</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-bold uppercase tracking-wider text-amber-800">Última produção</div>
                        <div class="text-sm font-semibold text-amber-900 mt-0.5">
                            <strong>{{ animal.status_reprodutivo.producao_recente.litros.toFixed(1) }} L</strong>
                        </div>
                        <div class="text-xs text-amber-700 mt-1">em {{ animal.status_reprodutivo.producao_recente.data_br }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ AÇÕES RÁPIDAS · filtradas pela espécie ═══ -->
        <div v-if="animal.acoes_rapidas && animal.acoes_rapidas.length > 0" class="mb-6 rounded-xl bg-white ring-1 ring-slate-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">
                    ⚡ Ações rápidas para {{ animal.species?.nome?.toLowerCase() || 'este animal' }}
                </h3>
                <span class="text-xs text-slate-500">{{ animal.acoes_rapidas.length }} disponível(is)</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                <Link
                    v-for="acao in animal.acoes_rapidas.filter(a => a.url)"
                    :key="acao.tipo"
                    :href="acao.url"
                    class="block rounded-lg ring-1 ring-slate-200 hover:ring-macaybas-primary hover:bg-macaybas-primary-50 p-3 transition group"
                >
                    <div class="flex items-start gap-2">
                        <span class="text-2xl flex-shrink-0">{{ acao.emoji }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-sm text-slate-900 group-hover:text-macaybas-primary-900">{{ acao.label }}</div>
                            <div class="text-xs text-slate-500 mt-0.5 line-clamp-2">{{ acao.desc }}</div>
                        </div>
                    </div>
                </Link>
                <button
                    v-for="acao in animal.acoes_rapidas.filter(a => !a.url)"
                    :key="`m-${acao.tipo}`"
                    type="button"
                    @click="abrirEvento(acao.tipo)"
                    class="text-left block rounded-lg ring-1 ring-slate-200 hover:ring-macaybas-primary hover:bg-macaybas-primary-50 p-3 transition group"
                >
                    <div class="flex items-start gap-2">
                        <span class="text-2xl flex-shrink-0">{{ acao.emoji }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-sm text-slate-900 group-hover:text-macaybas-primary-900">{{ acao.label }}</div>
                            <div class="text-xs text-slate-500 mt-0.5 line-clamp-2">{{ acao.desc }}</div>
                        </div>
                    </div>
                </button>
            </div>
            <p class="text-xs text-slate-500 mt-3">
                💡 As ações disponíveis variam por espécie — peixe não vacina, cavalo não ordenha, e por aí vai.
            </p>
        </div>

        <!-- ═══ VEREDITO · interpretação em linguagem natural ═══ -->
        <div v-if="interpretacao"
             class="mb-4 rounded-xl border-2 px-5 py-4 flex items-start gap-3"
             :class="interpretacaoClass">
            <div class="text-3xl leading-none flex-shrink-0" aria-hidden="true">
                {{ interpretacaoIcone }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-base leading-tight">{{ interpretacao.titulo }}</div>
                <div class="text-sm mt-1 leading-relaxed">{{ interpretacao.texto }}</div>
            </div>
        </div>

        <!-- ═══ TABELA DROVET · crescimento de fêmea leiteira (só p/ raças leiteiras) ═══ -->
        <div v-if="animal.crescimento_drovet"
             class="mb-4 rounded-xl border-2 px-5 py-4"
             :class="drovetClass">
            <div class="flex items-start gap-3">
                <div class="text-3xl leading-none flex-shrink-0" aria-hidden="true">
                    {{ drovetIcone }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-base leading-tight">{{ animal.crescimento_drovet.titulo }}</span>
                        <span class="text-xs font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full"
                              :class="drovetBadgeClass">
                            {{ animal.crescimento_drovet.fase_label }}
                        </span>
                    </div>
                    <div class="text-sm mt-2 leading-relaxed">{{ animal.crescimento_drovet.texto }}</div>

                    <!-- Mini "régua" peso atual vs alvo -->
                    <div class="mt-3 grid grid-cols-3 gap-3 text-sm">
                        <div class="rounded-md bg-white/60 ring-1 ring-current/10 p-2">
                            <div class="text-xs uppercase tracking-wide opacity-70">Peso atual</div>
                            <div class="font-bold text-lg">{{ animal.crescimento_drovet.peso_atual.toLocaleString('pt-BR', { minimumFractionDigits: 1 }) }} kg</div>
                        </div>
                        <div class="rounded-md bg-white/60 ring-1 ring-current/10 p-2">
                            <div class="text-xs uppercase tracking-wide opacity-70">Peso-alvo DROVET</div>
                            <div class="font-bold text-lg">{{ animal.crescimento_drovet.peso_alvo }} kg</div>
                        </div>
                        <div class="rounded-md bg-white/60 ring-1 ring-current/10 p-2">
                            <div class="text-xs uppercase tracking-wide opacity-70">Desvio</div>
                            <div class="font-bold text-lg">
                                {{ animal.crescimento_drovet.desvio_kg >= 0 ? '+' : '' }}{{ animal.crescimento_drovet.desvio_kg.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) }} kg
                                <span class="text-xs opacity-70">({{ animal.crescimento_drovet.desvio_pct >= 0 ? '+' : '' }}{{ animal.crescimento_drovet.desvio_pct }}%)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Faixa de cobrição quando aplicável -->
                    <div v-if="animal.crescimento_drovet.cobricao" class="mt-2 text-xs opacity-80">
                        🎯 Faixa de cobrição para {{ animal.crescimento_drovet.tamanho_label.split('(')[0].trim().toLowerCase() }}:
                        <strong>{{ animal.crescimento_drovet.cobricao.min }}–{{ animal.crescimento_drovet.cobricao.max }} kg</strong>
                    </div>

                    <div class="mt-2 text-xs opacity-70">
                        Referência DROVET+ · raça {{ animal.crescimento_drovet.breed_nome }} ({{ animal.crescimento_drovet.tamanho_label.split('(')[0].trim().toLowerCase() }}) ·
                        {{ animal.crescimento_drovet.idade_meses }} {{ animal.crescimento_drovet.idade_meses === 1 ? 'mês' : 'meses' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ KPIs redesenhados para usuário leigo ═══ -->
        <div class="grid gap-4 sm:grid-cols-3 mb-6">

            <!-- CARD 1 · Pesagens registradas -->
            <div class="card p-5">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs uppercase tracking-wider font-semibold text-slate-500">
                        Pesagens registradas
                    </div>
                    <div class="text-2xl leading-none" aria-hidden="true">⚖️</div>
                </div>
                <div class="text-3xl font-bold text-slate-900 leading-tight">
                    {{ totalPesagens }}
                    <span v-if="totalPesagens > 0" class="text-base font-medium text-slate-500">
                        {{ totalPesagens === 1 ? 'vez' : 'vezes' }}
                    </span>
                </div>
                <div v-if="totalPesagens >= 2" class="text-sm text-slate-600 mt-3 leading-relaxed">
                    De <strong>{{ dataBR(primeiraPesagem.data) }}</strong>
                    até <strong>{{ dataBR(ultimaPesagem.data) }}</strong>
                </div>
                <div v-else-if="totalPesagens === 1" class="text-sm text-amber-700 mt-3 leading-relaxed">
                    Faça outra pesagem para o sistema calcular o ganho de peso.
                </div>
                <div v-else class="text-sm text-slate-500 mt-3 leading-relaxed">
                    Clique em <strong>+ Novo evento</strong> para registrar a primeira pesagem.
                </div>
            </div>

            <!-- CARD 2 · Ganho/Perda total com contexto forte -->
            <div class="card p-5 relative overflow-hidden"
                 :class="{
                    'ring-2 ring-emerald-100': ganhoSinal === 'positivo',
                    'ring-2 ring-red-100':     ganhoSinal === 'negativo',
                 }">
                <!-- Barra colorida lateral para reforço visual -->
                <div v-if="ganhoSinal !== 'neutro'" class="absolute left-0 top-0 bottom-0 w-1"
                     :class="ganhoSinal === 'positivo' ? 'bg-emerald-500' : 'bg-red-500'"></div>

                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs uppercase tracking-wider font-semibold"
                         :class="{
                            'text-emerald-700': ganhoSinal === 'positivo',
                            'text-red-700':     ganhoSinal === 'negativo',
                            'text-slate-500':   ganhoSinal === 'neutro',
                         }">
                        {{ ganhoSinal === 'negativo' ? 'Perdeu peso' : ganhoSinal === 'positivo' ? 'Ganhou peso' : 'Ganho de peso' }}
                    </div>
                    <div class="text-2xl leading-none" aria-hidden="true">
                        {{ ganhoSinal === 'positivo' ? '📈' : ganhoSinal === 'negativo' ? '📉' : '—' }}
                    </div>
                </div>
                <div class="text-3xl font-bold leading-tight"
                     :class="{
                        'text-emerald-700': ganhoSinal === 'positivo',
                        'text-red-700':     ganhoSinal === 'negativo',
                        'text-slate-400':   ganhoSinal === 'neutro',
                     }">
                    <template v-if="ganhoTotal != null">
                        {{ ganhoTotal >= 0 ? '+' : '' }}{{ ganhoTotal.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) }}
                        <span class="text-base font-medium">kg</span>
                    </template>
                    <template v-else>—</template>
                </div>

                <div v-if="ganhoTotal != null && primeiraPesagem && ultimaPesagem"
                     class="text-sm text-slate-600 mt-3 leading-relaxed">
                    De <strong>{{ primeiraPesagem.peso.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) }} kg</strong>
                    em {{ dataBR(primeiraPesagem.data) }}<br>
                    para <strong>{{ ultimaPesagem.peso.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) }} kg</strong>
                    em {{ dataBR(ultimaPesagem.data) }}
                </div>
                <div v-if="ganhoTotal != null" class="text-xs text-slate-500 mt-2 pt-2 border-t border-slate-100">
                    Baseado em <strong>{{ totalPesagens }}</strong> {{ totalPesagens === 1 ? 'pesagem' : 'pesagens' }}
                    durante <strong>{{ diasEntrePesagens }}</strong> {{ diasEntrePesagens === 1 ? 'dia' : 'dias' }}.
                </div>
                <div v-else-if="totalPesagens < 2" class="text-sm text-slate-400 mt-3">
                    Disponível após a 2ª pesagem.
                </div>
            </div>

            <!-- CARD 3 · Média por dia -->
            <div class="card p-5">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs uppercase tracking-wider font-semibold text-slate-500">
                        Média por dia
                    </div>
                    <div class="text-2xl leading-none" aria-hidden="true">📅</div>
                </div>
                <template v-if="ganhoMedioDiario != null">
                    <div class="text-3xl font-bold leading-tight"
                         :class="{
                            'text-macaybas-primary': ganhoSinal === 'positivo',
                            'text-red-700':          ganhoSinal === 'negativo',
                            'text-slate-400':        ganhoSinal === 'neutro',
                         }">
                        {{ ganhoMedioDiario >= 0 ? '+' : '' }}{{ ganhoMedioDiario.toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }}
                        <span class="text-base font-medium">kg</span>
                    </div>
                    <div class="text-sm text-slate-600 mt-3 leading-relaxed">
                        {{ ganhoSinal === 'negativo' ? 'Perdeu' : 'Ganhou' }}
                        <strong>{{ Math.abs(ganhoMedioDiario).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }} kg por dia</strong>,
                        em média, durante <strong>{{ diasEntrePesagens }}</strong> {{ diasEntrePesagens === 1 ? 'dia' : 'dias' }}.
                    </div>
                </template>
                <template v-else>
                    <div class="text-2xl font-semibold text-slate-400 leading-tight">
                        Sem cálculo
                    </div>
                    <div class="text-sm text-slate-500 mt-3 leading-relaxed">
                        {{ totalPesagens === 0
                           ? 'Nenhuma pesagem registrada ainda.'
                           : 'Dados insuficientes: precisamos de pelo menos 2 pesagens em datas diferentes para calcular a média.' }}
                    </div>
                </template>
            </div>
        </div>

        <!-- Rodapé discreto explicando o cálculo (só quando há ganho/perda calculados) -->
        <div v-if="ganhoTotal != null" class="text-xs text-slate-500 mb-6 -mt-4 flex items-start gap-1.5">
            <svg class="h-3.5 w-3.5 mt-0.5 flex-shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>
                Os cálculos consideram a <strong>primeira</strong> e a <strong>última</strong> pesagem
                ({{ totalPesagens }} no total). A média é simples: diferença dividida pelos dias entre elas.
            </span>
        </div>

        <!-- Tabs -->
        <div class="border-b border-slate-200 mb-4 flex gap-6">
            <button @click="activeTab = 'timeline'"
                    :class="activeTab === 'timeline' ? 'border-macaybas-primary text-macaybas-primary' : 'border-transparent text-slate-500 hover:text-slate-800'"
                    class="py-3 border-b-2 font-medium transition-colors">
                📋 Linha do tempo
            </button>
            <button @click="activeTab = 'grafico'"
                    :class="activeTab === 'grafico' ? 'border-macaybas-primary text-macaybas-primary' : 'border-transparent text-slate-500 hover:text-slate-800'"
                    class="py-3 border-b-2 font-medium transition-colors">
                📈 Evolução de peso
            </button>
            <!-- Tab leiteira aparece SÓ pra fêmea com pelo menos 1 controle/ordenha -->
            <button v-if="temEvolucaoLeiteira" @click="activeTab = 'leite'"
                    :class="activeTab === 'leite' ? 'border-macaybas-primary text-macaybas-primary' : 'border-transparent text-slate-500 hover:text-slate-800'"
                    class="py-3 border-b-2 font-medium transition-colors">
                🥛 Evolução leiteira
            </button>
        </div>

        <!-- Timeline -->
        <div v-if="activeTab === 'timeline'" class="card">
            <div class="card-body">
                <div v-if="!events.length" class="text-center py-10 text-slate-500">
                    Nenhum evento registrado ainda. Clique em <strong>+ Novo evento</strong> pra começar o histórico.
                </div>
                <ol v-else class="relative border-l-2 border-slate-200 ml-3 space-y-6">
                    <li v-for="e in events" :key="e.id" class="ml-6">
                        <span class="absolute -left-[13px] flex items-center justify-center w-6 h-6 bg-white rounded-full ring-2 ring-macaybas-primary-100 text-sm">
                            {{ eventoIcone(e.tipo) }}
                        </span>
                        <div class="flex items-start justify-between gap-3 p-4 bg-slate-50 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-semibold text-slate-900">{{ eventoLabel(e.tipo) }}</span>
                                    <span class="text-xs text-slate-500">{{ dataBR(e.data) }}</span>
                                </div>
                                <div class="grid sm:grid-cols-2 gap-2 text-sm text-slate-600">
                                    <div v-if="e.peso">Peso: <strong>{{ Number(e.peso).toLocaleString('pt-BR', { minimumFractionDigits: 1 }) }} kg</strong></div>
                                    <div v-if="e.vacina">Vacina: {{ e.vacina }}</div>
                                    <div v-if="e.medicamento">Medicamento: {{ e.medicamento }}</div>
                                    <div v-if="e.dose">Dose: {{ e.dose }} {{ e.via_aplicacao || '' }}</div>
                                    <div v-if="e.responsavel">Responsável: {{ e.responsavel }}</div>
                                    <div v-if="e.valor">Valor: <strong class="text-red-700">{{ brl(e.valor) }}</strong></div>
                                    <div v-if="e.partner">Parceiro: {{ e.partner.nome }}</div>
                                    <div v-if="e.lot_origem || e.lot_destino" class="sm:col-span-2">
                                        🐄 Lote: {{ e.lot_origem?.nome || '—' }} → <strong>{{ e.lot_destino?.nome || '—' }}</strong>
                                    </div>
                                    <div v-if="e.location_origem || e.location_destino" class="sm:col-span-2">
                                        📍 Pasto: {{ e.location_origem?.nome || '—' }} → <strong>{{ e.location_destino?.nome || '—' }}</strong>
                                    </div>
                                </div>
                                <p v-if="e.observacoes" class="mt-2 text-sm text-slate-500 italic">{{ e.observacoes }}</p>
                                <p v-if="e.creator" class="mt-1 text-xs text-slate-400">Registrado por {{ e.creator.name }}</p>
                            </div>
                            <ActionIcon type="delete" size="sm" title="Remover evento" @click="removerEvento(e)" />
                        </div>
                    </li>
                </ol>
            </div>
        </div>

        <!-- Gráfico -->
        <div v-if="activeTab === 'grafico'" class="card">
            <div class="card-body">
                <div v-if="pesagens.length < 2" class="text-center py-10 text-slate-500">
                    São necessárias pelo menos 2 pesagens para exibir o gráfico de evolução.
                </div>
                <div v-else class="h-80">
                    <Line :data="chartData" :options="chartOptions" />
                </div>
            </div>
        </div>

        <!-- Evolução leiteira · só pra fêmea em manejo leiteiro -->
        <div v-if="activeTab === 'leite'" class="card">
            <div class="card-body">
                <!-- KPIs leiteiros antes do gráfico -->
                <div class="grid grid-cols-3 gap-3 mb-5">
                    <div class="rounded-lg bg-cyan-50 ring-1 ring-cyan-100 p-4">
                        <div class="text-xs uppercase tracking-wider font-semibold text-cyan-800">Total registrado</div>
                        <div class="text-2xl font-bold text-cyan-900 mt-1">
                            {{ totalLitrosTotal.toLocaleString('pt-BR', { minimumFractionDigits: 1 }) }}
                            <span class="text-sm font-medium">L</span>
                        </div>
                        <div class="text-xs text-cyan-700 mt-1">{{ totalOrdenhas }} controle(s)</div>
                    </div>
                    <div class="rounded-lg bg-emerald-50 ring-1 ring-emerald-100 p-4">
                        <div class="text-xs uppercase tracking-wider font-semibold text-emerald-800">Média por controle</div>
                        <div class="text-2xl font-bold text-emerald-900 mt-1">
                            {{ mediaLitros.toLocaleString('pt-BR', { minimumFractionDigits: 1 }) }}
                            <span class="text-sm font-medium">L</span>
                        </div>
                        <div class="text-xs text-emerald-700 mt-1">média histórica</div>
                    </div>
                    <div class="rounded-lg bg-amber-50 ring-1 ring-amber-100 p-4">
                        <div class="text-xs uppercase tracking-wider font-semibold text-amber-800">Maior produção</div>
                        <div class="text-2xl font-bold text-amber-900 mt-1">
                            {{ Math.max(...ordenhasOrdenadas.map(o => o.litros), 0).toLocaleString('pt-BR', { minimumFractionDigits: 1 }) }}
                            <span class="text-sm font-medium">L</span>
                        </div>
                        <div class="text-xs text-amber-700 mt-1">pico histórico</div>
                    </div>
                </div>

                <div v-if="totalOrdenhas < 2" class="text-center py-10 text-slate-500">
                    São necessários pelo menos 2 controles leiteiros para exibir o gráfico de evolução.
                </div>
                <div v-else class="h-80">
                    <Line :data="chartLeiteData" :options="chartLeiteOptions" />
                </div>
            </div>
        </div>

        <!-- Modal: Novo evento -->
        <Teleport to="body">
            <div v-if="novoEvento" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novoEvento = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <h3 class="text-lg font-semibold text-slate-900 mb-4">Novo evento — {{ animal.identificacao }}</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <InputLabel value="Tipo de evento *" />
                            <select v-model="eventForm.tipo" class="form-select">
                                <option v-for="opt in tiposPermitidosNoModal" :key="opt.value" :value="opt.value">
                                    {{ opt.emoji }} {{ opt.label }}
                                </option>
                            </select>
                            <p class="text-xs text-slate-500 mt-1">
                                Lista filtrada para <strong>{{ animal.species?.nome?.toLowerCase() || 'esta espécie' }}</strong>
                                {{ animal.sexo === 'F' ? '· fêmea' : '· macho' }} —
                                {{ tiposPermitidosNoModal.length }} {{ tiposPermitidosNoModal.length === 1 ? 'tipo aplicável' : 'tipos aplicáveis' }}.
                            </p>
                        </div>
                        <div>
                            <InputLabel value="Data *" />
                            <InputDate v-model="eventForm.data_evento" :max="hojeBR()" required />
                            <p v-if="eventForm.errors.data" class="text-xs text-red-600 mt-1">{{ eventForm.errors.data }}</p>
                        </div>

                        <!-- Pesagem -->
                        <div v-if="eventForm.tipo === 'pesagem'">
                            <InputLabel value="Peso (kg) *" />
                            <InputDecimal v-model="eventForm.peso" :decimals="2" :min="0" placeholder="0,00" required />
                            <p v-if="eventForm.errors.peso" class="text-xs text-red-600 mt-1">{{ eventForm.errors.peso }}</p>
                        </div>

                        <!-- Ordenha — array dinâmico de ordenhas (hora + litros) -->
                        <div v-if="eventForm.tipo === 'ordenha'" class="sm:col-span-2 space-y-2">
                            <div class="flex items-center justify-between">
                                <InputLabel :value="`Ordenhas do dia (${eventForm.ordenhas.length})`" />
                                <span class="text-sm font-bold text-emerald-700">
                                    Total: {{ totalOrdenhasModal.toFixed(1) }} L
                                </span>
                            </div>
                            <div v-for="(o, idx) in eventForm.ordenhas" :key="idx" class="flex gap-2 items-end">
                                <div class="flex-shrink-0 w-9 text-center pb-2 text-sm font-semibold text-slate-600">
                                    {{ LABELS_ORDENHA[idx] || `${idx+1}ª` }}
                                </div>
                                <div class="flex-shrink-0">
                                    <label class="block text-[10px] font-medium text-slate-500 mb-0.5">Hora</label>
                                    <input v-model="o.hora" type="time" class="px-2 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-sm font-mono w-[100px]">
                                </div>
                                <div class="flex-1 relative">
                                    <label class="block text-[10px] font-medium text-slate-500 mb-0.5">Litros</label>
                                    <InputDecimal v-model="o.litros" :decimals="1" :min="0" :max="99.99" placeholder="0,0" input-class="w-full px-3 py-2 pr-8 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-sm font-mono text-right" />
                                    <span class="absolute right-3 top-7 text-xs text-slate-400">L</span>
                                </div>
                                <button v-if="eventForm.ordenhas.length > 1" type="button" @click="removerOrdenhaModal(idx)" class="flex-shrink-0 w-9 h-10 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center" title="Remover">×</button>
                            </div>
                            <button v-if="eventForm.ordenhas.length < 6" type="button" @click="adicionarOrdenhaModal" class="inline-flex items-center min-h-9 px-3 py-2 text-xs text-macaybas-primary hover:bg-macaybas-primary-50 rounded-md font-medium">
                                + adicionar ordenha (2ª, 3ª…)
                            </button>
                            <p class="text-xs text-slate-500 mt-1">Cada ordenha tem horário próprio. Total do dia é calculado automaticamente.</p>
                        </div>

                        <!-- Exame de toque — diagnóstico de gestação -->
                        <template v-if="eventForm.tipo === 'exame_toque'">
                            <div class="sm:col-span-2">
                                <InputLabel value="Resultado *" />
                                <div class="grid grid-cols-3 gap-2 mt-1">
                                    <button type="button" @click="eventForm.gestacao_status = 'prenhe'"
                                        class="px-3 py-2 rounded-lg ring-2 text-sm font-semibold transition"
                                        :class="eventForm.gestacao_status === 'prenhe' ? 'bg-emerald-100 ring-emerald-300 text-emerald-900' : 'bg-white ring-slate-200 text-slate-600'">
                                        🤰 Prenhe
                                    </button>
                                    <button type="button" @click="eventForm.gestacao_status = 'vazia'"
                                        class="px-3 py-2 rounded-lg ring-2 text-sm font-semibold transition"
                                        :class="eventForm.gestacao_status === 'vazia' ? 'bg-slate-100 ring-slate-400 text-slate-900' : 'bg-white ring-slate-200 text-slate-600'">
                                        ⚪ Vazia
                                    </button>
                                    <button type="button" @click="eventForm.gestacao_status = 'duvida'"
                                        class="px-3 py-2 rounded-lg ring-2 text-sm font-semibold transition"
                                        :class="eventForm.gestacao_status === 'duvida' ? 'bg-amber-100 ring-amber-300 text-amber-900' : 'bg-white ring-slate-200 text-slate-600'">
                                        ⚠️ Em dúvida
                                    </button>
                                </div>
                            </div>
                            <div v-if="eventForm.gestacao_status === 'prenhe'">
                                <InputLabel value="Dias de gestação" />
                                <input type="number" min="0" max="340" v-model.number="eventForm.gestacao_dias" class="form-input" placeholder="Ex: 60">
                                <p class="text-xs text-slate-500 mt-1">Sistema calcula DPP automaticamente.</p>
                            </div>
                            <div v-if="eventForm.gestacao_status === 'prenhe'">
                                <InputLabel value="Data prevista parto" />
                                <input type="date" v-model="eventForm.data_prevista_parto" class="form-input">
                            </div>
                        </template>

                        <!-- Secagem — sem campo extra além de obs e medicamento (opcional) -->
                        <div v-if="eventForm.tipo === 'secagem'" class="sm:col-span-2">
                            <InputLabel value="Medicamento aplicado (opcional)" />
                            <input v-model="eventForm.medicamento" class="form-input" placeholder="Ex: Mamivete LA, Cefalonium...">
                        </div>

                        <!-- Vermifugação -->
                        <template v-if="eventForm.tipo === 'vermifugacao'">
                            <div class="sm:col-span-2"><InputLabel value="Vermífugo" /><input v-model="eventForm.medicamento" class="form-input" placeholder="Ex: Ivermectina"></div>
                            <div><InputLabel value="Dose (ml)" /><InputDecimal v-model="eventForm.dose" :decimals="2" :min="0" placeholder="0,00" /></div>
                        </template>

                        <!-- Vacinação -->
                        <template v-if="eventForm.tipo === 'vacinacao'">
                            <div class="sm:col-span-2"><InputLabel value="Vacina *" /><input v-model="eventForm.vacina" class="form-input" placeholder="Ex: Febre Aftosa"></div>
                            <div><InputLabel value="Dose (ml)" /><InputDecimal v-model="eventForm.dose" :decimals="2" :min="0" placeholder="0,00" /></div>
                            <div><InputLabel value="Via aplicação" /><input v-model="eventForm.via_aplicacao" class="form-input" placeholder="Ex: subcutânea"></div>
                        </template>

                        <!-- Medicação -->
                        <template v-if="eventForm.tipo === 'medicacao'">
                            <div class="sm:col-span-2"><InputLabel value="Medicamento *" /><input v-model="eventForm.medicamento" class="form-input" placeholder="Ex: Ivermectina"></div>
                            <div><InputLabel value="Dose" /><InputDecimal v-model="eventForm.dose" :decimals="2" :min="0" placeholder="0,00" /></div>
                            <div><InputLabel value="Via aplicação" /><input v-model="eventForm.via_aplicacao" class="form-input"></div>
                        </template>

                        <!-- Movimentação de LOTE (grupo lógico) -->
                        <template v-if="eventForm.tipo === 'movimentacao'">
                            <div class="sm:col-span-2 text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-md px-3 py-2">
                                🐄 <strong>Mudança de lote</strong>: muda o GRUPO do animal (ex.: Bezerros → Vacas em lactação). Não altera a posição física.
                            </div>
                            <div>
                                <InputLabel value="Lote de origem" />
                                <select v-model="eventForm.lot_origem_id" class="form-select">
                                    <option :value="null">—</option>
                                    <option v-for="l in lots" :key="l.id" :value="l.id">{{ l.nome }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Lote de destino *" />
                                <select v-model="eventForm.lot_destino_id" class="form-select" required>
                                    <option :value="null">—</option>
                                    <option v-for="l in lots" :key="l.id" :value="l.id">{{ l.nome }}</option>
                                </select>
                            </div>
                        </template>

                        <!-- Movimentação de LOCAL (pasto físico) -->
                        <template v-if="eventForm.tipo === 'movimentacao_local'">
                            <div class="sm:col-span-2 text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-md px-3 py-2">
                                📍 <strong>Mudança de pasto</strong>: muda a POSIÇÃO FÍSICA do animal (ex.: Pasto 1 → Piquete 3). Não altera o grupo de manejo.
                            </div>
                            <div>
                                <InputLabel value="Pasto de origem" />
                                <select v-model="eventForm.location_origem_id" class="form-select">
                                    <option :value="null">—</option>
                                    <option v-for="l in locations" :key="l.id" :value="l.id">
                                        {{ l.nome }}<span v-if="l.tipo"> · {{ l.tipo }}</span>
                                    </option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Pasto de destino *" />
                                <select v-model="eventForm.location_destino_id" class="form-select" required>
                                    <option :value="null">—</option>
                                    <option v-for="l in locations" :key="l.id" :value="l.id">
                                        {{ l.nome }}<span v-if="l.tipo"> · {{ l.tipo }}</span>
                                    </option>
                                </select>
                                <p v-if="!locations.length" class="text-xs text-amber-600 mt-1">
                                    Nenhum pasto cadastrado. Cadastre em <em>Rebanho → Locais</em>.
                                </p>
                            </div>
                        </template>

                        <!-- Comuns -->
                        <div v-if="['vacinacao', 'medicacao', 'pesagem'].includes(eventForm.tipo)">
                            <InputLabel value="Responsável" />
                            <input v-model="eventForm.responsavel" class="form-input" placeholder="Ex: Dr. João">
                        </div>
                        <div v-if="['vacinacao', 'medicacao', 'compra', 'venda'].includes(eventForm.tipo)">
                            <InputLabel value="Valor (opcional)" />
                            <InputMoney v-model="eventForm.valor" />
                            <p class="text-xs text-slate-400 mt-1">Se informado, gera despesa no Financeiro.</p>
                        </div>
                        <div v-if="['vacinacao', 'medicacao', 'compra', 'venda'].includes(eventForm.tipo)">
                            <InputLabel value="Fornecedor" />
                            <select v-model="eventForm.partner_id" class="form-select">
                                <option :value="null">—</option>
                                <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.nome }}</option>
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <InputLabel value="Observações" />
                            <textarea v-model="eventForm.observacoes" rows="2" class="form-textarea"></textarea>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoEvento = false" class="btn-outline">Cancelar</button>
                        <button @click="salvarEvento" :disabled="eventForm.processing" class="btn-primary">
                            {{ eventForm.processing ? 'Salvando...' : 'Registrar evento' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
