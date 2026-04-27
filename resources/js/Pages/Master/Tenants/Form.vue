<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import InputMasked from '@/Components/InputMasked.vue';

const props = defineProps({
    tenant: { type: Object, default: null },
});

const isEdit = computed(() => !!props.tenant?.id);
const isMaster = computed(() => !!props.tenant?.is_master_tenant);

// Host raiz da plataforma (ex.: fazendamacaybas.com.br) — extraído de APP_URL
// compartilhado pelo Inertia. Usado para mostrar o domínio reservado do master.
const page = usePage();
const hostRaiz = computed(() => {
    const url = page.props.app?.url ?? '';
    try { return new URL(url).host; } catch { return 'fazendamacaybas.com.br'; }
});
const hostApp = computed(() => 'app.' + hostRaiz.value);

// Placeholder do textarea de domínios — usa \n real (não entity &#10;)
// porque o Vue compiler não interpreta entity em string JS literal.
const placeholderDominios = computed(() => isMaster.value
    ? 'www.fazendamacaybas.com.br\nwww2.fazendamacaybas.com.br'
    : 'fazendadojoao.com.br\nwww.fazendadojoao.com.br');

// Domínios próprios são guardados como JSON array no backend.
// No form usamos textarea com 1 domínio por linha (mais natural que tags).
function domainsArrayToText(arr) {
    if (! Array.isArray(arr)) return '';
    return arr.join('\n');
}

const form = useForm({
    nome: props.tenant?.nome ?? '',
    slug: props.tenant?.slug ?? '',
    email: props.tenant?.email ?? '',
    telefone: props.tenant?.telefone ?? '',
    cidade: props.tenant?.cidade ?? '',
    estado: props.tenant?.estado ?? '',
    is_active: props.tenant ? Boolean(props.tenant.is_active) : true,
    domains_text: domainsArrayToText(props.tenant?.domains),
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
    if (/\s/.test(v)) return 'O identificador não pode ter espaços.';
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

// Validação client-side de domínios: 1 por linha, regex básica.
// Aceita www., subdomínios e domínios com hífen. Rejeita protocolo e path.
const dominiosErrosClient = computed(() => {
    const linhas = (form.domains_text || '').split('\n').map(s => s.trim()).filter(Boolean);
    const erros = [];
    for (const d of linhas) {
        if (/^https?:\/\//i.test(d)) erros.push(`"${d}" — não inclua http:// ou https://`);
        else if (d.includes('/')) erros.push(`"${d}" — informe apenas o domínio, sem caminho`);
        else if (! /^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i.test(d)) {
            erros.push(`"${d}" — formato inválido`);
        } else if (/fazendamacaybas\.com\.br$/i.test(d)) {
            erros.push(`"${d}" — domínio reservado da plataforma`);
        }
    }
    return erros;
});

function submit() {
    // Backend espera 'domains' como array. Convertemos antes de enviar.
    const dominios = (form.domains_text || '').split('\n').map(s => s.trim()).filter(Boolean);
    form.transform(data => ({
        ...data,
        domains: dominios,
    }));

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
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-serif font-bold text-slate-900">
                        {{ isEdit ? `Editar "${tenant.nome}"` : 'Cadastrar novo cliente' }}
                    </h2>
                    <span
                        v-if="isMaster"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-bold uppercase tracking-wider bg-amber-500 text-white shadow-sm"
                        title="Este é o cliente master da plataforma — sua landing pública renderiza no domínio raiz"
                    >
                        ⭐ Cliente Master
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-600">
                    <template v-if="isEdit">
                        Atualize os dados de cadastro deste cliente.
                    </template>
                    <template v-else>
                        Após criar, o cliente receberá automaticamente uma página pública no endereço
                        <code class="font-mono text-xs bg-slate-100 px-1.5 py-0.5 rounded">/c/identificador</code>.
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

        <!-- Banner explicativo do master tenant -->
        <div v-if="isMaster" class="mb-6 rounded-2xl bg-amber-50 border border-amber-300 p-5">
            <div class="flex items-start gap-3">
                <span class="text-3xl">⭐</span>
                <div class="flex-1 text-sm">
                    <p class="font-semibold text-amber-900 mb-2">Este é o Cliente Master da plataforma</p>
                    <p class="text-amber-800 mb-3">
                        Apenas 1 cliente pode ser master por vez. O master controla a landing pública institucional
                        e tem domínios reservados que são gerenciados automaticamente pelo sistema:
                    </p>
                    <ul class="space-y-1.5 text-amber-900">
                        <li class="flex items-center gap-2">
                            <span class="font-mono bg-white px-2 py-0.5 rounded ring-1 ring-amber-300">{{ hostRaiz }}</span>
                            <span class="text-xs">— landing pública (CMS deste cliente)</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="font-mono bg-white px-2 py-0.5 rounded ring-1 ring-amber-300">{{ hostApp }}</span>
                            <span class="text-xs">— ERP completo (login + admin + master)</span>
                        </li>
                    </ul>
                    <p class="mt-3 text-xs text-amber-700">
                        Para transferir o status de Master para outro cliente, use a opção
                        <strong>"Tornar este o Master"</strong> na lista de clientes (menu <strong>⋯</strong>).
                    </p>
                </div>
            </div>
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

                <!-- Identificador (slug) -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        Identificador da URL pública <span class="text-red-500">*</span>
                        <span class="ml-1 text-xs font-normal text-slate-500">(letras minúsculas, números e traços)</span>
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

            <!-- ============ DOMÍNIOS — comportamento depende se é master ============ -->
            <div class="mt-6 rounded-2xl bg-white ring-1 ring-slate-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 mb-0.5">
                        {{ isMaster ? 'Domínios deste cliente Master' : 'Domínios próprios' }}
                        <span v-if="!isMaster" class="font-normal text-slate-500">(opcional)</span>
                    </h3>
                    <p class="text-xs text-slate-500">
                        <template v-if="isMaster">
                            O master tem 2 domínios reservados pela plataforma (mostrados abaixo, inalteráveis).
                            Você pode adicionar domínios EXTRAS opcionais (ex.: <code>www.fazendamacaybas.com.br</code>).
                        </template>
                        <template v-else>
                            Se este cliente comprou um domínio próprio (ex.: <code>fazendadojoao.com.br</code>),
                            cadastre aqui. O sistema do cliente passa a rodar nesse domínio (whitelabel) e
                            os emails de boas-vindas usam essa URL no link de login.
                        </template>
                    </p>
                </div>

                <!-- Domínios reservados do master (read-only) -->
                <div v-if="isMaster" class="rounded-lg bg-slate-50 ring-1 ring-slate-200 p-4 space-y-2">
                    <div class="text-xs font-semibold text-slate-600 uppercase tracking-wider">
                        Domínios reservados (automáticos · inalteráveis)
                    </div>
                    <div class="grid gap-2 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-600">🔒</span>
                            <code class="font-mono bg-white px-2 py-1 rounded ring-1 ring-slate-300 flex-1">{{ hostRaiz }}</code>
                            <span class="text-xs text-slate-500">landing pública</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-600">🔒</span>
                            <code class="font-mono bg-white px-2 py-1 rounded ring-1 ring-slate-300 flex-1">{{ hostApp }}</code>
                            <span class="text-xs text-slate-500">ERP completo</span>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 italic">
                        Estes domínios pertencem automaticamente ao cliente master. Quando você transferir
                        o status de master para outro cliente, esses domínios passam junto.
                    </p>
                </div>

                <!-- Textarea para domínios EXTRAS (ambos master e comum) -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        {{ isMaster ? 'Domínios extras (opcional)' : 'Domínios aceitos' }}
                        <span class="ml-1 text-xs font-normal text-slate-500">(1 por linha, sem http://)</span>
                    </label>
                    <textarea
                        v-model="form.domains_text"
                        rows="3"
                        :placeholder="placeholderDominios"
                        class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm font-mono"
                        :class="(form.errors.domains || dominiosErrosClient.length) ? 'ring-red-400' : ''"
                    ></textarea>
                    <p v-if="form.errors.domains" class="mt-1 text-xs text-red-600">{{ form.errors.domains }}</p>
                    <ul v-if="dominiosErrosClient.length" class="mt-1 text-xs text-red-600 space-y-0.5">
                        <li v-for="erro in dominiosErrosClient" :key="erro">⚠ {{ erro }}</li>
                    </ul>

                    <!-- Preview da URL de login no email -->
                    <p v-if="isMaster" class="mt-1 text-xs text-emerald-700">
                        ✓ Login dos usuários deste cliente vai em
                        <code>https://{{ hostRaiz }}/login</code>
                    </p>
                    <p v-else-if="!form.domains_text" class="mt-1 text-xs text-slate-500">
                        Sem domínio próprio: cliente acessa via <code>https://{{ hostApp }}/c/{{ form.slug || 'slug-do-cliente' }}/login</code>
                    </p>
                    <p v-else class="mt-1 text-xs text-emerald-700">
                        ✓ Login do cliente vai em
                        <code>https://{{ (form.domains_text || '').split('\n').map(s => s.trim()).filter(Boolean)[0] }}/login</code>
                    </p>
                </div>

                <div v-if="!isMaster" class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
                    <strong>⚠️ Pré-requisito do cliente:</strong> o domínio precisa estar apontando
                    para a Hostinger (DNS A) e estar cadastrado como "domínio adicional" no hPanel.
                    SSL via Let's Encrypt é provisionado automaticamente após apontamento.
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
                    class="px-4 py-2 rounded-lg bg-macaybas-primary-700 text-white text-sm font-semibold hover:bg-macaybas-primary-800 shadow-sm disabled:opacity-60 disabled:cursor-wait"
                >
                    {{ form.processing
                        ? 'Salvando…'
                        : (isEdit ? 'Salvar alterações' : 'Criar cliente') }}
                </button>
            </div>
        </form>
    </MasterLayout>
</template>
