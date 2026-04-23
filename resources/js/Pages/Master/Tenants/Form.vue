<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import InputMasked from '@/Components/InputMasked.vue';

const props = defineProps({
    tenant: { type: Object, default: null },
});

const isEdit = computed(() => !!props.tenant?.id);

const form = useForm({
    nome: props.tenant?.nome ?? '',
    slug: props.tenant?.slug ?? '',
    email: props.tenant?.email ?? '',
    telefone: props.tenant?.telefone ?? '',
    cidade: props.tenant?.cidade ?? '',
    estado: props.tenant?.estado ?? '',
    is_active: props.tenant ? Boolean(props.tenant.is_active) : true,
});

/**
 * Auto-slug: derivado do nome enquanto o usuário não editar o campo slug
 * manualmente. Em edit também respeita — slug pré-existente não é sobrescrito.
 * Aceita somente letras minúsculas, números e hífens (não underscores, mais
 * amigável para URLs e compatível com `alpha_dash` do backend).
 */
const slugTouched = ref(false);
function slugify(str) {
    return String(str || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // remove acentos
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 120);
}
watch(() => form.nome, (novo) => {
    if (! slugTouched.value && ! isEdit.value) {
        form.slug = slugify(novo);
    }
});

// Feedback visual do slug enquanto o usuário digita no campo manualmente.
// Mostra erro se houver caracteres proibidos ou espaço.
const slugClientError = computed(() => {
    const v = form.slug;
    if (! v) return null;
    if (/\s/.test(v)) return 'O slug não pode ter espaços.';
    if (/[^a-z0-9-]/.test(v)) return 'Use apenas letras minúsculas, números e hífen.';
    return null;
});

// Estados brasileiros (UFs) para o select do form — lista oficial IBGE.
const ufs = [
    'AC','AL','AM','AP','BA','CE','DF','ES','GO','MA',
    'MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN',
    'RO','RR','RS','SC','SE','SP','TO',
];

function onSlugInput() {
    slugTouched.value = true;
    // Força minúsculas e remove caracteres inválidos enquanto digita — menos
    // retrabalho para o master (e evita submeter algo que o backend rejeita).
    form.slug = form.slug
        .toLowerCase()
        .replace(/\s+/g, '-')
        .replace(/[^a-z0-9-]/g, '');
}

function onEstadoInput() {
    form.estado = (form.estado || '').toUpperCase().slice(0, 2);
}

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
                    <template v-if="isEdit">
                        Atualize os dados de cadastro deste cliente.
                    </template>
                    <template v-else>
                        Após criar, o cliente receberá automaticamente uma landing pública em
                        <code class="font-mono text-xs bg-slate-100 px-1.5 py-0.5 rounded">/c/{slug}</code>.
                    </template>
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
        <form @submit.prevent="submit" class="max-w-2xl">
            <!-- ============ IDENTIFICAÇÃO ============ -->
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-6 space-y-5">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 mb-0.5">Identificação</h3>
                    <p class="text-xs text-slate-500">Dados que identificam o cliente na plataforma.</p>
                </div>

                <!-- Nome -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        Nome do cliente <span class="text-red-500">*</span>
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
                        Slug (URL pública) <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-stretch">
                        <span class="inline-flex items-center px-3 rounded-l-lg bg-slate-100 text-slate-500 text-xs font-mono border border-r-0 border-slate-200">
                            /c/
                        </span>
                        <input
                            v-model="form.slug"
                            @input="onSlugInput"
                            type="text"
                            autocomplete="off"
                            class="flex-1 px-3 py-2 rounded-r-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm font-mono"
                            :class="(form.errors.slug || slugClientError) ? 'ring-red-400' : ''"
                            placeholder="fazenda-sao-jose"
                        >
                    </div>
                    <p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">{{ form.errors.slug }}</p>
                    <p v-else-if="slugClientError" class="mt-1 text-xs text-red-600">{{ slugClientError }}</p>
                    <p v-else-if="! slugTouched && form.slug" class="mt-1 text-xs text-slate-500">
                        <span class="inline-flex items-center gap-1">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Sugerido automaticamente a partir do nome. Clique no campo para editar.
                        </span>
                    </p>
                    <p v-else class="mt-1 text-xs text-slate-500">
                        Apenas letras minúsculas, números e hífen. Ex.: <code class="font-mono">fazenda-sao-jose</code>
                    </p>
                </div>
            </div>

            <!-- ============ CONTATO / LOCALIZAÇÃO ============ -->
            <div class="mt-6 rounded-2xl bg-white ring-1 ring-slate-200 p-6 space-y-5">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 mb-0.5">Contato e localização</h3>
                    <p class="text-xs text-slate-500">Opcional. Facilita o contato com o cliente — não aparece na landing pública.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <!-- E-mail -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">E-mail</label>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="off"
                            class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm"
                            :class="form.errors.email ? 'ring-red-400' : ''"
                            placeholder="contato@cliente.com.br"
                        >
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <!-- Telefone -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Telefone</label>
                        <InputMasked
                            v-model="form.telefone"
                            :mask="['(##) ####-####', '(##) #####-####']"
                            placeholder="(31) 99999-9999"
                        />
                        <p v-if="form.errors.telefone" class="mt-1 text-xs text-red-600">{{ form.errors.telefone }}</p>
                    </div>

                    <!-- Cidade -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Cidade</label>
                        <input
                            v-model="form.cidade"
                            type="text"
                            autocomplete="off"
                            class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm"
                            :class="form.errors.cidade ? 'ring-red-400' : ''"
                            placeholder="Itabirito"
                        >
                        <p v-if="form.errors.cidade" class="mt-1 text-xs text-red-600">{{ form.errors.cidade }}</p>
                    </div>

                    <!-- Estado (UF) -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Estado (UF)</label>
                        <select
                            v-model="form.estado"
                            class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm bg-white"
                            :class="form.errors.estado ? 'ring-red-400' : ''"
                        >
                            <option value="">Selecione…</option>
                            <option v-for="uf in ufs" :key="uf" :value="uf">{{ uf }}</option>
                        </select>
                        <p v-if="form.errors.estado" class="mt-1 text-xs text-red-600">{{ form.errors.estado }}</p>
                    </div>
                </div>
            </div>

            <!-- ============ STATUS ============ -->
            <div class="mt-6 rounded-2xl bg-white ring-1 ring-slate-200 p-6">
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

            <!-- Ações -->
            <div class="mt-6 flex items-center justify-end gap-3">
                <Link
                    :href="route('master.tenants.index')"
                    class="px-4 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-100"
                >Cancelar</Link>
                <button
                    type="submit"
                    :disabled="form.processing || !! slugClientError"
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
