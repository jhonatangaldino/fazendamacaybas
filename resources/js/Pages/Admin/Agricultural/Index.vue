<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useAutoReload } from '@/composables/useAutoReload.js';

defineProps({ totals: Object });
useAutoReload(['totals'], 30000);
</script>

<template>
    <Head title="Agrícola" />
    <AdminLayout>
        <template #page-title>Produção agrícola</template>
        <PageHeader title="Produção agrícola" subtitle="Talhões, culturas, plantios, colheitas e aplicações" />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div class="card p-5"><div class="text-xs uppercase tracking-wider text-slate-500">Talhões ativos</div><div class="mt-1 text-2xl font-bold">{{ totals.fields }}</div></div>
            <div class="card p-5"><div class="text-xs uppercase tracking-wider text-slate-500">Área total (ha)</div><div class="mt-1 text-2xl font-bold">{{ Number(totals.area_total).toLocaleString('pt-BR', {maximumFractionDigits: 2}) }}</div></div>
            <div class="card p-5"><div class="text-xs uppercase tracking-wider text-slate-500">Plantios em andamento</div><div class="mt-1 text-2xl font-bold">{{ totals.plantings_ativos }}</div></div>
            <div class="card p-5"><div class="text-xs uppercase tracking-wider text-slate-500">Safras ativas</div><div class="mt-1 text-2xl font-bold">{{ totals.seasons }}</div></div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link :href="route('admin.agricola.talhoes.index')" class="card hover:ring-macaybas-primary transition">
                <div class="card-body">
                    <h3 class="font-semibold mb-1">Talhões / áreas</h3>
                    <p class="text-sm text-slate-500">Cadastre áreas da fazenda, com metragem e localização.</p>
                </div>
            </Link>
            <Link :href="route('admin.agricola.culturas.index')" class="card hover:ring-macaybas-primary transition">
                <div class="card-body">
                    <h3 class="font-semibold mb-1">Culturas e safras</h3>
                    <p class="text-sm text-slate-500">Café, milho, feijão... e os anos de safra.</p>
                </div>
            </Link>
            <Link :href="route('admin.agricola.plantios.index')" class="card hover:ring-macaybas-primary transition">
                <div class="card-body">
                    <h3 class="font-semibold mb-1">Plantios</h3>
                    <p class="text-sm text-slate-500">Registro por talhão, cultura e safra.</p>
                </div>
            </Link>
            <Link :href="route('admin.agricola.colheitas.index')" class="card hover:ring-macaybas-primary transition">
                <div class="card-body">
                    <h3 class="font-semibold mb-1">Colheitas</h3>
                    <p class="text-sm text-slate-500">Quantidade colhida com cálculo automático de produtividade.</p>
                </div>
            </Link>
            <Link :href="route('admin.agricola.aplicacoes.index')" class="card hover:ring-macaybas-primary transition">
                <div class="card-body">
                    <h3 class="font-semibold mb-1">Aplicações de insumos</h3>
                    <p class="text-sm text-slate-500">Adubação, herbicida, fungicida, irrigação.</p>
                </div>
            </Link>
        </div>
    </AdminLayout>
</template>
