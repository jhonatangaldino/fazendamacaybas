<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';

const props = defineProps({
    tenant: { type: Object, default: null },
});

const isEdit = computed(() => !!props.tenant?.id);

const form = useForm({
    nome: props.tenant?.nome ?? '',
    slug: props.tenant?.slug ?? '',
    is_active: props.tenant ? Boolean(props.tenant.is_active) : true,
});

// Auto-slug: atualiza slug enquanto o user digita o nome,
// desde que o slug ainda esteja "virgem" (vazio ou derivado do nome anterior).
const slugTouched = ref(false);
function slugify(str) {
    return String(str || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // remove acentos
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 120);
}
watch(() => form.nome, (novo) => {
    if (! slugTouched.value && ! isEdit.value) {
        form.slug = slugify(novo);
    }
});

function submit() {
    if (isEdit.value) {
        form.put(route('master.tenants.update', props.tenant.id));
    } else {
        form.post(route('master.tenants.store'));
    }
}
</script>

<template>
    <Head :title="(isEdit ? 'Editar cliente' : 'Novo cliente') + ' · Plataforma'" />
    <MasterLayout>
        <template #page-title>{{ isEdit ? 'Editar cliente' : 'Novo cliente' }}</template>

        <!-- Cabeçalho -->
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-serif font-bold text-slate-900">
                    {{ isEdit ? `Editar "${tenant.nome}"` : 'Cadastrar novo cliente' }}
                </h2>
                <p class="mt-1 text-sm text-slate-600">
                    {{ isEdit
                        ? 'Altere o nome, slug ou status de ativação.'
                        : 'Dados mínimos para identificar o cliente. Planos e cobrança serão adicionados em fases seguintes.' }}
                </p>
            </div>
            <Link
                :href="route('master.tenants.index')"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-sm hover:bg-slate-200"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Voltar
            </Link>
        </div>

        <!-- Form -->
        <form @submit.prevent="submit" class="max-w-xl">
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-6 space-y-5">
                <!-- Nome -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        Nome <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.nome"
                        type="text"
                        autocomplete="off"
                        class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm"
                        :class="form.errors.nome ? 'ring-red-400' : ''"
                        placeholder="Ex.: Fazenda São José"
                    >
                    <p v-if="form.errors.nome" class="mt-1 text-xs text-red-600">{{ form.errors.nome }}</p>
                </div>

                <!-- Slug -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        Slug <span class="text-red-500">*</span>
                        <span class="text-xs text-slate-500 font-normal">(identificador único)</span>
                    </label>
                    <input
                        v-model="form.slug"
                        @input="slugTouched = true"
                        type="text"
                        autocomplete="off"
                        class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm font-mono"
                        :class="form.errors.slug ? 'ring-red-400' : ''"
                        placeholder="fazenda-sao-jose"
                    >
                    <p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">{{ form.errors.slug }}</p>
                    <p v-else class="mt-1 text-xs text-slate-500">
                        Letras minúsculas, números e hífens. Ex.: <code class="font-mono">fazenda-sao-jose</code>
                    </p>
                </div>

                <!-- Status -->
                <div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                        >
                        <span class="text-sm text-slate-700">
                            Cliente ativo
                            <span class="block text-xs text-slate-500">
                                Controla se o cliente pode usar o sistema. Pode ser alterado depois.
                            </span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Ações -->
            <div class="mt-6 flex items-center justify-end gap-3">
                <Link
                    :href="route('master.tenants.index')"
                    class="px-4 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-100"
                >Cancelar</Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 disabled:opacity-60 disabled:cursor-wait"
                >
                    {{ form.processing
                        ? 'Salvando…'
                        : (isEdit ? 'Salvar alterações' : 'Criar cliente') }}
                </button>
            </div>
        </form>
    </MasterLayout>
</template>
