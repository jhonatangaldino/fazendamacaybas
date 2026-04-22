<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { brl } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

defineProps({ totals: Object });
useAutoReload(['totals'], 30000);
</script>

<template>
    <Head title="Máquinas" />
    <AdminLayout>
        <template #page-title>Máquinas e veículos</template>

        <PageHeader title="Máquinas e veículos" subtitle="Gerencie a frota e as manutenções" />

        <div class="grid gap-4 sm:grid-cols-3 mb-6">
            <div class="card p-5"><div class="text-xs uppercase tracking-wider text-slate-500">Veículos ativos</div><div class="mt-1 text-2xl font-bold">{{ totals.veiculos }}</div></div>
            <div class="card p-5"><div class="text-xs uppercase tracking-wider text-slate-500">Manutenções em aberto</div><div class="mt-1 text-2xl font-bold">{{ totals.manutencoes_abertas }}</div></div>
            <div class="card p-5"><div class="text-xs uppercase tracking-wider text-slate-500">Custo no mês</div><div class="mt-1 text-2xl font-bold">{{ brl(totals.custo_mes) }}</div></div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <Link :href="route('admin.maquinas.veiculos.index')" class="card hover:ring-macaybas-primary transition">
                <div class="card-body">
                    <h3 class="font-semibold mb-1">Frota</h3>
                    <p class="text-sm text-slate-500">Tratores, caminhões, pick-ups, implementos e colheitadeiras.</p>
                </div>
            </Link>
            <Link :href="route('admin.maquinas.manutencoes.index')" class="card hover:ring-macaybas-primary transition">
                <div class="card-body">
                    <h3 class="font-semibold mb-1">Manutenções</h3>
                    <p class="text-sm text-slate-500">Preventivas, corretivas e revisões — com custo integrado ao financeiro.</p>
                </div>
            </Link>
        </div>
    </AdminLayout>
</template>
