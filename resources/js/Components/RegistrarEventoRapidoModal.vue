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
import { computed, ref, watch } from 'vue';
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

// Combobox de animal
const buscaAnimal = ref('');
const animalSelecionado = ref(null);

const animaisFiltrados = computed(() => {
    const q = buscaAnimal.value.trim().toLowerCase();
    if (!q) return props.animals.slice(0, 10);
    return props.animals.filter(a =>
        (a.identificacao || '').toLowerCase().includes(q)
        || (a.nome || '').toLowerCase().includes(q)
    ).slice(0, 10);
});

watch(() => props.open, (open) => {
    if (open) {
        // Reset ao abrir
        form.value = { data: new Date().toISOString().slice(0, 10), observacoes: '' };
        animalSelecionado.value = null;
        buscaAnimal.value = '';
        erros.value = {};
        enviando.value = false;
    }
});

function selecionarAnimal(animal) {
    animalSelecionado.value = animal;
    buscaAnimal.value = `${animal.identificacao}${animal.nome ? ' · '+animal.nome : ''}`;
}

const formValido = computed(() => {
    if (!animalSelecionado.value) return false;
    if (!form.value.data) return false;
    if (props.tipo === 'pesagem' && !form.value.peso) return false;
    if (props.tipo === 'vacinacao' && !form.value.vacina) return false;
    if (props.tipo === 'medicacao' && !form.value.medicamento) return false;
    if (props.tipo === 'vermifugacao' && !form.value.medicamento) return false;
    if (props.tipo === 'ordenha' && !form.value.peso) return false;
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

    const payload = {
        tipo: props.tipo,
        data: form.value.data,
        observacoes: form.value.observacoes || null,
        peso: form.value.peso || null,
        vacina: form.value.vacina || null,
        medicamento: form.value.medicamento || null,
        dose: form.value.dose || null,
        via_aplicacao: form.value.via_aplicacao || null,
        producao_litros: props.tipo === 'ordenha' ? form.value.peso : null,
        gestacao_dias: form.value.gestacao_dias || null,
        data_prevista_parto: form.value.data_prevista_parto || null,
        lot_destino_id: form.value.lot_destino_id || null,
        location_destino_id: form.value.location_destino_id || null,
    };

    router.post(
        route('admin.rebanho.animais.eventos.store', animalSelecionado.value.id),
        payload,
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast?.(`✓ ${eventoMeta.value.label} registrada para ${animalSelecionado.value.identificacao}`, 'sucesso');
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
                    <!-- Combobox de animal -->
                    <div>
                        <InputLabel value="Animal *" />
                        <input v-model="buscaAnimal" type="text"
                               placeholder="Buscar por brinco ou nome..."
                               class="form-input"
                               :class="erros.animal_id ? 'ring-2 ring-red-300' : ''">
                        <p v-if="!animalSelecionado && buscaAnimal" class="text-xs text-amber-700 mt-1">
                            Digite o brinco ou nome — escolha um da lista abaixo.
                        </p>
                        <div v-if="!animalSelecionado && animaisFiltrados.length > 0"
                             class="mt-1 max-h-40 overflow-y-auto rounded-lg ring-1 ring-slate-200 divide-y divide-slate-100">
                            <button v-for="a in animaisFiltrados" :key="a.id"
                                    type="button" @click="selecionarAnimal(a)"
                                    class="w-full text-left px-3 py-2 hover:bg-slate-50 text-sm">
                                <span class="font-mono font-semibold">{{ a.identificacao }}</span>
                                <span v-if="a.nome" class="text-slate-500"> · {{ a.nome }}</span>
                            </button>
                        </div>
                        <div v-if="animalSelecionado" class="mt-1 text-xs text-emerald-700">
                            ✓ Selecionado:
                            <strong>{{ animalSelecionado.identificacao }}</strong>
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

                    <!-- ORDENHA (reusa peso pra litros) -->
                    <div v-if="tipo === 'ordenha'">
                        <InputLabel value="Litros produzidos *" />
                        <InputDecimal v-model="form.peso" :decimals="1" :min="0" placeholder="0,0" />
                        <p class="text-xs text-slate-500 mt-1">Total da ordenha (manhã + tarde, se aplicável).</p>
                    </div>

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
