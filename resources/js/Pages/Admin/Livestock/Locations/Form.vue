<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDecimal from '@/Components/InputDecimal.vue';

const props = defineProps({
    location: Object,
    farms: Array,
    tipos: Object,
});

const isEdit = !!props.location?.id;

// `codigo` é AUTO-GERADO pelo backend (MP-##### sequencial por tenant) —
// não enviamos do form. Em edição mantemos só pra exibir em readonly.
const form = useForm({
    farm_id: props.location?.farm_id ?? (props.farms[0]?.id ?? null),
    nome: props.location?.nome ?? '',
    tipo: props.location?.tipo ?? 'pasto',
    area_ha: props.location?.area_ha ?? '',
    capacidade_ua: props.location?.capacidade_ua ?? '',
    observacoes: props.location?.observacoes ?? '',
    is_active: props.location?.is_active ?? true,
});

function submit() {
    if (isEdit) {
        form.put(route('admin.rebanho.locais.update', props.location.id));
    } else {
        form.post(route('admin.rebanho.locais.store'));
    }
}

const precisaArea = () => ['pasto', 'piquete'].includes(form.tipo);
</script>

<template>
    <Head :title="isEdit ? 'Editar local' : 'Novo local'" />
    <AdminLayout>
        <template #page-title>Rebanho · Locais</template>

        <PageHeader
            :title="isEdit ? 'Editar local' : 'Novo local (pasto, piquete, curral…)'"
            :subtitle="isEdit ? 'Ajuste os dados e salve.' : 'Onde os animais ficam. Exemplo: \'Pasto das palmeiras\', \'Curral de manejo\'.'"
        >
            <template #actions>
                <Link :href="route('admin.rebanho.locais.index')" class="btn-outline">← Voltar</Link>
            </template>
        </PageHeader>

        <div class="card max-w-2xl mx-auto">
            <form @submit.prevent="submit" class="card-body space-y-5">
                <div>
                    <InputLabel value="Que tipo de lugar é?" />
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                        <button v-for="(label, id) in tipos" :key="id" type="button"
                                @click="form.tipo = id"
                                class="rounded-lg border-2 p-3 text-center transition-all"
                                :class="form.tipo === id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200'">
                            <div class="text-sm font-semibold">{{ label }}</div>
                        </button>
                    </div>
                </div>

                <div>
                    <InputLabel value="Nome do local" />
                    <input v-model="form.nome" type="text" maxlength="150" required
                           placeholder="Ex: Pasto das palmeiras, Curral de manejo"
                           class="form-input text-lg py-3">
                    <p v-if="form.errors.nome" class="text-sm text-red-700 mt-1">{{ form.errors.nome }}</p>
                </div>

                <!-- Código do local — VISUALIZAÇÃO APENAS.
                     • No cadastro: não aparece (será gerado automático no save).
                     • Na edição: aparece desabilitado, sem permitir alteração.
                     Padrão MP-##### auto-incrementado por tenant — controle interno. -->
                <div v-if="isEdit && location?.codigo">
                    <InputLabel value="Código (gerado automaticamente)" />
                    <input :value="location.codigo" type="text" disabled readonly
                           class="form-input bg-slate-100 text-slate-700 font-mono cursor-not-allowed">
                    <p class="text-xs text-slate-500 mt-1">Identificador único deste local no sistema. Não editável.</p>
                </div>

                <div v-if="precisaArea()" class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Área (hectares)" />
                        <InputDecimal v-model="form.area_ha" :decimals="2" :min="0" placeholder="Ex: 20,5" />
                    </div>
                    <div>
                        <InputLabel value="Capacidade (UA) — opcional" />
                        <InputDecimal v-model="form.capacidade_ua" :decimals="2" :min="0" placeholder="Ex: 40" />
                        <p class="text-xs text-slate-500 mt-0.5">Quantas unidades-animal cabem aqui.</p>
                    </div>
                </div>

                <div v-if="farms.length > 1">
                    <InputLabel value="Fazenda" />
                    <select v-model="form.farm_id" class="form-select">
                        <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.nome }}</option>
                    </select>
                </div>

                <div>
                    <InputLabel value="Observações (opcional)" />
                    <textarea v-model="form.observacoes" rows="2"
                              placeholder="Ex: Tem açude, sombreado, precisa de roçada…"
                              class="form-input"></textarea>
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="form.is_active" class="rounded border-slate-300">
                    <span class="text-sm">Local ativo (aparece nas listas)</span>
                </label>

                <div class="flex justify-end gap-3 pt-4">
                    <Link :href="route('admin.rebanho.locais.index')" class="btn-outline">Cancelar</Link>
                    <button type="submit" :disabled="form.processing" class="btn-primary">
                        {{ form.processing ? 'Salvando…' : (isEdit ? 'Salvar alterações' : 'Criar local') }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
