<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    lot: Object,
    farms: Array,
    finalidades: Object,
});

const isEdit = !!props.lot?.id;

const form = useForm({
    farm_id: props.lot?.farm_id ?? (props.farms[0]?.id ?? null),
    codigo: props.lot?.codigo ?? '',
    nome: props.lot?.nome ?? '',
    finalidade: props.lot?.finalidade ?? '',
    descricao: props.lot?.descricao ?? '',
    is_active: props.lot?.is_active ?? true,
});

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
                <div>
                    <InputLabel value="Nome do lote" />
                    <input v-model="form.nome" type="text" maxlength="150" required
                           placeholder="Ex: Engorda Q1 2026, Vacas leiteiras, Descarte"
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
