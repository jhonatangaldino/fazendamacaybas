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

import { computed, ref, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AlertBar from '@/Components/AlertBar.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import TutorialTour from '@/Components/TutorialTour.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import GlobalLoading from '@/Components/GlobalLoading.vue';
import Icon from '@/Components/Icon.vue';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';
import ToastContainer from '@/Components/ToastContainer.vue';
import { useProofAutoApprove } from '@/composables/useProofAutoApprove.js';
import { useToast } from '@/composables/useToast.js';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const sidebarOpen = ref(false);
// Counters para badges nos itens do menu Master (vindos do AlertsService).
// Estrutura: { 'master.cobrancas.index': { n: 5, sev: 'critico' }, ... }
const menuBadges = computed(() => page.props.menuBadges || {});
// Impersonação ativa controla offset do layout (banner fixed empurra com padding-top)
const impersonation = computed(() => page.props.impersonation || null);

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

// ── Auto-aprovação de comprovantes em background no LOGIN ──────────
// Quando: master fez toggle ON (cobrancas.auto-approve-config) E
// maturidade ≥ 70%. Roda 1× por sessão (sessionStorage), em background,
// sem bloquear nada. Toast no fim resume {aprovados, pendentes, erros}.
//
// Tesseract.js carrega lazy só quando há comprovantes — bundle não infla.
const lote = useProofAutoApprove();
const { toast } = useToast();

async function tryAutoApproveOnLogin() {
    if (typeof window === 'undefined') return;
    // Roda 1× por sessão pra não disparar em toda navegação Inertia.
    if (sessionStorage.getItem('proof_auto_approve_ran')) return;
    try {
        // 1. Lê config (toggle + maturidade)
        const cfgRes = await fetch(route('master.cobrancas.auto-approve-config'), {
            credentials: 'same-origin', headers: { Accept: 'application/json' },
        });
        if (! cfgRes.ok) return;
        const cfg = await cfgRes.json();
        if (! cfg.enabled) return; // toggle off OU abaixo do threshold de maturidade

        // 2. Pega lista de pendentes
        const listRes = await fetch(route('master.cobrancas.pending-review-list'), {
            credentials: 'same-origin', headers: { Accept: 'application/json' },
        });
        if (! listRes.ok) return;
        const { invoices } = await listRes.json();
        if (! invoices?.length) {
            sessionStorage.setItem('proof_auto_approve_ran', '1');
            return;
        }

        // 3. Marca como rodado ANTES de processar (evita re-trigger se demorar)
        sessionStorage.setItem('proof_auto_approve_ran', '1');

        toast.info(`🤖 Processando ${invoices.length} comprovante(s) em background...`);
        await lote.processarLote(invoices);
        const a = lote.aprovados.value;
        const pn = lote.pendentes.value;
        const e = lote.erros.value;
        if (a > 0 || pn > 0) {
            const msg = `🤖 Auto-aprovação: ${a} aprovado(s), ${pn} pra revisão manual${e > 0 ? `, ${e} erro(s)` : ''}`;
            (a > 0 ? toast.success : toast.info)(msg);
        }
    } catch (err) {
        // Falha silenciosa — auto-aprovação é otimização, não pode quebrar UX
        console.warn('[auto-approve] falhou:', err.message);
    }
}

onMounted(() => {
    // Aguarda 2s pra UI carregar primeiro (não competir com bundle inicial)
    setTimeout(() => { tryAutoApproveOnLogin(); }, 2000);
});

/**
 * Itens da sidebar.
 *   - `route`: rota Inertia real. Se null → placeholder desabilitado.
 *   - `phase`: fase roadmap em que ficará ativo (exibido como rótulo).
 */
// Menu do master — somente rotas OPERACIONAIS.
// Item "Configurações" (M8) removido para não exibir placeholder "em breve"
// em produto comercial. Quando tiver tela real, basta readicionar com route:'master.cms.settings'.
const menu = [
    { label: 'Painel', route: 'master.dashboard', phase: null, icon: 'dashboard' },
    { label: 'Clientes', route: 'master.tenants.index', phase: null, icon: 'building' },
    { label: 'Planos', route: 'master.planos.index', phase: null, icon: 'card' },
    { label: 'Cobranças', route: 'master.cobrancas.index', phase: null, icon: 'invoice' },
    { label: 'Auditoria', route: 'master.atividades.index', phase: null, icon: 'shield' },
    // "CMS (Landing)" foi removido da sidebar: o CMS é per-cliente e se
    // acessa via Clientes → ícone "CMS" em cada linha. A rota legada
    // `master.cms.index` permanece viva (compatibilidade), só não aparece
    // mais como item de menu para evitar confusão com CMS global.
];

// (paths centralizados em Icon.vue — usar <Icon name="..." />)
</script>

<template>
    <GlobalLoading />
    <ToastContainer />
    <ConfirmDialog />
    <TutorialTour />
    <FlashMessages />
    <ImpersonationBanner />

    <div :class="['min-h-screen flex bg-slate-50 w-full overflow-x-hidden', impersonation ? 'pt-10' : '']">
        <!-- SIDEBAR — mesmo padrão visual do AdminLayout (verde-escuro do brand)
             para consistência. A diferenciação semântica fica no badge MASTER
             âmbar do topbar e no item "Plataforma" abaixo da logo. -->
        <!-- B4.4 fix · flex column + flex-1 + dvh para sidebar scrollar até o fim
             em iOS Safari (problema reportado: itens inferiores inacessíveis em mobile) -->
        <aside
            class="fixed inset-y-0 left-0 z-40 w-64 bg-macaybas-primary-950 text-slate-300 transform transition-transform duration-200 lg:static lg:flex-shrink-0 lg:translate-x-0 flex flex-col h-screen lg:h-auto"
            style="height: 100dvh;"
            :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full', impersonation ? 'top-10' : 'top-0']"
        >
            <!-- Brand — mesmo formato do Admin (logo redonda + nome + papel) -->
            <div class="flex h-16 items-center gap-3 px-5 border-b border-white/10 flex-shrink-0">
                <div class="h-9 w-9 rounded-full bg-white text-macaybas-primary-900 flex items-center justify-center font-serif text-lg font-bold">M</div>
                <div class="min-w-0">
                    <div class="text-white font-serif font-bold leading-none truncate">Plataforma</div>
                    <div class="text-xs text-amber-400 uppercase tracking-[0.18em]">Área Master</div>
                </div>
            </div>

            <!-- Menu — mesmo estilo do AdminLayout (espaços, ícones, cores).
                 Indicador de ativo: faixa lateral âmbar (cor de identidade Master) +
                 fundo translúcido. Ícones específicos por item (não mais hambúrguer). -->
            <nav class="p-3 space-y-1 flex-1 min-h-0 overflow-y-auto overscroll-contain pb-8">
                <h3 class="text-xs uppercase tracking-widest text-white/40 px-3 mb-2">Plataforma</h3>
                <template v-for="item in menu" :key="item.label">
                    <Link
                        v-if="item.route"
                        :href="route(item.route)"
                        @click="sidebarOpen = false"
                        class="relative flex items-center gap-3 rounded-lg pl-4 pr-3 py-2.5 text-sm transition"
                        :class="isActive(item.route)
                            ? 'bg-white/10 text-white font-semibold'
                            : 'text-slate-300 hover:bg-white/5 hover:text-white'"
                    >
                        <span
                            v-if="isActive(item.route)"
                            class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r bg-amber-400"
                            aria-hidden="true"
                        ></span>
                        <Icon :name="item.icon" :size="20" :stroke-width="1.7" />
                        <span class="flex-1 truncate">{{ item.label }}</span>
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
                </template>
            </nav>
        </aside>

        <!-- Overlay mobile (mesma intensidade do Admin) -->
        <div v-if="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-black/40 z-30 lg:hidden"></div>

        <!-- MAIN — espaçamento, sticky topbar e padding idênticos ao Admin -->
        <div class="flex-1 flex flex-col min-w-0 w-full">
            <header class="sticky top-0 z-20 h-16 flex items-center justify-between bg-white border-b border-slate-200 px-4 lg:px-8">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-md hover:bg-slate-100" aria-label="Menu">
                    <svg class="h-6 w-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <div class="hidden lg:block">
                    <h1 class="text-lg font-semibold text-slate-900"><slot name="page-title">Plataforma</slot></h1>
                </div>

                <!-- User + badge MASTER (mesmo dropdown-style do Admin) -->
                <div class="flex items-center gap-3">
                    <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-700 text-[10px] font-semibold uppercase tracking-widest ring-1 ring-amber-500/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        Master
                    </span>
                    <div class="flex items-center gap-2 p-1.5">
                        <div class="h-8 w-8 rounded-full bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center text-sm font-semibold">
                            {{ user?.name?.[0]?.toUpperCase() }}
                        </div>
                        <span class="text-sm font-medium text-slate-700 hidden xl:block truncate max-w-[140px] whitespace-nowrap">{{ user?.name }}</span>
                    </div>
                    <button
                        @click="logout"
                        class="text-sm text-slate-600 hover:text-red-700 px-3 py-1.5 rounded-lg hover:bg-red-50 transition"
                    >Sair</button>
                </div>
            </header>

            <main :class="['flex-1 px-4 pb-4 lg:px-8 lg:pb-8 min-w-0 w-full max-w-full overflow-x-hidden',
                            impersonation ? 'pt-10 lg:pt-14' : 'pt-4 lg:pt-8']">
                <AlertBar />
                <slot />
            </main>
        </div>
    </div>
</template>
