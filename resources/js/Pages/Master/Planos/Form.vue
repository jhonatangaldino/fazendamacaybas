<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';

const props = defineProps({
    plan: { type: Object, default: null },
});

const isEdit = computed(() => !!props.plan?.id);

const form = useForm({
    nome: props.plan?.nome ?? '',
    slug: props.plan?.slug ?? '',
    preco_mensal: props.plan?.preco_mensal ?? 0,
    max_farms: props.plan?.max_farms ?? null,
    max_users: props.plan?.max_users ?? null,
    features: props.plan?.features ? [...props.plan.features] : [],
    is_active: props.plan ? Boolean(props.plan.is_active) : true,
    sort_order: 0,
});

// Auto-slug (só no create, para não surpreender edições)
const slugTouched = ref(false);
function slugify(s) {
    return String(s || '')
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
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

// Features — UI de lista com add/remove
const novaFeature = ref('');
function adicionarFeature() {
    const v = novaFeature.value.trim();
    if (! v) return;
    if (! form.features.includes(v)) form.features.push(v);
    novaFeature.value = '';
}
function removerFeature(idx) {
    form.features.splice(idx, 1);
}

function submit() {
    if (isEdit.value) {
        form.put(route('master.planos.update', props.plan.id));
    } else {
        form.post(route('master.planos.store'));
    }
}
</script>

<template>
    <Head :title="(isEdit ? 'Editar plano' : 'Novo plano') + ' · Plataforma'" />
    <MasterLayout>
        <template #page-title>{{ isEdit ? 'Editar plano' : 'Novo plano' }}</template>

        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-serif font-bold text-slate-900">
                    {{ isEdit ? `Editar "${plan.nome}"` : 'Cadastrar novo plano' }}
                </h2>
                <p class="mt-1 text-sm text-slate-600">
                    {{ isEdit ? 'Ajuste preço, limites ou features.' : 'Defina os parâmetros comerciais do pacote.' }}
                </p>
            </div>
            <Link
                :href="route('master.planos.index')"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-sm hover:bg-slate-200"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Voltar
            </Link>
        </div>

        <form @submit.prevent="submit" class="max-w-2xl">
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-6 space-y-5">
                <div class="grid sm:grid-cols-2 gap-4">
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
                            placeholder="Ex.: Profissional"
                        >
                        <p v-if="form.errors.nome" class="mt-1 text-xs text-red-600">{{ form.errors.nome }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Slug <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.slug"
                            @input="slugTouched = true"
                            type="text"
                            autocomplete="off"
                            class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm font-mono"
                            :class="form.errors.slug ? 'ring-red-400' : ''"
                            placeholder="profissional"
                        >
                        <p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">{{ form.errors.slug }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        Preço mensal (R$) <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model.number="form.preco_mensal"
                        type="number"
                        step="0.01"
                        min="0"
                        class="w-full max-w-xs px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm font-mono"
                        :class="form.errors.preco_mensal ? 'ring-red-400' : ''"
                    >
                    <p v-if="form.errors.preco_mensal" class="mt-1 text-xs text-red-600">{{ form.errors.preco_mensal }}</p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Máximo de fazendas
                            <span class="text-xs text-slate-500 font-normal">(opcional)</span>
                        </label>
                        <input
                            v-model.number="form.max_farms"
                            type="number"
                            min="0"
                            class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm font-mono"
                            placeholder="vazio = sem limite"
                        >
                        <p v-if="form.errors.max_farms" class="mt-1 text-xs text-red-600">{{ form.errors.max_farms }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Máximo de usuários
                            <span class="text-xs text-slate-500 font-normal">(opcional)</span>
                        </label>
                        <input
                            v-model.number="form.max_users"
                            type="number"
                            min="0"
                            class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm font-mono"
                            placeholder="vazio = sem limite"
                        >
                        <p v-if="form.errors.max_users" class="mt-1 text-xs text-red-600">{{ form.errors.max_users }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Features</label>
                    <div class="flex gap-2">
                        <input
                            v-model="novaFeature"
                            @keydown.enter.prevent="adicionarFeature"
                            type="text"
                            class="flex-1 px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm"
                            placeholder="Ex.: Suporte prioritário"
                        >
                        <button
                            type="button"
                            @click="adicionarFeature"
                            class="px-3 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm hover:bg-slate-200"
                        >Adicionar</button>
                    </div>

                    <ul v-if="form.features.length" class="mt-3 space-y-1">
                        <li
                            v-for="(f, idx) in form.features"
                            :key="idx"
                            class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg bg-slate-50 ring-1 ring-slate-100 text-sm"
                        >
                            <span class="text-slate-700">{{ f }}</span>
                            <button
                                type="button"
                                @click="removerFeature(idx)"
                                class="text-slate-400 hover:text-red-600"
                                title="Remover"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </li>
                    </ul>
                </div>

                <div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                        >
                        <span class="text-sm text-slate-700">
                            Plano ativo
                            <span class="block text-xs text-slate-500">
                                Planos inativos não aparecem como opção ao atribuir assinatura.
                            </span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <Link :href="route('master.planos.index')" class="px-4 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-100">Cancelar</Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 disabled:opacity-60 disabled:cursor-wait"
                >
                    {{ form.processing ? 'Salvando…' : (isEdit ? 'Salvar alterações' : 'Criar plano') }}
                </button>
            </div>
        </form>
    </MasterLayout>
</template>
