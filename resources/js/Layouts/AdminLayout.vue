<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import FlashMessages from '@/Components/FlashMessages.vue';
import GlobalLoading from '@/Components/GlobalLoading.vue';
import AvatarUpload from '@/Components/AvatarUpload.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import ToastContainer from '@/Components/ToastContainer.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const perms = computed(() => page.props.auth.user?.permissions ?? []);
const siteLogo = computed(() => page.props.settings?.logo || null);
const siteNome = computed(() => page.props.settings?.nome || 'Macaybas');
const menuUsage = computed(() => page.props.menuUsage || {});
const sidebarOpen = ref(false);
const userMenuOpen = ref(false);
const profileModalOpen = ref(false);

/**
 * Registra uso de um menu (fire-and-forget). Não usa Inertia pra evitar reload.
 * O backend incrementa hits; na próxima navegação a ordenação reflete.
 */
function trackMenuHit(menuKey) {
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch(route('admin.menu-usage.bump'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ key: menuKey }),
            keepalive: true,
        }).catch(() => {});
    } catch (_) { /* noop */ }
}

function openProfile() {
    userMenuOpen.value = false;
    profileModalOpen.value = true;
}

function onAvatarUpdated() {
    // Recarrega apenas auth.user pra topbar refletir avatar novo
    router.reload({ only: ['auth'], preserveScroll: true, preserveState: true,
        onBefore: (v) => { v.__silent = true; },
    });
}

function can(permission) {
    return perms.value.includes(permission);
}

function anyCan(permissions) {
    return permissions.some((p) => perms.value.includes(p));
}

const menu = computed(() => [
    {
        section: 'Principal',
        items: [
            { label: 'Dashboard', route: 'admin.dashboard', icon: 'dashboard', perm: 'dashboard.view' },
        ],
    },
    {
        section: 'Operação',
        items: [
            { label: 'Financeiro', route: 'admin.financeiro.index', icon: 'cash', perm: 'financeiro.view' },
            { label: 'Rebanho', route: 'admin.rebanho.index', icon: 'cow', perm: 'rebanho.view' },
            { label: 'Agrícola', route: 'admin.agricola.index', icon: 'wheat', perm: 'agricola.view' },
            { label: 'Estoque', route: 'admin.estoque.index', icon: 'box', perm: 'estoque.view' },
            { label: 'Máquinas', route: 'admin.maquinas.index', icon: 'truck', perm: 'maquinas.view' },
            { label: 'Funcionários', route: 'admin.funcionarios.index', icon: 'users', perm: 'funcionarios.view' },
            { label: 'Tarefas', route: 'admin.tarefas.index', icon: 'check-square', perm: 'funcionarios.tarefas.view' },
            { label: 'Documentos', route: 'admin.documentos.index', icon: 'folder', perm: 'documentos.view' },
            { label: 'Parceiros', route: 'admin.parceiros.index', icon: 'handshake', perm: 'parceiros.view' },
        ],
    },
    {
        section: 'Relatórios',
        items: [
            { label: 'Relatórios', route: 'admin.relatorios.index', icon: 'chart', perm: 'relatorios.view' },
        ],
    },
    {
        section: 'Site',
        items: [
            { label: 'CMS — Landing', route: 'admin.cms.index', icon: 'globe', perm: 'cms.view' },
            { label: 'Menus', route: 'admin.cms.menus.index', icon: 'menu', perm: 'cms.menus.view' },
            { label: 'Configurações do site', route: 'admin.cms.settings', icon: 'cog', perm: 'cms.settings.view' },
        ],
    },
    {
        section: 'Administração',
        items: [
            { label: 'Usuários', route: 'admin.users.index', icon: 'user-cog', perm: 'users.view' },
            { label: 'Perfis e permissões', route: 'admin.roles.index', icon: 'shield', perm: 'roles.view' },
        ],
    },
]);

/**
 * Aplica permissão + ordena a seção "Operação" pelos itens mais usados por este usuário.
 * Itens sem histórico ficam na ordem original (estável), abaixo dos mais usados.
 */
const visibleMenu = computed(() =>
    menu.value
        .map((section) => {
            const allowed = section.items.filter((i) => can(i.perm));
            if (section.section === 'Operação') {
                const indexed = allowed.map((item, idx) => ({
                    item,
                    idx,
                    hits: menuUsage.value[item.route] || 0,
                }));
                indexed.sort((a, b) => {
                    if (b.hits !== a.hits) return b.hits - a.hits;
                    return a.idx - b.idx;
                });
                return { ...section, items: indexed.map((x) => x.item) };
            }
            return { ...section, items: allowed };
        })
        .filter((section) => section.items.length > 0)
);

function logout() {
    router.post(route('logout'));
}

const iconPath = {
    dashboard: 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z',
    cash: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M3 12a9 9 0 1118 0 9 9 0 01-18 0z',
    cow: 'M12 20.5c-4.5 0-8-3-8-7 0-4 3.5-7.5 8-7.5s8 3.5 8 7.5c0 4-3.5 7-8 7zM9 11h.01M15 11h.01M9 15c1 1 2 1.5 3 1.5s2-.5 3-1.5',
    wheat: 'M12 2l2 4h-4l2-4zM6 6l3 3-2 2-3-3 2-2zm12 0l2 2-3 3-2-2 3-3zM4 11l3 3-1 1-3-3 1-1zm16 0l1 1-3 3-1-1 3-3zM12 10v12M8 14l4 4M16 14l-4 4',
    box: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    truck: 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
    users: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    'check-square': 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    folder: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
    handshake: 'M12 6V4m0 16v-2m-4-8H4m16 0h-4M7.5 7.5l1.5 1.5m6 0l1.5-1.5m0 9l-1.5-1.5m-6 0l-1.5 1.5',
    chart: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    globe: 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    menu: 'M4 6h16M4 12h16M4 18h16',
    cog: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    'user-cog': 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    shield: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
};
</script>

<template>
    <GlobalLoading />
    <ToastContainer />
    <ConfirmDialog />
    <FlashMessages />
    <div class="min-h-screen flex bg-slate-50 w-full overflow-x-hidden">
        <!-- SIDEBAR -->
        <aside
            :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full', 'lg:translate-x-0']"
            class="fixed inset-y-0 left-0 z-40 w-64 bg-macaybas-primary-950 text-slate-300 transform transition-transform lg:static lg:flex-shrink-0"
        >
            <div class="flex h-16 items-center gap-3 px-5 border-b border-white/10">
                <img v-if="siteLogo"
                     :src="`/storage/${siteLogo}`"
                     :alt="siteNome"
                     class="h-10 w-10 rounded-full object-contain bg-white p-0.5 ring-1 ring-white/20">
                <div v-else class="h-9 w-9 rounded-full bg-white text-macaybas-primary-900 flex items-center justify-center font-serif text-lg font-bold">M</div>
                <div class="min-w-0">
                    <div class="text-white font-serif font-bold leading-none truncate">{{ siteNome }}</div>
                    <div class="text-xs text-macaybas-secondary-300">Sistema</div>
                </div>
            </div>

            <nav class="p-3 space-y-6 overflow-y-auto max-h-[calc(100vh-4rem)]">
                <div v-for="section in visibleMenu" :key="section.section">
                    <h3 class="text-xs uppercase tracking-widest text-white/40 px-3 mb-2">{{ section.section }}</h3>
                    <ul class="space-y-1">
                        <li v-for="item in section.items" :key="item.label">
                            <Link
                                :href="route(item.route)"
                                @click="sidebarOpen = false; trackMenuHit(item.route)"
                                :class="[route().current(item.route + '*') ? 'bg-white/10 text-white' : 'hover:bg-white/5 hover:text-white']"
                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition"
                            >
                                <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" :d="iconPath[item.icon]" />
                                </svg>
                                <span>{{ item.label }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <div v-if="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-black/40 z-30 lg:hidden"></div>

        <!-- MAIN -->
        <div class="flex-1 flex flex-col min-w-0 w-full">
            <!-- TOPBAR -->
            <header class="sticky top-0 z-20 h-16 flex items-center justify-between bg-white border-b border-slate-200 px-4 lg:px-8">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-md hover:bg-slate-100" aria-label="Menu">
                    <svg class="h-6 w-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <div class="hidden lg:block">
                    <h1 class="text-lg font-semibold text-slate-900"><slot name="page-title">Painel</slot></h1>
                </div>

                <div class="flex items-center gap-3 relative">
                    <a href="/" target="_blank" class="hidden sm:flex items-center gap-2 text-sm text-slate-600 hover:text-macaybas-primary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        Ver site público
                    </a>

                    <button @click="userMenuOpen = !userMenuOpen" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-100">
                        <img v-if="user?.avatar"
                             :src="user.avatar"
                             class="h-8 w-8 rounded-full object-cover ring-1 ring-slate-200">
                        <div v-else class="h-8 w-8 rounded-full bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center text-sm font-semibold">
                            {{ user?.name?.[0]?.toUpperCase() }}
                        </div>
                        <span class="text-sm font-medium text-slate-700 hidden sm:block">{{ user?.name }}</span>
                        <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div v-if="userMenuOpen" @click.away="userMenuOpen = false"
                         class="absolute right-0 top-full mt-2 w-64 bg-white rounded-xl shadow-lg ring-1 ring-slate-200 py-2">
                        <div class="px-4 py-3 border-b border-slate-100">
                            <div class="flex items-center gap-3 mb-2">
                                <img v-if="user?.avatar"
                                     :src="user.avatar"
                                     class="h-12 w-12 rounded-full object-cover ring-1 ring-slate-200">
                                <div v-else class="h-12 w-12 rounded-full bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center text-lg font-semibold">
                                    {{ user?.name?.[0]?.toUpperCase() }}
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-slate-900 truncate">{{ user?.name }}</div>
                                    <div class="text-xs text-slate-500 truncate">{{ user?.email }}</div>
                                    <div class="text-xs text-macaybas-primary font-medium truncate">{{ user?.cargo }}</div>
                                </div>
                            </div>
                        </div>
                        <button @click="openProfile" class="w-full text-left px-4 py-2 text-sm hover:bg-slate-50">Alterar foto do perfil</button>
                        <Link :href="route('password.change')" class="block px-4 py-2 text-sm hover:bg-slate-50">Alterar senha</Link>
                        <button @click="logout" class="w-full text-left px-4 py-2 text-sm hover:bg-slate-50 text-red-600">Sair</button>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 lg:p-8 min-w-0 w-full max-w-full overflow-x-hidden">
                <slot />
            </main>
        </div>

        <!-- Modal: Alterar foto do perfil -->
        <Teleport to="body">
            <div v-if="profileModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="profileModalOpen = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Sua foto de perfil</h3>
                            <p class="text-sm text-slate-500">Aparece no topo do sistema e nas listagens.</p>
                        </div>
                        <button @click="profileModalOpen = false" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
                    </div>
                    <AvatarUpload
                        :url="user?.avatar"
                        :name="user?.name"
                        size="h-28 w-28"
                        :upload-url="route('me.avatar.upload')"
                        :remove-url="route('me.avatar.remove')"
                        @updated="onAvatarUpdated"
                    />
                </div>
            </div>
        </Teleport>
    </div>
</template>
