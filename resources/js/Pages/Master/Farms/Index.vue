<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';

defineProps({
    tenant: { type: Object, required: true },
    farms: { type: Array, default: () => [] },
});

function toggle(tenant, farm) {
    const verbo = farm.is_active ? 'desativar' : 'reativar';
    if (! confirm(`Confirma ${verbo} "${farm.nome}"?`)) return;

    router.post(route('master.tenants.farms.toggle', [tenant.id, farm.id]), {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Fazendas · ${tenant.nome}`" />
    <MasterLayout>
        <template #page-title>Fazendas · {{ tenant.nome }}</template>

        <!-- Breadcrumb minimalista -->
        <nav class="text-sm text-slate-500 mb-4 flex items-center gap-1.5">
            <Link :href="route('master.tenants.index')" class="hover:text-slate-900">Clientes</Link>
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-900 font-medium">{{ tenant.nome }}</span>
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-900">Fazendas</span>
        </nav>

        <!-- Header -->
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-serif font-bold text-slate-900">
                    Fazendas de {{ tenant.nome }}
                </h2>
                <p class="mt-1 text-sm text-slate-600">
                    Unidades operacionais vinculadas a este cliente.
                    <span v-if="! tenant.is_active" class="ml-2 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 ring-1 ring-amber-200 text-xs">
                        Cliente inativo
                    </span>
                </p>
            </div>
            <Link
                :href="route('master.tenants.farms.create', tenant.id)"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nova fazenda
            </Link>
        </div>

        <!-- Empty state -->
        <div v-if="farms.length === 0" class="rounded-2xl bg-white ring-1 ring-slate-200 p-10 text-center">
            <div class="h-12 w-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
            </div>
            <h3 class="mt-3 text-sm font-semibold text-slate-900">Nenhuma fazenda cadastrada</h3>
            <p class="mt-1 text-sm text-slate-500">Este cliente ainda não tem fazendas. Clique em <strong>Nova fazenda</strong> para criar a primeira.</p>
        </div>

        <!-- Tabela -->
        <div v-else class="rounded-2xl bg-white ring-1 ring-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Nome</th>
                            <th class="px-4 py-3 text-left font-medium hidden sm:table-cell">Localização</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-left font-medium hidden md:table-cell">Criada em</th>
                            <th class="px-4 py-3 text-right font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="f in farms" :key="f.id" class="hover:bg-slate-50/60">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ f.nome }}</div>
                                <div class="text-xs text-slate-500 sm:hidden">
                                    {{ [f.cidade, f.estado].filter(Boolean).join(' — ') || '—' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600 hidden sm:table-cell">
                                {{ [f.cidade, f.estado].filter(Boolean).join(' — ') || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1"
                                    :class="f.is_active
                                        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                        : 'bg-slate-100 text-slate-600 ring-slate-200'"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="f.is_active ? 'bg-emerald-500' : 'bg-slate-400'"
                                    ></span>
                                    {{ f.is_active ? 'Ativa' : 'Inativa' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 hidden md:table-cell">{{ f.created_at }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button
                                        @click="toggle(tenant, f)"
                                        :title="f.is_active ? 'Desativar' : 'Reativar'"
                                        class="p-2 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900"
                                    >
                                        <svg v-if="f.is_active" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        <svg v-else class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </button>
                                    <Link
                                        :href="route('master.tenants.farms.edit', [tenant.id, f.id])"
                                        title="Editar"
                                        class="p-2 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MasterLayout>
</template>
