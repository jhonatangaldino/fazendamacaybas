<script setup>
/**
 * BLOCO 4.3 RN3 — CRUD UI de Contas Financeiras (tenant area).
 * Padrão obrigatório B4.3: tabela desktop + cards mobile + ações com label.
 */
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useConfirm } from '@/composables/useConfirm.js';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';

const { confirm } = useConfirm();

const props = defineProps({
    contas: { type: Array, default: () => [] },
    tipos: { type: Object, default: () => ({}) },
});

const showForm = ref(false);
useBodyScrollLock(showForm);
const editing = ref(null);

const form = useForm({
    nome: '',
    tipo: 'corrente',
    banco: '',
    agencia: '',
    conta: '',
    saldo_inicial: 0,
    observacoes: '',
});

function abrirNova() {
    editing.value = null;
    form.reset();
    showForm.value = true;
}

function abrirEdicao(c) {
    editing.value = c;
    form.nome = c.nome;
    form.tipo = c.tipo;
    form.banco = c.banco || '';
    form.agencia = c.agencia || '';
    form.conta = c.conta || '';
    form.saldo_inicial = c.saldo_inicial || 0;
    form.observacoes = c.observacoes || '';
    showForm.value = true;
}

function salvar() {
    if (editing.value) {
        form.put(route('admin.financeiro.contas.update', editing.value.id), {
            onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post(route('admin.financeiro.contas.store'), {
            onSuccess: () => { showForm.value = false; },
        });
    }
}

async function toggle(c) {
    const verbo = c.is_active ? 'desativar' : 'reativar';
    const ok = await confirm({
        title: `${verbo[0].toUpperCase() + verbo.slice(1)} conta`,
        message: c.is_active
            ? `Desativar "${c.nome}"? Não será possível selecioná-la em novas despesas/receitas. Histórico de lançamentos permanece.`
            : `Reativar "${c.nome}"?`,
        confirmText: verbo[0].toUpperCase() + verbo.slice(1),
        variant: c.is_active ? 'danger' : 'primary',
    });
    if (! ok) return;
    router.post(route('admin.financeiro.contas.toggle', c.id), {}, { preserveScroll: true });
}

function brl(v) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
}
</script>

<template>
    <Head title="Financeiro · Contas" />
    <AdminLayout>
        <template #page-title>Financeiro · Contas</template>
        <PageHeader
            title="Contas financeiras"
            subtitle="Bancos, caixa interno e dinheiro. Use ao lançar receitas, despesas e pagamentos.">
            <template #actions>
                <button @click="abrirNova" class="btn-primary">+ Nova conta</button>
            </template>
        </PageHeader>

        <!-- Empty state -->
        <div v-if="contas.length === 0 && ! showForm"
             class="rounded-2xl bg-white ring-1 ring-slate-200 p-8 text-center">
            <div class="text-4xl mb-2">🏦</div>
            <h3 class="text-lg font-semibold text-slate-900 mb-1">Nenhuma conta cadastrada</h3>
            <p class="text-sm text-slate-600 mb-4">Cadastre pelo menos uma conta para lançar despesas e receitas.</p>
            <button @click="abrirNova" class="btn-primary">+ Cadastrar primeira conta</button>
        </div>

        <!-- DESKTOP table -->
        <div v-else-if="contas.length" class="hidden xl:block rounded-2xl bg-white ring-1 ring-slate-200 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Nome</th>
                        <th class="px-4 py-3 text-left font-medium">Tipo</th>
                        <th class="px-4 py-3 text-left font-medium">Banco</th>
                        <th class="px-4 py-3 text-right font-medium">Saldo atual</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in contas" :key="c.id" class="hover:bg-slate-50/60">
                        <td class="px-4 py-3 font-semibold text-slate-900">{{ c.nome }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ tipos[c.tipo] || c.tipo }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ c.banco || '—' }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ brl(c.saldo_atual) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1"
                                  :class="c.is_active
                                      ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                      : 'bg-slate-100 text-slate-500 ring-slate-200'">
                                {{ c.is_active ? 'Ativa' : 'Inativa' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <button @click="abrirEdicao(c)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200">
                                    Editar
                                </button>
                                <button @click="toggle(c)"
                                        :class="c.is_active
                                            ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 hover:bg-amber-100'
                                            : 'bg-emerald-600 text-white hover:bg-emerald-700'"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium">
                                    {{ c.is_active ? 'Desativar' : 'Reativar' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- MOBILE cards -->
        <div v-if="contas.length" class="xl:hidden space-y-2.5">
            <div v-for="c in contas" :key="c.id" class="rounded-xl bg-white ring-1 ring-slate-200 p-3.5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold">{{ tipos[c.tipo] || c.tipo }}</div>
                        <div class="font-semibold text-slate-900 truncate">{{ c.nome }}</div>
                        <div v-if="c.banco" class="text-xs text-slate-500">{{ c.banco }}</div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1 flex-shrink-0"
                          :class="c.is_active
                              ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                              : 'bg-slate-100 text-slate-500 ring-slate-200'">
                        {{ c.is_active ? 'Ativa' : 'Inativa' }}
                    </span>
                </div>
                <div class="mt-2 text-sm">
                    <span class="text-slate-500 text-xs">Saldo atual: </span>
                    <span class="font-mono font-bold text-slate-900">{{ brl(c.saldo_atual) }}</span>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <button @click="abrirEdicao(c)"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 text-slate-700 hover:bg-slate-200">
                        Editar
                    </button>
                    <button @click="toggle(c)"
                            :class="c.is_active
                                ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 hover:bg-amber-100'
                                : 'bg-emerald-600 text-white hover:bg-emerald-700'"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium">
                        {{ c.is_active ? 'Desativar' : 'Reativar' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Form modal -->
        <Teleport to="body">
            <div v-if="showForm" class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center p-0 sm:p-4">
                <div class="absolute inset-0 bg-slate-900/60" @click="showForm = false"></div>
                <div class="relative w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl ring-1 ring-slate-200 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <div class="px-5 pt-5 pb-3 border-b border-slate-100">
                        <h2 class="text-base font-semibold text-slate-900">{{ editing ? 'Editar conta' : 'Nova conta financeira' }}</h2>
                    </div>
                    <form @submit.prevent="salvar" class="px-5 py-4 space-y-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Nome *</label>
                            <input v-model="form.nome" type="text" required
                                   placeholder="Ex.: Banco do Brasil PJ"
                                   class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500 focus:outline-none text-sm">
                            <p v-if="form.errors.nome" class="text-xs text-red-700 mt-1">{{ form.errors.nome }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Tipo *</label>
                            <select v-model="form.tipo" required class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-500 focus:outline-none text-sm">
                                <option v-for="(label, value) in tipos" :key="value" :value="value">{{ label }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Banco</label>
                                <input v-model="form.banco" type="text" class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Saldo inicial (R$)</label>
                                <input v-model.number="form.saldo_inicial" type="number" step="0.01" :disabled="!! editing"
                                       class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:outline-none text-sm disabled:bg-slate-100">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Agência</label>
                                <input v-model="form.agencia" type="text" class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Conta</label>
                                <input v-model="form.conta" type="text" class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:outline-none text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Observação</label>
                            <textarea v-model="form.observacoes" rows="2" class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:outline-none text-sm resize-none"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="showForm = false"
                                    class="px-4 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-100">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing"
                                    class="px-4 py-2 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50">
                                {{ form.processing ? 'Salvando…' : (editing ? 'Salvar alterações' : 'Cadastrar conta') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
