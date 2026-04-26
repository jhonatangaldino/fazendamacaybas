<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { brl } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ totals: Object });
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

        <!-- B4.4 fix · empty state com CTA primário quando frota vazia -->
        <div v-if="totals.veiculos === 0" class="card mb-6">
            <div class="card-body text-center py-10">
                <div class="mx-auto h-14 w-14 rounded-full bg-macaybas-primary-50 ring-1 ring-macaybas-primary-200 flex items-center justify-center mb-4">
                    <svg class="h-7 w-7 text-macaybas-primary-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-slate-900">Nenhum veículo cadastrado</h3>
                <p class="mt-1 text-sm text-slate-500 mb-4">Cadastre o primeiro trator, caminhão ou implemento para começar a gerenciar manutenções.</p>
                <Link :href="route('admin.maquinas.veiculos.index')" class="btn-primary">
                    + Cadastrar primeiro veículo
                </Link>
            </div>
        </div>

        <!-- B4.4 fix · cards com ícones contextuais (frota = caminhão; manutenções = chave inglesa) -->
        <div class="grid gap-4 md:grid-cols-2">
            <Link :href="route('admin.maquinas.veiculos.index')"
                  class="card hover:ring-macaybas-primary hover:shadow-md transition group">
                <div class="card-body flex items-start gap-4">
                    <div class="flex-shrink-0 h-12 w-12 rounded-xl bg-blue-50 ring-1 ring-blue-200 flex items-center justify-center text-2xl group-hover:scale-110 transition">
                        🚜
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold mb-1 flex items-center gap-2">
                            Frota
                            <svg class="h-4 w-4 text-slate-400 group-hover:text-macaybas-primary transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </h3>
                        <p class="text-sm text-slate-500">Tratores, caminhões, pick-ups, implementos e colheitadeiras.</p>
                    </div>
                </div>
            </Link>
            <Link :href="route('admin.maquinas.manutencoes.index')"
                  class="card hover:ring-macaybas-primary hover:shadow-md transition group">
                <div class="card-body flex items-start gap-4">
                    <div class="flex-shrink-0 h-12 w-12 rounded-xl bg-amber-50 ring-1 ring-amber-200 flex items-center justify-center text-2xl group-hover:scale-110 transition">
                        🔧
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold mb-1 flex items-center gap-2">
                            Manutenções
                            <svg class="h-4 w-4 text-slate-400 group-hover:text-macaybas-primary transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </h3>
                        <p class="text-sm text-slate-500">Preventivas, corretivas e revisões — com custo integrado ao financeiro.</p>
                    </div>
                </div>
            </Link>
        </div>
    </AdminLayout>
</template>
