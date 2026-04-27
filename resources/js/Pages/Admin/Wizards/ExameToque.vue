<script setup>
/**
 * Wizard "Exame de toque" — multi-passo, padrão do sistema.
 *
 * Passo 1 · As fêmeas examinadas · multi-select de fêmeas em idade reprodutiva
 * Passo 2 · Resultado · status (prenhe/vazia/dúvida) + dias gestação + DPP + vet
 * Passo 3 · Conferência · resumo
 * Passo 4 · Pronto! · com nota das tarefas auto criadas
 */
import { ref, computed, watch, onMounted } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';

const props = defineProps({
    femeas: Array,
    veterinarios: Array,
    data_hoje: String,
    preselectId: { type: Number, default: null },
});

function hojeLocal() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

const returnTo = new URLSearchParams(window.location.search).get('return_to') || null;

// Selecionadas
const selecionadas = ref(new Set(
    props.preselectId && props.femeas.find(f => f.id === props.preselectId)
        ? [props.preselectId]
        : []
));
function toggleAnimal(id) {
    if (selecionadas.value.has(id)) selecionadas.value.delete(id);
    else selecionadas.value.add(id);
    selecionadas.value = new Set(selecionadas.value);
}
const isSelected = (id) => selecionadas.value.has(id);
const totalSelecionadas = computed(() => selecionadas.value.size);

const PASSOS = [
    { n: 1, titulo: 'Fêmeas',       icon: '🐄' },
    { n: 2, titulo: 'Resultado',    icon: '🩺' },
    { n: 3, titulo: 'Conferência',  icon: '📋' },
    { n: 4, titulo: 'Pronto!',      icon: '✅' },
];
const passo = ref(props.preselectId ? 2 : 1);
const sucesso = ref(null);

const form = useForm({
    animal_ids: [],
    data_exame: props.data_hoje || hojeLocal(),
    partner_id: '',
    gestacao_status: 'prenhe',
    gestacao_dias: 0,
    data_prevista_parto: '',
    observacoes: '',
    return_to: returnTo,
});

onMounted(() => {
    if (! form.data_exame) form.data_exame = hojeLocal();
});

// DPP automático
watch([() => form.gestacao_dias, () => form.data_exame, () => form.gestacao_status, selecionadas], () => {
    if (form.gestacao_status !== 'prenhe' || ! form.gestacao_dias || ! form.data_exame) {
        form.data_prevista_parto = '';
        return;
    }
    const ids = [...selecionadas.value];
    if (ids.length === 0) return;
    const primeira = props.femeas.find(f => f.id === ids[0]);
    if (! primeira) return;
    const gestTotal = primeira.gestacao_dias_padrao || 280;
    const dataExame = new Date(form.data_exame + 'T12:00:00');
    const dpp = new Date(dataExame.getTime() + (gestTotal - form.gestacao_dias) * 86400000);
    form.data_prevista_parto = dpp.toISOString().slice(0, 10);
}, { deep: true });

const statusLabel = {
    prenhe: { label: 'Prenhe',      icon: '🤰', cor: 'bg-emerald-100 text-emerald-900 ring-emerald-300' },
    vazia:  { label: 'Vazia',       icon: '⚪', cor: 'bg-slate-100 text-slate-900 ring-slate-300' },
    duvida: { label: 'Em dúvida',   icon: '⚠️', cor: 'bg-amber-100 text-amber-900 ring-amber-300' },
};

function avancar() { if (passo.value < PASSOS.length) passo.value++; }
function voltar()  { if (passo.value > 1) passo.value--; }

function submit() {
    form.animal_ids = [...selecionadas.value];
    form.post(route('admin.fluxos.exame-toque.store'), {
        preserveScroll: true,
        onSuccess: () => {
            sucesso.value = {
                total: form.animal_ids.length,
                status: form.gestacao_status,
                dpp: form.data_prevista_parto,
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

const veterinarioNome = computed(() => {
    if (! form.partner_id) return null;
    const v = props.veterinarios.find(x => x.id === Number(form.partner_id));
    return v?.nome;
});
</script>

<template>
    <Head title="Exame de toque" />
    <AdminLayout>
        <template #page-title>Assistente · Exame de toque</template>

        <PageHeader
            title="🩺 Exame de toque"
            subtitle="Vamos registrar passo a passo — não precisa saber onde clicar."
        >
            <template #actions>
                <Link :href="returnTo || route('admin.inicio')" class="btn-outline">Sair do assistente</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- ─── PASSO 1 · Fêmeas ─── -->
        <div v-if="passo === 1" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Quais fêmeas foram examinadas?</h2>
                <p class="text-sm text-slate-600">
                    Toque pra selecionar. Você pode marcar várias se o veterinário fez o exame em mais de uma na mesma visita.
                </p>

                <div v-if="femeas.length === 0" class="rounded-lg bg-amber-50 ring-1 ring-amber-200 p-4 text-sm text-amber-900">
                    Nenhuma fêmea encontrada. Cadastre fêmeas no rebanho antes.
                </div>

                <div class="space-y-2">
                    <button
                        v-for="f in femeas"
                        :key="f.id"
                        type="button"
                        @click="toggleAnimal(f.id)"
                        class="w-full text-left rounded-xl ring-2 p-3 transition"
                        :class="isSelected(f.id) ? 'bg-emerald-50 ring-emerald-400' : 'bg-white ring-slate-200 hover:ring-slate-300'"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-6 h-6 rounded ring-2 flex items-center justify-center flex-shrink-0"
                                :class="isSelected(f.id) ? 'bg-emerald-500 ring-emerald-500 text-white' : 'bg-white ring-slate-300'">
                                <span v-if="isSelected(f.id)" class="text-sm">✓</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-slate-900">{{ f.identificacao }}<span v-if="f.nome" class="font-normal text-slate-600"> — {{ f.nome }}</span></div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    <span v-if="f.especie">{{ f.especie }}</span>
                                    <span v-if="f.idade_meses !== null" class="ml-1">· {{ f.idade_meses }} meses</span>
                                    <span v-else-if="f.idade_desconhecida" class="ml-1 text-amber-700">· 📅 idade não cadastrada</span>
                                    <span v-if="f.too_young" class="ml-1 text-amber-700">⚠ abaixo de {{ f.idade_min_meses }}m</span>
                                    <span v-if="f.lote" class="ml-2">· 📋 {{ f.lote }}</span>
                                </div>
                            </div>
                        </div>
                    </button>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-100">
                    <button @click="avancar" :disabled="totalSelecionadas === 0" class="btn-primary">
                        Continuar com {{ totalSelecionadas }} →
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── PASSO 2 · Resultado ─── -->
        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Qual foi o resultado?</h2>
                <p class="text-sm text-slate-600">{{ totalSelecionadas }} fêmea(s) examinada(s).</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Data do exame</label>
                        <input :value="form.data_exame" @input="form.data_exame = $event.target.value" type="date" class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Veterinário</label>
                        <select v-model="form.partner_id" class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base">
                            <option value="">— sem cadastro —</option>
                            <option v-for="v in veterinarios" :key="v.id" :value="v.id">{{ v.nome }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Resultado *</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button v-for="(s, key) in statusLabel" :key="key" type="button" @click="form.gestacao_status = key" class="px-3 py-3 rounded-lg ring-2 text-sm font-semibold transition" :class="form.gestacao_status === key ? s.cor : 'bg-white ring-slate-200 text-slate-500 hover:ring-slate-300'">
                            {{ s.icon }} {{ s.label }}
                        </button>
                    </div>
                </div>

                <div v-if="form.gestacao_status === 'prenhe'" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-slate-100">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Dias de gestação</label>
                        <input v-model.number="form.gestacao_dias" type="number" inputmode="numeric" min="0" max="340" placeholder="Ex.: 60" class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">DPP <span class="text-slate-400 normal-case">(calculada)</span></label>
                        <input :value="form.data_prevista_parto" @input="form.data_prevista_parto = $event.target.value" type="date" class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base">
                        <p class="text-xs text-emerald-700 mt-1">✓ Calculada automaticamente — pode editar.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Observações</label>
                    <textarea v-model="form.observacoes" rows="2" placeholder="Anotações do exame (opcional)" class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base"></textarea>
                </div>

                <div class="flex justify-between pt-4 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="! form.gestacao_status" class="btn-primary">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- ─── PASSO 3 · Conferência ─── -->
        <div v-if="passo === 3" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Confira antes de salvar</h2>

                <div class="rounded-lg bg-slate-50 ring-1 ring-slate-200 p-4 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Data exame:</span><strong>{{ dataBR(form.data_exame) }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">Fêmeas examinadas:</span><strong>{{ totalSelecionadas }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">Resultado:</span><strong>{{ statusLabel[form.gestacao_status]?.icon }} {{ statusLabel[form.gestacao_status]?.label }}</strong></div>
                    <div v-if="form.gestacao_status === 'prenhe' && form.data_prevista_parto" class="flex justify-between"><span class="text-slate-500">DPP:</span><strong class="text-emerald-700">{{ dataBR(form.data_prevista_parto) }}</strong></div>
                    <div v-if="veterinarioNome" class="flex justify-between"><span class="text-slate-500">Veterinário:</span><strong>{{ veterinarioNome }}</strong></div>
                </div>

                <div v-if="form.gestacao_status === 'prenhe' && form.data_prevista_parto" class="rounded-lg bg-emerald-50 ring-1 ring-emerald-200 p-3 text-xs text-emerald-900">
                    💡 Para cada fêmea prenhe, o sistema vai criar automaticamente:
                    <ul class="list-disc ml-5 mt-1">
                        <li>Tarefa "Secar" 60 dias antes do parto</li>
                        <li>Tarefa "Registrar parto" na data prevista</li>
                    </ul>
                </div>

                <div class="flex justify-between pt-4 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="submit" :disabled="form.processing" class="btn-primary">
                        <span v-if="form.processing">Salvando…</span>
                        <span v-else>✓ Salvar exame</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── PASSO 4 · Pronto! ─── -->
        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto">
            <div class="card-body text-center space-y-5 py-12">
                <div class="text-6xl">🩺</div>
                <h2 class="text-2xl font-semibold text-slate-900">Pronto!</h2>
                <p class="text-slate-600">
                    Exame registrado para <strong>{{ sucesso.total }} fêmea(s)</strong> · resultado: <strong>{{ statusLabel[sucesso.status]?.label }}</strong>.
                </p>
                <p v-if="sucesso.status === 'prenhe' && sucesso.dpp" class="text-sm text-emerald-700">
                    Tarefas automáticas criadas: secar 60d antes + registrar parto em {{ dataBR(sucesso.dpp) }}.
                </p>
                <div class="flex flex-wrap gap-3 justify-center pt-4">
                    <Link :href="returnTo || route('admin.inicio')" class="btn-primary">Voltar ao início</Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
