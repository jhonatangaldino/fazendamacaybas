<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';

const props = defineProps({
    tenant: { type: Object, required: true },
    subscription: { type: Object, default: null },
    plans: { type: Array, default: () => [] },
});

function brl(v) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
}

function hoje() {
    const d = new Date();
    return d.toISOString().slice(0, 10);
}
function hojeMais(days) {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

const statusLabel = {
    active: { text: 'Ativa', color: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
    trial: { text: 'Em teste', color: 'bg-sky-50 text-sky-700 ring-sky-200', dot: 'bg-sky-500' },
    overdue: { text: 'Em atraso', color: 'bg-amber-50 text-amber-700 ring-amber-200', dot: 'bg-amber-500' },
    canceled: { text: 'Cancelada', color: 'bg-rose-50 text-rose-700 ring-rose-200', dot: 'bg-rose-500' },
};

// Form de alteração de assinatura
const editMode = ref(false);
const form = useForm({
    plan_id: props.subscription?.plan_id ?? (props.plans[0]?.id ?? null),
    status: props.subscription?.status ?? 'active',
    current_period_start: null,
    current_period_end: null,
});

function salvarAssinatura() {
    form.put(route('master.tenants.subscription.update', props.tenant.id), {
        onSuccess: () => { editMode.value = false; },
    });
}

function cancelarAssinatura() {
    if (! confirm(`Confirma cancelar a assinatura de "${props.tenant.nome}"?`)) return;
    useForm({}).post(route('master.tenants.subscription.cancel', props.tenant.id));
}

// Form de geração de invoice
const showInvoiceForm = ref(false);
const invoiceForm = useForm({
    valor: computed(() => props.subscription?.plan_preco ?? 0).value,
    data_emissao: hoje(),
    data_vencimento: hojeMais(10),
});

function gerarCobranca() {
    invoiceForm.post(route('master.tenants.invoices.store', props.tenant.id), {
        onSuccess: () => { showInvoiceForm.value = false; },
    });
}
</script>

<template>
    <Head :title="`Assinatura · ${tenant.nome}`" />
    <MasterLayout>
        <template #page-title>Assinatura · {{ tenant.nome }}</template>

        <!-- Breadcrumb -->
        <nav class="text-sm text-slate-500 mb-4 flex items-center gap-1.5">
            <Link :href="route('master.tenants.index')" class="hover:text-slate-900">Clientes</Link>
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span>{{ tenant.nome }}</span>
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-900 font-medium">Assinatura</span>
        </nav>

        <!-- Header -->
        <div class="mb-6">
            <h2 class="text-xl font-serif font-bold text-slate-900">Assinatura de {{ tenant.nome }}</h2>
            <p class="mt-1 text-sm text-slate-600">Plano contratado, status e cobranças.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Card: estado atual -->
            <div class="lg:col-span-2 rounded-2xl bg-white ring-1 ring-slate-200 p-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Plano contratado</h3>
                    </div>
                    <span
                        v-if="subscription"
                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1"
                        :class="statusLabel[subscription.status]?.color || 'bg-slate-100 text-slate-700 ring-slate-200'"
                    >
                        <span class="h-1.5 w-1.5 rounded-full" :class="statusLabel[subscription.status]?.dot || 'bg-slate-400'"></span>
                        {{ statusLabel[subscription.status]?.text || subscription.status }}
                    </span>
                </div>

                <div v-if="subscription" class="space-y-4">
                    <div>
                        <div class="text-2xl font-serif font-bold text-slate-900">{{ subscription.plan_nome || '—' }}</div>
                        <div v-if="subscription.plan_preco !== null" class="text-sm text-slate-600">
                            {{ brl(subscription.plan_preco) }}/mês
                        </div>
                    </div>

                    <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                        <div v-if="subscription.started_at">
                            <dt class="text-xs uppercase tracking-wider text-slate-500">Início</dt>
                            <dd class="text-slate-800">{{ subscription.started_at }}</dd>
                        </div>
                        <div v-if="subscription.current_period_start">
                            <dt class="text-xs uppercase tracking-wider text-slate-500">Período atual</dt>
                            <dd class="text-slate-800">{{ subscription.current_period_start }} → {{ subscription.current_period_end || '—' }}</dd>
                        </div>
                        <div v-if="subscription.trial_ends_at">
                            <dt class="text-xs uppercase tracking-wider text-slate-500">Fim do trial</dt>
                            <dd class="text-slate-800">{{ subscription.trial_ends_at }}</dd>
                        </div>
                        <div v-if="subscription.canceled_at">
                            <dt class="text-xs uppercase tracking-wider text-slate-500">Cancelada em</dt>
                            <dd class="text-rose-700">{{ subscription.canceled_at }}</dd>
                        </div>
                    </dl>

                    <div class="pt-4 border-t border-slate-100 flex items-center gap-2">
                        <button
                            type="button"
                            @click="editMode = true"
                            class="px-3 py-1.5 rounded-lg bg-macaybas-primary-700 text-white text-sm font-semibold hover:bg-macaybas-primary-800"
                        >Alterar plano/status</button>
                        <button
                            type="button"
                            v-if="subscription.status !== 'canceled'"
                            @click="cancelarAssinatura"
                            class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 text-sm font-medium hover:bg-rose-100 ring-1 ring-rose-200"
                        >Cancelar assinatura</button>
                    </div>
                </div>

                <div v-else class="text-center py-6">
                    <p class="text-sm text-slate-600 mb-4">Este cliente ainda não tem assinatura.</p>
                    <button
                        type="button"
                        @click="editMode = true"
                        class="px-4 py-2 rounded-lg bg-macaybas-primary-700 text-white text-sm font-semibold hover:bg-macaybas-primary-800 shadow-sm"
                    >Atribuir plano</button>
                </div>

                <!-- Form de edição de assinatura -->
                <div v-if="editMode" class="mt-6 pt-6 border-t border-slate-100">
                    <h4 class="text-sm font-semibold text-slate-800 mb-3">{{ subscription ? 'Alterar assinatura' : 'Nova assinatura' }}</h4>
                    <form @submit.prevent="salvarAssinatura" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Plano</label>
                            <select
                                v-model="form.plan_id"
                                class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm"
                                :class="form.errors.plan_id ? 'ring-red-400' : ''"
                            >
                                <option :value="null" disabled>Selecione um plano</option>
                                <option v-for="p in plans" :key="p.id" :value="p.id">
                                    {{ p.nome }} — {{ brl(p.preco_mensal) }}/mês
                                </option>
                            </select>
                            <p v-if="form.errors.plan_id" class="mt-1 text-xs text-red-600">{{ form.errors.plan_id }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Status</label>
                            <select
                                v-model="form.status"
                                class="w-full max-w-xs px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm"
                            >
                                <option value="active">Ativa</option>
                                <option value="trial">Em teste (trial)</option>
                                <option value="overdue">Em atraso</option>
                                <option value="canceled">Cancelada</option>
                            </select>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Período: início <span class="text-xs text-slate-500 font-normal">(opcional)</span></label>
                                <input v-model="form.current_period_start" type="date" class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Período: fim <span class="text-xs text-slate-500 font-normal">(opcional)</span></label>
                                <input v-model="form.current_period_end" type="date" class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm">
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="submit" :disabled="form.processing" class="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 disabled:opacity-60">
                                {{ form.processing ? 'Salvando…' : 'Salvar' }}
                            </button>
                            <button type="button" @click="editMode = false" class="px-3 py-1.5 rounded-lg text-slate-700 hover:bg-slate-100 text-sm">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Card: gerar cobrança -->
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Gerar cobrança</h3>

                <button
                    type="button"
                    v-if="! showInvoiceForm"
                    @click="showInvoiceForm = true"
                    class="w-full px-4 py-2.5 rounded-lg bg-macaybas-primary-700 text-white text-sm font-semibold hover:bg-macaybas-primary-800"
                >
                    + Nova cobrança
                </button>

                <form v-else @submit.prevent="gerarCobranca" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Valor (R$)</label>
                        <input
                            v-model.number="invoiceForm.valor"
                            type="number" step="0.01" min="0"
                            class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm font-mono"
                            :class="invoiceForm.errors.valor ? 'ring-red-400' : ''"
                        >
                        <p v-if="invoiceForm.errors.valor" class="mt-1 text-xs text-red-600">{{ invoiceForm.errors.valor }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Emissão</label>
                        <input
                            v-model="invoiceForm.data_emissao" type="date"
                            class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm"
                            :class="invoiceForm.errors.data_emissao ? 'ring-red-400' : ''"
                        >
                        <p v-if="invoiceForm.errors.data_emissao" class="mt-1 text-xs text-red-600">{{ invoiceForm.errors.data_emissao }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Vencimento</label>
                        <input
                            v-model="invoiceForm.data_vencimento" type="date"
                            class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm"
                            :class="invoiceForm.errors.data_vencimento ? 'ring-red-400' : ''"
                        >
                        <p v-if="invoiceForm.errors.data_vencimento" class="mt-1 text-xs text-red-600">{{ invoiceForm.errors.data_vencimento }}</p>
                    </div>
                    <div class="flex items-center gap-2 pt-2">
                        <button type="submit" :disabled="invoiceForm.processing" class="flex-1 px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 disabled:opacity-60">
                            {{ invoiceForm.processing ? 'Gerando…' : 'Gerar' }}
                        </button>
                        <button type="button" @click="showInvoiceForm = false" class="px-3 py-2 rounded-lg text-slate-700 hover:bg-slate-100 text-sm">Cancelar</button>
                    </div>
                </form>

                <Link
                    :href="route('master.cobrancas.index')"
                    class="mt-4 block text-center text-xs text-slate-500 hover:text-slate-900"
                >Ver todas as cobranças →</Link>
            </div>
        </div>
    </MasterLayout>
</template>
