<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';

defineProps({
    plans: { type: Array, default: () => [] },
});

function brl(v) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
}

function toggle(plan) {
    const verbo = plan.is_active ? 'desativar' : 'reativar';
    if (! confirm(`Confirma ${verbo} "${plan.nome}"?`)) return;

    router.post(route('master.planos.toggle', plan.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Planos · Plataforma" />
    <MasterLayout>
        <template #page-title>Planos</template>

        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-serif font-bold text-slate-900">Catálogo de planos</h2>
                <p class="mt-1 text-sm text-slate-600">Defina os pacotes que serão oferecidos aos clientes.</p>
            </div>
            <Link
                :href="route('master.planos.create')"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-macaybas-primary-700 text-white text-sm font-semibold hover:bg-macaybas-primary-800 shadow-sm"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Novo plano
            </Link>
        </div>

        <div v-if="plans.length === 0" class="rounded-2xl bg-white ring-1 ring-slate-200 p-10 text-center">
            <h3 class="text-sm font-semibold text-slate-900">Nenhum plano cadastrado</h3>
            <p class="mt-1 text-sm text-slate-500">Crie o primeiro plano para começar a vender a plataforma.</p>
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="p in plans"
                :key="p.id"
                class="rounded-2xl bg-white ring-1 ring-slate-200 p-5 flex flex-col"
                :class="{ 'opacity-60': !p.is_active }"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3 class="text-lg font-serif font-bold text-slate-900 truncate">{{ p.nome }}</h3>
                        <code class="text-xs text-slate-500">{{ p.slug }}</code>
                    </div>
                    <span
                        class="flex-shrink-0 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1"
                        :class="p.is_active
                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                            : 'bg-slate-100 text-slate-600 ring-slate-200'"
                    >
                        <span class="h-1.5 w-1.5 rounded-full" :class="p.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                        {{ p.is_active ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>

                <div class="mt-3">
                    <span class="text-2xl font-serif font-bold text-slate-900">{{ brl(p.preco_mensal) }}</span>
                    <span class="text-sm text-slate-500">/mês</span>
                </div>

                <div class="mt-3 text-xs text-slate-600 space-y-1">
                    <div v-if="p.max_farms" class="flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3"/></svg>
                        Até {{ p.max_farms }} fazendas
                    </div>
                    <div v-if="p.max_users" class="flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857"/></svg>
                        Até {{ p.max_users }} usuários
                    </div>
                </div>

                <ul v-if="p.features?.length" class="mt-3 space-y-1 flex-1">
                    <li v-for="f in p.features" :key="f" class="text-xs text-slate-700 flex items-baseline gap-2">
                        <span class="text-emerald-500">✓</span>
                        <span>{{ f }}</span>
                    </li>
                </ul>

                <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-end gap-1">
                    <button
                        @click="toggle(p)"
                        :title="p.is_active ? 'Desativar' : 'Reativar'"
                        class="p-2 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900"
                    >
                        <svg v-if="p.is_active" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        <svg v-else class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                    <Link
                        :href="route('master.planos.edit', p.id)"
                        title="Editar"
                        class="p-2 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </Link>
                </div>
            </div>
        </div>
    </MasterLayout>
</template>
