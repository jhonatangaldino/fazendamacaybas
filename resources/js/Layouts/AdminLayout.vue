<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import AlertBar from '@/Components/AlertBar.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import GlobalLoading from '@/Components/GlobalLoading.vue';
import AvatarUpload from '@/Components/AvatarUpload.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import TutorialTour from '@/Components/TutorialTour.vue';
import Icon from '@/Components/Icon.vue';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';
import ToastContainer from '@/Components/ToastContainer.vue';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';

const page = usePage();
const user = computed(() => page.props.auth.user);
const perms = computed(() => page.props.auth.user?.permissions ?? []);
const siteLogo = computed(() => page.props.settings?.logo || null);
const siteNome = computed(() => page.props.settings?.nome || 'Macaybas');
const menuUsage = computed(() => page.props.menuUsage || {});
const menuUsageGlobal = computed(() => page.props.menuUsageGlobal || {});

// Espécies do tenant pra renderizar submenu dinâmico de Rebanho
import { emojiEspecie } from '@/utils/emojiEspecie.js';
const tenantSpecies = computed(() => page.props.tenantSpecies || []);

// Estado de expansão dos menus pais (persiste em localStorage por chave)
const expandedMenus = ref(JSON.parse(localStorage.getItem('admin_menu_expanded') || '{}'));
function toggleMenu(key) {
    expandedMenus.value = { ...expandedMenus.value, [key]: ! expandedMenus.value[key] };
    localStorage.setItem('admin_menu_expanded', JSON.stringify(expandedMenus.value));
}
function isExpanded(key) {
    return !! expandedMenus.value[key];
}

// Badges de contagem nos itens do menu (vindos do AlertsService).
// Estrutura: { 'admin.financeiro.index': { n: 3, sev: 'critico' }, ... }
const menuBadges = computed(() => page.props.menuBadges || {});

// Banner de impersonação é fixed top-0 h-10 (40px), fora do flow.
//
// Estratégia de layout:
//   • body.paddingTop = 40 quando há banner → todo conteúdo do flow começa
//     em y=40 (abaixo do banner). Header e main já nascem na posição correta.
//   • header permanece SEMPRE com top-0 (sem offset sticky). Se usássemos
//     top:40 (top-10) o sticky em estado relative ADICIONA 40 à posição
//     natural — header iria pra y=80 e sobreporia o H1 do main. Bug
//     reportado pelo PO em 2026-04-28.
//   • aside é fixed e seu top muda explicitamente (top-10 quando banner)
//     porque fixed é em relação ao viewport, não ao body padding.
const impersonation = computed(() => page.props.impersonation || null);

// Padding-top do body agora é aplicado via Blade root (app.blade.php) com base em
// session('impersonation'). Antes era aplicado via JS watch — causava flash
// inicial onde o header/título de wizard ficava encoberto pela tarja amarela
// (especialmente notado em primeiro mount após navegar pra um wizard).
// Mantemos cleanup defensivo em caso de algum browser ter cacheado o paddingTop antigo.
onUnmounted(() => {
    if (typeof document !== 'undefined' && document.body.style.paddingTop) {
        document.body.style.paddingTop = '';
    }
});

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
useBodyScrollLock(profileModalOpen);

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
            { label: 'Painel', route: 'admin.dashboard', icon: 'dashboard', perm: 'operational.dashboard.view' },
        ],
    },
    {
        section: 'Operação',
        items: [
            { label: 'Financeiro', route: 'admin.financeiro.index', icon: 'cash', perm: 'operational.financeiro.view', feature: 'financeiro' },
            // Rebanho expansível: ao expandir mostra submenu dinâmico SOMENTE
            // por espécie (apenas as COM animais ativos no tenant). Lotes e
            // Locais ficam separados — são entidades estruturais que servem
            // pra MAIS coisas que só rebanho (ex.: um "lote" de ração no estoque).
            {
                label: 'Rebanho', route: 'admin.rebanho.index', icon: 'cow',
                perm: 'operational.rebanho.view', feature: 'rebanho',
                expandable: true,
                children: () => tenantSpecies.value.map(s => ({
                    // Emoji separado do label pra renderizar bigger no template
                    emoji: emojiEspecie(s.nome),
                    label: s.nome,
                    href: route('admin.rebanho.especies.dashboard', { especie: s.slug }),
                    badge: s.animals_count,
                    currentMatch: (currentUrl) => {
                        try {
                            const u = new URL(currentUrl, window.location.origin);
                            // Match dashboard da espécie OU listagem filtrada por species_id
                            return u.pathname === `/admin/rebanho/especies/${s.slug}`
                                || (u.pathname === '/admin/rebanho/animais' && u.searchParams.get('species_id') === String(s.id));
                        } catch { return false; }
                    },
                })),
            },
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
            // Faturas: requer operational.financeiro.view (mesma rota tem essa
            // middleware desde B4.4). Antes estava como perm:null, fazendo
            // visitante/auditor verem o link e pegarem 403 ao clicar (BUG-C-01).
            { label: 'Faturas', route: 'admin.faturas.index', icon: 'invoice', perm: 'operational.financeiro.view' },
            { label: 'Usuários', route: 'admin.users.index', icon: 'user-cog', perm: 'operational.usuarios.view' },
            { label: 'Perfis e permissões', route: 'admin.roles.index', icon: 'shield', perm: 'platform.roles.view' },
        ],
    },
]);

/**
 * Filtra item do menu por permissão E por feature do plano.
 *
 * Regras:
 *   - perm: null     → ignora permissão (item core/sempre visível)
 *   - feature: null  → ignora feature gate (item core)
 *   - tenantFeatures: null → MASTER sem impersonar → libera tudo (admin global)
 *   - tenantFeatures: []   → plano SEM features marcadas → BLOQUEIA tudo com feature gate
 *   - tenantFeatures: ['financeiro', 'rebanho'] → libera só essas
 *
 * Antes (bug detectado pelo dono): plano sem features marcadas liberava
 * todos os menus mesmo assim. Agora master impersonando tenant SEM rebanho
 * no plano NÃO vê o menu Rebanho.
 */
function isItemAvailable(item) {
    if (item.perm != null && ! can(item.perm)) return false;
    if (! item.feature) return true; // item sem feature gate é sempre visível
    // null = master global (libera). Array (incluindo vazio) = filtra pelo conteúdo.
    if (tenantFeatures.value === null) return true;
    if (! Array.isArray(tenantFeatures.value)) return true;
    return tenantFeatures.value.includes(item.feature);
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
    <TutorialTour />
    <FlashMessages />
    <!-- Wrapper: pt-10 quando impersona, empurrando todo layout abaixo da
         tarja amarela (40px). Sem isso, o header sticky aparece coberto
         pela tarja antes do scroll (sticky:top-10 só ativa durante scroll). -->
    <div :class="['min-h-screen flex bg-slate-50 w-full overflow-x-hidden',
                  impersonation ? 'pt-10' : '']">
        <!-- SIDEBAR -->
        <!-- B4.4 fix · sidebar usa flex column + flex-1 + min-h-0 + overscroll-contain
             para que o <nav> sempre scrolle dentro do espaço disponível.
             ANTES: max-h-[calc(100vh-4rem)] cortava itens em iOS Safari porque
             100vh ignora a barra de URL inferior, deixando "Faturas/Usuários"
             inacessíveis. h-screen + dvh resolve em viewports modernos. -->
        <!-- Sidebar fixa.
             height = 100dvh quando sem impersonation,
             height = calc(100dvh - 40px) quando banner amarelo IMPERSONAÇÃO ativo.
             Sem isso, o último item do menu (Perfis/Faturas) cai abaixo do
             viewport quando há banner empurrando 40px pra baixo. -->
        <!-- Aside fixed: top muda via Vue conditional baseado em `impersonation`.
             Antes era CSS-only (body[data-impersonation] aside.fixed { top: 40px })
             mas estava sendo sobrescrito pela utility `top-0` do Tailwind em alguns
             contextos, deixando o sidebar coberto pela tarja. Vue conditional é
             explícito e à prova de race condition no first paint. -->
        <aside
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                'md:translate-x-0',
                impersonation ? 'top-10' : 'top-0',
            ]"
            class="fixed left-0 bottom-0 z-40 w-72 md:w-64 bg-macaybas-primary-950 text-slate-300 transform transition-transform md:flex-shrink-0 flex flex-col"
            :style="impersonation ? 'height: calc(100dvh - 40px);' : 'height: 100dvh;'"
        >
            <div class="flex h-16 items-center gap-3 px-5 border-b border-white/10 flex-shrink-0">
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

            <!-- Mobile: tap targets ≥44px (min-h-11), texto base (16px), padding generoso.
                 Desktop (md+): compacto (text-sm, py-2). Tudo via classes responsivas.
                 pb-20 (80px) + safe-area: respiro entre último item e borda inferior. -->
            <nav class="p-3 space-y-6 flex-1 min-h-0 overflow-y-auto overscroll-contain pb-20" style="padding-bottom: max(5rem, env(safe-area-inset-bottom, 0) + 2rem);">
                <div v-for="section in visibleMenu" :key="section.section">
                    <h3 class="text-[11px] md:text-xs uppercase tracking-widest text-white/40 px-3 mb-2">{{ section.section }}</h3>
                    <ul class="space-y-1">
                        <li v-for="item in section.items" :key="item.label">
                            <!-- Item EXPANSÍVEL (Rebanho com submenus por espécie):
                                 row contém Link pra navegar + botão pra expandir -->
                            <div v-if="item.expandable" class="flex items-stretch">
                                <Link
                                    :href="route(item.route)"
                                    @click="sidebarOpen = false; trackMenuHit(item.route)"
                                    :class="[route().current(item.route + '*') ? 'bg-white/10 text-white' : 'hover:bg-white/5 hover:text-white']"
                                    class="flex-1 flex items-center gap-3 rounded-l-lg px-3 py-3 md:py-2 text-base md:text-sm min-h-11 md:min-h-0 transition touch-manipulation"
                                >
                                    <Icon :name="item.icon" :size="22" :stroke-width="1.7" class="md:!w-5 md:!h-5 flex-shrink-0" />
                                    <span class="flex-1">{{ item.label }}</span>
                                    <span
                                        v-if="menuBadges[item.route]"
                                        :class="[
                                            'inline-flex items-center justify-center min-w-[22px] h-6 md:min-w-[20px] md:h-5 px-1.5 rounded-full text-[11px] md:text-[10px] font-bold',
                                            menuBadges[item.route].sev === 'critico' ? 'bg-rose-500 text-white' : 'bg-amber-400 text-amber-900',
                                        ]"
                                    >
                                        {{ menuBadges[item.route].n > 99 ? '99+' : menuBadges[item.route].n }}
                                    </span>
                                </Link>
                                <button @click="toggleMenu(item.route)" type="button"
                                        class="px-3 md:px-2 min-h-11 md:min-h-0 rounded-r-lg hover:bg-white/5 text-white/60 hover:text-white transition touch-manipulation"
                                        :aria-label="isExpanded(item.route) ? 'Recolher' : 'Expandir'">
                                    <svg class="h-5 w-5 md:h-4 md:w-4 transition-transform" :class="isExpanded(item.route) ? 'rotate-90' : ''"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                            </div>
                            <!-- Item NORMAL -->
                            <Link
                                v-else
                                :href="route(item.route)"
                                @click="sidebarOpen = false; trackMenuHit(item.route)"
                                :class="[route().current(item.route + '*') ? 'bg-white/10 text-white' : 'hover:bg-white/5 hover:text-white']"
                                class="flex items-center gap-3 rounded-lg px-3 py-3 md:py-2 text-base md:text-sm min-h-11 md:min-h-0 transition touch-manipulation"
                            >
                                <Icon :name="item.icon" :size="22" :stroke-width="1.7" class="md:!w-5 md:!h-5 flex-shrink-0" />
                                <span class="flex-1">{{ item.label }}</span>
                                <span
                                    v-if="menuBadges[item.route]"
                                    :class="[
                                        'inline-flex items-center justify-center min-w-[22px] h-6 md:min-w-[20px] md:h-5 px-1.5 rounded-full text-[11px] md:text-[10px] font-bold',
                                        menuBadges[item.route].sev === 'critico'
                                            ? 'bg-rose-500 text-white'
                                            : 'bg-amber-400 text-amber-900',
                                    ]"
                                    :title="`${menuBadges[item.route].n} pendência(s)`"
                                >
                                    {{ menuBadges[item.route].n > 99 ? '99+' : menuBadges[item.route].n }}
                                </span>
                            </Link>

                            <!-- Submenus dinâmicos (só renderiza se expandido).
                                 Emoji da espécie renderizado em text-xl mobile / text-base desktop
                                 — antes ficava colado ao label no mesmo text-xs (12px) — pequeno demais. -->
                            <ul v-if="item.expandable && isExpanded(item.route)" class="mt-1 ml-4 md:ml-7 space-y-0.5 border-l border-white/10 pl-2 md:pl-2">
                                <li v-for="child in item.children()" :key="child.label">
                                    <Link
                                        :href="child.href"
                                        @click="sidebarOpen = false"
                                        :class="[
                                            child.currentMatch?.(page.url) ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white',
                                            (child.badge === 0 || child.badge === '0') ? 'opacity-60' : '',
                                        ]"
                                        class="flex items-center gap-3 rounded-md px-3 md:px-2 py-2.5 md:py-1.5 text-sm md:text-xs min-h-11 md:min-h-0 transition touch-manipulation"
                                    >
                                        <span v-if="child.emoji" class="text-xl md:text-base flex-shrink-0 leading-none w-6 text-center" aria-hidden="true">{{ child.emoji }}</span>
                                        <span class="flex-1 truncate">{{ child.label }}</span>
                                        <span v-if="child.badge != null"
                                              :class="(child.badge === 0 || child.badge === '0') ? 'text-white/30' : 'text-white/50'"
                                              class="text-xs md:text-[10px] tabular-nums">{{ child.badge }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <div v-if="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-black/40 z-30 md:hidden"></div>

        <!-- MAIN — margin-left compensa sidebar fixed em desktop (≥768px).
             Antes usava md:static que jogava sidebar no fluxo normal e ela
             subia junto com scroll em listas grandes. -->
        <div class="flex-1 flex flex-col min-w-0 w-full md:ml-64">
            <!-- TOPBAR — sombra reforçada (shadow-md) + bg-white opaco garantem
                 que conteúdo do main NUNCA fique visível atrás do header durante
                 scroll. Antes shadow-sm + border-b 1px deixava títulos do main
                 parecerem 'meio cortados' em qualquer rolagem mínima. -->
            <!-- Header sticky: SEMPRE top-0. O wrapper externo já tem pt-10
                 quando impersona, então o header em fluxo natural já fica
                 abaixo da tarja. Adicionar top-10 aqui empurrava o header
                 40px ABAIXO de onde deveria — bug detectado pelo dono. -->
            <header class="sticky top-0 z-20 h-16 flex items-center justify-between bg-white shadow-md px-3 lg:px-8 gap-2">
                <!-- Mobile: hambúrguer com tap target ≥44px (era p-2 = 36px) + título da página visível -->
                <button @click="sidebarOpen = !sidebarOpen"
                        class="md:hidden inline-flex items-center justify-center min-h-11 min-w-11 rounded-md hover:bg-slate-100 active:bg-slate-200 touch-manipulation"
                        aria-label="Abrir menu">
                    <svg class="h-7 w-7 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <!-- Título: agora visível em mobile também (era hidden) — orienta o usuário -->
                <div class="flex-1 min-w-0">
                    <h1 class="text-base md:text-lg font-semibold text-slate-900 truncate">
                        <slot name="page-title">Painel</slot>
                    </h1>
                </div>

                <div class="flex items-center gap-3 relative">
                    <!-- R2.6 — Badge de fazenda ativa (só aparece se houver >1 fazenda) -->
                    <div v-if="showFarmBadge" class="relative">
                        <button
                            @click="farmMenuOpen = !farmMenuOpen"
                            class="min-h-10 flex items-center gap-2 px-3 py-2 rounded-full bg-macaybas-primary-50 text-macaybas-primary-800 hover:bg-macaybas-primary-100 text-sm font-medium"
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
                       class="hidden sm:inline-flex items-center min-h-9 gap-2 px-3 py-2 whitespace-nowrap text-sm text-slate-600 hover:text-macaybas-primary hover:bg-slate-50 rounded-lg">
                        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        <span class="hidden xl:inline">Ver site público</span>
                    </a>

                    <button @click="userMenuOpen = !userMenuOpen"
                            class="flex items-center gap-2 p-1.5 min-h-11 min-w-11 md:min-h-0 md:min-w-0 rounded-lg hover:bg-slate-100 active:bg-slate-200 touch-manipulation"
                            aria-label="Menu do usuário">
                        <img v-if="user?.avatar"
                             :src="user.avatar"
                             class="h-9 w-9 md:h-8 md:w-8 rounded-full object-cover ring-1 ring-slate-200">
                        <div v-else class="h-9 w-9 md:h-8 md:w-8 rounded-full bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center text-sm font-semibold">
                            {{ user?.name?.[0]?.toUpperCase() }}
                        </div>
                        <span class="text-sm font-medium text-slate-700 hidden xl:block whitespace-nowrap">{{ (user?.name || '').split(' ')[0] }}</span>
                        <svg class="h-4 w-4 text-slate-500 hidden md:inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
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

            <!-- Main: respiro mínimo entre topbar e o título da página.
                 pt-3/lg:pt-4 (12/16px) — só o suficiente pra não colar.
                 Antes pt-6/lg:pt-8 (24/32px) era considerado "espaço branco
                 grande demais" pelo dono. Cada página controla seu próprio
                 espaçamento interno via mb-* nos blocos de saudação. -->
            <main class="flex-1 px-4 pb-4 lg:px-8 lg:pb-8 pt-3 lg:pt-4 min-w-0 w-full max-w-full overflow-x-hidden">
                <AlertBar />
                <slot />
            </main>
        </div>

        <!-- Modal: Alterar foto do perfil -->
        <Teleport to="body">
            <div v-if="profileModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="profileModalOpen = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
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
