<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineProps({ totals: Object });
</script>

<template>
    <Head title="Estoque" />
    <AdminLayout>
        <template #page-title>Estoque</template>

        <PageHeader title="Estoque e almoxarifado" subtitle="Gerencie itens, armazéns e movimentações" />

        <div class="grid gap-4 md:grid-cols-3">
            <Link :href="route('admin.estoque.itens.index')" class="card hover:ring-macaybas-primary transition">
                <div class="card-body">
                    <div class="h-12 w-12 rounded-xl bg-macaybas-primary-100 text-macaybas-primary-800 flex items-center justify-center mb-3">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900">Itens</h3>
                    <p class="text-sm text-slate-500">Insumos, medicamentos, ração, ferramentas, peças e combustíveis.</p>
                </div>
            </Link>

            <Link :href="route('admin.estoque.movimentos.index')" class="card hover:ring-macaybas-primary transition">
                <div class="card-body">
                    <div class="h-12 w-12 rounded-xl bg-macaybas-secondary-100 text-macaybas-secondary-800 flex items-center justify-center mb-3">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900">Movimentações</h3>
                    <p class="text-sm text-slate-500">Entradas, saídas, ajustes e transferências.</p>
                </div>
            </Link>

            <Link :href="route('admin.estoque.armazens.index')" class="card hover:ring-macaybas-primary transition">
                <div class="card-body">
                    <div class="h-12 w-12 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center mb-3">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900">Armazéns</h3>
                    <p class="text-sm text-slate-500">Locais físicos de estoque e responsáveis.</p>
                </div>
            </Link>
        </div>
    </AdminLayout>
</template>
