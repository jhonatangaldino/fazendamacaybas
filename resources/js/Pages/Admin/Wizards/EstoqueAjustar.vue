<script setup>
/**
 * Assistente — Ajustar estoque (correção de saldo).
 * 3 passos + sucesso.
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import InputDecimal from '@/Components/InputDecimal.vue';
import { hojeBR } from '@/utils/format.js';
import { useInlineCreate } from '@/composables/useInlineCreate.js';

const props = defineProps({
    itens: { type: Array, required: true },
    armazens: { type: Array, required: true },
});

// Itens local (recebe novos criados inline)
const itensLocal = ref([...props.itens]);

// Criação inline de ITEM de estoque sem sair do wizard
const novoItem = useInlineCreate({
    endpoint: route('admin.estoque.itens.inline'),
    initialForm: { nome: '', tipo: 'insumo', unidade: 'kg', estoque_minimo: 0 },
    onCreated: (i) => {
        itensLocal.value = [...itensLocal.value, i];
        form.item_id = i.id;
    },
});

const PASSOS = [
    { n: 1, titulo: 'O produto',   icon: '📦' },
    { n: 2, titulo: 'A diferença', icon: '🔢' },
    { n: 3, titulo: 'Conferência', icon: '📋' },
    { n: 4, titulo: 'Pronto!',     icon: '✅' },
];

// Motivos redigidos em tom operacional — evitam acusações diretas
// (o antigo "Roubo ou sumiço" força o usuário a etiquetar evento como crime).
// "Desaparecimento" cobre roubo, extravio, erro de registro anterior, etc.
const MOTIVOS = [
    { id: 'contagem', nome: 'Contagem física não bate' },
    { id: 'perda',    nome: 'Perda (vencimento, avaria, quebra)' },
    { id: 'roubo',    nome: 'Desaparecimento / sumiço inexplicado' },
    { id: 'outro',    nome: 'Outro motivo' },
];

const passo = ref(1);
const sucesso = ref(null);

// `data` colide com método data() do useForm (Inertia). Usamos data_movimento.
const form = useForm({
    item_id: null,
    warehouse_id: props.armazens[0]?.id ?? null,
    quantidade: '',           // pode ser negativa (diferença)
    motivo: 'contagem',
    data_movimento: hojeBR(),
    observacoes: '',
});

const itemAtual = computed(() => itensLocal.value.find(i => i.id === form.item_id));
const armazemAtual = computed(() => props.armazens.find(a => a.id === form.warehouse_id));

const podeAvancar1 = computed(() => !!form.item_id && !!form.warehouse_id);
const podeAvancar2 = computed(() => {
    const q = parseFloat(String(form.quantidade).replace(',', '.'));
    return !isNaN(q) && q !== 0 && !!form.motivo;
});

function avancar() { if (passo.value < PASSOS.length) passo.value++; }
function voltar() { if (passo.value > 1) passo.value--; }
function irPara(n) { passo.value = n; }

function confirmar() {
    const snapshot = {
        item: itemAtual.value?.nome,
        quantidade: parseFloat(String(form.quantidade).replace(',', '.')),
        unidade: itemAtual.value?.unidade,
    };

    sucesso.value = snapshot;
    passo.value = 4;

    form.transform((d) => ({
        item_id: d.item_id,
        warehouse_id: d.warehouse_id,
        quantidade: parseFloat(String(d.quantidade).replace(',', '.')),
        motivo: d.motivo,
        data: d.data_movimento, // backend espera 'data'
        observacoes: d.observacoes,
    }));

    form.post(route('admin.fluxos.ajustar-estoque.store'), {
        preserveScroll: false,
        onSuccess: (page) => {
            // Contexto de saldo (anterior + atual) enriquece o passo 4 —
            // usuário vê que o ajuste teve efeito, não só "registrado".
            const ctx = page?.props?.flash?.ajuste_contexto
                ?? usePage()?.props?.flash?.ajuste_contexto
                ?? null;
            if (ctx) sucesso.value = { ...sucesso.value, ctx };
        },
        onError: () => {
            sucesso.value = null;
            passo.value = 3;
        },
    });
}

function reiniciar() {
    form.reset();
    form.warehouse_id = props.armazens[0]?.id ?? null;
    form.motivo = 'contagem';
    form.data_movimento = hojeBR();
    sucesso.value = null;
    passo.value = 1;
}
</script>

<template>
    <Head title="Ajustar estoque" />
    <AdminLayout>
        <template #page-title>Assistente · Ajustar estoque</template>

        <PageHeader title="Ajustar estoque" subtitle="Corrigir saldo do armazém.">
            <template #actions>
                <Link :href="route('admin.inicio')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <template v-if="itensLocal.length > 0 || passo === 1">
        <div v-if="passo === 1" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Qual produto precisa ajustar?</h2>
                <div>
                    <InputLabel value="Produto" />
                    <select v-model="form.item_id" data-cy="select-item" class="form-select text-base py-3">
                        <option :value="null">Escolha…</option>
                        <option v-for="i in itensLocal" :key="i.id" :value="i.id">{{ i.nome }} ({{ i.unidade }})</option>
                    </select>
                    <div class="mt-1 flex items-center gap-2">
                        <button type="button" @click="novoItem.abrir()" class="inline-flex items-center min-h-9 px-3 py-2 text-sm text-macaybas-primary hover:bg-macaybas-primary-50 rounded-md">
                            + Cadastrar produto novo
                        </button>
                        <span v-if="itensLocal.length === 0" class="text-xs text-amber-700">
                            Nenhum produto ainda — cadastre aqui.
                        </span>
                    </div>
                </div>
                <div>
                    <InputLabel value="Em qual armazém?" />
                    <select v-model="form.warehouse_id" class="form-select text-base py-3">
                        <option v-for="a in armazens" :key="a.id" :value="a.id">{{ a.nome }}</option>
                    </select>
                </div>
                <div class="flex justify-end pt-4">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Qual a diferença?</h2>
                <p class="text-sm text-slate-600">
                    Se <strong>sobrou</strong> (conferi e tem mais do que aparecia), use valor <strong>positivo</strong>.<br>
                    Se <strong>faltou</strong> (conferi e tem menos), use valor <strong>negativo</strong> (com sinal de menos).
                </p>

                <div>
                    <InputLabel :value="`Diferença em ${itemAtual?.unidade || 'un'} (use negativo se faltou)`" />
                    <InputDecimal v-model="form.quantidade" :decimals="2"
                                  placeholder="Ex: 5 ou -3"
                                  input-class="form-input text-2xl py-4 font-mono" />
                </div>

                <div>
                    <InputLabel value="Motivo" />
                    <div class="grid gap-2">
                        <button v-for="m in MOTIVOS" :key="m.id" type="button"
                                @click="form.motivo = m.id"
                                :data-motivo="m.id"
                                class="text-left rounded-lg border-2 p-3 transition-all"
                                :class="form.motivo === m.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200'">
                            {{ m.nome }}
                        </button>
                    </div>
                </div>

                <div>
                    <InputLabel value="Detalhes (opcional)" />
                    <textarea v-model="form.observacoes" rows="2" class="form-input"></textarea>
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar2" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <div v-if="passo === 3" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Confere?</h2>
                <div class="space-y-3">
                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between">
                        <div>
                            <div class="text-xs uppercase tracking-wider text-slate-500">Produto</div>
                            <div class="font-semibold mt-1">{{ itemAtual?.nome }} · {{ armazemAtual?.nome }}</div>
                        </div>
                        <button @click="irPara(1)" class="text-sm text-macaybas-primary hover:underline">Trocar</button>
                    </div>
                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between">
                        <div>
                            <div class="text-xs uppercase tracking-wider text-slate-500">Ajuste</div>
                            <div class="text-2xl font-bold mt-1" :class="form.quantidade > 0 ? 'text-emerald-700' : 'text-red-700'">
                                {{ form.quantidade > 0 ? '+' : '' }}{{ form.quantidade }} {{ itemAtual?.unidade }}
                            </div>
                            <div class="text-sm text-slate-500 mt-1">{{ MOTIVOS.find(m => m.id === form.motivo)?.nome }}</div>
                        </div>
                        <button @click="irPara(2)" class="text-sm text-macaybas-primary hover:underline">Trocar</button>
                    </div>
                </div>
                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="form.processing" data-cy="confirmar" class="btn-primary px-8 py-3 text-base">
                        {{ form.processing ? 'Salvando…' : 'Confirmar ✓' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
            <div class="card-body text-center space-y-5 py-8">
                <div class="text-6xl">🔢</div>
                <h2 class="text-2xl font-semibold text-slate-900">Estoque ajustado!</h2>
                <p class="text-base text-slate-600">
                    <strong>{{ sucesso.item }}</strong>: {{ sucesso.quantidade > 0 ? '+' : '' }}{{ sucesso.quantidade }} {{ sucesso.unidade }}
                </p>

                <!-- Saldo anterior → atual (entrega de valor: usuário vê o efeito real) -->
                <div v-if="sucesso.ctx" class="rounded-lg p-4 bg-white border-2 border-slate-200 text-left">
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold mb-2">
                        📊 Saldo no armazém
                    </div>
                    <div class="grid grid-cols-3 gap-3 items-center text-sm">
                        <div class="text-center">
                            <div class="text-xs text-slate-500">Antes</div>
                            <div class="font-mono font-semibold text-slate-700 mt-1">
                                {{ Number(sucesso.ctx.saldo_anterior).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) }} {{ sucesso.unidade }}
                            </div>
                        </div>
                        <div class="text-center text-2xl" aria-hidden="true">
                            {{ sucesso.ctx.ajuste > 0 ? '↗' : '↘' }}
                        </div>
                        <div class="text-center">
                            <div class="text-xs text-slate-500">Agora</div>
                            <div class="font-mono font-bold text-lg mt-1"
                                 :class="sucesso.ctx.saldo_atual >= 0 ? 'text-emerald-700' : 'text-red-700'">
                                {{ Number(sucesso.ctx.saldo_atual).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) }} {{ sucesso.unidade }}
                            </div>
                        </div>
                    </div>
                    <p v-if="sucesso.ctx.saldo_atual < 0" class="mt-3 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1">
                        ⚠ Saldo negativo — revise as entradas ou ajuste novamente se for erro.
                    </p>
                </div>

                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-left">
                    <ul class="text-sm text-emerald-800 space-y-1">
                        <li>✓ Saldo corrigido para bater com a realidade</li>
                        <li>✓ Ajuste registrado no histórico</li>
                    </ul>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 pt-3">
                    <button @click="reiniciar" class="btn-primary flex-1 py-3">Ajustar outro</button>
                    <Link :href="route('admin.estoque.movimentos.index')" class="btn-outline flex-1 py-3 text-center">Ver movimentações</Link>
                    <Link :href="route('admin.inicio')" class="btn-outline flex-1 py-3 text-center">Voltar ao início</Link>
                </div>
            </div>
        </div>
        </template>

        <!-- Modal inline: cadastrar produto de estoque sem sair do wizard -->
        <Teleport to="body">
            <div v-if="novoItem.modalAberto.value" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novoItem.fechar"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <h3 class="text-lg font-semibold mb-1">Novo produto</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        Informe nome, tipo e unidade. Depois você pode completar mais detalhes em "Estoque → Itens".
                    </p>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Nome *" />
                            <input v-model="novoItem.form.value.nome" class="form-input" placeholder="Ex.: Ração bovina" required>
                        </div>
                        <div>
                            <InputLabel value="Tipo *" />
                            <select v-model="novoItem.form.value.tipo" class="form-select">
                                <option value="insumo">Insumo</option>
                                <option value="medicamento">Medicamento</option>
                                <option value="racao">Ração</option>
                                <option value="ferramenta">Ferramenta</option>
                                <option value="peca">Peça</option>
                                <option value="combustivel">Combustível</option>
                                <option value="material">Material</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Unidade *" />
                            <div class="grid grid-cols-4 gap-2">
                                <button v-for="u in ['kg','L','un','saco','cx','g','mL','m']"
                                        :key="u" type="button"
                                        @click="novoItem.form.value.unidade = u"
                                        class="py-2 rounded border-2 text-sm font-medium"
                                        :class="novoItem.form.value.unidade === u ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200'">
                                    {{ u }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-if="novoItem.erro.value" class="mt-3 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1.5">
                        {{ novoItem.erro.value }}
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoItem.fechar" class="btn-outline">Cancelar</button>
                        <button @click="novoItem.salvar"
                                :disabled="novoItem.salvando.value || !novoItem.form.value.nome?.trim()"
                                class="btn-primary">
                            {{ novoItem.salvando.value ? 'Salvando…' : 'Cadastrar e voltar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
