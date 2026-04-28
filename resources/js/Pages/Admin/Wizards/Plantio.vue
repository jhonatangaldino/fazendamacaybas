<script setup>
/**
 * Assistente guiado — Registrar plantio.
 * 5 passos: cultura (com inline) → talhão+área (com inline) → datas → custo → sucesso.
 * Previsão de colheita auto-sugerida pelo ciclo da cultura.
 */
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMoney from '@/Components/InputMoney.vue';
import InputDecimal from '@/Components/InputDecimal.vue';
import { brl, dataBR, hojeBR } from '@/utils/format.js';
import { useInlineCreate } from '@/composables/useInlineCreate.js';

const props = defineProps({
    crops: { type: Array, required: true },
    fields: { type: Array, required: true },
    seasons: { type: Array, default: () => [] },
});

const PASSOS = [
    { n: 1, titulo: 'Cultura',  icon: '🌾' },
    { n: 2, titulo: 'Talhão',   icon: '🗺️' },
    { n: 3, titulo: 'Datas',    icon: '📅' },
    { n: 4, titulo: 'Custo',    icon: '💰' },
    { n: 5, titulo: 'Pronto!',  icon: '✅' },
];

const passo = ref(1);
const sucesso = ref(null);
const cropsLocal = ref([...props.crops]);
const fieldsLocal = ref([...props.fields]);

// A3 — multi-talhão: form usa `field_ids[]`. Quando 1 talhão, área editável.
// Quando >1, cada talhão usa sua área completa.
const form = useForm({
    field_ids: [],
    crop_id: null,
    season_id: null,
    data_plantio: hojeBR(),
    previsao_colheita: '',
    area_plantada_ha: 0,
    custo_previsto: 0,
    observacoes: '',
});

const cropSelecionada = computed(() => cropsLocal.value.find(c => c.id === form.crop_id));
const fieldsSelecionados = computed(() =>
    fieldsLocal.value.filter(f => form.field_ids.includes(f.id)));
const isMulti = computed(() => form.field_ids.length > 1);
const areaTotalSelecionada = computed(() =>
    fieldsSelecionados.value.reduce((acc, f) => acc + parseFloat(f.area_ha || 0), 0));

function toggleField(id) {
    const idx = form.field_ids.indexOf(id);
    if (idx === -1) form.field_ids.push(id);
    else form.field_ids.splice(idx, 1);
}

// Auto-sugere previsão de colheita pelo ciclo da cultura
watch(() => [form.crop_id, form.data_plantio], ([_, dt]) => {
    if (cropSelecionada.value?.ciclo_dias && dt) {
        const d = new Date(dt);
        d.setDate(d.getDate() + cropSelecionada.value.ciclo_dias);
        form.previsao_colheita = d.toISOString().slice(0, 10);
    }
});

// Auto-sugere área plantada quando talhão único, ou força área total quando multi
watch(() => form.field_ids.slice(), () => {
    if (form.field_ids.length === 1) {
        const f = fieldsSelecionados.value[0];
        form.area_plantada_ha = parseFloat(f.area_ha || 0);
    } else if (form.field_ids.length > 1) {
        form.area_plantada_ha = areaTotalSelecionada.value;
    } else {
        form.area_plantada_ha = 0;
    }
}, { deep: true });

// Inline create
const novaCultura = useInlineCreate({
    endpoint: route('admin.fluxos.registrar-plantio.cultura.inline'),
    initialForm: { nome: '', unidade_producao: 'sc', ciclo_dias: '' },
    onCreated: (c) => {
        cropsLocal.value = [...cropsLocal.value, c];
        form.crop_id = c.id;
    },
});

const novoTalhao = useInlineCreate({
    endpoint: route('admin.fluxos.registrar-plantio.talhao.inline'),
    initialForm: { nome: '', area_ha: '', descricao: '' },
    onCreated: (f) => {
        fieldsLocal.value = [...fieldsLocal.value, f];
        // Adiciona o novo talhão à seleção (não substitui se já tem outros)
        if (! form.field_ids.includes(f.id)) form.field_ids.push(f.id);
    },
});

const podeAvancar1 = computed(() => !!form.crop_id);
const podeAvancar2 = computed(() => form.field_ids.length > 0 && form.area_plantada_ha > 0);
const podeAvancar3 = computed(() => !!form.data_plantio);
const podeAvancar4 = computed(() => true); // custo opcional

function avancar() { if (passo.value < 5) passo.value++; }
function voltar()  { if (passo.value > 1) passo.value--; }

function confirmar() {
    const snap = {
        cultura: cropSelecionada.value?.nome,
        talhoes: fieldsSelecionados.value.map(f => f.nome),
        totalTalhoes: form.field_ids.length,
        area: form.area_plantada_ha,
        data: form.data_plantio,
        previsao: form.previsao_colheita,
    };
    form.post(route('admin.fluxos.registrar-plantio.store'), {
        preserveScroll: false,
        onSuccess: () => {
            sucesso.value = snap;
            passo.value = 5;
        },
    });
}

function reiniciar() {
    form.reset();
    form.field_ids = [];
    form.data_plantio = hojeBR();
    sucesso.value = null;
    passo.value = 1;
}
</script>

<template>
    <Head title="Registrar plantio" />
    <AdminLayout>
        <template #page-title>Assistente · Plantio</template>

        <PageHeader title="Registrar plantio" subtitle="Vamos registrar o que vai pra terra. Passo a passo, sem confusão.">
            <template #actions>
                <Link :href="route('admin.agricola.plantios.index')" class="btn-outline">Sair</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- PASSO 1 — Cultura -->
        <div v-if="passo === 1" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Qual cultura você está plantando?</h2>
                <p class="text-base text-slate-600">Soja, milho, café, cana — escolhe ou cria.</p>

                <div v-if="cropsLocal.length === 0" class="rounded-xl border-2 border-amber-200 bg-amber-50 p-5">
                    <div class="font-semibold text-amber-900">Nenhuma cultura cadastrada ainda</div>
                    <button type="button" @click="novaCultura.abrir()" data-cy="abrir-cultura"
                            class="mt-3 btn-primary">+ Cadastrar primeira cultura</button>
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2">
                    <button v-for="c in cropsLocal" :key="c.id" type="button" @click="form.crop_id = c.id"
                        :data-crop-id="c.id"
                        class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary"
                        :class="form.crop_id === c.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                        <div class="font-bold text-slate-900">🌾 {{ c.nome }}</div>
                        <div class="text-sm text-slate-600 mt-1">
                            <span v-if="c.ciclo_dias">Ciclo: ~{{ c.ciclo_dias }} dias</span>
                            <span v-else>Ciclo não informado</span>
                            <span v-if="c.unidade_producao"> · unidade: {{ c.unidade_producao }}</span>
                        </div>
                    </button>
                </div>

                <div v-if="cropsLocal.length > 0" class="text-right">
                    <button type="button" @click="novaCultura.abrir()" class="inline-flex items-center min-h-9 px-2 text-sm text-macaybas-primary hover:underline lg:min-h-0 lg:px-0">
                        + Cadastrar nova cultura
                    </button>
                </div>

                <div class="flex justify-end pt-4 border-t">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2 — Talhão (multi) -->
        <div v-if="passo === 2" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Em qual(is) talhão(ões)?</h2>
                <p class="text-base text-slate-600">
                    Pode plantar a mesma cultura em vários talhões de uma vez. Selecione os que vão receber.
                </p>

                <div v-if="fieldsLocal.length === 0" class="rounded-xl border-2 border-amber-200 bg-amber-50 p-5">
                    <div class="font-semibold text-amber-900">Nenhum talhão cadastrado ainda</div>
                    <button type="button" @click="novoTalhao.abrir()" data-cy="abrir-talhao"
                            class="mt-3 btn-primary">+ Cadastrar primeiro talhão</button>
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2">
                    <button v-for="f in fieldsLocal" :key="f.id" type="button" @click="toggleField(f.id)"
                        :data-field-id="f.id"
                        class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary"
                        :class="form.field_ids.includes(f.id) ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                        <div class="flex items-start gap-2">
                            <input type="checkbox" :checked="form.field_ids.includes(f.id)"
                                   class="w-4 h-4 mt-1 accent-emerald-600 pointer-events-none" tabindex="-1">
                            <div>
                                <div class="font-bold text-slate-900">🗺️ {{ f.nome }}</div>
                                <div class="text-sm text-slate-600 mt-1">{{ f.area_ha }} ha de área total</div>
                            </div>
                        </div>
                    </button>
                </div>

                <div v-if="fieldsLocal.length > 0" class="text-right">
                    <button type="button" @click="novoTalhao.abrir()" class="text-sm text-macaybas-primary hover:underline">
                        + Cadastrar novo talhão
                    </button>
                </div>

                <div v-if="form.field_ids.length === 1" class="max-w-md">
                    <InputLabel value="Área plantada (ha) *" />
                    <InputDecimal v-model="form.area_plantada_ha" :decimals="2" :min="0.01"
                                  :max="fieldsSelecionados[0]?.area_ha"
                                  placeholder="0,00"
                                  input-class="form-input text-lg font-mono py-3" />
                    <p class="text-xs text-slate-500 mt-1">
                        Talhão tem {{ fieldsSelecionados[0]?.area_ha }} ha — pode plantar parte dele.
                    </p>
                </div>

                <div v-else-if="form.field_ids.length > 1"
                     class="rounded-lg border border-emerald-200 bg-emerald-50/40 p-3 text-sm">
                    <div class="font-semibold text-emerald-900">
                        {{ form.field_ids.length }} talhões · {{ areaTotalSelecionada.toFixed(2) }} ha total
                    </div>
                    <ul class="text-xs text-slate-700 mt-1 space-y-0.5">
                        <li v-for="f in fieldsSelecionados" :key="f.id">
                            • {{ f.nome }}: {{ parseFloat(f.area_ha).toFixed(2) }} ha
                        </li>
                    </ul>
                    <p class="text-xs text-emerald-800 mt-2">
                        Cada talhão será plantado com sua área completa. O custo previsto será dividido proporcional.
                    </p>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar2" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 3 — Datas -->
        <div v-if="passo === 3" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Quando plantou e quando colhe?</h2>
                <p class="text-base text-slate-600" v-if="cropSelecionada?.ciclo_dias">
                    Sugerimos a colheita pelo ciclo da {{ cropSelecionada?.nome }} (~{{ cropSelecionada?.ciclo_dias }} dias).
                </p>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Data do plantio *" />
                        <InputDate v-model="form.data_plantio" :max="hojeBR()" />
                    </div>
                    <div>
                        <InputLabel value="Previsão de colheita" />
                        <InputDate v-model="form.previsao_colheita" :min="form.data_plantio" />
                    </div>
                </div>

                <div v-if="seasons.length">
                    <InputLabel value="Safra (opcional)" />
                    <select v-model="form.season_id" class="form-select">
                        <option :value="null">— Sem safra específica —</option>
                        <option v-for="s in seasons" :key="s.id" :value="s.id">{{ s.nome }}</option>
                    </select>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar3" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 4 — Custo -->
        <div v-if="passo === 4" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Custo previsto (opcional)</h2>
                <p class="text-base text-slate-600">Sementes, defensivos, mão de obra. Pode ajustar depois pelo custo real.</p>

                <div class="max-w-md">
                    <InputLabel value="Custo total previsto" />
                    <InputMoney v-model="form.custo_previsto" class="text-xl py-3" />
                </div>

                <div>
                    <InputLabel value="Observações (opcional)" />
                    <textarea v-model="form.observacoes" rows="2" class="form-textarea"
                        placeholder="Variedade da semente, espaçamento, etc."></textarea>
                </div>

                <!-- Resumo -->
                <div class="rounded-xl border-2 border-emerald-200 bg-emerald-50 p-4 text-sm">
                    <div class="font-semibold text-emerald-900 mb-2">Conferência:</div>
                    <ul class="text-emerald-900 space-y-1">
                        <li v-if="!isMulti">
                            🌾 <strong>{{ cropSelecionada?.nome }}</strong> em
                            <strong>{{ fieldsSelecionados[0]?.nome }}</strong>
                        </li>
                        <li v-else>
                            🌾 <strong>{{ cropSelecionada?.nome }}</strong> em
                            <strong>{{ form.field_ids.length }} talhões</strong>
                            ({{ fieldsSelecionados.map(f => f.nome).join(', ') }})
                        </li>
                        <li>📐 <strong>{{ form.area_plantada_ha }} ha</strong> plantados<span v-if="isMulti"> (total)</span></li>
                        <li>📅 Plantio em <strong>{{ dataBR(form.data_plantio) }}</strong>
                            <span v-if="form.previsao_colheita"> · colheita prevista <strong>{{ dataBR(form.previsao_colheita) }}</strong></span>
                        </li>
                        <li v-if="form.custo_previsto > 0">
                            💰 Custo previsto: <strong>{{ brl(form.custo_previsto) }}</strong>
                            <span v-if="isMulti" class="text-xs text-emerald-700">(será dividido proporcional à área)</span>
                        </li>
                    </ul>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="form.processing" data-cy="confirmar"
                            class="btn-primary px-8 py-2.5">
                        {{ form.processing ? 'Salvando…' : '✓ Registrar plantio' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO 5 — Sucesso -->
        <div v-if="passo === 5 && sucesso" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
            <div class="card-body text-center py-8 space-y-4">
                <div class="text-5xl">🌱</div>
                <h2 class="text-2xl font-semibold text-slate-900">Plantio registrado!</h2>
                <p class="text-slate-700">
                    <strong>{{ sucesso.area }} ha de {{ sucesso.cultura }}</strong>
                    <span v-if="sucesso.totalTalhoes === 1">em {{ sucesso.talhoes[0] }}</span>
                    <span v-else>em <strong>{{ sucesso.totalTalhoes }} talhões</strong></span>
                </p>
                <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-left text-sm">
                    <p v-if="sucesso.totalTalhoes > 1">
                        ✓ {{ sucesso.totalTalhoes }} plantios criados — um por talhão ({{ sucesso.talhoes.join(', ') }})
                    </p>
                    <p>✓ Plantio em <strong>{{ dataBR(sucesso.data) }}</strong></p>
                    <p v-if="sucesso.previsao">✓ Colheita prevista em <strong>{{ dataBR(sucesso.previsao) }}</strong></p>
                    <p>✓ Pode aplicar adubo/defensivo neste plantio agora</p>
                    <p>✓ Quando colher, abre "Registrar colheita" — sistema fecha o ciclo e gera receita</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 justify-center pt-3">
                    <button @click="reiniciar" class="btn-primary">Registrar outro</button>
                    <Link :href="route('admin.agricola.plantios.index')" class="btn-outline">Ver plantios</Link>
                </div>
            </div>
        </div>

        <!-- Modais inline -->
        <Teleport to="body">
            <div v-if="novaCultura.modalAberto.value" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novaCultura.fechar"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <h3 class="text-lg font-semibold mb-1">Nova cultura</h3>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Nome *" />
                            <input v-model="novaCultura.form.value.nome" data-cy="cultura-nome"
                                class="form-input" placeholder="Ex.: Soja, Milho, Café" />
                        </div>
                        <div>
                            <InputLabel value="Unidade de produção *" />
                            <select v-model="novaCultura.form.value.unidade_producao" class="form-select">
                                <option value="sc">saca (60 kg)</option>
                                <option value="kg">kg</option>
                                <option value="t">tonelada</option>
                                <option value="un">unidade</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Ciclo médio (dias) — opcional" />
                            <input v-model="novaCultura.form.value.ciclo_dias" type="number" min="1"
                                class="form-input" placeholder="Ex.: 120" />
                            <p class="text-xs text-slate-500 mt-1">Usado pra sugerir data de colheita.</p>
                        </div>
                    </div>
                    <p v-if="novaCultura.erro.value" class="mt-3 text-xs text-red-700">{{ novaCultura.erro.value }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novaCultura.fechar" class="btn-outline">Cancelar</button>
                        <button @click="novaCultura.salvar" data-cy="cultura-salvar"
                                :disabled="novaCultura.salvando.value || !novaCultura.form.value.nome?.trim()"
                                class="btn-primary">
                            {{ novaCultura.salvando.value ? 'Salvando…' : 'Cadastrar' }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="novoTalhao.modalAberto.value" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novoTalhao.fechar"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <h3 class="text-lg font-semibold mb-1">Novo talhão</h3>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Nome do talhão *" />
                            <input v-model="novoTalhao.form.value.nome" data-cy="talhao-nome"
                                class="form-input" placeholder="Ex.: Talhão Norte, Lavoura Baixada" />
                        </div>
                        <div>
                            <InputLabel value="Área total (ha) *" />
                            <InputDecimal v-model="novoTalhao.form.value.area_ha" :decimals="2" :min="0.01"
                                          placeholder="Ex.: 12,5" />
                        </div>
                    </div>
                    <p v-if="novoTalhao.erro.value" class="mt-3 text-xs text-red-700">{{ novoTalhao.erro.value }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoTalhao.fechar" class="btn-outline">Cancelar</button>
                        <button @click="novoTalhao.salvar" data-cy="talhao-salvar"
                                :disabled="novoTalhao.salvando.value || !novoTalhao.form.value.nome?.trim()"
                                class="btn-primary">
                            {{ novoTalhao.salvando.value ? 'Salvando…' : 'Cadastrar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
