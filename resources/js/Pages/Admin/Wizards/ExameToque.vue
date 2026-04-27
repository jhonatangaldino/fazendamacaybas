<script setup>
/**
 * Wizard "Exame de toque" — palpação retal pra diagnóstico de gestação.
 *
 * Mobile-first. Permite selecionar várias fêmeas de uma vez (mesmo veterinário,
 * mesma data). Calcula DPP automático mas permite override manual pelo veterinário.
 */
import { ref, computed, watch } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    femeas: Array,
    veterinarios: Array,
    data_hoje: String,
    preselectId: { type: Number, default: null },
});

// Pre-seleciona se veio via Animal show com ?animal_id=X
const selecionadas = ref(new Set(
    props.preselectId && props.femeas.find(f => f.id === props.preselectId)
        ? [props.preselectId]
        : []
));
function toggleAnimal(id) {
    if (selecionadas.value.has(id)) selecionadas.value.delete(id);
    else selecionadas.value.add(id);
    // Triggera reatividade do Set
    selecionadas.value = new Set(selecionadas.value);
}
const isSelected = (id) => selecionadas.value.has(id);

const returnTo = new URLSearchParams(window.location.search).get('return_to') || null;

function hojeLocal() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

// `data` é palavra reservada do Vue Options API → bug no useForm. Renomeado.
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

// Calcula DPP automaticamente sempre que muda gestacao_dias OU data
watch([() => form.gestacao_dias, () => form.data_exame, () => form.gestacao_status, selecionadas], () => {
    if (form.gestacao_status !== 'prenhe' || ! form.gestacao_dias || ! form.data_exame) {
        form.data_exame_prevista_parto = '';
        return;
    }
    // Pega a primeira fêmea selecionada para descobrir gestacao_dias_padrao
    const ids = [...selecionadas.value];
    if (ids.length === 0) return;
    const primeira = props.femeas.find(f => f.id === ids[0]);
    if (! primeira) return;
    const gestacaoTotal = primeira.gestacao_dias_padrao || 280;
    const dataExame = new Date(form.data_exame + 'T12:00:00');
    const dpp = new Date(dataExame.getTime() + (gestacaoTotal - form.gestacao_dias) * 86400000);
    form.data_exame_prevista_parto = dpp.toISOString().slice(0, 10);
}, { deep: true });

const totalSelecionadas = computed(() => selecionadas.value.size);

function submit() {
    form.animal_ids = [...selecionadas.value];
    form.post(route('admin.fluxos.exame-toque.store'));
}

const statusLabel = {
    prenhe: { label: 'Prenhe', icon: '🤰', cor: 'bg-emerald-100 text-emerald-900 ring-emerald-300' },
    vazia: { label: 'Vazia', icon: '⚪', cor: 'bg-slate-100 text-slate-900 ring-slate-300' },
    duvida: { label: 'Em dúvida', icon: '⚠️', cor: 'bg-amber-100 text-amber-900 ring-amber-300' },
};
</script>

<template>
    <Head title="Exame de toque" />
    <AdminLayout>
        <template #page-title>Assistente · Exame de toque</template>

        <PageHeader
            title="🩺 Exame de toque"
            subtitle="Diagnóstico de gestação (palpação retal). Selecione as fêmeas examinadas e informe o resultado."
        >
            <template #actions>
                <Link :href="returnTo || route('admin.inicio')" class="btn-outline">← Voltar</Link>
            </template>
        </PageHeader>

        <div class="max-w-3xl mx-auto pb-8">

            <div v-if="femeas.length === 0" class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-6 text-center">
                <p class="text-amber-900">Nenhuma fêmea em idade reprodutiva encontrada.</p>
                <p class="mt-1 text-xs text-amber-700">Bovinos ≥18 meses · Equinos ≥36 meses · Caprinos/Ovinos ≥10 meses · Suínos ≥8 meses.</p>
            </div>

            <!-- Cabeçalho do exame (data, vet, status) -->
            <div v-if="femeas.length > 0" class="rounded-xl bg-white ring-1 ring-slate-200 p-4 space-y-4 mb-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Data do exame</label>
                        <input
                            :value="form.data_exame"
                            @input="form.data_exame = $event.target.value"
                            type="date"
                            class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base"
                        >
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
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Resultado do exame</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="(s, key) in statusLabel"
                            :key="key"
                            type="button"
                            @click="form.gestacao_status = key"
                            class="px-3 py-3 rounded-lg ring-2 text-sm font-semibold transition"
                            :class="form.gestacao_status === key ? s.cor : 'bg-white ring-slate-200 text-slate-500 hover:ring-slate-300'"
                        >
                            {{ s.icon }} {{ s.label }}
                        </button>
                    </div>
                </div>

                <!-- Se prenhe: dias gestação + DPP -->
                <div v-if="form.gestacao_status === 'prenhe'" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-slate-100">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Dias de gestação</label>
                        <input
                            v-model.number="form.gestacao_dias"
                            type="number"
                            inputmode="numeric"
                            min="0"
                            max="340"
                            placeholder="Ex.: 60"
                            class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base font-mono"
                        >
                        <p class="text-xs text-slate-500 mt-1">A partir disto o sistema calcula a data prevista do parto.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Data prevista do parto</label>
                        <input
                            v-model="form.data_exame_prevista_parto"
                            type="date"
                            class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base"
                        >
                        <p class="text-xs text-emerald-700 mt-1">✓ Calculada automaticamente — pode editar se quiser.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Observações</label>
                    <textarea
                        v-model="form.observacoes"
                        rows="2"
                        placeholder="Anotações do exame (opcional)"
                        class="w-full px-4 py-3 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-macaybas-primary focus:outline-none text-base"
                    ></textarea>
                </div>
            </div>

            <!-- Seleção das fêmeas -->
            <div v-if="femeas.length > 0">
                <h2 class="text-sm font-bold text-slate-700 mb-2 uppercase tracking-wider">
                    Fêmeas examinadas
                    <span v-if="totalSelecionadas > 0" class="ml-2 px-2 py-0.5 rounded-full bg-macaybas-primary text-white text-xs">
                        {{ totalSelecionadas }} selecionada(s)
                    </span>
                </h2>
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
                                <div class="font-bold text-slate-900">
                                    {{ f.identificacao }}<span v-if="f.nome" class="font-normal text-slate-600"> — {{ f.nome }}</span>
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    <span v-if="f.especie">{{ f.especie }}</span>
                                    <span v-if="f.idade_meses !== null" class="ml-1">· {{ f.idade_meses }} meses</span>
                                    <span v-else-if="f.idade_desconhecida" class="ml-1 text-amber-700">· 📅 idade não cadastrada</span>
                                    <span v-if="f.too_young" class="ml-1 text-amber-700">⚠ abaixo de {{ f.idade_min_meses }}m</span>
                                    <span v-if="f.lote" class="ml-2">· 📋 {{ f.lote }}</span>
                                    <span v-if="f.ultimo_toque?.gestacao_status" class="ml-2">
                                        · último toque: <strong>{{ statusLabel[f.ultimo_toque.gestacao_status]?.label || f.ultimo_toque.gestacao_status }}</strong>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Botão Salvar dentro do conteúdo (segue padrão dos outros wizards) -->
        <div v-if="femeas.length > 0" class="card max-w-3xl mx-auto mt-4">
            <div class="card-body flex items-center justify-end gap-3">
                <Link :href="returnTo || route('admin.inicio')" class="btn-outline">Cancelar</Link>
                <button
                    type="button"
                    @click="submit"
                    :disabled="totalSelecionadas === 0 || form.processing"
                    class="btn-primary"
                >
                    <span v-if="form.processing">Salvando…</span>
                    <span v-else-if="totalSelecionadas === 0">Selecione 1+ fêmea</span>
                    <span v-else>✓ Salvar exame de {{ totalSelecionadas }} fêmea(s)</span>
                </button>
            </div>
        </div>
    </AdminLayout>
</template>
