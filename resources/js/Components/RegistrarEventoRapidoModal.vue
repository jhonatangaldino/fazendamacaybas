<script setup>
/**
 * RegistrarEventoRapidoModal — modal único pra todas as ações rápidas
 * do dashboard de espécie (Pesagem, Vacinação, Medicação, Vermifugação,
 * Reprodução, Movimentação, Ordenha, Secagem). Substitui o link pra
 * wizards de página inteira.
 *
 * Como funciona:
 *   • Recebe tipo do evento + species_id + lista de animais elegíveis
 *   • Renderiza COMBOBOX de animal (busca por brinco/nome) + campos
 *     específicos do tipo + data + observação
 *   • Salva via POST pro endpoint admin.rebanho.animais.eventos.store
 *   • Sucesso: toast + emit close + emit success (pai recarrega KPIs)
 *
 * Tipos suportados (eventos básicos do agro):
 *   pesagem, vacinacao, medicacao, vermifugacao, ordenha, secagem,
 *   movimentacao (lote/local), reproducao (cobertura/parto previsto)
 */
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import InputDecimal from '@/Components/InputDecimal.vue';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';
import { useToast } from '@/composables/useToast';
import { EVENT_CATALOG } from '@/utils/animalProfile.js';

const props = defineProps({
    open: { type: Boolean, default: false },
    tipo: { type: String, default: null }, // pesagem, vacinacao, etc.
    species: { type: Object, default: null },
    animals: { type: Array, default: () => [] }, // {id, identificacao, nome}
    lots: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'success']);

useBodyScrollLock(() => props.open);
const { toast } = useToast();

const eventoMeta = computed(() => EVENT_CATALOG[props.tipo] || { label: props.tipo, icon: '📝' });

// Form local
const form = ref({});
const enviando = ref(false);
const erros = ref({});

// Detecção de modo: lote agregado (Ave/Peixe) usa LOTE como alvo,
// não animal individual. Eventos vão pra rota lotes.eventos.store.
const isLoteAgregado = computed(() => props.species?.gestao === 'lote');

// Lotes agregados elegíveis (filtra os com qtde > 0)
const lotesAgregados = computed(() => {
    if (!isLoteAgregado.value) return [];
    return (props.lots || []).filter(l =>
        l.species_id === props.species?.id && (l.quantidade_atual ?? 0) > 0
    );
});

// Combobox de animal (modo individual) ou lote (modo agregado)
const buscaAnimal = ref('');
const animalSelecionado = ref(null);
const loteSelecionado = ref(null);

const animaisFiltrados = computed(() => {
    const q = buscaAnimal.value.trim().toLowerCase();
    // Mostra peso atual + lote pra ajudar quem não sabe o brinco/nome
    const enrich = (a) => ({
        ...a,
        sub: [
            a.peso_atual ? `${parseFloat(a.peso_atual).toLocaleString('pt-BR')}kg` : null,
            a.categoria,
            a.lot?.nome ? `🏷 ${a.lot.nome}` : null,
            a.location?.nome ? `📍 ${a.location.nome}` : null,
        ].filter(Boolean).join(' · '),
    });
    if (!q) return (props.animals || []).slice(0, 10).map(enrich);
    return (props.animals || []).filter(a =>
        (a.identificacao || '').toLowerCase().includes(q)
        || (a.nome || '').toLowerCase().includes(q)
        || (a.lot?.nome || '').toLowerCase().includes(q)
    ).slice(0, 10).map(enrich);
});

watch(() => props.open, (open) => {
    if (open) {
        // Reset ao abrir
        form.value = { data: new Date().toISOString().slice(0, 10), observacoes: '' };
        animalSelecionado.value = null;
        loteSelecionado.value = null;
        buscaAnimal.value = '';
        erros.value = {};
        enviando.value = false;
        // Auto-seleciona único lote se houver só 1 (fluxo Ave/Peixe comum)
        if (isLoteAgregado.value && lotesAgregados.value.length === 1) {
            loteSelecionado.value = lotesAgregados.value[0];
        }
    }
});

// Listener global de ESC pra fechar modal (acessibilidade)
function handleEsc(e) {
    if (e.key === 'Escape' && props.open) {
        e.stopPropagation();
        emit('close');
    }
}
onMounted(() => window.addEventListener('keydown', handleEsc));
onBeforeUnmount(() => window.removeEventListener('keydown', handleEsc));

function selecionarAnimal(animal) {
    animalSelecionado.value = animal;
    buscaAnimal.value = `${animal.identificacao}${animal.nome ? ' · '+animal.nome : ''}`;
}

const formValido = computed(() => {
    // Lote agregado: precisa lote selecionado
    if (isLoteAgregado.value) {
        if (!loteSelecionado.value) return false;
    } else {
        if (!animalSelecionado.value) return false;
    }
    if (!form.value.data) return false;
    if (props.tipo === 'pesagem' && !form.value.peso) return false;
    if (props.tipo === 'vacinacao' && !form.value.vacina) return false;
    if (props.tipo === 'medicacao' && !form.value.medicamento) return false;
    if (props.tipo === 'vermifugacao' && !form.value.medicamento) return false;
    // Ordenha aceita manhã + tarde (somatório)
    if (props.tipo === 'ordenha') {
        const total = (parseFloat(form.value.litros_manha) || 0) + (parseFloat(form.value.litros_tarde) || 0);
        if (total <= 0) return false;
    }
    if (props.tipo === 'postura_diaria' && !form.value.quantidade_ovos) return false;
    if (props.tipo === 'biometria_amostral' && !form.value.peso_medio_amostra) return false;
    if (props.tipo === 'mortalidade' && !form.value.quantidade_baixa) return false;
    if (props.tipo === 'alimentacao' && !form.value.kg_racao) return false;
    if (props.tipo === 'qualidade_agua' && (form.value.ph == null || form.value.ph === '')) return false;
    return true;
});

function fechar() {
    if (enviando.value) return;
    emit('close');
}

function salvar() {
    if (!formValido.value || enviando.value) return;
    enviando.value = true;
    erros.value = {};

    const litrosOrdenha = props.tipo === 'ordenha'
        ? ((parseFloat(form.value.litros_manha) || 0) + (parseFloat(form.value.litros_tarde) || 0))
        : null;

    const payload = {
        tipo: props.tipo,
        data: form.value.data,
        observacoes: form.value.observacoes || null,
        peso: form.value.peso || null,
        vacina: form.value.vacina || null,
        medicamento: form.value.medicamento || null,
        dose: form.value.dose || null,
        via_aplicacao: form.value.via_aplicacao || null,
        // Ordenha: total = manhã + tarde (mantém compatibilidade legada
        // gravando `producao_litros` somado, mas envia também breakdown)
        producao_litros: litrosOrdenha,
        litros_manha: form.value.litros_manha || null,
        litros_tarde: form.value.litros_tarde || null,
        gestacao_dias: form.value.gestacao_dias || null,
        data_prevista_parto: form.value.data_prevista_parto || null,
        lot_destino_id: form.value.lot_destino_id || null,
        location_destino_id: form.value.location_destino_id || null,
        // Eventos agregados em lote (Ave/Peixe)
        quantidade_ovos: form.value.quantidade_ovos || null,
        peso_medio_amostra: form.value.peso_medio_amostra || null,
        quantidade_amostra: form.value.quantidade_amostra || null,
        quantidade_baixa: form.value.quantidade_baixa || null,
        kg_racao: form.value.kg_racao || null,
        ph: form.value.ph ?? null,
        temperatura_agua: form.value.temperatura_agua || null,
        oxigenio_dissolvido: form.value.oxigenio_dissolvido || null,
    };

    // Rota difere por gestão: lote agregado → lotes.eventos.store, individual → animais.eventos.store
    const url = isLoteAgregado.value
        ? route('admin.rebanho.lotes.eventos.store', loteSelecionado.value.id)
        : route('admin.rebanho.animais.eventos.store', animalSelecionado.value.id);

    router.post(url, payload,
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                const alvoLabel = isLoteAgregado.value
                    ? `lote "${loteSelecionado.value.nome}"`
                    : animalSelecionado.value.identificacao;
                toast.success(`${eventoMeta.value.label} registrada para ${alvoLabel}`);
                emit('success');
                emit('close');
            },
            onError: (e) => { erros.value = e; },
            onFinish: () => { enviando.value = false; },
        }
    );
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60" @click="fechar"></div>
            <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto overscroll-contain">
                <!-- Header -->
                <div class="px-5 pt-5 pb-3 border-b border-slate-100 flex items-start gap-3 sticky top-0 bg-white">
                    <span class="text-3xl">{{ eventoMeta.icon }}</span>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-semibold text-slate-900">Registrar {{ eventoMeta.label.toLowerCase() }}</h3>
                        <p class="text-xs text-slate-500">
                            {{ species?.nome }} — escolha o animal e preencha os dados.
                        </p>
                    </div>
                    <button @click="fechar" class="text-slate-400 hover:text-slate-600 text-2xl leading-none -mt-1">&times;</button>
                </div>

                <!-- Body -->
                <div class="p-5 space-y-4">
                    <!-- Seletor de LOTE (gestao=lote: Ave/Peixe) -->
                    <div v-if="isLoteAgregado">
                        <InputLabel value="Lote *" />
                        <select v-model="loteSelecionado" class="form-select">
                            <option :value="null" disabled>— Escolha o lote —</option>
                            <option v-for="l in lotesAgregados" :key="l.id" :value="l">
                                {{ l.nome }} ({{ l.quantidade_atual }} cabeças{{ l.codigo ? ' · '+l.codigo : '' }})
                            </option>
                        </select>
                        <p v-if="lotesAgregados.length === 0" class="text-xs text-amber-700 mt-1">
                            Sem lote ativo dessa espécie. Crie um lote primeiro pra registrar eventos.
                        </p>
                        <p v-else-if="loteSelecionado" class="text-xs text-emerald-700 mt-1">
                            ✓ {{ loteSelecionado.quantidade_atual }} cabeças nesse lote
                        </p>
                    </div>

                    <!-- Combobox de animal (gestao=individual) — agora com peso/categoria/lote pra
                         ajudar quem não sabe o brinco/nome decorado. -->
                    <div v-else>
                        <InputLabel value="Animal *" />
                        <input v-model="buscaAnimal" type="text"
                               placeholder="Buscar por brinco, nome ou lote…"
                               class="form-input"
                               :class="erros.animal_id ? 'ring-2 ring-red-300' : ''">
                        <p v-if="!animalSelecionado && buscaAnimal" class="text-xs text-amber-700 mt-1">
                            Digite o brinco, nome ou nome do lote — escolha um da lista abaixo.
                        </p>
                        <div v-if="!animalSelecionado && animaisFiltrados.length > 0"
                             class="mt-1 max-h-56 overflow-y-auto rounded-lg ring-1 ring-slate-200 divide-y divide-slate-100">
                            <button v-for="a in animaisFiltrados" :key="a.id"
                                    type="button" @click="selecionarAnimal(a)"
                                    class="w-full text-left px-3 py-2 hover:bg-slate-50">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="font-mono font-semibold text-sm">{{ a.identificacao }}</span>
                                    <span v-if="a.nome" class="text-slate-700 text-sm truncate">{{ a.nome }}</span>
                                </div>
                                <div v-if="a.sub" class="text-xs text-slate-500 mt-0.5">{{ a.sub }}</div>
                            </button>
                        </div>
                        <div v-if="animalSelecionado" class="mt-1 text-xs text-emerald-700">
                            ✓ Selecionado:
                            <strong>{{ animalSelecionado.identificacao }}</strong>
                            <span v-if="animalSelecionado.nome"> · {{ animalSelecionado.nome }}</span>
                            <button @click="animalSelecionado = null; buscaAnimal = ''" class="ml-2 underline">trocar</button>
                        </div>
                    </div>

                    <!-- Data (todos os eventos) -->
                    <div>
                        <InputLabel value="Data *" />
                        <InputDate v-model="form.data" :max="new Date().toISOString().slice(0, 10)" />
                    </div>

                    <!-- PESAGEM -->
                    <div v-if="tipo === 'pesagem'">
                        <InputLabel value="Peso (kg) *" />
                        <InputDecimal v-model="form.peso" :decimals="2" :min="0" placeholder="0,00" />
                    </div>

                    <!-- ORDENHA — manhã + tarde separados (padrão DROVET).
                         Quem ordenha 1x/dia preenche só uma e total = somatório. -->
                    <template v-if="tipo === 'ordenha'">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Manhã (litros)" />
                                <InputDecimal v-model="form.litros_manha" :decimals="1" :min="0" placeholder="0,0" />
                                <p class="text-[11px] text-slate-500 mt-0.5">deixe 0 se não houve</p>
                            </div>
                            <div>
                                <InputLabel value="Tarde (litros)" />
                                <InputDecimal v-model="form.litros_tarde" :decimals="1" :min="0" placeholder="0,0" />
                                <p class="text-[11px] text-slate-500 mt-0.5">deixe 0 se não houve</p>
                            </div>
                        </div>
                        <p class="text-xs text-slate-600 bg-slate-50 rounded px-2 py-1.5">
                            Total: <strong>{{ ((parseFloat(form.litros_manha) || 0) + (parseFloat(form.litros_tarde) || 0)).toFixed(1) }} L</strong>
                        </p>
                    </template>

                    <!-- POSTURA DIÁRIA (Ave) -->
                    <div v-if="tipo === 'postura_diaria'">
                        <InputLabel value="Quantidade de ovos *" />
                        <InputDecimal v-model="form.quantidade_ovos" :decimals="0" :min="0" placeholder="Ex.: 850" />
                        <p class="text-xs text-slate-500 mt-1">Total coletado no dia (todas as galinhas do lote).</p>
                    </div>

                    <!-- BIOMETRIA AMOSTRAL (Ave/Peixe) -->
                    <template v-if="tipo === 'biometria_amostral'">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Peso médio (kg) *" />
                                <InputDecimal v-model="form.peso_medio_amostra" :decimals="3" :min="0" placeholder="0,000" />
                            </div>
                            <div>
                                <InputLabel value="Qtd amostra" />
                                <InputDecimal v-model="form.quantidade_amostra" :decimals="0" :min="1" placeholder="50" />
                            </div>
                        </div>
                        <p class="text-xs text-slate-500">Pesa N animais aleatórios — média representa o lote.</p>
                    </template>

                    <!-- MORTALIDADE (lote) -->
                    <div v-if="tipo === 'mortalidade'">
                        <InputLabel value="Quantidade de baixas *" />
                        <InputDecimal v-model="form.quantidade_baixa" :decimals="0" :min="1" placeholder="Ex.: 5" />
                        <p class="text-xs text-amber-700 mt-1">
                            ⚠ Decrementa do lote ({{ loteSelecionado?.quantidade_atual ?? '?' }} → {{ (loteSelecionado?.quantidade_atual ?? 0) - (parseInt(form.quantidade_baixa) || 0) }})
                        </p>
                    </div>

                    <!-- ALIMENTAÇÃO (lote) -->
                    <div v-if="tipo === 'alimentacao'">
                        <InputLabel value="Ração consumida (kg) *" />
                        <InputDecimal v-model="form.kg_racao" :decimals="2" :min="0" placeholder="Ex.: 25,50" />
                    </div>

                    <!-- QUALIDADE DA ÁGUA (Peixe) -->
                    <template v-if="tipo === 'qualidade_agua'">
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <InputLabel value="pH *" />
                                <InputDecimal v-model="form.ph" :decimals="2" :min="0" :max="14" placeholder="7,00" />
                            </div>
                            <div>
                                <InputLabel value="Temp (°C)" />
                                <InputDecimal v-model="form.temperatura_agua" :decimals="1" :min="0" placeholder="26,5" />
                            </div>
                            <div>
                                <InputLabel value="O₂ (mg/L)" />
                                <InputDecimal v-model="form.oxigenio_dissolvido" :decimals="2" :min="0" placeholder="6,50" />
                            </div>
                        </div>
                    </template>

                    <!-- VACINAÇÃO -->
                    <template v-if="tipo === 'vacinacao'">
                        <div>
                            <InputLabel value="Vacina *" />
                            <input v-model="form.vacina" type="text" maxlength="100"
                                   placeholder="Ex.: Febre Aftosa, Brucelose"
                                   class="form-input">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Dose (ml)" />
                                <InputDecimal v-model="form.dose" :decimals="2" :min="0" placeholder="5,00" />
                            </div>
                            <div>
                                <InputLabel value="Via" />
                                <input v-model="form.via_aplicacao" type="text" maxlength="30"
                                       placeholder="subcutânea"
                                       class="form-input">
                            </div>
                        </div>
                    </template>

                    <!-- MEDICAÇÃO + VERMIFUGAÇÃO (mesmo formulário) -->
                    <template v-if="tipo === 'medicacao' || tipo === 'vermifugacao'">
                        <div>
                            <InputLabel :value="tipo === 'vermifugacao' ? 'Vermífugo *' : 'Medicamento *'" />
                            <input v-model="form.medicamento" type="text" maxlength="100"
                                   :placeholder="tipo === 'vermifugacao' ? 'Ex.: Ivermectina 1%' : 'Ex.: Antibiótico XYZ'"
                                   class="form-input">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Dose" />
                                <InputDecimal v-model="form.dose" :decimals="2" :min="0" placeholder="0,00" />
                            </div>
                            <div>
                                <InputLabel value="Via" />
                                <input v-model="form.via_aplicacao" type="text" maxlength="30"
                                       placeholder="oral, subcutânea..."
                                       class="form-input">
                            </div>
                        </div>
                    </template>

                    <!-- REPRODUÇÃO -->
                    <template v-if="tipo === 'reproducao'">
                        <div>
                            <InputLabel value="Dias de gestação" />
                            <InputDecimal v-model="form.gestacao_dias" :decimals="0" :min="0" :max="340" placeholder="60" />
                        </div>
                        <div>
                            <InputLabel value="Data prevista de parto" />
                            <InputDate v-model="form.data_prevista_parto" />
                        </div>
                    </template>

                    <!-- MOVIMENTAÇÃO -->
                    <template v-if="tipo === 'movimentacao'">
                        <div v-if="lots.length > 0">
                            <InputLabel value="Mudar pra lote" />
                            <select v-model.number="form.lot_destino_id" class="form-select">
                                <option :value="null">— Não mudar lote —</option>
                                <option v-for="l in lots" :key="l.id" :value="l.id">{{ l.nome }}</option>
                            </select>
                        </div>
                        <div v-if="locations.length > 0">
                            <InputLabel value="Mudar pra pasto/local" />
                            <select v-model.number="form.location_destino_id" class="form-select">
                                <option :value="null">— Não mudar local —</option>
                                <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.nome }}</option>
                            </select>
                        </div>
                    </template>

                    <!-- SECAGEM (sem campos extras além de data e obs) -->

                    <!-- Observações -->
                    <div>
                        <InputLabel value="Observações (opcional)" />
                        <textarea v-model="form.observacoes" rows="2" maxlength="500"
                                  placeholder="Detalhes sobre o evento..."
                                  class="form-textarea resize-none"></textarea>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex justify-end gap-2 sticky bottom-0">
                    <button type="button" @click="fechar" :disabled="enviando" class="btn-outline">Cancelar</button>
                    <button type="button" @click="salvar" :disabled="!formValido || enviando" class="btn-primary disabled:opacity-50">
                        {{ enviando ? 'Salvando…' : `✓ Registrar ${eventoMeta.label.toLowerCase()}` }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
