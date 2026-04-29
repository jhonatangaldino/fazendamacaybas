<script setup>
/**
 * Master · Manuais
 *
 * Tela de distribuição de manuais. Para cada manual disponível no catálogo:
 *   - "Baixar"  → download direto (HTML self-contained com imagens em base64)
 *   - "Enviar"  → modal pra escolher cliente + dono ATIVO + mensagem opcional
 *
 * Filtros do destinatário:
 *   1. Cliente (tenant ativo)
 *   2. Usuário (dono_fazenda + ativo, do tenant escolhido)
 * Busca de donos: AJAX em /master/manuais/tenants/{tenant}/donos.
 */
import { ref, computed, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import Icon from '@/Components/Icon.vue';
import { useToast } from '@/composables/useToast.js';

const props = defineProps({
    manuais: { type: Array, default: () => [] },
    tenants: { type: Array, default: () => [] },
});

const { toast } = useToast();
const page = usePage();

// ── Modal de envio ────────────────────────────────────────────
const modalAberto = ref(false);
const manualSelecionado = ref(null);
const carregandoDonos = ref(false);
const donos = ref([]);
const erroDonos = ref('');

const form = useForm({
    tenant_id: '',
    user_id: '',
    mensagem: '',
});

function abrirModalEnvio(manual) {
    manualSelecionado.value = manual;
    form.reset();
    form.clearErrors();
    donos.value = [];
    erroDonos.value = '';
    modalAberto.value = true;
}

function fecharModal() {
    modalAberto.value = false;
    manualSelecionado.value = null;
    form.reset();
}

// Quando o tenant muda, busca donos via AJAX.
watch(() => form.tenant_id, async (novoId) => {
    form.user_id = '';
    donos.value = [];
    erroDonos.value = '';
    if (! novoId) return;

    carregandoDonos.value = true;
    try {
        const res = await fetch(route('master.manuais.donos', novoId), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (! res.ok) throw new Error('http '+res.status);
        const data = await res.json();
        donos.value = data.donos || [];
        if (! donos.value.length) {
            erroDonos.value = 'Este cliente não tem dono(s) com cadastro ativo. Cadastre um dono em Clientes → Usuários antes de enviar.';
        }
    } catch (e) {
        erroDonos.value = 'Erro ao carregar donos. Recarregue a página e tente de novo.';
    } finally {
        carregandoDonos.value = false;
    }
});

const tenantSelecionado = computed(() =>
    props.tenants.find(t => t.id === Number(form.tenant_id)) || null
);

const donoSelecionado = computed(() =>
    donos.value.find(d => d.id === Number(form.user_id)) || null
);

function enviar() {
    if (! manualSelecionado.value) return;

    form.post(route('master.manuais.enviar', manualSelecionado.value.slug), {
        preserveScroll: true,
        onSuccess: () => {
            // Mensagem flash do controller já aparece via FlashMessages global.
            // Mantemos toast adicional como reforço imediato.
            toast.success('Manual enviado com sucesso.');
            fecharModal();
        },
        onError: (errors) => {
            const msg = Object.values(errors)[0] || 'Não foi possível enviar. Confira os campos.';
            toast.error(msg);
        },
    });
}

function baixar(manual) {
    // Download direto via window.location (preserva session/cookies).
    window.location.href = route('master.manuais.baixar', manual.slug);
    toast.info('Preparando download… o arquivo abre em alguns segundos.');
}
</script>

<template>
    <Head title="Manuais · Plataforma" />
    <MasterLayout>
        <template #page-title>Manuais</template>

        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-serif font-bold text-slate-900">Manuais de Usuários</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Baixe o manual ou envie diretamente por e-mail para o dono de um cliente.
                    O destinatário recebe um arquivo HTML que abre em qualquer navegador.
                </p>
            </div>
        </div>

        <!-- Catálogo de manuais -->
        <div v-if="manuais.length === 0" class="rounded-2xl bg-white ring-1 ring-slate-200 p-10 text-center">
            <h3 class="text-sm font-semibold text-slate-900">Nenhum manual disponível</h3>
            <p class="mt-1 text-sm text-slate-500">O catálogo está vazio.</p>
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2">
            <div
                v-for="manual in manuais"
                :key="manual.slug"
                class="rounded-2xl bg-white ring-1 ring-slate-200 p-6 flex flex-col"
            >
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 h-12 w-12 rounded-xl bg-macaybas-primary-50 text-macaybas-primary-700 flex items-center justify-center">
                        <Icon name="book" :size="24" :stroke-width="1.7" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-serif font-bold text-slate-900">{{ manual.titulo }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ manual.publico }}</p>
                    </div>
                </div>

                <p class="mt-4 text-sm text-slate-600 leading-relaxed flex-1">{{ manual.descricao }}</p>

                <div class="mt-4 grid grid-cols-2 gap-2 text-xs text-slate-500 border-t border-slate-100 pt-3">
                    <div>
                        <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Páginas</div>
                        <div class="text-slate-700 font-semibold">~{{ manual.paginas_aprox }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Tamanho</div>
                        <div class="text-slate-700 font-semibold">~{{ manual.tamanho_aprox_mb }} MB</div>
                    </div>
                </div>

                <div class="mt-5 flex flex-col sm:flex-row gap-2">
                    <button
                        type="button"
                        @click="baixar(manual)"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-white ring-1 ring-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 flex-1"
                    >
                        <Icon name="download" :size="16" />
                        Baixar
                    </button>
                    <button
                        type="button"
                        @click="abrirModalEnvio(manual)"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-macaybas-primary-700 text-white text-sm font-semibold hover:bg-macaybas-primary-800 flex-1 shadow-sm"
                    >
                        <Icon name="envelope" :size="16" />
                        Enviar manual
                    </button>
                </div>
            </div>
        </div>

        <!-- ────── Modal de envio ────── -->
        <div
            v-if="modalAberto"
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/60 backdrop-blur-sm p-0 sm:p-4"
            @click.self="fecharModal"
        >
            <div class="bg-white w-full sm:max-w-xl sm:rounded-2xl sm:shadow-2xl max-h-[95vh] flex flex-col rounded-t-2xl">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h3 class="text-lg font-serif font-bold text-slate-900">Enviar manual por e-mail</h3>
                        <p class="text-xs text-slate-500 mt-0.5 truncate">
                            <span class="font-medium">{{ manualSelecionado?.titulo }}</span> ·
                            o destinatário recebe um anexo .html
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="fecharModal"
                        class="flex-shrink-0 h-8 w-8 rounded-lg flex items-center justify-center text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Fechar"
                    >
                        <Icon name="x" :size="20" />
                    </button>
                </div>

                <!-- Body -->
                <form @submit.prevent="enviar" class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
                    <!-- Cliente -->
                    <div>
                        <label for="tenant_id" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            1. Cliente <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="tenant_id"
                            v-model="form.tenant_id"
                            class="block w-full rounded-lg border-slate-300 bg-white text-slate-900 text-sm shadow-sm focus:border-macaybas-primary-500 focus:ring-macaybas-primary-500"
                            required
                        >
                            <option value="">Selecione um cliente…</option>
                            <option v-for="t in tenants" :key="t.id" :value="t.id">
                                {{ t.nome }}<span v-if="t.cidade && t.estado"> · {{ t.cidade }}/{{ t.estado }}</span>
                            </option>
                        </select>
                        <p v-if="form.errors.tenant_id" class="mt-1 text-xs text-rose-600">{{ form.errors.tenant_id }}</p>
                    </div>

                    <!-- Usuário (carregado conforme cliente) -->
                    <div>
                        <label for="user_id" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            2. Dono da fazenda (ativo) <span class="text-rose-500">*</span>
                        </label>

                        <div v-if="! form.tenant_id" class="px-3 py-2.5 rounded-lg bg-slate-50 ring-1 ring-slate-200 text-sm text-slate-500">
                            Selecione um cliente primeiro.
                        </div>

                        <div v-else-if="carregandoDonos" class="px-3 py-2.5 rounded-lg bg-slate-50 ring-1 ring-slate-200 text-sm text-slate-500 flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Buscando donos…
                        </div>

                        <div v-else-if="erroDonos" class="px-3 py-2.5 rounded-lg bg-amber-50 ring-1 ring-amber-200 text-sm text-amber-800">
                            {{ erroDonos }}
                        </div>

                        <select
                            v-else
                            id="user_id"
                            v-model="form.user_id"
                            class="block w-full rounded-lg border-slate-300 bg-white text-slate-900 text-sm shadow-sm focus:border-macaybas-primary-500 focus:ring-macaybas-primary-500"
                            required
                        >
                            <option value="">Selecione o destinatário…</option>
                            <option v-for="d in donos" :key="d.id" :value="d.id">
                                {{ d.name }} ({{ d.email }})<span v-if="d.cargo"> · {{ d.cargo }}</span>
                            </option>
                        </select>
                        <p v-if="form.errors.user_id" class="mt-1 text-xs text-rose-600">{{ form.errors.user_id }}</p>
                        <p v-else class="mt-1 text-xs text-slate-500">Apenas usuários com perfil <span class="font-semibold">"dono_fazenda"</span> e ativos aparecem aqui.</p>
                    </div>

                    <!-- Mensagem (opcional) -->
                    <div>
                        <label for="mensagem" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Mensagem personalizada <span class="text-slate-400 font-normal">(opcional)</span>
                        </label>
                        <textarea
                            id="mensagem"
                            v-model="form.mensagem"
                            rows="4"
                            maxlength="1500"
                            placeholder="Ex.: Conforme conversamos no telefone, segue em anexo o manual atualizado…"
                            class="block w-full rounded-lg border-slate-300 bg-white text-slate-900 text-sm shadow-sm focus:border-macaybas-primary-500 focus:ring-macaybas-primary-500 resize-none"
                        ></textarea>
                        <p class="mt-1 text-xs text-slate-500">{{ form.mensagem.length }}/1500 caracteres</p>
                        <p v-if="form.errors.mensagem" class="mt-1 text-xs text-rose-600">{{ form.errors.mensagem }}</p>
                    </div>

                    <!-- Resumo do envio (preview) -->
                    <div
                        v-if="tenantSelecionado && donoSelecionado"
                        class="rounded-lg bg-emerald-50 ring-1 ring-emerald-200 p-3 text-sm text-emerald-900"
                    >
                        <div class="flex items-start gap-2">
                            <Icon name="check-circle" :size="18" class="text-emerald-600 mt-0.5" />
                            <div class="flex-1">
                                <p class="font-semibold">Pronto pra enviar:</p>
                                <p class="text-xs mt-1 leading-relaxed">
                                    <span class="font-semibold">{{ manualSelecionado?.titulo }}</span> →
                                    <span class="font-semibold">{{ donoSelecionado.name }}</span>
                                    <span class="text-emerald-700"> ({{ donoSelecionado.email }})</span><br>
                                    Cliente: {{ tenantSelecionado.nome }}
                                </p>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex flex-col-reverse sm:flex-row gap-2 sm:justify-end rounded-b-none sm:rounded-b-2xl">
                    <button
                        type="button"
                        @click="fecharModal"
                        :disabled="form.processing"
                        class="px-4 py-2.5 rounded-lg bg-white ring-1 ring-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-100 disabled:opacity-50"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="enviar"
                        :disabled="form.processing || ! form.user_id"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-macaybas-primary-700 text-white text-sm font-semibold hover:bg-macaybas-primary-800 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                    >
                        <svg
                            v-if="form.processing"
                            class="animate-spin h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <Icon v-else name="envelope" :size="16" />
                        {{ form.processing ? 'Enviando…' : 'Enviar agora' }}
                    </button>
                </div>
            </div>
        </div>
    </MasterLayout>
</template>
