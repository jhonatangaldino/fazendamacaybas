<script setup>
/**
 * ImpersonationBanner — M5
 *
 * Banner global e persistente que aparece no topo de TODAS as páginas enquanto
 * houver sessão de impersonação ativa. Incluído em:
 *   - MasterLayout (master impersonando visita /master)
 *   - AdminLayout  (master operando no /admin como tenant user)
 *
 * Dado vem do Inertia share `impersonation` (HandleInertiaRequests):
 *   { tenant_id, tenant_nome, started_at } ou null
 *
 * Quando null → v-if oculta o banner sem efeito.
 *
 * UX:
 *   - cor âmbar (sempre visível, nunca alarmante como vermelho)
 *   - sticky no topo, z-index alto para ficar sobre o topbar
 *   - botão "Sair" à direita, POST /master/impersonation/sair
 */

import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const impersonation = computed(() => page.props.impersonation || null);

function exit() {
    router.post(route('master.impersonation.exit'), {}, {
        preserveScroll: false,
    });
}
</script>

<template>
    <!--
      `fixed top-0 left-0 right-0` garante que o banner sempre está no topo da
      viewport, mesmo com scroll. Para não cobrir o conteúdo, o AdminLayout e
      MasterLayout aplicam `padding-top` na raiz quando `impersonation` existe.
      Altura útil: 40px (h-10 em CSS data-attr abaixo). z-[70] fica acima de tudo
      (sidebar fixed mobile, popovers, modais comuns).
    -->
    <div
        v-if="impersonation"
        class="fixed top-0 left-0 right-0 z-[70] bg-amber-500 text-slate-900 shadow-md h-10 flex items-center"
        data-impersonation-banner
    >
        <div class="px-4 lg:px-8 w-full flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <!-- ícone aviso -->
                <svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2L1 21h22L12 2zm0 4.1L19.5 19h-15L12 6.1zM11 10v4h2v-4h-2zm0 6v2h2v-2h-2z"/>
                </svg>
                <div class="text-sm min-w-0 truncate">
                    <span class="font-semibold uppercase tracking-wider text-[11px] mr-1.5">Impersonação</span>
                    <span class="hidden sm:inline">Operando como</span>
                    <strong class="ml-1 truncate inline-block align-bottom max-w-[160px] sm:max-w-none">{{ impersonation.tenant_nome }}</strong>
                </div>
            </div>

            <button
                @click="exit"
                class="flex-shrink-0 px-3 py-1 rounded-md bg-slate-900 text-amber-300 text-xs font-semibold hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2 focus:ring-offset-amber-500"
            >
                Sair
            </button>
        </div>
    </div>
</template>
