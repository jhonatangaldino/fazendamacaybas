<script setup>
/**
 * Assistente guiado — Criar tarefa.
 * 5 passos: o que / sobre / quem+quando / confirmar / pronto.
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import { dataBR } from '@/utils/format.js';

const props = defineProps({
    funcionarios: { type: Array, required: true },
    linkables: { type: Object, required: true },
});

// `singular` usado na label dinâmica "Qual {singular}?" — evita gramática
// estranha como "Qual rebanho?" (quando na verdade queremos "Qual animal?").
const MODULOS = [
    { id: 'rebanho',   nome: 'Rebanho',    singular: 'animal',    icon: '🐄', linkType: 'App\\Models\\Livestock\\Animal',  lista: 'animais',   label: (x) => `${x.identificacao}${x.nome ? ' · ' + x.nome : ''}` },
    { id: 'maquinas',  nome: 'Máquinas',   singular: 'máquina',   icon: '🔧', linkType: 'App\\Models\\Vehicle\\Vehicle',   lista: 'veiculos',  label: (x) => x.nome },
    { id: 'financeiro',nome: 'Financeiro', singular: 'parceiro',  icon: '💰', linkType: 'App\\Models\\Partner',             lista: 'parceiros', label: (x) => x.nome },
    { id: 'geral',     nome: 'Geral',      singular: 'parceiro',  icon: '📋', linkType: 'App\\Models\\Partner',             lista: 'parceiros', label: (x) => x.nome },
];

const PRIORIDADES = [
    { id: 'baixa',   nome: 'Baixa',   emoji: '🌿' },
    { id: 'media',   nome: 'Média',   emoji: '📋' },
    { id: 'alta',    nome: 'Alta',    emoji: '⚠️' },
    { id: 'urgente', nome: 'Urgente', emoji: '🚨' },
];

const PASSOS = [
    { n: 1, titulo: 'O que fazer', icon: '📋' },
    { n: 2, titulo: 'Sobre',       icon: '🔗' },
    { n: 3, titulo: 'Quem e quando', icon: '👷' },
    { n: 4, titulo: 'Conferência', icon: '✓' },
    { n: 5, titulo: 'Pronto!',     icon: '✅' },
];

const passo = ref(1);
const sucesso = ref(null);

const form = useForm({
    titulo: '',
    descricao: '',
    modulo: 'geral',
    related_type: 'App\\Models\\Partner',
    related_id: null,
    prioridade: 'media',
    data_vencimento: '',
    assignees: [],
});

const moduloAtual = computed(() => MODULOS.find(m => m.id === form.modulo));
const listaVinculos = computed(() => props.linkables[moduloAtual.value?.lista] ?? []);
const vinculoSelecionado = computed(() => listaVinculos.value.find(x => x.id === form.related_id));

const podeAvancar1 = computed(() => form.titulo.trim().length >= 3);
const podeAvancar2 = computed(() => !!form.modulo && !!form.related_id);
const podeAvancar3 = computed(() => form.assignees.length > 0);

function selecionarModulo(m) {
    form.modulo = m.id;
    form.related_type = m.linkType;
    form.related_id = null;
}

function toggleAssignee(id) {
    const i = form.assignees.indexOf(id);
    if (i >= 0) form.assignees.splice(i, 1);
    else form.assignees.push(id);
}

function avancar() { if (passo.value < PASSOS.length) passo.value++; }
function voltar() { if (passo.value > 1) passo.value--; }
function irPara(n) { passo.value = n; }

function confirmar() {
    form.post(route('admin.fluxos.criar-tarefa.store'), {
        preserveScroll: false,
        onSuccess: () => {
            sucesso.value = {
                titulo: form.titulo,
                modulo: moduloAtual.value?.nome,
                vinculo: vinculoSelecionado.value ? moduloAtual.value.label(vinculoSelecionado.value) : '',
                responsaveis: props.funcionarios.filter(f => form.assignees.includes(f.id)).map(f => f.nome).join(', '),
            };
            passo.value = 5;
        },
    });
}

function reiniciar() {
    form.reset();
    form.modulo = 'geral';
    form.prioridade = 'media';
    form.related_type = 'App\\Models\\Partner';
    sucesso.value = null;
    passo.value = 1;
}
</script>

<template>
    <Head title="Criar tarefa" />
    <AdminLayout>
        <template #page-title>Assistente · Criar tarefa</template>

        <PageHeader
            title="Criar tarefa"
            subtitle="Vamos combinar o que precisa ser feito."
        >
            <template #actions>
                <Link :href="route('admin.inicio')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- B4.4 fix · padronização de bloqueio com pattern do wizard Despesa
             (2 CTAs estilizados: ação principal verde + secundária branca) -->
        <div v-if="funcionarios.length === 0" class="max-w-2xl mx-auto card border-amber-300 bg-amber-50 p-5 mb-4">
            <div class="font-semibold text-amber-900 mb-1">Você precisa cadastrar um funcionário primeiro</div>
            <p class="text-sm text-amber-800 mb-4">Tarefas precisam ter pelo menos um responsável atribuído.</p>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('admin.funcionarios.index') + '?abrir=novo'" class="btn-primary">
                    + Cadastrar funcionário agora
                </Link>
                <Link :href="route('admin.funcionarios.index')" class="btn-outline">
                    Gerenciar funcionários
                </Link>
            </div>
        </div>

        <template v-else>
        <!-- PASSO 1 -->
        <div v-if="passo === 1" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">O que é pra fazer?</h2>

                <div>
                    <InputLabel value="O que precisa ser feito?" />
                    <input v-model="form.titulo" type="text" maxlength="200"
                           data-cy="input-titulo"
                           placeholder="Ex: Capinar pasto 2, Trocar óleo do trator"
                           class="form-input text-lg py-3">
                </div>

                <div>
                    <InputLabel value="Detalhes (opcional)" />
                    <textarea v-model="form.descricao" rows="3"
                              placeholder="Detalhes extras: como fazer, ferramentas, cuidados"
                              class="form-input"></textarea>
                </div>

                <div class="flex justify-end pt-4">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2 -->
        <div v-if="passo === 2" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Sobre o quê é essa tarefa?</h2>
                <p class="text-sm text-slate-600">
                    Toda tarefa precisa ser sobre alguma coisa da fazenda — um animal, uma máquina, ou um parceiro. Isso ajuda a achar a tarefa depois.
                </p>

                <div>
                    <InputLabel value="Área da fazenda" />
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <button v-for="m in MODULOS" :key="m.id" type="button"
                                @click="selecionarModulo(m)"
                                class="rounded-xl border-2 p-3 text-center transition-all"
                                :class="form.modulo === m.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200'">
                            <div class="text-2xl">{{ m.icon }}</div>
                            <div class="text-sm font-semibold mt-1">{{ m.nome }}</div>
                        </button>
                    </div>
                </div>

                <div v-if="moduloAtual">
                    <InputLabel :value="`Qual ${moduloAtual.singular}?`" />
                    <select v-model="form.related_id" class="form-select text-base py-3">
                        <option :value="null">— Escolha —</option>
                        <option v-for="x in listaVinculos" :key="x.id" :value="x.id">{{ moduloAtual.label(x) }}</option>
                    </select>
                    <p v-if="listaVinculos.length === 0" class="text-sm text-amber-700 mt-1">
                        Nenhum {{ moduloAtual.singular }} cadastrado ainda.
                    </p>
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar2" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 3 -->
        <div v-if="passo === 3" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Quem faz e quando?</h2>

                <div>
                    <InputLabel value="Quem vai fazer?" />
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button v-for="f in funcionarios" :key="f.id" type="button"
                                @click="toggleAssignee(f.id)"
                                class="text-left rounded-lg border-2 p-3 transition-all"
                                :class="form.assignees.includes(f.id) ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200'">
                            <div class="flex items-center gap-2">
                                <span v-if="form.assignees.includes(f.id)" class="text-macaybas-primary">✓</span>
                                <span class="text-slate-900">{{ f.nome }}</span>
                            </div>
                        </button>
                    </div>
                </div>

                <div>
                    <InputLabel value="Prioridade" />
                    <div class="grid grid-cols-4 gap-2">
                        <button v-for="p in PRIORIDADES" :key="p.id" type="button"
                                @click="form.prioridade = p.id"
                                class="rounded-lg border-2 p-2 text-center transition-all"
                                :class="form.prioridade === p.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200'">
                            <div class="text-xl">{{ p.emoji }}</div>
                            <div class="text-xs mt-1">{{ p.nome }}</div>
                        </button>
                    </div>
                </div>

                <div>
                    <InputLabel value="Prazo (opcional)" />
                    <InputDate v-model="form.data_vencimento" />
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar3" class="btn-primary px-8 py-3 text-base">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 4 -->
        <div v-if="passo === 4" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900">Confere os dados?</h2>
                    <p class="text-base text-slate-600 mt-2">
                        Antes de criar a tarefa, dê uma olhada. Se precisar mudar algo, clique em <strong>Trocar</strong> ao lado.
                    </p>
                </div>

                <div class="space-y-3">
                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-slate-500">A tarefa</div>
                            <div class="font-semibold text-slate-900 mt-1">{{ form.titulo }}</div>
                            <div v-if="form.descricao" class="text-sm text-slate-600 mt-1">{{ form.descricao }}</div>
                        </div>
                        <button @click="irPara(1)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>
                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-slate-500">Sobre o quê</div>
                            <div class="font-medium text-slate-900 mt-1">{{ moduloAtual?.icon }} {{ moduloAtual?.nome }}<span v-if="vinculoSelecionado"> · {{ moduloAtual.label(vinculoSelecionado) }}</span></div>
                        </div>
                        <button @click="irPara(2)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>
                    <div class="p-4 rounded-lg border border-slate-200 bg-white flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-slate-500">Quem faz e quando</div>
                            <div class="text-slate-900 mt-1">
                                {{ funcionarios.filter(f => form.assignees.includes(f.id)).map(f => f.nome).join(', ') }}
                            </div>
                            <div class="text-sm text-slate-500 mt-0.5">
                                Prioridade: <strong>{{ PRIORIDADES.find(p => p.id === form.prioridade)?.emoji }} {{ PRIORIDADES.find(p => p.id === form.prioridade)?.nome }}</strong>
                            </div>
                            <div v-if="form.data_vencimento" class="text-sm text-slate-500 mt-0.5">Para ser feito até {{ dataBR(form.data_vencimento) }}</div>
                        </div>
                        <button @click="irPara(3)" class="text-sm text-macaybas-primary hover:underline flex-shrink-0">Trocar</button>
                    </div>
                </div>

                <div class="flex justify-between pt-4">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="form.processing" data-cy="confirmar" class="btn-primary px-8 py-3 text-base">
                        {{ form.processing ? 'Salvando…' : 'Criar tarefa' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO 5 · Sucesso -->
        <div v-if="passo === 5 && sucesso" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
            <div class="card-body text-center space-y-5 py-8">
                <div class="text-6xl">📋</div>
                <h2 class="text-2xl font-semibold text-slate-900">Tarefa criada!</h2>
                <p class="text-base text-slate-600">
                    <strong>{{ sucesso.titulo }}</strong><br>
                    {{ sucesso.modulo }}<span v-if="sucesso.vinculo"> · {{ sucesso.vinculo }}</span><br>
                    Responsável: <strong>{{ sucesso.responsaveis }}</strong>
                </p>

                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-left">
                    <div class="text-sm font-semibold text-emerald-900 mb-2">O que vai acontecer:</div>
                    <ul class="text-sm text-emerald-800 space-y-1">
                        <li>✓ Tarefa entrou na lista de pendentes</li>
                        <li>✓ Responsáveis verão na área deles</li>
                    </ul>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-3">
                    <button @click="reiniciar" class="btn-primary flex-1 py-3">Criar outra tarefa</button>
                    <Link :href="route('admin.tarefas.index')" class="btn-outline flex-1 py-3 text-center">Ver tarefas</Link>
                    <Link :href="route('admin.inicio')" class="btn-outline flex-1 py-3 text-center">Voltar ao início</Link>
                </div>
            </div>
        </div>
        </template>
    </AdminLayout>
</template>
