<script setup>
/**
 * MasterLayout — M2
 *
 * Layout dedicado da área da plataforma SaaS (/master/*).
 * Isolado visualmente do AdminLayout (áreas não se misturam):
 *   - Sidebar escura (slate-900), vs. AdminLayout claro
 *   - Badge "MASTER" em âmbar, vs. sem badge no admin
 *   - Tipografia e contraste invertidos
 *
 * Responsabilidades:
 *   - Render da sidebar com os 7 itens previstos (só Dashboard ativo em M2;
 *     demais como placeholders com rótulo "Em breve (Mx)").
 *   - Header com nome do master, badge e logout.
 *   - Suporte mobile (hamburger colapsa sidebar).
 *   - Sem dependência de contexto tenant (farm_id, currentFarm etc).
 *
 * Não usa nenhum componente Admin/Tenant. Reaproveita apenas os componentes
 * UI genéricos (FlashMessages, GlobalLoading, ToastContainer).
 */

import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import FlashMessages from '@/Components/FlashMessages.vue';
import GlobalLoading from '@/Components/GlobalLoading.vue';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';
import ToastContainer from '@/Components/ToastContainer.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const sidebarOpen = ref(false);

/**
 * Detecta se uma rota está ativa. Para rotas `.index` de um CRUD, considera
 * ativa toda a família (index/create/edit/etc) — assim ao entrar em
 * /master/tenants/novo, o item "Tenants" na sidebar permanece destacado.
 */
function isActive(routeName) {
    if (routeName.endsWith('.index')) {
        const family = routeName.replace(/\.index$/, '.*');
        return route().current(family);
    }
    return route().current(routeName);
}

function logout() {
    router.post(route('logout'));
}

/**
 * Itens da sidebar.
 *   - `route`: rota Inertia real. Se null → placeholder desabilitado.
 *   - `phase`: fase roadmap em que ficará ativo (exibido como rótulo).
 */
const menu = [
    { label: 'Dashboard', route: 'master.dashboard', phase: null, icon: 'dashboard' },
    { label: 'Tenants', route: 'master.tenants.index', phase: null, icon: 'building' },
    { label: 'Planos', route: 'master.planos.index', phase: null, icon: 'card' },
    { label: 'Cobranças', route: 'master.cobrancas.index', phase: null, icon: 'invoice' },
    { label: 'CMS (Landing)', route: 'master.cms.index', phase: null, icon: 'globe' },
    { label: 'Configurações', route: null, phase: 'M8', icon: 'cog' },
];
</script>

<template>
    <GlobalLoading />
    <ToastContainer />
    <FlashMessages />
    <ImpersonationBanner />

    <div class="min-h-screen flex bg-slate-50">
        <!-- SIDEBAR -->
        <aside
            class="fixed inset-y-0 left-0 z-40 w-64 bg-slate-900 text-slate-100 transform transition-transform duration-200 lg:relative lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <!-- Brand -->
            <div class="h-16 px-5 flex items-center gap-3 border-b border-slate-800">
                <div class="h-9 w-9 rounded-full bg-white text-slate-900 flex items-center justify-center font-serif text-lg font-bold">M</div>
                <div class="min-w-0">
                    <div class="font-serif font-semibold text-sm leading-tight truncate">Plataforma</div>
                    <div class="text-[10px] text-amber-400 uppercase tracking-[0.2em]">Área Master</div>
                </div>
            </div>

            <!-- Menu -->
            <nav class="p-3 space-y-1">
                <template v-for="item in menu" :key="item.label">
                    <!-- Item ativo (rota real) -->
                    <Link
                        v-if="item.route"
                        :href="route(item.route)"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition"
                        :class="isActive(item.route)
                            ? 'bg-slate-800 text-white font-medium'
                            : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                    >
                        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        <span class="truncate">{{ item.label }}</span>
                    </Link>

                    <!-- Placeholder — em breve -->
                    <div
                        v-else
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-500 cursor-not-allowed select-none"
                        :title="`Disponível em ${item.phase}`"
                    >
                        <svg class="h-4 w-4 flex-shrink-0 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="truncate flex-1">{{ item.label }}</span>
                        <span class="text-[9px] uppercase tracking-widest text-slate-600 bg-slate-800 px-1.5 py-0.5 rounded">{{ item.phase }}</span>
                    </div>
                </template>
            </nav>

            <!-- Footer -->
            <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-slate-800 text-[10px] text-slate-500">
                <div class="uppercase tracking-widest">Fase M2</div>
                <div class="mt-0.5">Estrutura base — sem funcionalidades ainda</div>
            </div>
        </aside>

        <!-- Overlay mobile -->
        <div
            v-if="sidebarOpen"
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-black/50 lg:hidden"
        ></div>

        <!-- CONTENT -->
        <div class="flex-1 flex flex-col min-w-0 w-full">
            <!-- TOPBAR -->
            <header class="sticky top-0 z-20 h-16 flex items-center justify-between bg-white border-b border-slate-200 px-4 lg:px-8">
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="lg:hidden p-2 rounded-md hover:bg-slate-100"
                    aria-label="Menu"
                >
                    <svg class="h-6 w-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <!-- Título da página (slot) -->
                <div class="hidden lg:block">
                    <h1 class="text-lg font-semibold text-slate-900"><slot name="page-title">Plataforma</slot></h1>
                </div>

                <!-- User + MASTER badge + logout -->
                <div class="flex items-center gap-3">
                    <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-700 text-[10px] font-semibold uppercase tracking-widest ring-1 ring-amber-500/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        Master
                    </span>
                    <span class="text-sm text-slate-700 hidden sm:block truncate max-w-[180px]">{{ user?.name }}</span>
                    <button
                        @click="logout"
                        class="text-sm text-slate-600 hover:text-red-600 px-2 py-1 rounded hover:bg-red-50"
                    >Sair</button>
                </div>
            </header>

            <main class="flex-1 p-4 lg:p-8 min-w-0 w-full max-w-full overflow-x-hidden">
                <slot />
            </main>
        </div>
    </div>
</template>
