<script setup>
/**
 * Assistente — Registrar saída de estoque.
 * 4 passos: item (com saldo) → quantidade + motivo → confere → pronto.
 * Mostra saldo antes/depois para o usuário ver o impacto.
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import { dataBR, hojeBR } from '@/utils/format.js';
import { useInlineCreate } from '@/composables/useInlineCreate.js';

const props = defineProps({
    itens: { type: Array, required: true },
    armazens: { type: Array, required: true },
});

const PASSOS = [
    { n: 1, titulo: 'Item',     icon: '📦' },
    { n: 2, titulo: 'Detalhes', icon: '📤' },
    { n: 3, titulo: 'Confere',  icon: '📋' },
    { n: 4, titulo: 'Pronto!',  icon: '✅' },
];

const MOTIVOS = [
    { id: 'uso', rotulo: 'Uso na fazenda', desc: 'Aplicação no rebanho/lavoura/manutenção', emoji: '🚜' },
    { id: 'perda', rotulo: 'Perda', desc: 'Vencido, danificado, contaminado', emoji: '🗑️' },
    { id: 'doacao', rotulo: 'Doação', desc: 'Cedido a terceiros sem custo', emoji: '🤝' },
    { id: 'transferencia', rotulo: 'Transferência', desc: 'Mudança entre locais/farms', emoji: '↔️' },
    { id: 'outro', rotulo: 'Outro', desc: 'Outra movimentação não-listada', emoji: '📋' },
];

const passo = ref(1);
const sucesso = ref(null);
const itensLocal = ref([...props.itens]);
const armazensLocal = ref([...props.armazens]);

// `data` colide com método data() do useForm Inertia. Usamos data_movimento.
const form = useForm({
    item_id: null,
    warehouse_id: props.armazens[0]?.id ?? null,
    quantidade: 0,
    motivo: 'uso',
    data_movimento: hojeBR(),
    observacoes: '',
});

const itemSelecionado = computed(() => itensLocal.value.find(i => i.id === form.item_id));
const motivoSelecionado = computed(() => MOTIVOS.find(m => m.id === form.motivo));
const armazemSelecionado = computed(() => armazensLocal.value.find(a => a.id === form.warehouse_id));

const saldoAntes = computed(() => itemSelecionado.value?.saldo ?? 0);
const saldoDepois = computed(() => saldoAntes.value - (parseFloat(form.quantidade) || 0));
const ultrapassaSaldo = computed(() => saldoDepois.value < 0);

const novoItem = useInlineCreate({
    endpoint: route('admin.estoque.itens.inline'),
    initialForm: { nome: '', tipo: 'insumo', unidade: 'un' },
    onCreated: (i) => {
        itensLocal.value = [...itensLocal.value, { ...i, saldo: 0 }];
        form.item_id = i.id;
    },
});

const podeAvancar1 = computed(() => !!form.item_id && !!form.warehouse_id);
const podeAvancar2 = computed(() => parseFloat(form.quantidade) > 0 && !!form.motivo && !!form.data_movimento);

function avancar() { if (passo.value < 4) passo.value++; }
function voltar()  { if (passo.value > 1) passo.value--; }

function confirmar() {
    const snap = {
        item: itemSelecionado.value?.nome,
        unidade: itemSelecionado.value?.unidade,
        quantidade: parseFloat(form.quantidade),
        motivo: motivoSelecionado.value?.rotulo,
        armazem: armazemSelecionado.value?.nome,
        saldoAntes: saldoAntes.value,
        saldoDepois: saldoDepois.value,
    };
    form.transform((d) => ({
        item_id: d.item_id,
        warehouse_id: d.warehouse_id,
        quantidade: parseFloat(d.quantidade),
        motivo: d.motivo,
        data: d.data_movimento, // backend espera 'data'
        observacoes: d.observacoes,
    }));

    form.post(route('admin.fluxos.saida-estoque.store'), {
        preserveScroll: false,
        onSuccess: () => {
            sucesso.value = snap;
            passo.value = 4;
        },
    });
}

function reiniciar() {
    form.reset();
    form.warehouse_id = props.armazens[0]?.id ?? null;
    form.motivo = 'uso';
    form.data_movimento = hojeBR();
    sucesso.value = null;
    passo.value = 1;
}
</script>

<template>
    <Head title="Saída de estoque" />
    <AdminLayout>
        <template #page-title>Assistente · Saída de estoque</template>

        <PageHeader title="Registrar saída de estoque" subtitle="Anote o que saiu do estoque e o motivo. Saldo atualiza na hora.">
            <template #actions>
                <Link :href="route('admin.estoque.movimentos.index')" class="btn-outline">Sair</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- PASSO 1 — Item + armazém -->
        <div v-if="passo === 1" class="card max-w-4xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">O que está saindo?</h2>
                <p class="text-base text-slate-600">Lista mostra o saldo atual de cada item.</p>

                <div v-if="armazens.length > 1">
                    <InputLabel value="De qual armazém?" />
                    <select v-model="form.warehouse_id" class="form-select">
                        <option v-for="a in armazens" :key="a.id" :value="a.id">{{ a.nome }}</option>
                    </select>
                </div>

                <div v-if="itensLocal.length === 0" class="rounded-xl border-2 border-amber-200 bg-amber-50 p-5">
                    <div class="font-semibold text-amber-900">Nenhum item no estoque ainda</div>
                    <button type="button" @click="novoItem.abrir()" data-cy="abrir-item"
                            class="mt-3 btn-primary">+ Cadastrar item</button>
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 max-h-[55vh] overflow-y-auto pr-1">
                    <button v-for="i in itensLocal" :key="i.id" type="button" @click="form.item_id = i.id"
                        :data-item-id="i.id"
                        class="text-left rounded-xl border-2 p-3 transition-all hover:border-macaybas-primary"
                        :class="form.item_id === i.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                        <div class="font-bold text-slate-900 truncate">📦 {{ i.nome }}</div>
                        <div class="text-sm text-slate-600 mt-1">
                            Saldo: <strong>{{ Number(i.saldo).toLocaleString('pt-BR') }} {{ i.unidade }}</strong>
                        </div>
                    </button>
                </div>

                <div v-if="itensLocal.length > 0" class="text-right">
                    <button @click="novoItem.abrir()" class="inline-flex items-center min-h-9 px-2 text-sm text-macaybas-primary hover:underline lg:min-h-0 lg:px-0">
                        + Cadastrar item novo
                    </button>
                </div>

                <div class="flex justify-end pt-4 border-t">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2 — Quantidade + motivo -->
        <div v-if="passo === 2" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Quanto saiu e por quê?</h2>

                <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm">
                    <strong>{{ itemSelecionado?.nome }}</strong> · saldo: {{ saldoAntes }} {{ itemSelecionado?.unidade }}
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel :value="'Quantidade que saiu (' + (itemSelecionado?.unidade ?? '') + ')'" />
                        <input v-model="form.quantidade" type="number" step="0.01" min="0.01"
                            data-cy="input-quantidade"
                            class="form-input text-xl py-3 font-mono" />
                        <p v-if="ultrapassaSaldo" class="text-xs text-amber-700 mt-1">
                            ⚠ Saída maior que o saldo atual ({{ saldoAntes }} {{ itemSelecionado?.unidade }}). Sistema permite — ajuste depois com "Ajustar estoque" se preciso.
                        </p>
                    </div>
                    <div>
                        <InputLabel value="Data" />
                        <InputDate v-model="form.data_movimento" :max="hojeBR()" />
                    </div>
                </div>

                <div>
                    <InputLabel value="Motivo *" />
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button v-for="m in MOTIVOS" :key="m.id" type="button" @click="form.motivo = m.id"
                            :data-motivo="m.id"
                            class="text-left rounded-lg border-2 p-3 transition-all"
                            :class="form.motivo === m.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                            <div class="flex items-start gap-2">
                                <span class="text-xl">{{ m.emoji }}</span>
                                <div>
                                    <div class="font-semibold text-slate-900">{{ m.rotulo }}</div>
                                    <div class="text-xs text-slate-500">{{ m.desc }}</div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <div>
                    <InputLabel value="Observações (opcional)" />
                    <textarea v-model="form.observacoes" rows="2" class="form-textarea"
                        placeholder="Ex.: aplicado no rebanho leiteiro, lote N12"></textarea>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar2" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 3 — Conferência -->
        <div v-if="passo === 3" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Confere antes de salvar</h2>

                <div class="rounded-xl border-2 border-emerald-200 bg-emerald-50 p-4 text-sm">
                    <ul class="text-emerald-900 space-y-1.5">
                        <li>📦 <strong>{{ form.quantidade }} {{ itemSelecionado?.unidade }}</strong> de {{ itemSelecionado?.nome }}</li>
                        <li>{{ motivoSelecionado?.emoji }} Motivo: <strong>{{ motivoSelecionado?.rotulo }}</strong></li>
                        <li>📅 {{ dataBR(form.data_movimento) }}</li>
                        <li v-if="armazemSelecionado">📍 De: {{ armazemSelecionado.nome }}</li>
                    </ul>
                </div>

                <!-- Impacto no saldo -->
                <div class="grid grid-cols-3 gap-2 text-center text-sm">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-xs text-slate-500">Saldo agora</div>
                        <div class="text-xl font-bold font-mono text-slate-700">{{ saldoAntes }}</div>
                    </div>
                    <div class="rounded-lg bg-amber-50 p-3">
                        <div class="text-xs text-amber-700">Saindo</div>
                        <div class="text-xl font-bold font-mono text-amber-700">- {{ form.quantidade }}</div>
                    </div>
                    <div class="rounded-lg p-3" :class="ultrapassaSaldo ? 'bg-red-50' : 'bg-emerald-50'">
                        <div class="text-xs" :class="ultrapassaSaldo ? 'text-red-700' : 'text-emerald-700'">Saldo final</div>
                        <div class="text-xl font-bold font-mono" :class="ultrapassaSaldo ? 'text-red-700' : 'text-emerald-700'">
                            {{ saldoDepois.toFixed(2) }}
                        </div>
                    </div>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="form.processing" data-cy="confirmar"
                            class="btn-primary px-8 py-2.5">
                        {{ form.processing ? 'Salvando…' : '✓ Registrar saída' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO 4 — Sucesso -->
        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
            <div class="card-body text-center py-8 space-y-4">
                <div class="text-5xl">📤</div>
                <h2 class="text-2xl font-semibold text-slate-900">Saída registrada!</h2>
                <p class="text-slate-700">
                    <strong>{{ sucesso.quantidade }} {{ sucesso.unidade }}</strong> de {{ sucesso.item }} ({{ sucesso.motivo }})
                </p>
                <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-left text-sm">
                    <p>✓ Saldo de <strong>{{ sucesso.saldoAntes }}</strong> agora é <strong>{{ sucesso.saldoDepois.toFixed(2) }}</strong></p>
                    <p>✓ Movimento gravado no histórico do item</p>
                    <p v-if="sucesso.saldoDepois <= 0">⚠ Item zerou — considere repor</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 justify-center pt-3">
                    <button @click="reiniciar" class="btn-primary">Outra saída</button>
                    <Link :href="route('admin.estoque.movimentos.index')" class="btn-outline">Ver movimentos</Link>
                </div>
            </div>
        </div>

        <!-- Modal: criar item inline -->
        <Teleport to="body">
            <div v-if="novoItem.modalAberto.value" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novoItem.fechar"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <h3 class="text-lg font-semibold mb-1">Novo item de estoque</h3>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Nome *" />
                            <input v-model="novoItem.form.value.nome" data-cy="item-nome"
                                class="form-input" placeholder="Ex.: Ração bovino" />
                        </div>
                        <div>
                            <InputLabel value="Unidade *" />
                            <select v-model="novoItem.form.value.unidade" class="form-select">
                                <option value="un">unidade</option>
                                <option value="kg">kg</option>
                                <option value="L">litro</option>
                                <option value="sc">saca</option>
                                <option value="cx">caixa</option>
                            </select>
                        </div>
                    </div>
                    <p v-if="novoItem.erro.value" class="mt-3 text-xs text-red-700">{{ novoItem.erro.value }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoItem.fechar" class="btn-outline">Cancelar</button>
                        <button @click="novoItem.salvar" data-cy="item-salvar"
                                :disabled="novoItem.salvando.value || !novoItem.form.value.nome?.trim()"
                                class="btn-primary">
                            {{ novoItem.salvando.value ? 'Salvando…' : 'Cadastrar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
