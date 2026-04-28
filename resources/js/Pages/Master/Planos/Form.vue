<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import InputMoney from '@/Components/InputMoney.vue';

const props = defineProps({
    plan: { type: Object, default: null },
    features_catalog: { type: Array, default: () => [] },
});

// Agrupa o catálogo por tipo (modulo / soft) para a UI organizar visualmente
const moduleFeatures = computed(() =>
    props.features_catalog.filter(f => f.tipo === 'modulo'));
const softFeatures = computed(() =>
    props.features_catalog.filter(f => f.tipo === 'soft'));

function toggleFeature(key) {
    const i = form.features.indexOf(key);
    if (i === -1) form.features.push(key);
    else form.features.splice(i, 1);
}
function selectAllModules() {
    moduleFeatures.value.forEach(f => {
        if (! form.features.includes(f.key)) form.features.push(f.key);
    });
}
function clearFeatures() {
    form.features = [];
}

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
                            class="form-input"
                            :class="form.errors.nome ? 'ring-2 ring-red-400' : ''"
                            placeholder="Ex.: Profissional"
                        >
                        <p v-if="form.errors.nome" class="mt-1 text-xs text-red-600">{{ form.errors.nome }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Identificador <span class="text-red-500">*</span>
                            <span class="ml-1 text-xs font-normal text-slate-500">(usado internamente)</span>
                        </label>
                        <input
                            v-model="form.slug"
                            @input="slugTouched = true"
                            type="text"
                            autocomplete="off"
                            class="form-input font-mono"
                            :class="form.errors.slug ? 'ring-2 ring-red-400' : ''"
                            placeholder="profissional"
                        >
                        <p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">{{ form.errors.slug }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        Preço mensal (R$) <span class="text-red-500">*</span>
                    </label>
                    <InputMoney
                        v-model="form.preco_mensal"
                        :class="form.errors.preco_mensal ? 'ring-red-400' : ''"
                        class="max-w-xs"
                    />
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
                            inputmode="numeric"
                            min="0"
                            class="form-input font-mono"
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
                            inputmode="numeric"
                            min="0"
                            class="form-input font-mono"
                            placeholder="vazio = sem limite"
                        >
                        <p v-if="form.errors.max_users" class="mt-1 text-xs text-red-600">{{ form.errors.max_users }}</p>
                    </div>
                </div>

                <!-- Funcionalidades — checklist do catálogo (sem texto livre) -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-sm font-medium text-slate-700">
                            Funcionalidades incluídas
                        </label>
                        <div class="text-xs space-x-2">
                            <button type="button" @click="selectAllModules"
                                class="text-macaybas-primary-700 hover:underline">Selecionar todos os módulos</button>
                            <span class="text-slate-300">·</span>
                            <button type="button" @click="clearFeatures"
                                class="text-slate-500 hover:underline">Limpar</button>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mb-3">
                        Selecione quais módulos do sistema este plano libera.
                        Quem assina vê apenas os módulos marcados aqui.
                    </p>

                    <div class="space-y-2">
                        <label
                            v-for="f in moduleFeatures" :key="f.key"
                            class="flex items-start gap-3 p-3 rounded-lg ring-1 cursor-pointer transition"
                            :class="form.features.includes(f.key)
                                ? 'ring-macaybas-primary-300 bg-macaybas-primary-50'
                                : 'ring-slate-200 bg-white hover:ring-macaybas-primary-200'"
                        >
                            <input type="checkbox"
                                :checked="form.features.includes(f.key)"
                                @change="toggleFeature(f.key)"
                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-macaybas-primary-700 focus:ring-macaybas-primary-500">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-slate-900">{{ f.nome }}</div>
                                <div class="text-xs text-slate-600 mt-0.5">{{ f.descricao }}</div>
                            </div>
                            <code class="text-[10px] text-slate-400 font-mono pt-1">{{ f.key }}</code>
                        </label>
                    </div>

                    <div v-if="softFeatures.length" class="mt-4">
                        <div class="text-xs uppercase tracking-wider text-slate-500 mb-2 font-semibold">
                            Diferenciais comerciais (informativo)
                        </div>
                        <div class="space-y-2">
                            <label
                                v-for="f in softFeatures" :key="f.key"
                                class="flex items-start gap-3 p-3 rounded-lg ring-1 cursor-pointer transition"
                                :class="form.features.includes(f.key)
                                    ? 'ring-amber-300 bg-amber-50'
                                    : 'ring-slate-200 bg-white hover:ring-amber-200'"
                            >
                                <input type="checkbox"
                                    :checked="form.features.includes(f.key)"
                                    @change="toggleFeature(f.key)"
                                    class="mt-0.5 h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-slate-900">{{ f.nome }}</div>
                                    <div class="text-xs text-slate-600 mt-0.5">{{ f.descricao }}</div>
                                </div>
                                <code class="text-[10px] text-slate-400 font-mono pt-1">{{ f.key }}</code>
                            </label>
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-slate-500">
                        💡 Plano sem nenhuma funcionalidade selecionada = libera tudo (compatibilidade com planos antigos).
                        Para restringir, marque pelo menos uma.
                    </p>
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
                    class="px-4 py-2 rounded-lg bg-macaybas-primary-700 text-white text-sm font-semibold hover:bg-macaybas-primary-800 shadow-sm disabled:opacity-60 disabled:cursor-wait"
                >
                    {{ form.processing ? 'Salvando…' : (isEdit ? 'Salvar alterações' : 'Criar plano') }}
                </button>
            </div>
        </form>
    </MasterLayout>
</template>
