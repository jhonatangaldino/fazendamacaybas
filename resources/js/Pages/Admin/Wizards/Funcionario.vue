<script setup>
/**
 * Assistente guiado — Cadastrar funcionário.
 * 5 passos: tipo de contrato → identificação → cargo → remuneração → conferir/sucesso.
 * Adapta perguntas conforme tipo de contrato escolhido.
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMoney from '@/Components/InputMoney.vue';
import { brl, dataBR, hojeBR } from '@/utils/format.js';

const props = defineProps({
    tipos_contrato: { type: Array, required: true },
});

const PASSOS = [
    { n: 1, titulo: 'Vínculo',     icon: '📋' },
    { n: 2, titulo: 'Identificação', icon: '👤' },
    { n: 3, titulo: 'Função',      icon: '⚒️' },
    { n: 4, titulo: 'Pagamento',   icon: '💰' },
    { n: 5, titulo: 'Pronto!',     icon: '✅' },
];

const passo = ref(1);
const sucesso = ref(null);

const form = useForm({
    tipo_contrato: null,
    nome: '',
    cpf: '',
    funcao: '',
    setor: '',
    salario: 0,
    data_admissao: hojeBR(),
    data_demissao: '',
    telefone: '',
    celular: '',
    email: '',
});

const tipoSelecionado = computed(() => props.tipos_contrato.find(t => t.id === form.tipo_contrato));

// Adaptação: safrista exige data fim. CLT exige admissão. Diarista é informal.
const exigeDataFim = computed(() => form.tipo_contrato === 'safrista');
const recomendaCpf = computed(() => ['clt', 'pj'].includes(form.tipo_contrato));

const podeAvancar1 = computed(() => !!form.tipo_contrato);
const podeAvancar2 = computed(() => form.nome.trim().length >= 2);
const podeAvancar3 = computed(() => true); // cargo opcional
const podeAvancar4 = computed(() => {
    if (exigeDataFim.value && !form.data_demissao) return false;
    return true;
});

function avancar() { if (passo.value < 5) passo.value++; }
function voltar() { if (passo.value > 1) passo.value--; }
function irPara(n) { passo.value = n; }

function confirmar() {
    form.post(route('admin.fluxos.cadastrar-funcionario.store'), {
        preserveScroll: false,
        onSuccess: () => {
            sucesso.value = {
                nome: form.nome,
                tipo: tipoSelecionado.value?.rotulo,
                funcao: form.funcao,
                salario: form.salario,
            };
            passo.value = 5;
        },
    });
}

function reiniciar() {
    form.reset();
    form.data_admissao = hojeBR();
    form.salario = 0;
    sucesso.value = null;
    passo.value = 1;
}
</script>

<template>
    <Head title="Cadastrar funcionário" />
    <AdminLayout>
        <template #page-title>Assistente · Funcionário</template>

        <PageHeader title="Cadastrar funcionário" subtitle="Vamos te guiar passo a passo. Pode preencher só o essencial agora.">
            <template #actions>
                <Link :href="route('admin.funcionarios.index')" class="btn-outline">Sair</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- PASSO 1 — Tipo de contrato -->
        <div v-if="passo === 1" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Que tipo de vínculo?</h2>
                <p class="text-base text-slate-600">Isso ajuda a escolher quais campos pedir depois. CLT/PJ = carteira; diarista/safrista = informal/temporário.</p>

                <div class="grid gap-3 sm:grid-cols-2">
                    <button v-for="t in tipos_contrato" :key="t.id" type="button" @click="form.tipo_contrato = t.id"
                        :data-tipo="t.id"
                        class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary"
                        :class="form.tipo_contrato === t.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                        <div class="font-bold text-slate-900">{{ t.rotulo }}</div>
                        <div class="text-sm text-slate-600 mt-1">{{ t.desc }}</div>
                    </button>
                </div>

                <div class="flex justify-end pt-4 border-t">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2 — Identificação -->
        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Quem é o funcionário?</h2>
                <p v-if="recomendaCpf" class="text-sm text-slate-600">
                    Para {{ tipoSelecionado?.rotulo }}, é importante registrar o CPF. Pode preencher depois também.
                </p>
                <p v-else class="text-sm text-slate-600">
                    Diarista/safrista pode ficar com CPF em branco — preenche se tiver.
                </p>

                <div>
                    <InputLabel value="Nome completo *" />
                    <input v-model="form.nome" data-cy="input-nome" class="form-input text-lg py-3" placeholder="Ex.: João da Silva" />
                </div>
                <div>
                    <InputLabel :value="recomendaCpf ? 'CPF (recomendado)' : 'CPF (opcional)'" />
                    <input v-model="form.cpf" type="text" class="form-input" placeholder="000.000.000-00" />
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar2" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 3 — Função -->
        <div v-if="passo === 3" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">O que ele faz na fazenda?</h2>
                <p class="text-sm text-slate-600">Pode descrever do jeito que você fala. Ajuda na hora de atribuir tarefas.</p>

                <div>
                    <InputLabel value="Função / cargo (opcional)" />
                    <input v-model="form.funcao" data-cy="input-funcao" class="form-input" placeholder="Ex.: Vaqueiro, Operador de trator, Tratorista" />
                </div>
                <div>
                    <InputLabel value="Setor (opcional)" />
                    <input v-model="form.setor" class="form-input" placeholder="Ex.: Rebanho leiteiro, Lavoura, Manutenção" />
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar3" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 4 — Pagamento -->
        <div v-if="passo === 4" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Quanto e desde quando?</h2>

                <div>
                    <InputLabel :value="form.tipo_contrato === 'diarista' ? 'Valor da diária (opcional)' : 'Salário mensal (opcional)'" />
                    <InputMoney v-model="form.salario" data-cy="input-salario" class="text-xl py-3" />
                </div>
                <div>
                    <InputLabel value="Data de admissão" />
                    <InputDate v-model="form.data_admissao" :max="hojeBR()" />
                </div>
                <div v-if="exigeDataFim">
                    <InputLabel value="Fim do contrato (safrista) *" />
                    <InputDate v-model="form.data_demissao" :min="form.data_admissao" />
                    <p class="text-xs text-slate-500 mt-1">Safrista exige data prevista de fim do contrato.</p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4 pt-3 border-t">
                    <div>
                        <InputLabel value="Telefone (opcional)" />
                        <input v-model="form.celular" class="form-input" placeholder="(00) 00000-0000" />
                    </div>
                    <div>
                        <InputLabel value="E-mail (opcional)" />
                        <input v-model="form.email" type="email" class="form-input" placeholder="email@exemplo.com" />
                    </div>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="!podeAvancar4 || form.processing"
                        data-cy="confirmar"
                        class="btn-primary px-8 py-2.5">
                        {{ form.processing ? 'Salvando…' : '✓ Cadastrar funcionário' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO 5 — Sucesso -->
        <div v-if="passo === 5 && sucesso" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
            <div class="card-body text-center py-8 space-y-4">
                <div class="text-5xl">✅</div>
                <h2 class="text-2xl font-semibold text-slate-900">Funcionário cadastrado!</h2>
                <p class="text-slate-700"><strong>{{ sucesso.nome }}</strong>{{ sucesso.funcao ? ' · ' + sucesso.funcao : '' }}</p>
                <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-left text-sm">
                    <p>✓ Vínculo: <strong>{{ sucesso.tipo }}</strong></p>
                    <p v-if="sucesso.salario">✓ Pagamento: <strong>{{ brl(sucesso.salario) }}</strong></p>
                    <p>✓ Pode atribuir tarefas a este funcionário a partir de agora</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 justify-center pt-3">
                    <button @click="reiniciar" class="btn-primary">Cadastrar outro</button>
                    <Link :href="route('admin.funcionarios.index')" class="btn-outline">Ver todos</Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
