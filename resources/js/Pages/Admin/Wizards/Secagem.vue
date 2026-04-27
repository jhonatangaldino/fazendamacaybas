<script setup>
/**
 * Wizard "Secar vaca" — multi-passo, padrão do sistema.
 *
 * Passo 1 · Qual vaca? · escolha de fêmea bovina (sugere as próximas do parto)
 * Passo 2 · Quando e como? · data + medicamento + observações
 * Passo 3 · Conferência · resumo
 * Passo 4 · Pronto!
 */
import { ref, computed, onMounted } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';

const props = defineProps({
    vacas: Array,
    data_hoje: String,
    preselectId: { type: Number, default: null },
});

function hojeLocal() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

const vacaPreSelected = props.preselectId ? props.vacas.find(v => v.id === props.preselectId) : null;
const vacaSelecionada = ref(vacaPreSelected || null);

const sugeridas = computed(() => props.vacas.filter(v => v.sugerida));
const demais = computed(() => props.vacas.filter(v => ! v.sugerida));

const returnTo = new URLSearchParams(window.location.search).get('return_to') || null;

const PASSOS = [
    { n: 1, titulo: 'A vaca',       icon: '🐄' },
    { n: 2, titulo: 'Quando',       icon: '📅' },
    { n: 3, titulo: 'Conferência',  icon: '📋' },
    { n: 4, titulo: 'Pronto!',      icon: '✅' },
];
const passo = ref(vacaPreSelected ? 2 : 1);
const sucesso = ref(null);

const form = useForm({
    animal_id: vacaPreSelected?.id ?? null,
    data_secagem: props.data_hoje || hojeLocal(),
    medicamento: '',
    observacoes: '',
    return_to: returnTo,
});

onMounted(() => {
    if (! form.data_secagem) form.data_secagem = hojeLocal();
});

function selecionar(vaca) {
    vacaSelecionada.value = vaca;
    form.animal_id = vaca.id;
    avancar();
}
function avancar() { if (passo.value < PASSOS.length) passo.value++; }
function voltar()  { if (passo.value > 1) passo.value--; }
function trocarVaca() { vacaSelecionada.value = null; form.animal_id = null; passo.value = 1; }

function submit() {
    form.post(route('admin.fluxos.secar-vaca.store'), {
        preserveScroll: true,
        onSuccess: () => {
            sucesso.value = {
                identificacao: vacaSelecionada.value.identificacao,
                nome: vacaSelecionada.value.nome,
                data: form.data_secagem,
            };
            passo.value = 4;
        },
    });
}

function dataBR(iso) {
    if (! iso) return '';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}
function diasParaPartoLabel(d) {
    if (d === null || d === undefined) return null;
    if (d < 0) return 'parto previsto passou';
    if (d === 0) return 'parto hoje!';
    return `parto em ${d} dias`;
}
</script>

<template>
    <Head title="Secar vaca" />
    <AdminLayout>
        <template #page-title>Assistente · Secar vaca</template>

        <PageHeader
            title="💧 Secar vaca"
            subtitle="Vamos registrar passo a passo — não precisa saber onde clicar."
        >
            <template #actions>
                <Link :href="returnTo || route('admin.inicio')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- ─── PASSO 1 · A vaca ─── -->
        <div v-if="passo === 1" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Qual vaca você está secando?</h2>
                <p class="text-sm text-slate-600">
                    A vaca deve ser secada <strong>2 meses antes do parto</strong> ou se estiver dando pouco leite.
                    As sugeridas têm parto próximo (≤75 dias).
                </p>

                <div v-if="vacas.length === 0" class="rounded-lg bg-amber-50 ring-1 ring-amber-200 p-4 text-sm text-amber-900">
                    Nenhuma vaca encontrada. Cadastre fêmeas bovinas no rebanho antes.
                </div>

                <div v-if="sugeridas.length > 0">
                    <h3 class="text-sm font-bold text-emerald-800 uppercase tracking-wider mb-2">⭐ Sugeridas (parto próximo)</h3>
                    <div class="space-y-2">
                        <button
                            v-for="vaca in sugeridas"
                            :key="vaca.id"
                            type="button"
                            @click="selecionar(vaca)"
                            class="w-full text-left rounded-xl bg-white ring-2 ring-emerald-300 p-4 hover:bg-emerald-50 transition"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-slate-900">
                                        {{ vaca.identificacao }}<span v-if="vaca.nome" class="font-normal text-slate-600"> — {{ vaca.nome }}</span>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1"><span v-if="vaca.lote">📋 {{ vaca.lote }}</span></div>
                                </div>
                                <span class="inline-block px-2 py-1 rounded-full bg-emerald-200 text-emerald-900 text-xs font-semibold">
                                    🤰 {{ diasParaPartoLabel(vaca.dias_para_parto) }}
                                </span>
                            </div>
                        </button>
                    </div>
                </div>

                <div v-if="demais.length > 0">
                    <h3 v-if="sugeridas.length > 0" class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-2 mt-4">Demais vacas em lactação</h3>
                    <div class="space-y-2">
                        <button
                            v-for="vaca in demais"
                            :key="vaca.id"
                            type="button"
                            @click="selecionar(vaca)"
                            class="w-full text-left rounded-xl bg-white ring-1 ring-slate-200 p-4 hover:ring-macaybas-primary transition"
                        >
                            <div class="font-bold text-slate-900">{{ vaca.identificacao }}<span v-if="vaca.nome" class="font-normal text-slate-600"> — {{ vaca.nome }}</span></div>
                            <div class="text-xs text-slate-500 mt-1">
                                <span v-if="vaca.lote">📋 {{ vaca.lote }}</span>
                                <span v-if="vaca.ultima_secagem" class="ml-2">· última secagem: {{ vaca.ultima_secagem }}</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── PASSO 2 · Quando e como? ─── -->
        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-xs text-slate-500 uppercase tracking-wider">Vaca selecionada</div>
                        <h2 class="text-2xl font-semibold text-slate-900 mt-1">
                            {{ vacaSelecionada.identificacao }}<span v-if="vacaSelecionada.nome" class="font-normal text-slate-600"> — {{ vacaSelecionada.nome }}</span>
                        </h2>
                    </div>
                    <button v-if="! preselectId" type="button" @click="trocarVaca" class="text-sm text-macaybas-primary hover:underline">Trocar</button>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Data da secagem</label>
                    <input :value="form.data_secagem" @input="form.data_secagem = $event.target.value" type="date" class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Medicamento aplicado <span class="text-slate-400 normal-case">(opcional)</span></label>
                    <input v-model="form.medicamento" type="text" placeholder="Ex.: Mamivete LA, Cefalonium, etc" class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Observações <span class="text-slate-400 normal-case">(opcional)</span></label>
                    <textarea v-model="form.observacoes" rows="3" placeholder="Tratamento efetuado, dose, etc" class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base"></textarea>
                </div>

                <div class="flex justify-between pt-4 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="! form.data_secagem" class="btn-primary">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- ─── PASSO 3 · Conferência ─── -->
        <div v-if="passo === 3" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Confira antes de salvar</h2>

                <div class="rounded-lg bg-slate-50 ring-1 ring-slate-200 p-4 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Vaca:</span><strong>{{ vacaSelecionada.identificacao }}{{ vacaSelecionada.nome ? ' — ' + vacaSelecionada.nome : '' }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">Data secagem:</span><strong>{{ dataBR(form.data_secagem) }}</strong></div>
                    <div v-if="form.medicamento" class="flex justify-between"><span class="text-slate-500">Medicamento:</span><strong>{{ form.medicamento }}</strong></div>
                    <div v-if="form.observacoes" class="pt-2 border-t border-slate-200"><span class="text-slate-500 text-xs uppercase">Observações:</span><div class="mt-1">{{ form.observacoes }}</div></div>
                </div>

                <div class="flex justify-between pt-4 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="submit" :disabled="form.processing" class="btn-primary">
                        <span v-if="form.processing">Salvando…</span>
                        <span v-else>✓ Confirmar secagem</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── PASSO 4 · Pronto! ─── -->
        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto">
            <div class="card-body text-center space-y-5 py-12">
                <div class="text-6xl">💧</div>
                <h2 class="text-2xl font-semibold text-slate-900">Pronto!</h2>
                <p class="text-slate-600">
                    Vaca <strong>{{ sucesso.identificacao }}{{ sucesso.nome ? ' (' + sucesso.nome + ')' : '' }}</strong> marcada como <strong>SECA</strong> em {{ dataBR(sucesso.data) }}.
                </p>
                <div class="flex flex-wrap gap-3 justify-center pt-4">
                    <Link :href="returnTo || route('admin.inicio')" class="btn-primary">Voltar ao início</Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
