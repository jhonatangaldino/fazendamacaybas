<script setup>
/**
 * Assistente guiado — Registrar colheita.
 * 4 passos: plantio → quantidade → preço (opcional) → sucesso.
 * Encerra o plantio e gera receita financeira automaticamente.
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMoney from '@/Components/InputMoney.vue';
import InputDecimal from '@/Components/InputDecimal.vue';
import { brl, dataBR, hojeBR } from '@/utils/format.js';

const props = defineProps({
    plantings: { type: Array, required: true },
});

const PASSOS = [
    { n: 1, titulo: 'Plantio',  icon: '🌾' },
    { n: 2, titulo: 'Quantidade', icon: '⚖️' },
    { n: 3, titulo: 'Preço',    icon: '💰' },
    { n: 4, titulo: 'Pronto!',  icon: '✅' },
];

const passo = ref(1);
const sucesso = ref(null);

const form = useForm({
    planting_id: null,
    data_colheita: hojeBR(),
    quantidade_colhida: 0,
    unidade: 'sc',
    valor_total: 0,
    observacoes: '',
});

const plantingSelecionado = computed(() => props.plantings.find(p => p.id === form.planting_id));

// Default unidade do plantio (cultura) ao selecionar
const onSelecionarPlantio = (p) => {
    form.planting_id = p.id;
    if (p.crop_unidade) form.unidade = p.crop_unidade;
};

const produtividade = computed(() => {
    if (!plantingSelecionado.value || !form.quantidade_colhida) return 0;
    return form.quantidade_colhida / plantingSelecionado.value.area_plantada_ha;
});

const podeAvancar1 = computed(() => !!form.planting_id);
const podeAvancar2 = computed(() => form.quantidade_colhida > 0 && !!form.data_colheita);
const podeAvancar3 = computed(() => true); // valor opcional

function avancar() { if (passo.value < 4) passo.value++; }
function voltar()  { if (passo.value > 1) passo.value--; }

function confirmar() {
    // Snapshot ANTES — após POST, props.plantings é re-fetched (sem o que
    // virou colhido) e plantingSelecionado fica null, perdendo cultura/talhão.
    const snap = {
        cultura: plantingSelecionado.value?.crop_nome,
        talhao: plantingSelecionado.value?.field_nome,
        quantidade: form.quantidade_colhida,
        unidade: form.unidade,
        produtividade: produtividade.value,
        valor: form.valor_total,
    };
    form.post(route('admin.fluxos.registrar-colheita.store'), {
        preserveScroll: false,
        onSuccess: () => {
            sucesso.value = snap;
            passo.value = 4;
        },
    });
}

function reiniciar() {
    form.reset();
    form.data_colheita = hojeBR();
    form.unidade = 'sc';
    sucesso.value = null;
    passo.value = 1;
}
</script>

<template>
    <Head title="Registrar colheita" />
    <AdminLayout>
        <template #page-title>Assistente · Colheita</template>

        <PageHeader title="Registrar colheita" subtitle="Encerra o plantio, anota produtividade e gera receita.">
            <template #actions>
                <Link :href="route('admin.agricola.colheitas.index')" class="btn-outline">Sair</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- PASSO 1 — Plantio -->
        <div v-if="passo === 1" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Qual plantio você está colhendo?</h2>

                <div v-if="plantings.length === 0" class="rounded-xl border-2 border-amber-200 bg-amber-50 p-5 text-amber-900">
                    <div class="font-semibold">Nenhum plantio em andamento</div>
                    <p class="text-sm mt-1">Você precisa registrar um plantio primeiro pra poder colher.</p>
                    <Link :href="route('admin.fluxos.registrar-plantio')" class="inline-block mt-3 btn-primary">
                        + Registrar plantio
                    </Link>
                </div>

                <div v-else class="grid gap-3">
                    <button v-for="p in plantings" :key="p.id" type="button" @click="onSelecionarPlantio(p)"
                        :data-planting-id="p.id"
                        class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary"
                        :class="form.planting_id === p.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-bold text-slate-900">🌾 {{ p.crop_nome }} em {{ p.field_nome }}</div>
                                <div class="text-sm text-slate-600 mt-1">
                                    {{ p.area_plantada_ha }} ha · plantado em {{ dataBR(p.data_plantio) }}
                                    <span v-if="p.previsao_colheita"> · previsão {{ dataBR(p.previsao_colheita) }}</span>
                                </div>
                            </div>
                        </div>
                    </button>
                </div>

                <div class="flex justify-end pt-4 border-t" v-if="plantings.length > 0">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2 — Quantidade -->
        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Quanto você colheu?</h2>
                <p class="text-base text-slate-600">
                    Plantio: <strong>{{ plantingSelecionado?.crop_nome }}</strong> em <strong>{{ plantingSelecionado?.field_nome }}</strong>
                    ({{ plantingSelecionado?.area_plantada_ha }} ha)
                </p>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Quantidade *" />
                        <InputDecimal v-model="form.quantidade_colhida" :decimals="2" :min="0.01"
                                      placeholder="0,00"
                                      input-class="form-input text-xl font-mono py-3" />
                    </div>
                    <div>
                        <InputLabel value="Unidade *" />
                        <select v-model="form.unidade" data-cy="input-unidade" class="form-select py-3">
                            <option value="sc">saca (60 kg)</option>
                            <option value="kg">kg</option>
                            <option value="t">tonelada</option>
                            <option value="un">unidade</option>
                        </select>
                    </div>
                </div>

                <div>
                    <InputLabel value="Data da colheita *" />
                    <InputDate v-model="form.data_colheita" :max="hojeBR()" />
                </div>

                <!-- Produtividade calculada ao vivo -->
                <div v-if="produtividade > 0" class="rounded-lg bg-emerald-50 border border-emerald-200 p-3">
                    <div class="text-xs uppercase tracking-wide text-emerald-700 font-semibold">Produtividade</div>
                    <div class="text-xl font-bold text-emerald-700 font-mono">
                        {{ produtividade.toFixed(2) }} {{ form.unidade }}/ha
                    </div>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar2" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 3 — Preço (opcional) -->
        <div v-if="passo === 3" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Já vendeu? Por quanto?</h2>
                <p class="text-base text-slate-600">
                    Se já vendeu, informa o valor e o sistema gera receita automaticamente. Se ainda não, deixa zero.
                </p>

                <div class="max-w-md">
                    <InputLabel value="Valor total recebido (opcional)" />
                    <InputMoney v-model="form.valor_total" data-cy="input-valor" class="text-2xl py-3" />
                    <p class="text-xs text-slate-500 mt-1">
                        {{ form.quantidade_colhida }} {{ form.unidade }} × preço unitário = valor total
                    </p>
                </div>

                <div>
                    <InputLabel value="Observações (opcional)" />
                    <textarea v-model="form.observacoes" rows="2" class="form-textarea"
                        placeholder="Cooperativa, comprador, qualidade etc."></textarea>
                </div>

                <!-- Resumo -->
                <div class="rounded-xl border-2 border-emerald-200 bg-emerald-50 p-4 text-sm">
                    <div class="font-semibold text-emerald-900 mb-2">Conferência:</div>
                    <ul class="text-emerald-900 space-y-1">
                        <li>🌾 <strong>{{ plantingSelecionado?.crop_nome }}</strong> em {{ plantingSelecionado?.field_nome }}</li>
                        <li>⚖️ <strong>{{ form.quantidade_colhida }} {{ form.unidade }}</strong> · {{ produtividade.toFixed(2) }} {{ form.unidade }}/ha</li>
                        <li>📅 Colhido em <strong>{{ dataBR(form.data_colheita) }}</strong></li>
                        <li v-if="form.valor_total > 0">💰 Receita auto-gerada: <strong>{{ brl(form.valor_total) }}</strong></li>
                        <li v-else class="text-slate-700">💰 Sem valor — não vai gerar receita financeira agora</li>
                    </ul>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="form.processing" data-cy="confirmar"
                            class="btn-primary px-8 py-2.5">
                        {{ form.processing ? 'Salvando…' : '✓ Registrar colheita' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO 4 — Sucesso -->
        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
            <div class="card-body text-center py-8 space-y-4">
                <div class="text-5xl">🌽</div>
                <h2 class="text-2xl font-semibold text-slate-900">Colheita registrada!</h2>
                <p class="text-slate-700">
                    <strong>{{ sucesso.quantidade }} {{ sucesso.unidade }}</strong> de {{ sucesso.cultura }}
                    em {{ sucesso.talhao }}
                </p>
                <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-left text-sm">
                    <p>✓ Plantio fechado (status: <strong>colhido</strong>)</p>
                    <p>✓ Produtividade: <strong>{{ sucesso.produtividade?.toFixed(2) }} {{ sucesso.unidade }}/ha</strong></p>
                    <p v-if="sucesso.valor > 0">
                        ✓ Receita de <strong>{{ brl(sucesso.valor) }}</strong> gerada no financeiro como conta a receber
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 justify-center pt-3">
                    <button @click="reiniciar" class="btn-primary">Registrar outra</button>
                    <Link :href="route('admin.agricola.colheitas.index')" class="btn-outline">Ver colheitas</Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
