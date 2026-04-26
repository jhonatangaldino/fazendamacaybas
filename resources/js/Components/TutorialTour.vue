<script setup>
/**
 * TutorialTour — popup contextual com "passo X de Y" + checkbox "não exibir mais".
 *
 * Comportamento:
 *   - Monta no AdminLayout. Em cada navegação Inertia, faz GET /admin/tutorials/active?rota=PATH.
 *   - Se vier tutorial: mostra popup ancorado ao centro inferior.
 *   - Botões: Anterior · Próximo · Pular · ☐ Não mostrar mais.
 *   - "Próximo" no último passo → POST complete (não volta mais).
 *   - "Pular" SEM o checkbox marcado → POST snooze (volta em 15 dias).
 *   - "Pular" COM o checkbox marcado → POST dismiss (não volta mais).
 *
 * Configuração mínima: adicione <TutorialTour /> uma vez no AdminLayout.
 * O resto é puxado do backend.
 */
import { ref, computed, watch, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const tutorial = ref(null);    // { key, titulo, passos[] }
const passoIdx = ref(0);
const naoExibirMais = ref(false);
const carregando = ref(false);

const passoAtual = computed(() => tutorial.value?.passos[passoIdx.value] ?? null);
const totalPassos = computed(() => tutorial.value?.passos.length ?? 0);
const ultimoPasso = computed(() => passoIdx.value >= totalPassos.value - 1);

// B4.4 fix · NÃO exibir tutorial em rotas de form/assistente onde popup bloquearia ações.
// Tutorial só faz sentido em telas de início/listas/painel, não no meio de operações.
function rotaPermiteTutorial(path) {
    const blacklist = ['/fluxos/', '/novo', '/editar', '/edit', '/criar', '/cadastrar'];
    return ! blacklist.some(b => path.includes(b));
}

// B4.4 fix · NÃO auto-exibir em mobile pequeno (<480px) — popup ocupa toda tela
// e bloqueia visualização da sidebar. Usuário pode acionar manual via botão "?"
function viewportPermiteTutorial() {
    return window.innerWidth >= 480;
}

async function fetchTutorial() {
    if (carregando.value) return;
    const path = window.location.pathname;
    if (! rotaPermiteTutorial(path)) {
        tutorial.value = null;
        return;
    }
    if (! viewportPermiteTutorial()) {
        tutorial.value = null;
        return;
    }
    carregando.value = true;
    try {
        const resp = await fetch(`/admin/tutorials/active?rota=${encodeURIComponent(path)}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (! resp.ok) return;
        const data = await resp.json();
        if (data.tutorial) {
            tutorial.value = data.tutorial;
            passoIdx.value = 0;
            naoExibirMais.value = false;
        }
    } catch (e) {
        // silencioso — não quebra UX se backend falhar
    } finally {
        carregando.value = false;
    }
}

async function postAcao(acao) {
    if (! tutorial.value) return;
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    await fetch(`/admin/tutorials/${tutorial.value.key}/${acao}`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
    });
    tutorial.value = null;
}

function proximo() {
    if (ultimoPasso.value) {
        postAcao('complete');
    } else {
        passoIdx.value++;
    }
}

function anterior() {
    if (passoIdx.value > 0) passoIdx.value--;
}

function pular() {
    postAcao(naoExibirMais.value ? 'dismiss' : 'snooze');
}

function fechar() {
    postAcao(naoExibirMais.value ? 'dismiss' : 'snooze');
}

// Tenta carregar no mount + após cada navegação Inertia
onMounted(() => {
    fetchTutorial();
    router.on('navigate', () => {
        // Pequeno delay pra DOM novo carregar antes do popup
        setTimeout(fetchTutorial, 400);
    });
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <!-- B4.4 fix · pointer-events-none no wrapper deixa cliques passarem
                 para elementos do conteúdo. Apenas o card interno captura eventos. -->
            <div v-if="tutorial && passoAtual"
                 class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 sm:w-[420px] z-50 pointer-events-none">
                <div class="rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 p-5 pointer-events-auto">
                    <!-- Header com indicador de progresso + close -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-1.5">
                            <span v-for="i in totalPassos" :key="i"
                                  class="h-1.5 rounded-full transition-all"
                                  :class="i - 1 === passoIdx
                                      ? 'w-6 bg-macaybas-primary'
                                      : i - 1 < passoIdx
                                          ? 'w-1.5 bg-macaybas-primary-300'
                                          : 'w-1.5 bg-slate-200'"></span>
                        </div>
                        <button @click="fechar"
                                aria-label="Fechar tutorial"
                                class="text-slate-400 hover:text-slate-600 p-1 rounded-md hover:bg-slate-100">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Conteúdo -->
                    <h3 class="text-lg font-bold text-slate-900 mb-2">{{ passoAtual.titulo }}</h3>
                    <p class="text-sm text-slate-600 leading-relaxed mb-4">{{ passoAtual.descricao }}</p>

                    <!-- Footer -->
                    <div class="flex items-center justify-between gap-3 pt-3 border-t border-slate-100">
                        <div class="text-xs text-slate-500">
                            {{ passoIdx + 1 }} de {{ totalPassos }}
                        </div>
                        <div class="flex items-center gap-2">
                            <button v-if="passoIdx > 0"
                                    @click="anterior"
                                    class="text-xs text-slate-600 hover:text-slate-900 px-2 py-1.5 rounded-md hover:bg-slate-100">
                                Anterior
                            </button>
                            <button @click="pular"
                                    class="text-xs text-slate-500 hover:text-slate-700 px-2 py-1.5 rounded-md hover:bg-slate-100">
                                Pular
                            </button>
                            <button @click="proximo"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-white bg-macaybas-primary hover:bg-macaybas-primary-900 px-3 py-1.5 rounded-md">
                                {{ ultimoPasso ? 'Entendi!' : 'Próximo →' }}
                            </button>
                        </div>
                    </div>

                    <!-- Não mostrar mais -->
                    <label class="flex items-center gap-2 mt-3 text-xs text-slate-500 cursor-pointer">
                        <input type="checkbox" v-model="naoExibirMais"
                               class="rounded border-slate-300 text-macaybas-primary focus:ring-macaybas-primary">
                        Não mostrar este tutorial novamente
                    </label>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
