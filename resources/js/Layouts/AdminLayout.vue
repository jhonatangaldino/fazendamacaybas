<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import AlertBar from '@/Components/AlertBar.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import GlobalLoading from '@/Components/GlobalLoading.vue';
import AvatarUpload from '@/Components/AvatarUpload.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import Icon from '@/Components/Icon.vue';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';
import ToastContainer from '@/Components/ToastContainer.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const perms = computed(() => page.props.auth.user?.permissions ?? []);
const siteLogo = computed(() => page.props.settings?.logo || null);
const siteNome = computed(() => page.props.settings?.nome || 'Macaybas');
const menuUsage = computed(() => page.props.menuUsage || {});
const menuUsageGlobal = computed(() => page.props.menuUsageGlobal || {});

// Badges de contagem nos itens do menu (vindos do AlertsService).
// Estrutura: { 'admin.financeiro.index': { n: 3, sev: 'critico' }, ... }
const menuBadges = computed(() => page.props.menuBadges || {});

// Banner de impersonação está em position:fixed (40px). Quando ativo,
// aplicamos padding-top no container raiz para que header sticky e
// sidebar não fiquem cobertos.
const impersonation = computed(() => page.props.impersonation || null);
const layoutPadTop = computed(() => impersonation.value ? 'pt-10' : '');
const stickyTop = computed(() => impersonation.value ? 'top-10' : 'top-0');

// Features liberadas pelo plano do tenant (vêm do HandleInertiaRequests).
// Usado para esconder itens do menu que não são incluídos no plano.
// `null` = sem restrição (master ou plano legado sem features marcadas).
const tenantFeatures = computed(() => page.props.tenantFeatures ?? null);

// R2.6 — contexto de fazenda.
// `availableFarms` só é preenchido pelo backend quando há >1 fazenda no tenant.
// Com 1 fazenda: [] → badge NÃO renderiza (regra UX: zero fricção).
const currentFarm = computed(() => page.props.currentFarm || null);
const availableFarms = computed(() => page.props.availableFarms || []);
const showFarmBadge = computed(() => availableFarms.value.length > 1);
const farmMenuOpen = ref(false);

function switchFarm(farmId) {
    farmMenuOpen.value = false;
    if (currentFarm.value && currentFarm.value.id === farmId) return;
    router.post(route('admin.fazenda.switch'), { farm_id: farmId });
}

/**
 * Score de uso: prioriza o uso pessoal (peso 10) sobre o global (peso 1).
 * Assim usuários sem histórico ainda veem a ordem "mais usados na fazenda";
 * à medida que usam, seu próprio perfil toma conta.
 */
function usageScore(routeKey) {
    const pessoal = menuUsage.value[routeKey] || 0;
    const global = menuUsageGlobal.value[routeKey] || 0;
    return pessoal * 10 + global;
}
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
    // Defensivo: perms pode não ser Array se backend mudou formato
    const p = perms.value;
    if (!Array.isArray(p)) return false;
    return p.includes(permission);
}

function anyCan(permissions) {
    const p = perms.value;
    if (!Array.isArray(p)) return false;
    return permissions.some((x) => p.includes(x));
}

const menu = computed(() => [
    {
        section: 'Principal',
        items: [
            // Início (Hub "O que você quer fazer?") — porta de entrada do sistema.
            // Sem `perm`: sempre visível pra qualquer usuário logado.
            { label: 'Início', route: 'admin.inicio', icon: 'home', perm: null },
            { label: 'Dashboard', route: 'admin.dashboard', icon: 'dashboard', perm: 'operational.dashboard.view' },
        ],
    },
    {
        section: 'Operação',
        items: [
            { label: 'Financeiro', route: 'admin.financeiro.index', icon: 'cash', perm: 'operational.financeiro.view', feature: 'financeiro' },
            { label: 'Rebanho', route: 'admin.rebanho.index', icon: 'cow', perm: 'operational.rebanho.view', feature: 'rebanho' },
            { label: '↳ Lotes', route: 'admin.rebanho.lotes.index', icon: 'users-group', perm: 'operational.rebanho.view', feature: 'rebanho' },
            { label: '↳ Locais (pastos)', route: 'admin.rebanho.locais.index', icon: 'map-pin', perm: 'operational.rebanho.view', feature: 'rebanho' },
            { label: 'Agrícola', route: 'admin.agricola.index', icon: 'wheat', perm: 'operational.agricola.view', feature: 'agricola' },
            { label: 'Estoque', route: 'admin.estoque.index', icon: 'box', perm: 'operational.estoque.view', feature: 'estoque' },
            { label: 'Máquinas', route: 'admin.maquinas.index', icon: 'truck', perm: 'operational.maquinas.view', feature: 'maquinas' },
            { label: 'Funcionários', route: 'admin.funcionarios.index', icon: 'users', perm: 'operational.funcionarios.view', feature: 'funcionarios' },
            { label: 'Tarefas', route: 'admin.tarefas.index', icon: 'check-square', perm: 'operational.funcionarios.tarefas.view', feature: 'tarefas' },
            { label: 'Documentos', route: 'admin.documentos.index', icon: 'folder', perm: 'operational.documentos.view', feature: 'documentos' },
            { label: 'Parceiros', route: 'admin.parceiros.index', icon: 'briefcase', perm: 'operational.parceiros.view', feature: 'parceiros' },
        ],
    },
    {
        section: 'Relatórios',
        items: [
            { label: 'Relatórios', route: 'admin.relatorios.index', icon: 'chart', perm: 'operational.relatorios.view', feature: 'relatorios' },
        ],
    },
    // Seção "Site" removida em M7 — CMS migrou para /master/cms (área plataforma).
    {
        section: 'Administração',
        items: [
            // Faturas: sem permissão restrita; tenant sempre vê suas próprias
            { label: 'Faturas', route: 'admin.faturas.index', icon: 'invoice', perm: null },
            { label: 'Usuários', route: 'admin.users.index', icon: 'user-cog', perm: 'operational.usuarios.view' },
            { label: 'Perfis e permissões', route: 'admin.roles.index', icon: 'shield', perm: 'platform.roles.view' },
        ],
    },
]);

/**
 * Filtra item do menu por permissão E por feature do plano.
 *   - perm: null     → ignora permissão (item core)
 *   - feature: null  → ignora feature gate (item core)
 *   - tenantFeatures: null → master ou plano sem features (libera tudo)
 *   - tenantFeatures: []   → plano vazio explícito; comportamento decidido no
 *     backend (Tenant::hasFeature retorna true para [] vazio = libera tudo).
 */
function isItemAvailable(item) {
    if (item.perm != null && ! can(item.perm)) return false;
    if (item.feature && Array.isArray(tenantFeatures.value) && tenantFeatures.value.length > 0) {
        return tenantFeatures.value.includes(item.feature);
    }
    return true;
}

const visibleMenu = computed(() =>
    menu.value
        .map((section) => {
            const allowed = section.items.filter(isItemAvailable);
            if (section.section === 'Operação') {
                const indexed = allowed.map((item, idx) => ({
                    item,
                    idx,
                    score: usageScore(item.route),
                }));
                indexed.sort((a, b) => {
                    if (b.score !== a.score) return b.score - a.score;
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
// (iconPath legado removido — agora usamos <Icon name="..." /> centralizado)
</script>

<template>
    <GlobalLoading />
    <ToastContainer />
    <ImpersonationBanner />
    <ConfirmDialog />
    <FlashMessages />
    <div :class="['min-h-screen flex bg-slate-50 w-full overflow-x-hidden', layoutPadTop]">
        <!-- SIDEBAR -->
        <aside
            :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full', 'md:translate-x-0', impersonation ? 'top-10' : 'top-0']"
            class="fixed inset-y-0 left-0 z-40 w-64 bg-macaybas-primary-950 text-slate-300 transform transition-transform md:static md:flex-shrink-0"
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
                                <Icon :name="item.icon" :size="20" :stroke-width="1.7" />
                                <span class="flex-1">{{ item.label }}</span>
                                <span
                                    v-if="menuBadges[item.route]"
                                    :class="[
                                        'inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[10px] font-bold',
                                        menuBadges[item.route].sev === 'critico'
                                            ? 'bg-rose-500 text-white'
                                            : 'bg-amber-400 text-amber-900',
                                    ]"
                                    :title="`${menuBadges[item.route].n} pendência(s)`"
                                >
                                    {{ menuBadges[item.route].n > 99 ? '99+' : menuBadges[item.route].n }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <div v-if="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-black/40 z-30 md:hidden"></div>

        <!-- MAIN -->
        <div class="flex-1 flex flex-col min-w-0 w-full">
            <!-- TOPBAR -->
            <header :class="['sticky z-20 h-16 flex items-center justify-between bg-white border-b border-slate-200 px-4 lg:px-8', stickyTop]">
                <button @click="sidebarOpen = !sidebarOpen" class="md:hidden p-2 rounded-md hover:bg-slate-100" aria-label="Menu">
                    <svg class="h-6 w-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <div class="hidden md:block">
                    <h1 class="text-lg font-semibold text-slate-900"><slot name="page-title">Painel</slot></h1>
                </div>

                <div class="flex items-center gap-3 relative">
                    <!-- R2.6 — Badge de fazenda ativa (só aparece se houver >1 fazenda) -->
                    <div v-if="showFarmBadge" class="relative">
                        <button
                            @click="farmMenuOpen = !farmMenuOpen"
                            class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-macaybas-primary-50 text-macaybas-primary-800 hover:bg-macaybas-primary-100 text-sm font-medium"
                            :title="currentFarm?.nome"
                        >
                            <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            <!-- BLOCO 4.3 — fazenda ativa SEMPRE visível em mobile (era hidden sm:inline) -->
                            <span class="max-w-[110px] sm:max-w-[140px] truncate">{{ currentFarm?.nome || 'Escolher fazenda' }}</span>
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div v-if="farmMenuOpen" @click.away="farmMenuOpen = false"
                             class="absolute right-0 top-full mt-2 w-64 bg-white rounded-xl shadow-lg ring-1 ring-slate-200 py-2 z-30">
                            <div class="px-4 py-2 text-xs uppercase tracking-widest text-slate-500 font-semibold">Fazendas</div>
                            <button
                                v-for="farm in availableFarms"
                                :key="farm.id"
                                @click="switchFarm(farm.id)"
                                class="w-full flex items-center justify-between text-left px-4 py-2 text-sm hover:bg-slate-50"
                                :class="currentFarm?.id === farm.id ? 'text-macaybas-primary-800 font-semibold' : 'text-slate-700'"
                            >
                                <span class="truncate">{{ farm.nome }}</span>
                                <svg v-if="currentFarm?.id === farm.id" class="h-4 w-4 text-macaybas-primary-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <a href="/" target="_blank" title="Ver site público"
                       class="hidden sm:flex items-center gap-2 whitespace-nowrap text-sm text-slate-600 hover:text-macaybas-primary">
                        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        <span class="hidden xl:inline">Ver site público</span>
                    </a>

                    <button @click="userMenuOpen = !userMenuOpen" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-100">
                        <img v-if="user?.avatar"
                             :src="user.avatar"
                             class="h-8 w-8 rounded-full object-cover ring-1 ring-slate-200">
                        <div v-else class="h-8 w-8 rounded-full bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center text-sm font-semibold">
                            {{ user?.name?.[0]?.toUpperCase() }}
                        </div>
                        <span class="text-sm font-medium text-slate-700 hidden xl:block max-w-[140px] truncate whitespace-nowrap">{{ user?.name }}</span>
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
                <AlertBar />
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
