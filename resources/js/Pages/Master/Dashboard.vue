<script setup>
import { Head } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
</script>

<template>
    <Head title="Dashboard · Plataforma" />
    <MasterLayout>
        <template #page-title>Dashboard</template>

        <!-- Conteúdo mínimo — M2 entrega apenas estrutura visual.
             Widgets reais (MRR, tenants ativos, inadimplência) chegam em M3+. -->
        <div class="max-w-4xl mx-auto">
            <div class="mb-8">
                <h2 class="text-2xl font-serif font-bold text-slate-900">Painel da Plataforma</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Visão geral da operação SaaS. Os indicadores e ações serão incorporados incrementalmente.
                </p>
            </div>

            <!-- Card informativo do estado atual -->
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 shadow-sm p-6">
                <div class="flex items-start gap-3">
                    <div class="h-10 w-10 rounded-lg bg-amber-500/10 text-amber-700 flex items-center justify-center ring-1 ring-amber-500/30 flex-shrink-0">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-slate-900">Estrutura base entregue (M2)</h3>
                        <p class="mt-1 text-sm text-slate-600 leading-relaxed">
                            A sidebar e o layout estão funcionais. Nenhuma funcionalidade operacional foi
                            ativada ainda — os itens marcados como "Em breve" serão implementados em fases
                            subsequentes, cada uma validada isoladamente.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Roadmap das próximas fases -->
            <div class="mt-6 grid sm:grid-cols-2 gap-3">
                <div class="rounded-xl bg-white ring-1 ring-slate-200 p-4">
                    <div class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Próximas fases</div>
                    <ul class="mt-3 space-y-2 text-sm text-slate-700">
                        <li class="flex items-baseline gap-2"><span class="text-[10px] font-mono text-slate-400 w-8">M3</span> CRUD de clientes</li>
                        <li class="flex items-baseline gap-2"><span class="text-[10px] font-mono text-slate-400 w-8">M4</span> Fazendas e 1º usuário por cliente</li>
                        <li class="flex items-baseline gap-2"><span class="text-[10px] font-mono text-slate-400 w-8">M5</span> Impersonação controlada</li>
                        <li class="flex items-baseline gap-2"><span class="text-[10px] font-mono text-slate-400 w-8">M6</span> Planos, assinaturas, cobranças</li>
                    </ul>
                </div>
                <div class="rounded-xl bg-white ring-1 ring-slate-200 p-4">
                    <div class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Consolidação</div>
                    <ul class="mt-3 space-y-2 text-sm text-slate-700">
                        <li class="flex items-baseline gap-2"><span class="text-[10px] font-mono text-slate-400 w-8">M7</span> Migração do CMS institucional</li>
                        <li class="flex items-baseline gap-2"><span class="text-[10px] font-mono text-slate-400 w-8">M8</span> Separação de permissions platform × operacional</li>
                    </ul>
                </div>
            </div>
        </div>
    </MasterLayout>
</template>
