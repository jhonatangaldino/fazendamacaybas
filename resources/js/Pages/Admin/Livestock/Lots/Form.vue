<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDecimal from '@/Components/InputDecimal.vue';
import InputDate from '@/Components/InputDate.vue';
import { emojiEspecie } from '@/utils/emojiEspecie.js';

const props = defineProps({
    lot: Object,
    farms: Array,
    finalidades: Object,
    species: { type: Array, default: () => [] },
    prefillSpeciesId: { type: Number, default: null },
});

const isEdit = !!props.lot?.id;

// Pré-seleção via ?species_id=X (vem do dashboard de espécie lote)
function speciesIdInicial() {
    if (props.lot?.species_id) return props.lot.species_id;
    if (props.prefillSpeciesId) return props.prefillSpeciesId;
    return null;
}

const form = useForm({
    farm_id: props.lot?.farm_id ?? (props.farms[0]?.id ?? null),
    species_id: speciesIdInicial(),
    codigo: props.lot?.codigo ?? '',
    nome: props.lot?.nome ?? '',
    finalidade: props.lot?.finalidade ?? '',
    descricao: props.lot?.descricao ?? '',
    is_active: props.lot?.is_active ?? true,
    quantidade_inicial: props.lot?.quantidade_inicial ?? null,
    peso_medio_kg: props.lot?.peso_medio_kg ?? null,
    data_inicio: props.lot?.data_inicio ?? '',
});

const selectedSpecies = computed(() =>
    props.species.find(s => s.id === form.species_id) ?? null
);
const isLoteAgregado = computed(() => selectedSpecies.value?.gestao === 'lote');
const speciesEmoji = computed(() => selectedSpecies.value ? emojiEspecie(selectedSpecies.value.nome) : '🏷');

function submit() {
    if (isEdit) {
        form.put(route('admin.rebanho.lotes.update', props.lot.id));
    } else {
        form.post(route('admin.rebanho.lotes.store'));
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Editar lote' : 'Novo lote'" />
    <AdminLayout>
        <template #page-title>Rebanho · Lotes</template>

        <PageHeader
            :title="isEdit ? 'Editar lote' : 'Novo lote'"
            :subtitle="isEdit ? 'Ajuste os dados e salve.' : 'Um lote agrupa animais por finalidade comum. Exemplo: \'Engorda Q1 2026\', \'Vacas leiteiras\'.'"
        >
            <template #actions>
                <Link :href="route('admin.rebanho.lotes.index')" class="btn-outline">← Voltar</Link>
            </template>
        </PageHeader>

        <div class="card max-w-2xl mx-auto">
            <form @submit.prevent="submit" class="card-body space-y-5">
                <!-- ESPÉCIE — só aparece se temos species disponíveis -->
                <div v-if="species.length > 0">
                    <InputLabel value="Espécie do lote *" />
                    <select v-model.number="form.species_id" class="form-select text-base py-3" required>
                        <option :value="null">— Selecione a espécie —</option>
                        <option v-for="s in species" :key="s.id" :value="s.id">
                            {{ emojiEspecie(s.nome) }} {{ s.nome }}{{ s.gestao === 'lote' ? ' (gestão em lote)' : '' }}
                        </option>
                    </select>
                    <p v-if="form.errors.species_id" class="text-sm text-red-700 mt-1">{{ form.errors.species_id }}</p>
                </div>

                <div>
                    <InputLabel value="Nome do lote" />
                    <input v-model="form.nome" type="text" maxlength="150" required
                           :placeholder="isLoteAgregado
                               ? `Ex: ${selectedSpecies?.nome} galpão A — abr/2026`
                               : 'Ex: Engorda Q1 2026, Vacas leiteiras, Descarte'"
                           class="form-input text-lg py-3">
                    <p v-if="form.errors.nome" class="text-sm text-red-700 mt-1">{{ form.errors.nome }}</p>
                </div>

                <div>
                    <InputLabel value="Código curto" />
                    <input v-model="form.codigo" type="text" maxlength="30" required
                           placeholder="Ex: ENG-2026-Q1, LEITE, DESCARTE"
                           class="form-input">
                    <p v-if="form.errors.codigo" class="text-sm text-red-700 mt-1">{{ form.errors.codigo }}</p>
                </div>

                <div>
                    <InputLabel value="Para quê serve este lote? (opcional)" />
                    <select v-model="form.finalidade" class="form-select text-base py-3">
                        <option value="">— Não definir —</option>
                        <option v-for="(label, id) in finalidades" :key="id" :value="id">{{ label }}</option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Ajuda a organizar relatórios depois.</p>
                </div>

                <div v-if="farms.length > 1">
                    <InputLabel value="Fazenda" />
                    <select v-model="form.farm_id" class="form-select">
                        <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.nome }}</option>
                    </select>
                </div>

                <!-- SEÇÃO LOTE AGREGADO (Ave/Peixe/etc.) — cadastro em massa -->
                <div v-if="isLoteAgregado" class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4 space-y-3">
                    <div class="flex items-start gap-2">
                        <span class="text-2xl">{{ speciesEmoji }}</span>
                        <div>
                            <div class="text-sm font-semibold text-amber-900">Cadastro em massa de {{ selectedSpecies?.nome }}</div>
                            <p class="text-xs text-amber-800">
                                {{ selectedSpecies?.nome }} é gerido em LOTE — sem registro individual por cabeça.
                                Informe quantos animais entraram no lote.
                            </p>
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-3">
                        <div>
                            <InputLabel value="Quantidade inicial *" />
                            <InputDecimal v-model="form.quantidade_inicial" :decimals="0" :min="1"
                                          placeholder="Ex.: 1000" required />
                            <p class="text-xs text-amber-800 mt-1">cabeças no lote</p>
                        </div>
                        <div>
                            <InputLabel value="Peso médio (kg)" />
                            <InputDecimal v-model="form.peso_medio_kg" :decimals="3" :min="0"
                                          placeholder="0,045" />
                            <p class="text-xs text-amber-800 mt-1">por cabeça (opcional)</p>
                        </div>
                        <div>
                            <InputLabel value="Data de início" />
                            <InputDate v-model="form.data_inicio" />
                            <p class="text-xs text-amber-800 mt-1">quando o lote chegou</p>
                        </div>
                    </div>
                </div>

                <div>
                    <InputLabel value="Descrição (opcional)" />
                    <textarea v-model="form.descricao" rows="2"
                              placeholder="Detalhes sobre o lote"
                              class="form-input"></textarea>
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="form.is_active" class="rounded border-slate-300">
                    <span class="text-sm">Lote ativo (aparece nas listas)</span>
                </label>

                <div class="flex justify-end gap-3 pt-4">
                    <Link :href="route('admin.rebanho.lotes.index')" class="btn-outline">Cancelar</Link>
                    <button type="submit" :disabled="form.processing" class="btn-primary">
                        {{ form.processing ? 'Salvando…' : (isEdit ? 'Salvar alterações' : 'Criar lote') }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
