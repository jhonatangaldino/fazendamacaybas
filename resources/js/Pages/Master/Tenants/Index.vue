<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import Icon from '@/Components/Icon.vue';
import Alert from '@/Components/Alert.vue';
import OverflowMenu from '@/Components/OverflowMenu.vue';
import OverflowMenuItem from '@/Components/OverflowMenuItem.vue';
import OverflowMenuDivider from '@/Components/OverflowMenuDivider.vue';
import { useToast } from '@/composables/useToast.js';
import { useConfirm } from '@/composables/useConfirm.js';

const props = defineProps({
    tenants: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '' }) },
});

// Filtro de busca — auto-pesquisa a partir de 3 caracteres digitados.
// Debounce de 350ms evita disparar 1 request por tecla.
const searchTerm = ref(props.filters?.search ?? '');
let searchTimer = null;

watch(searchTerm, (val) => {
    clearTimeout(searchTimer);
    const v = (val ?? '').trim();
    // Se < 3 chars E não está limpando um filtro ativo, ignora
    if (v.length > 0 && v.length < 3) return;
    searchTimer = setTimeout(() => {
        router.get(route('master.tenants.index'), v ? { search: v } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 350);
});

function limparBusca() {
    searchTerm.value = '';
}

const { toast } = useToast();
const { confirm } = useConfirm();
const page = usePage();

// Flash do último cliente criado (payload preparado pelo TenantController@store).
// null quando não veio da tela de criação.
const createdTenant = computed(() => page.props.flash?.created_tenant ?? null);
const createdVisible = ref(true); // permite fechar o card sem reload

// Dialog "Mensagem de entrega" — usado por botão em cada linha + pelo card de
// criação. Guarda o texto a copiar e o nome do cliente que gerou.
const deliveryDialog = ref({ open: false, tenant: null, message: '' });

// Menu overflow é agora responsabilidade do componente <OverflowMenu/>,
// que encapsula estado open/close + click-outside + esc. Uma instância
// por linha, sem coordenação externa.

async function toggle(tenant) {
    const ativando = ! tenant.is_active;
    const ok = await confirm({
        title: ativando ? `Reativar ${tenant.nome}?` : `Desativar ${tenant.nome}?`,
        message: ativando
            ? `O cliente voltará a operar normalmente no sistema.`
            : `O cliente perderá acesso ao sistema. Você pode reativar a qualquer momento.`,
        confirmText: ativando ? 'Reativar' : 'Desativar',
        cancelText: 'Cancelar',
        variant: ativando ? 'primary' : 'danger',
        icon: ativando ? 'question' : 'warning',
    });
    if (! ok) return;

    router.post(route('master.tenants.toggle', tenant.id), {}, {
        preserveScroll: true,
    });
}

async function toggleMaster(tenant) {
    if (tenant.is_master_tenant) {
        const ok = await confirm({
            title: `Desmarcar ${tenant.nome} como Master?`,
            message: `O cliente NÃO será mais o "master" da plataforma. A landing pública (fazendamacaybas.com.br) pode parar de renderizar até você marcar outro cliente como master.`,
            confirmText: 'Desmarcar',
            cancelText: 'Cancelar',
            variant: 'danger',
            icon: 'warning',
        });
        if (! ok) return;
    } else {
        const ok = await confirm({
            title: `Tornar ${tenant.nome} o Master?`,
            message: `Este cliente passará a ser o "master" da plataforma. A landing pública de fazendamacaybas.com.br renderizará o CMS deste cliente. Apenas 1 cliente pode ser master — se houver outro, será desmarcado automaticamente.`,
            confirmText: 'Tornar Master',
            cancelText: 'Cancelar',
            variant: 'primary',
            icon: 'question',
        });
        if (! ok) return;
    }
    router.post(route('master.tenants.toggle-master', tenant.id), {}, {
        preserveScroll: true,
    });
}

async function impersonate(tenant) {
    const ok = await confirm({
        title: 'Entrar como cliente',
        message: `Você operará como ${tenant.nome} até sair da impersonação. Todas as ações serão registradas para auditoria.`,
        confirmText: 'Entrar',
        cancelText: 'Cancelar',
        variant: 'primary',
        icon: 'question',
    });
    if (! ok) return;

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
        <Alert
            v-if="createdTenant && createdVisible"
            variant="success"
            title="Página pronta para uso"
            dismissible
            class="mb-6"
            @dismiss="createdVisible = false"
        >
            <p>
                O cliente <strong>{{ createdTenant.nome }}</strong> foi criado com landing padrão
                <span v-if="createdTenant.senha_temporaria"> e usuário dono pronto para primeiro acesso</span>.
                Compartilhe as informações abaixo com o cliente.
            </p>

            <!-- Credenciais de primeiro acesso. A senha foi enviada por email
                 automaticamente para o dono. Fica também visível na ficha de
                 usuários do tenant (em /master/tenants/{id}/usuarios) ATÉ que
                 o usuário troque a senha no primeiro acesso. -->
            <div v-if="createdTenant.senha_temporaria" class="mt-4 p-4 rounded-lg bg-white ring-1 ring-emerald-300">
                <div class="text-xs uppercase tracking-wider text-emerald-700 font-semibold mb-2">
                    🔐 Acesso ao painel
                </div>
                <dl class="grid gap-1.5 text-sm">
                    <div class="flex flex-wrap gap-2">
                        <dt class="text-slate-500 w-24">E-mail:</dt>
                        <dd class="font-mono text-slate-900 break-all">{{ createdTenant.dono_email }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <dt class="text-slate-500 w-24">Senha temp:</dt>
                        <dd class="font-mono text-slate-900 font-semibold bg-amber-50 px-2 py-0.5 rounded ring-1 ring-amber-200">
                            {{ createdTenant.senha_temporaria }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-2 text-xs text-slate-500">
                    📧 Senha enviada automaticamente para o e-mail do cliente. Expira em 2 horas.
                    Se não trocar a tempo, sistema gera uma nova e reenvia.
                    A senha fica visível na ficha de usuários do cliente até ser trocada.
                </p>
            </div>

            <div class="mt-3 inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white ring-1 ring-emerald-200 text-sm font-mono text-slate-800 max-w-full">
                <Icon name="external-link" :size="16" class="text-slate-400" />
                <span class="truncate">{{ createdTenant.landing_url }}</span>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <a
                    :href="createdTenant.landing_url"
                    target="_blank"
                    rel="noopener"
                    v-tooltip="'Abrir em nova aba'"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700"
                >
                    <Icon name="external-link" :size="16" />
                    Abrir página
                </a>
                <button
                    type="button"
                    @click="openDeliveryDialog({ nome: createdTenant.nome, slug: createdTenant.slug, landing_url: createdTenant.landing_url }, createdTenant.delivery_message)"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white ring-1 ring-emerald-300 text-sm font-medium text-emerald-800 hover:bg-emerald-50"
                >
                    <Icon name="copy" :size="16" />
                    Mensagem de entrega
                </button>
                <Link
                    :href="route('master.clientes.cms.settings', createdTenant.id)"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white ring-1 ring-emerald-300 text-sm font-medium text-emerald-800 hover:bg-emerald-50"
                >
                    <Icon name="cog" :size="16" />
                    Configurar landing
                </Link>
            </div>
        </Alert>

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
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-macaybas-primary-700 text-white text-sm font-medium hover:bg-macaybas-primary-800 shadow-sm"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Novo Cliente
            </Link>
        </div>

        <!-- Filtro de busca · auto-search a partir de 3 caracteres -->
        <div class="mb-4 max-w-md">
            <div class="relative">
                <Icon name="search" :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="searchTerm"
                    type="search"
                    placeholder="Buscar cliente por nome, identificador ou e-mail (digite 3+ caracteres)"
                    class="w-full pl-10 pr-10 py-2.5 rounded-lg ring-1 ring-slate-200 bg-white text-sm focus:ring-2 focus:ring-macaybas-primary-500 focus:outline-none"
                >
                <button
                    v-if="searchTerm"
                    type="button"
                    @click="limparBusca"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                    title="Limpar busca"
                >
                    ✕
                </button>
            </div>
            <p v-if="searchTerm && searchTerm.length > 0 && searchTerm.length < 3" class="mt-1 text-xs text-slate-500">
                Digite mais {{ 3 - searchTerm.length }} {{ (3 - searchTerm.length) === 1 ? 'caractere' : 'caracteres' }} para buscar
            </p>
            <p v-else-if="filters?.search" class="mt-1 text-xs text-slate-500">
                Mostrando resultados para <strong>"{{ filters.search }}"</strong> · {{ tenants.length }} {{ tenants.length === 1 ? 'cliente' : 'clientes' }}
            </p>
        </div>

        <!-- Listagem vazia -->
        <div v-if="tenants.length === 0" class="rounded-2xl bg-white ring-1 ring-slate-200 p-10 text-center">
            <div class="h-12 w-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <h3 class="mt-3 text-sm font-semibold text-slate-900">Nenhum cliente cadastrado</h3>
            <p class="mt-1 text-sm text-slate-500">Comece cadastrando o primeiro cliente da plataforma.</p>
        </div>

        <!-- BLOCO 4.3/4.4 — Tabela DESKTOP pleno (xl+ · iPad usa cards) -->
        <div v-else class="hidden xl:block rounded-2xl bg-white ring-1 ring-slate-200 overflow-hidden">
            <div>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Nome</th>
                            <th class="px-4 py-3 text-left font-medium">Identificador</th>
                            <th class="px-4 py-3 text-left font-medium">Ativo</th>
                            <th class="px-4 py-3 text-left font-medium">Configuração</th>
                            <th class="px-4 py-3 text-left font-medium hidden md:table-cell">Criado em</th>
                            <th class="px-4 py-3 text-right font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="t in tenants" :key="t.id" class="hover:bg-slate-50/60" :class="t.is_master_tenant ? 'bg-amber-50/40' : ''">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-900">{{ t.nome }}</span>
                                    <span
                                        v-if="t.is_master_tenant"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-500 text-white"
                                        title="Cliente master da plataforma — sua landing pública renderiza no domínio raiz"
                                    >
                                        ⭐ Master
                                    </span>
                                </div>
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
                                <div class="inline-flex items-center gap-2">
                                    <!-- Ação PRIMÁRIA: Editar (acessar dados do cliente) — destacada em verde brand -->
                                    <Link
                                        :href="route('master.tenants.edit', t.id)"
                                        v-tooltip="`Editar dados de ${t.nome}`"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-macaybas-primary-700 text-white hover:bg-macaybas-primary-800 shadow-sm"
                                    >
                                        <Icon name="edit" :size="14" />
                                        <span class="hidden sm:inline">Editar</span>
                                    </Link>

                                    <!-- Ação secundária visível: Ver página pública -->
                                    <a
                                        :href="t.landing_url"
                                        target="_blank"
                                        rel="noopener"
                                        v-tooltip="`Abrir ${t.landing_url}`"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-white ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50 hover:ring-macaybas-primary-300 hover:text-macaybas-primary-800"
                                    >
                                        <Icon name="external-link" :size="14" />
                                        <span class="hidden sm:inline">Ver página</span>
                                    </a>

                                    <!-- Ação secundária visível: editor de Site do cliente -->
                                    <Link
                                        :href="route('master.clientes.cms.index', t.id)"
                                        v-tooltip="`Editar site de ${t.nome}`"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-white ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50 hover:ring-macaybas-primary-300 hover:text-macaybas-primary-800"
                                    >
                                        <Icon name="globe" :size="14" />
                                        <span class="hidden sm:inline">Site</span>
                                    </Link>

                                    <!-- BLOCO 4.3 — Impersonar saiu do ⋯ pra ação secundária visível
                                         (audit B4 apontou que era ação principal escondida) -->
                                    <button
                                        type="button"
                                        @click="impersonate(t)"
                                        :disabled="! t.is_active"
                                        v-tooltip="t.is_active ? `Acessar como ${t.nome}` : 'Cliente desativado — ative antes de impersonar'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-100 text-amber-900 ring-1 ring-amber-200 hover:bg-amber-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <Icon name="user" :size="14" />
                                        <span class="hidden sm:inline">Impersonar</span>
                                    </button>

                                    <!-- Menu secundário "⋯" — somente ações ocasionais -->
                                    <OverflowMenu :label="`Mais ações para ${t.nome}`">
                                        <OverflowMenuItem icon="home" :href="route('master.tenants.farms.index', t.id)">
                                            Fazendas
                                        </OverflowMenuItem>
                                        <OverflowMenuItem icon="user" :href="route('master.tenants.users', t.id)">
                                            Usuários
                                        </OverflowMenuItem>
                                        <OverflowMenuItem icon="invoice" :href="route('master.tenants.subscription.show', t.id)">
                                            Assinatura
                                        </OverflowMenuItem>
                                        <OverflowMenuItem icon="copy" @click="openDeliveryDialog(t)">
                                            Mensagem de entrega
                                        </OverflowMenuItem>
                                        <OverflowMenuDivider />
                                        <OverflowMenuItem
                                            icon="star"
                                            :success="! t.is_master_tenant"
                                            :danger="t.is_master_tenant"
                                            @click="toggleMaster(t)"
                                        >
                                            {{ t.is_master_tenant ? 'Desmarcar como Master' : 'Tornar este o Master' }}
                                        </OverflowMenuItem>
                                        <OverflowMenuItem
                                            :icon="t.is_active ? 'power' : 'check-circle'"
                                            :danger="t.is_active"
                                            :success="! t.is_active"
                                            @click="toggle(t)"
                                        >
                                            {{ t.is_active ? 'Desativar cliente' : 'Reativar cliente' }}
                                        </OverflowMenuItem>
                                    </OverflowMenu>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- BLOCO 4.3/4.4 — MOBILE/iPad cards (< xl) — todas as ações visíveis, sem scroll horizontal -->
        <div v-if="tenants.length" class="xl:hidden space-y-2.5">
            <div v-for="t in tenants" :key="t.id"
                 class="rounded-xl bg-white ring-1 p-3.5 shadow-sm"
                 :class="t.is_master_tenant
                    ? 'ring-2 ring-amber-400 bg-amber-50/40'
                    : 'ring-slate-200'">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-slate-900 truncate">{{ t.nome }}</span>
                            <span
                                v-if="t.is_master_tenant"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-500 text-white shadow-sm flex-shrink-0"
                                title="Cliente master da plataforma"
                            >
                                ⭐ Master
                            </span>
                        </div>
                        <div class="text-xs text-slate-500 font-mono">{{ t.slug }}</div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1 flex-shrink-0"
                          :class="t.is_active
                              ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                              : 'bg-slate-100 text-slate-500 ring-slate-200'">
                        <span class="h-1.5 w-1.5 rounded-full" :class="t.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                        {{ t.is_active ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>

                <div v-if="! t.is_ready" class="mb-2">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1 bg-amber-50 text-amber-700 ring-amber-200">
                        ⚠ Configuração incompleta
                    </span>
                </div>
                <div class="text-xs text-slate-500 mb-3">Criado em {{ t.created_at }}</div>

                <!-- Ações principais visíveis (não em ⋯) -->
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('master.tenants.edit', t.id)"
                          class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-macaybas-primary-700 text-white hover:bg-macaybas-primary-800 min-w-[100px]">
                        <Icon name="edit" :size="14" />Editar
                    </Link>
                    <button type="button" @click="impersonate(t)" :disabled="! t.is_active"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-amber-100 text-amber-900 ring-1 ring-amber-200 hover:bg-amber-200 disabled:opacity-50 disabled:cursor-not-allowed min-w-[100px]">
                        <Icon name="user" :size="14" />Impersonar
                    </button>
                    <Link :href="route('master.clientes.cms.index', t.id)"
                          class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 text-slate-700 hover:bg-slate-200 min-w-[100px]">
                        <Icon name="globe" :size="14" />Site
                    </Link>
                </div>
                <!-- Ações secundárias em ⋯ -->
                <div class="mt-2 flex justify-end">
                    <OverflowMenu :label="`Mais ações para ${t.nome}`">
                        <OverflowMenuItem icon="home" :href="route('master.tenants.farms.index', t.id)">Fazendas</OverflowMenuItem>
                        <OverflowMenuItem icon="user" :href="route('master.tenants.users', t.id)">Usuários</OverflowMenuItem>
                        <OverflowMenuItem icon="invoice" :href="route('master.tenants.subscription.show', t.id)">Assinatura</OverflowMenuItem>
                        <OverflowMenuItem icon="copy" @click="openDeliveryDialog(t)">Mensagem de entrega</OverflowMenuItem>
                        <OverflowMenuDivider />
                        <OverflowMenuItem
                            icon="star"
                            :success="! t.is_master_tenant"
                            :danger="t.is_master_tenant"
                            @click="toggleMaster(t)">
                            {{ t.is_master_tenant ? 'Desmarcar como Master' : 'Tornar este o Master' }}
                        </OverflowMenuItem>
                        <OverflowMenuItem
                            :icon="t.is_active ? 'power' : 'check-circle'"
                            :danger="t.is_active"
                            :success="! t.is_active"
                            @click="toggle(t)">
                            {{ t.is_active ? 'Desativar cliente' : 'Reativar cliente' }}
                        </OverflowMenuItem>
                    </OverflowMenu>
                </div>
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
