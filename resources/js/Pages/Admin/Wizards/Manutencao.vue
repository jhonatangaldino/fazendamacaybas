<script setup>
/**
 * Assistente guiado — Arrumar máquina.
 *
 * 4 passos:
 *   1 · Qual máquina?         (grid de veículos)
 *   2 · O que foi feito?      (tipo + descrição + data + valor opcional)
 *   3 · Confere?              (resumo)
 *   4 · Pronto!               (sucesso)
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMoney from '@/Components/InputMoney.vue';
import { hojeBR, dataBR, brl } from '@/utils/format.js';

const props = defineProps({
    veiculos: { type: Array, required: true },
    contas: { type: Array, required: true },
    oficinas: { type: Array, required: true },
});

const PASSOS = [
    { n: 1, titulo: 'A máquina',   icon: '🚜' },
    { n: 2, titulo: 'O serviço',   icon: '🔧' },
    { n: 3, titulo: 'Conferência', icon: '📋' },
    { n: 4, titulo: 'Pronto!',     icon: '✅' },
];

const TIPOS = [
    { id: 'corretiva', nome: 'Conserto', desc: 'Quebrou algo, estou arrumando', emoji: '🛠' },
    { id: 'preventiva', nome: 'Prevenção', desc: 'Troca rotineira (óleo, filtro)', emoji: '🧴' },
    { id: 'revisao',    nome: 'Revisão completa', desc: 'Revisão de safra ou periódica', emoji: '🔍' },
];

const passo = ref(1);
const sucesso = ref(null);
const veiculoSelecionado = ref(null);

const form = useForm({
    vehicle_id: null,
    tipo: 'corretiva',
    descricao: '',
    data_realizada: hojeBR(),
    medidor: null,
    valor_pecas: '',
    valor_servico: '',
    partner_id: null,
    status: 'concluida',
    observacoes: '',
    gerar_lancamento_financeiro: false,
    account_id: null,
});

const podeAvancar1 = computed(() => !!veiculoSelecionado.value);
const podeAvancar2 = computed(() => {
    return !!form.tipo && form.descricao.trim().length >= 2 && !!form.data_realizada;
});

const tipoAtual = computed(() => TIPOS.find(t => t.id === form.tipo));
const contaNome = computed(() => props.contas.find(c => c.id === form.account_id)?.nome ?? null);
const oficinaNome = computed(() => props.oficinas.find(o => o.id === form.partner_id)?.nome ?? null);
const valorTotal = computed(() => {
    const p = parseFloat(String(form.valor_pecas || '0').replace(',', '.')) || 0;
    const s = parseFloat(String(form.valor_servico || '0').replace(',', '.')) || 0;
    return p + s;
});
const geraDespesa = computed(() => form.gerar_lancamento_financeiro && valorTotal.value > 0 && !!form.account_id);

function selecionarVeiculo(v) {
    veiculoSelecionado.value = v;
    form.vehicle_id = v.id;
}

function avancar() { if (passo.value < PASSOS.length) passo.value++; }
function voltar() { if (passo.value > 1) passo.value--; }
function irPara(n) { passo.value = n; }

function confirmar() {
    if (form.valor_pecas) form.valor_pecas = parseFloat(String(form.valor_pecas).replace(',', '.'));
    if (form.valor_servico) form.valor_servico = parseFloat(String(form.valor_servico).replace(',', '.'));
    if (form.medidor) form.medidor = parseFloat(String(form.medidor).replace(',', '.'));

    form.post(route('admin.maquinas.manutencoes.store'), {
        preserveScroll: false,
        onSuccess: () => {
            sucesso.value = {
                veiculo: veiculoSelecionado.value?.nome,
                tipoNome: tipoAtual.value?.nome,
                descricao: form.descricao,
                valorTotal: valorTotal.value,
                gerouDespesa: geraDespesa.value,
            };
            passo.value = 4;
        },
    });
}

function reiniciar() {
    form.reset();
    form.tipo = 'corretiva';
    form.status = 'concluida';
    form.data_realizada = hojeBR();
    veiculoSelecionado.value = null;
    sucesso.value = null;
    passo.value = 1;
}

function emojiVeiculo(tipo) {
    const map = { trator: '🚜', caminhao: '🚚', pick_up: '🛻', motocicleta: '🏍', implemento: '⚙️', colheitadeira: '🌾' };
    return map[tipo] || '🚜';
}
</script>

<template>
    <Head title="Arrumar máquina" />
    <AdminLayout>
        <template #page-title>Assistente · Manutenção</template>

        <PageHeader
            title="Arrumar máquina"
            subtitle="Vamos registrar a manutenção passo a passo."
        >
            <template #actions>
                <Link :href="route('admin.inicio')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <div v-if="veiculos.length === 0" class="max-w-2xl mx-auto card border-amber-300 bg-amber-50 p-4 mb-4">
            <div class="font-semibold text-amber-900">Cadastre uma máquina primeiro</div>
            <Link :href="route('admin.maquinas.veiculos.index')" class="btn-outline mt-3">Cadastrar máquina</Link>
        </div>

        <template v-else>
        <!-- PASSO 1 · A máquina -->
        <div v-if="passo === 1" class="card max-w-4xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Qual máquina você quer arrumar?</h2>
                    <p class="text-base text-slate-600 mt-2">Toque na máquina para escolher.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <button v-for="v in veiculos" :key="v.id" type="button"
                            @click="selecionarVeiculo(v)"
                            data-cy="card-veiculo"
                            :data-veiculo-id="v.id"
                            class="rounded-xl border-2 p-4 text-left transition-all hover:border-macaybas-primary"
                            :class="veiculoSelecionado?.id === v.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                        <div class="flex items-start gap-3">
                            <span class="text-3xl flex-shrink-0" aria-hidden="true">{{ emojiVeiculo(v.tipo) }}</span>
                            <div class="min-w-0">
                                <div class="font-semibold text-slate-900 truncate">{{ v.nome }}</div>
                                <div class="text-sm text-slate-500 mt-0.5 capitalize">{{ v.tipo.replace('_', ' ') }}</div>
                                <div v-if="v.placa" class="text-xs text-slate-400">{{ v.placa }}</div>
                            </div>
                        </div>
                    </button>
                </div>

                <div class="flex justify-end pt-4">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2 · O serviço -->
        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">
                    {{ emojiVeiculo(veiculoSelecionado?.tipo) }} O que foi feito no {{ veiculoSelecionado?.nome }}?
                </h2>

                <div>
                    <InputLabel value="Tipo de serviço" />
                    <div class="grid gap-2 sm:grid-cols-3">
                        <button v-for="t in TIPOS" :key="t.id" type="button"
                                @click="form.tipo = t.id"
                                :data-tipo="t.id"
                                class="rounded-xl border-2 p-3 text-left transition-all"
                                :class="form.tipo === t.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200'">
                            <div class="font-semibold text-sm">{{ t.emoji }} {{ t.nome }}</div>
                            <div class="text-xs text-slate-500 mt-1">{{ t.desc }}</div>
                        </button>
                    </div>
                </div>

                <div>
                    <InputLabel value="O que foi feito?" />
                    <textarea v-model="form.descricao" rows="3" maxlength="500"
                              data-cy="input-descricao"
                              placeholder="Ex: Troca de óleo do motor + filtro de ar"
                              class="form-input text-base"></textarea>
                </div>

                <div>
                    <InputLabel value="Quando foi feito?" />
                    <InputDate v-model="form.data_realizada" :max="hojeBR()" />
                </div>

                <div>
                    <InputLabel value="Horímetro / km (opcional)" />
                    <input v-model="form.medidor" type="number" step="1" min="0" inputmode="numeric"
                           placeholder="Ex: 1250"
                           class="form-input">
                </div>

                <div>
                    <InputLabel value="Oficina ou mecânico (opcional)" />
                    <select v-model="form.partner_id" class="form-select text-base py-3">
                        <option :value="null">— Não informar —</option>
                        <option v-for="o in oficinas" :key="o.id" :value="o.id">{{ o.nome }}</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Quanto gastou com peças?" />
                        <InputMoney v-model="form.valor_pecas" class="form-input py-3" />
                    </div>
                    <div>
                        <InputLabel value="Quanto pagou de mão de obra?" />
                        <InputMoney v-model="form.valor_servico" class="form-input py-3" />
                    </div>
                </div>

                <div v-if="valorTotal > 0" class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                    <div class="text-sm text-slate-600">Total gasto: <strong class="text-slate-900">{{ brl(valorTotal) }}</strong></div>

                    <label class="flex items-start gap-2 mt-3 cursor-pointer">
                        <input type="checkbox" v-model="form.gerar_lancamento_financeiro"
                               class="mt-1 rounded border-slate-300">
                        <span class="text-sm text-slate-700">
                            Criar <strong>conta a pagar</strong> no financeiro automaticamente
                        </span>
                    </label>

                    <div v-if="form.gerar_lancamento_financeiro" class="mt-3">
                        <InputLabel value="De qual conta vai sair o dinheiro?" />
                        <select v-model="form.account_id" class="form-select py-3">
                            <option :value="null">— Escolha a conta —</option>
                            <option v-for="c in contas" :key="c.id" :value="c.id">{{ c.nome }}</option>
                        </select>
                    </div>
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
                            <div class="text-xs uppercase tracking-wider text-slate-500">Máquina</div>
                            <div class="font-semibold text-slate-900 mt-1">{{ veiculoSelecionado?.nome }}</div>
                        </div>
                        <button @click="irPara(1)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>

                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-slate-500">Serviço</div>
                            <div class="font-semibold text-slate-900 mt-1">{{ tipoAtual?.emoji }} {{ tipoAtual?.nome }}</div>
                            <div class="text-sm text-slate-600 mt-1">{{ form.descricao }}</div>
                            <div class="text-sm text-slate-500 mt-0.5">Em {{ dataBR(form.data_realizada) }}<span v-if="oficinaNome"> · {{ oficinaNome }}</span></div>
                            <div v-if="valorTotal > 0" class="text-sm text-slate-700 mt-2 font-medium">Total: {{ brl(valorTotal) }}</div>
                            <div v-if="geraDespesa" class="text-xs text-emerald-700 mt-1">✓ Vai gerar despesa em "{{ contaNome }}"</div>
                        </div>
                        <button @click="irPara(2)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="form.processing" data-cy="confirmar" class="btn-primary px-8 py-3 text-base">
                        {{ form.processing ? 'Salvando…' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO 4 · Sucesso -->
        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
            <div class="card-body text-center space-y-5 py-8">
                <div class="text-6xl" aria-hidden="true">🔧</div>
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Manutenção registrada!</h2>
                    <p class="text-base text-slate-600 mt-2">
                        <strong>{{ sucesso.tipoNome }}</strong> em <strong>{{ sucesso.veiculo }}</strong>
                    </p>
                </div>

                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-left">
                    <div class="text-sm font-semibold text-emerald-900 mb-2">O que vai acontecer:</div>
                    <ul class="text-sm text-emerald-800 space-y-1">
                        <li>✓ Manutenção entrou no histórico da máquina</li>
                        <li v-if="sucesso.valorTotal > 0">✓ Custo total: {{ brl(sucesso.valorTotal) }}</li>
                        <li v-if="sucesso.gerouDespesa">✓ Despesa lançada no financeiro automaticamente</li>
                    </ul>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-3">
                    <button @click="reiniciar" class="btn-primary flex-1 py-3">Nova manutenção</button>
                    <Link :href="route('admin.maquinas.manutencoes.index')" class="btn-outline flex-1 py-3 text-center">Ver todas</Link>
                    <Link :href="route('admin.inicio')" class="btn-outline flex-1 py-3 text-center">Voltar ao início</Link>
                </div>
            </div>
        </div>
        </template>
    </AdminLayout>
</template>
