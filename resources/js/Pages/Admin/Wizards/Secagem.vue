<script setup>
/**
 * Wizard "Secar vaca" — registra secagem (cessação da lactação) antes do parto.
 *
 * Sugere automaticamente vacas com data prevista de parto a ≤75 dias.
 */
import { ref, computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    vacas: Array,
    data_hoje: String,
    preselectId: { type: Number, default: null },
});

// Se chegou via Animal show com ?animal_id=X, abre direto no passo 2 com a vaca
const vacaPreSelected = props.preselectId
    ? props.vacas.find(v => v.id === props.preselectId)
    : null;
const vacaSelecionada = ref(vacaPreSelected || null);

const returnTo = new URLSearchParams(window.location.search).get('return_to') || null;

function hojeLocal() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

// `data` é palavra reservada do Vue Options API → useForm renderiza JS no value.
// Renomeado pra data_secagem; backend mapeia de volta antes de validar.
const form = useForm({
    animal_id: vacaPreSelected?.id ?? null,
    data_secagem: props.data_hoje || hojeLocal(),
    medicamento: '',
    observacoes: '',
    return_to: returnTo,
});

function selecionar(vaca) {
    vacaSelecionada.value = vaca;
    form.animal_id = vaca.id;
}

const sugeridas = computed(() => props.vacas.filter(v => v.sugerida));
const demais = computed(() => props.vacas.filter(v => ! v.sugerida));

function submit() {
    form.post(route('admin.fluxos.secar-vaca.store'));
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
            subtitle="A vaca deve ser secada 2 meses antes do parto ou se estiver dando pouco leite."
        >
            <template #actions>
                <Link :href="returnTo || route('admin.inicio')" class="btn-outline">← Voltar</Link>
            </template>
        </PageHeader>

        <div class="max-w-2xl mx-auto pb-8">

            <!-- Sem vacas -->
            <div v-if="vacas.length === 0" class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-6 text-center">
                <p class="text-amber-900">Nenhuma vaca em lactação encontrada.</p>
            </div>

            <!-- Passo 1: Escolha a vaca -->
            <div v-if="! vacaSelecionada">
                <div v-if="sugeridas.length > 0" class="mb-4">
                    <h2 class="text-sm font-bold text-emerald-800 mb-2 uppercase tracking-wider">⭐ Sugeridas (parto próximo)</h2>
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
                                    <div class="text-xs text-slate-500 mt-1">
                                        <span v-if="vaca.lote">📋 {{ vaca.lote }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-block px-2 py-1 rounded-full bg-emerald-200 text-emerald-900 text-xs font-semibold">
                                        🤰 {{ diasParaPartoLabel(vaca.dias_para_parto) }}
                                    </span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <div>
                    <h2 v-if="sugeridas.length > 0" class="text-sm font-bold text-slate-700 mb-2 uppercase tracking-wider">Demais vacas em lactação</h2>
                    <div class="space-y-2">
                        <button
                            v-for="vaca in demais"
                            :key="vaca.id"
                            type="button"
                            @click="selecionar(vaca)"
                            class="w-full text-left rounded-xl bg-white ring-1 ring-slate-200 p-4 hover:ring-macaybas-primary transition"
                        >
                            <div class="font-bold text-slate-900">
                                {{ vaca.identificacao }}<span v-if="vaca.nome" class="font-normal text-slate-600"> — {{ vaca.nome }}</span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1">
                                <span v-if="vaca.lote">📋 {{ vaca.lote }}</span>
                                <span v-if="vaca.ultima_secagem" class="ml-2">· última secagem: {{ vaca.ultima_secagem }}</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Passo 2: Detalhes da secagem -->
            <div v-else class="rounded-xl bg-white ring-1 ring-slate-200 p-5 space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-xs text-slate-500 uppercase tracking-wider">Vaca selecionada</div>
                        <div class="text-lg font-bold text-slate-900 mt-1">
                            {{ vacaSelecionada.identificacao }}<span v-if="vacaSelecionada.nome" class="font-normal text-slate-600"> — {{ vacaSelecionada.nome }}</span>
                        </div>
                    </div>
                    <button type="button" @click="vacaSelecionada = null; form.animal_id = null" class="text-sm text-macaybas-primary hover:underline">Trocar</button>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Data da secagem</label>
                    <input
                        :value="form.data_secagem"
                        @input="form.data_secagem = $event.target.value"
                        type="date"
                        class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Medicamento aplicado <span class="text-slate-400 normal-case">(opcional)</span></label>
                    <input
                        v-model="form.medicamento"
                        type="text"
                        placeholder="Ex.: Mamivete LA, Cefalonium, etc"
                        class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Observações <span class="text-slate-400 normal-case">(opcional)</span></label>
                    <textarea
                        v-model="form.observacoes"
                        rows="3"
                        placeholder="Tratamento efetuado, dose, etc"
                        class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base"
                    ></textarea>
                </div>
            </div>
        </div>

        <!-- Botão Confirmar dentro do conteúdo (segue padrão dos outros wizards) -->
        <div v-if="vacaSelecionada" class="card max-w-2xl mx-auto mt-4">
            <div class="card-body flex items-center justify-end gap-3">
                <Link :href="returnTo || route('admin.inicio')" class="btn-outline">Cancelar</Link>
                <button
                    type="button"
                    @click="submit"
                    :disabled="form.processing"
                    class="btn-primary"
                >
                    <span v-if="form.processing">Salvando…</span>
                    <span v-else>✓ Confirmar secagem</span>
                </button>
            </div>
        </div>
    </AdminLayout>
</template>
