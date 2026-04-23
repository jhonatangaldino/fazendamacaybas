<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import { useToast } from '@/composables/useToast.js';

defineProps({
    tenants: { type: Array, default: () => [] },
});

const { toast } = useToast();
const page = usePage();

// Flash do último cliente criado (payload preparado pelo TenantController@store).
// null quando não veio da tela de criação.
const createdTenant = computed(() => page.props.flash?.created_tenant ?? null);
const createdVisible = ref(true); // permite fechar o card sem reload

// Dialog "Mensagem de entrega" — usado por botão em cada linha + pelo card de
// criação. Guarda o texto a copiar e o nome do cliente que gerou.
const deliveryDialog = ref({ open: false, tenant: null, message: '' });

function toggle(tenant) {
    const verbo = tenant.is_active ? 'desativar' : 'reativar';
    if (! confirm(`Confirma ${verbo} "${tenant.nome}"?`)) return;

    router.post(route('master.tenants.toggle', tenant.id), {}, {
        preserveScroll: true,
    });
}

function impersonate(tenant) {
    if (! confirm(`Entrar no sistema do cliente "${tenant.nome}" em modo impersonação?\n\nVocê operará como usuário deste cliente até sair da impersonação.`)) return;

    router.post(route('master.tenants.impersonate', tenant.id));
}

function buildDeliveryMessage(tenant) {
    // Espelha o formato gerado pelo backend (TenantController::buildDeliveryMessage)
    // para casos em que não viemos da tela de criação.
    return `Olá! Sua página já está disponível em:\n${tenant.landing_url}\nVocê pode editar acessando o painel.`;
}

function openDeliveryDialog(tenant, precomputedMessage = null) {
    deliveryDialog.value = {
        open: true,
        tenant,
        message: precomputedMessage ?? buildDeliveryMessage(tenant),
    };
}

function closeDeliveryDialog() {
    deliveryDialog.value = { open: false, tenant: null, message: '' };
}

async function copyDeliveryMessage() {
    try {
        await navigator.clipboard.writeText(deliveryDialog.value.message);
        toast.success('Mensagem copiada para a área de transferência.');
    } catch (e) {
        // Fallback para navegadores sem clipboard API
        const ta = document.createElement('textarea');
        ta.value = deliveryDialog.value.message;
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            toast.success('Mensagem copiada.');
        } catch {
            toast.error('Não foi possível copiar. Selecione o texto manualmente.');
        } finally {
            document.body.removeChild(ta);
        }
    }
}
</script>

<template>
    <Head title="Clientes · Plataforma" />
    <MasterLayout>
        <template #page-title>Clientes</template>

        <!-- Banner pós-criação: "Página pronta para uso" -->
        <div
            v-if="createdTenant && createdVisible"
            class="mb-6 rounded-2xl bg-emerald-50 ring-2 ring-emerald-300 p-6"
        >
            <div class="flex items-start gap-4">
                <div class="h-12 w-12 rounded-full bg-emerald-500 text-white flex items-center justify-center flex-shrink-0">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-serif text-lg font-bold text-emerald-900">
                        Página pronta para uso
                    </h3>
                    <p class="mt-1 text-sm text-emerald-800">
                        O cliente <strong>{{ createdTenant.nome }}</strong> foi criado com landing padrão.
                        Compartilhe o link abaixo para ele começar a usar.
                    </p>

                    <div class="mt-3 inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white ring-1 ring-emerald-200 text-sm font-mono text-slate-800 max-w-full">
                        <svg class="h-4 w-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                        <span class="truncate">{{ createdTenant.landing_url }}</span>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <a
                            :href="createdTenant.landing_url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            Abrir página
                        </a>
                        <button
                            type="button"
                            @click="openDeliveryDialog({ nome: createdTenant.nome, slug: createdTenant.slug, landing_url: createdTenant.landing_url }, createdTenant.delivery_message)"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white ring-1 ring-emerald-300 text-sm font-medium text-emerald-800 hover:bg-emerald-50"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                            </svg>
                            Mensagem de entrega
                        </button>
                        <Link
                            :href="route('master.clientes.cms.settings', createdTenant.id)"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white ring-1 ring-emerald-300 text-sm font-medium text-emerald-800 hover:bg-emerald-50"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Configurar landing
                        </Link>
                    </div>
                </div>
                <button
                    type="button"
                    @click="createdVisible = false"
                    class="h-8 w-8 flex items-center justify-center rounded-full hover:bg-emerald-100 text-emerald-800 flex-shrink-0"
                    aria-label="Fechar"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Cabeçalho da página -->
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-serif font-bold text-slate-900">Clientes da plataforma</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Gestão dos clientes que operam o sistema.
                </p>
            </div>
            <Link
                :href="route('master.tenants.create')"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Novo Cliente
            </Link>
        </div>

        <!-- Listagem vazia -->
        <div v-if="tenants.length === 0" class="rounded-2xl bg-white ring-1 ring-slate-200 p-10 text-center">
            <div class="h-12 w-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <h3 class="mt-3 text-sm font-semibold text-slate-900">Nenhum cliente cadastrado</h3>
            <p class="mt-1 text-sm text-slate-500">Comece cadastrando o primeiro cliente da plataforma.</p>
        </div>

        <!-- Tabela -->
        <div v-else class="rounded-2xl bg-white ring-1 ring-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Nome</th>
                            <th class="px-4 py-3 text-left font-medium">Slug</th>
                            <th class="px-4 py-3 text-left font-medium">Ativo</th>
                            <th class="px-4 py-3 text-left font-medium">Configuração</th>
                            <th class="px-4 py-3 text-left font-medium hidden md:table-cell">Criado em</th>
                            <th class="px-4 py-3 text-right font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="t in tenants" :key="t.id" class="hover:bg-slate-50/60">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ t.nome }}</div>
                                <div class="text-xs text-slate-500 md:hidden">{{ t.created_at }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <code class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-xs">{{ t.slug }}</code>
                            </td>
                            <!-- Ativo / Inativo -->
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1"
                                    :class="t.is_active
                                        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                        : 'bg-slate-100 text-slate-600 ring-slate-200'"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="t.is_active ? 'bg-emerald-500' : 'bg-slate-400'"
                                    ></span>
                                    {{ t.is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <!-- Status de configuração -->
                            <td class="px-4 py-3">
                                <span
                                    v-if="t.is_ready"
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1 bg-emerald-50 text-emerald-700 ring-emerald-200"
                                    title="Mapa ou descrição configurados — pronto para entrega ao cliente"
                                >
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Pronto para uso
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1 bg-amber-50 text-amber-700 ring-amber-200"
                                    title="Ainda sem mapa ou descrição — abra o CMS para configurar"
                                >
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Configuração incompleta
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 hidden md:table-cell">{{ t.created_at }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <!-- Ver página: abre /c/{slug} -->
                                    <a
                                        :href="t.landing_url"
                                        target="_blank"
                                        rel="noopener"
                                        :title="`Abrir landing pública em ${t.landing_url}`"
                                        class="p-2 rounded-md hover:bg-slate-100 text-slate-600 hover:text-macaybas-primary"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>

                                    <!-- Mensagem de entrega -->
                                    <button
                                        type="button"
                                        @click="openDeliveryDialog(t)"
                                        title="Copiar mensagem de entrega para enviar ao cliente"
                                        class="p-2 rounded-md hover:bg-slate-100 text-slate-600 hover:text-macaybas-primary"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                        </svg>
                                    </button>

                                    <!-- Impersonar -->
                                    <button
                                        @click="impersonate(t)"
                                        :disabled="! t.is_active"
                                        :title="t.is_active ? 'Entrar no sistema deste cliente' : 'Cliente inativo — ative para entrar'"
                                        class="p-2 rounded-md hover:bg-amber-50 text-slate-600 hover:text-amber-700 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-slate-600"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </button>

                                    <!-- Fazendas -->
                                    <Link
                                        :href="route('master.tenants.farms.index', t.id)"
                                        title="Fazendas"
                                        class="p-2 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                    </Link>

                                    <!-- Assinatura -->
                                    <Link
                                        :href="route('master.tenants.subscription.show', t.id)"
                                        title="Assinatura e cobranças"
                                        class="p-2 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                    </Link>

                                    <!-- CMS do cliente -->
                                    <Link
                                        :href="route('master.clientes.cms.index', t.id)"
                                        title="CMS / Landing deste cliente"
                                        class="p-2 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </Link>

                                    <!-- Ativar/desativar -->
                                    <button
                                        @click="toggle(t)"
                                        :title="t.is_active ? 'Desativar' : 'Reativar'"
                                        class="p-2 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900"
                                    >
                                        <svg v-if="t.is_active" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        <svg v-else class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </button>

                                    <!-- Editar -->
                                    <Link
                                        :href="route('master.tenants.edit', t.id)"
                                        title="Editar"
                                        class="p-2 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============ DIALOG: MENSAGEM DE ENTREGA ============ -->
        <div
            v-if="deliveryDialog.open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
            @click.self="closeDeliveryDialog"
        >
            <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6">
                <div class="flex items-start gap-3 mb-4">
                    <div class="h-10 w-10 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-serif text-lg font-bold text-slate-900">
                            Mensagem de entrega
                        </h3>
                        <p class="text-sm text-slate-600 mt-0.5">
                            Para enviar ao cliente <strong>{{ deliveryDialog.tenant?.nome }}</strong>.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeDeliveryDialog"
                        class="h-8 w-8 flex items-center justify-center rounded-full hover:bg-slate-100 text-slate-500 flex-shrink-0"
                        aria-label="Fechar"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <textarea
                    :value="deliveryDialog.message"
                    readonly
                    rows="4"
                    class="w-full text-sm font-mono bg-slate-50 border border-slate-200 rounded-lg p-3 text-slate-800"
                    @focus="$event.target.select()"
                ></textarea>

                <div class="mt-4 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="closeDeliveryDialog"
                        class="px-4 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-100"
                    >
                        Fechar
                    </button>
                    <button
                        type="button"
                        @click="copyDeliveryMessage"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        Copiar texto
                    </button>
                </div>
            </div>
        </div>
    </MasterLayout>
</template>
