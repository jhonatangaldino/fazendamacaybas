<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import KpiDrawer from '@/Components/KpiDrawer.vue';
import { dataBR } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

defineProps({ totals: Object, drillFields: Array, drillPlantings: Array, drillSeasons: Array });
useAutoReload(['totals', 'drillFields', 'drillPlantings', 'drillSeasons'], 30000);

// Drawers de detalhamento — abrem ao clicar no respectivo KPI
const drawer = ref(null); // 'fields' | 'area' | 'plantings' | 'seasons'
function abrir(nome) { drawer.value = nome; }
function fechar() { drawer.value = null; }
</script>

<template>
    <Head title="Agrícola" />
    <AdminLayout>
        <template #page-title>Produção agrícola</template>
        <PageHeader title="Produção agrícola" subtitle="Talhões, culturas, plantios, colheitas e aplicações" />

        <!-- KPIs: cada card abre um drawer lateral com o detalhamento (sem sair da página) -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <button @click="abrir('fields')"
                    class="card p-5 text-left hover:shadow-md hover:ring-macaybas-primary-200 transition-all group">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Talhões ativos</div>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-macaybas-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H3m12 0l-4-4m4 4l-4 4m10-8v8"/></svg>
                </div>
                <div class="mt-1 text-2xl font-bold">{{ totals.fields }}</div>
                <div class="text-xs text-slate-400 mt-1">Clique para ver quais</div>
            </button>
            <button @click="abrir('area')"
                    class="card p-5 text-left hover:shadow-md hover:ring-macaybas-primary-200 transition-all group">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Área total (ha)</div>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-macaybas-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H3m12 0l-4-4m4 4l-4 4m10-8v8"/></svg>
                </div>
                <div class="mt-1 text-2xl font-bold">{{ Number(totals.area_total).toLocaleString('pt-BR', {maximumFractionDigits: 2}) }}</div>
                <div class="text-xs text-slate-400 mt-1">Distribuição por talhão</div>
            </button>
            <button @click="abrir('plantings')"
                    class="card p-5 text-left hover:shadow-md hover:ring-emerald-200 transition-all group">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Plantios em andamento</div>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H3m12 0l-4-4m4 4l-4 4m10-8v8"/></svg>
                </div>
                <div class="mt-1 text-2xl font-bold text-emerald-700">{{ totals.plantings_ativos }}</div>
                <div class="text-xs text-slate-400 mt-1">Clique para ver</div>
            </button>
            <button @click="abrir('seasons')"
                    class="card p-5 text-left hover:shadow-md hover:ring-amber-200 transition-all group">
                <div class="flex items-start justify-between">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Safras ativas</div>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-amber-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H3m12 0l-4-4m4 4l-4 4m10-8v8"/></svg>
                </div>
                <div class="mt-1 text-2xl font-bold text-amber-700">{{ totals.seasons }}</div>
                <div class="text-xs text-slate-400 mt-1">Clique para ver</div>
            </button>
        </div>

        <!-- Atalhos para os submódulos -->
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

        <!-- Drawer: Talhões ativos -->
        <KpiDrawer :open="drawer === 'fields'" title="Talhões ativos"
                   :subtitle="`${totals.fields} talhão${totals.fields === 1 ? '' : 'ões'} cadastrado${totals.fields === 1 ? '' : 's'}`"
                   :full-link="{ href: route('admin.agricola.talhoes.index'), label: 'Ver tudo em Talhões' }"
                   @close="fechar">
            <div v-if="!drillFields?.length" class="text-center text-slate-500 py-10">
                Nenhum talhão ativo ainda.
            </div>
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="f in drillFields" :key="f.id" class="py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-medium text-slate-900 truncate">{{ f.nome }}</div>
                        <div class="text-xs text-slate-500">{{ f.codigo }}</div>
                    </div>
                    <div class="text-sm font-mono text-slate-700 flex-shrink-0">
                        {{ Number(f.area_ha).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) }} ha
                    </div>
                </li>
            </ul>
        </KpiDrawer>

        <!-- Drawer: Área total -->
        <KpiDrawer :open="drawer === 'area'" title="Área total"
                   :subtitle="`${Number(totals.area_total).toLocaleString('pt-BR', {maximumFractionDigits: 2})} hectares distribuídos`"
                   :full-link="{ href: route('admin.agricola.talhoes.index'), label: 'Gerenciar talhões' }"
                   @close="fechar">
            <ul class="divide-y divide-slate-100">
                <li v-for="f in drillFields" :key="f.id" class="py-3 flex items-center justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-slate-900 truncate">{{ f.nome }}</div>
                        <!-- Barra de proporção visual -->
                        <div class="mt-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-macaybas-primary-500"
                                 :style="{ width: ((f.area_ha / totals.area_total) * 100) + '%' }"></div>
                        </div>
                    </div>
                    <div class="text-sm font-mono text-slate-700 flex-shrink-0">
                        {{ Number(f.area_ha).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) }} ha
                    </div>
                </li>
            </ul>
        </KpiDrawer>

        <!-- Drawer: Plantios em andamento -->
        <KpiDrawer :open="drawer === 'plantings'" title="Plantios em andamento"
                   :subtitle="`${totals.plantings_ativos} plantio${totals.plantings_ativos === 1 ? '' : 's'} em curso`"
                   :full-link="{ href: route('admin.agricola.plantios.index'), label: 'Ver tudo em Plantios' }"
                   @close="fechar">
            <div v-if="!drillPlantings?.length" class="text-center text-slate-500 py-10">
                Nenhum plantio em andamento no momento.
            </div>
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="p in drillPlantings" :key="p.id" class="py-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-slate-900 truncate">{{ p.crop_nome }}</div>
                            <div class="text-xs text-slate-500">{{ p.field_nome }}</div>
                        </div>
                        <span class="badge-green flex-shrink-0">Em andamento</span>
                    </div>
                    <div class="mt-1.5 flex gap-4 text-xs text-slate-500">
                        <span>Plantado em {{ dataBR(p.data_plantio) }}</span>
                        <span v-if="p.area_plantada">{{ Number(p.area_plantada).toLocaleString('pt-BR', {maximumFractionDigits: 2}) }} ha</span>
                    </div>
                </li>
            </ul>
        </KpiDrawer>

        <!-- Drawer: Safras ativas -->
        <KpiDrawer :open="drawer === 'seasons'" title="Safras ativas"
                   :subtitle="`${totals.seasons} safra${totals.seasons === 1 ? '' : 's'} aberta${totals.seasons === 1 ? '' : 's'}`"
                   :full-link="{ href: route('admin.agricola.culturas.index'), label: 'Gerenciar safras' }"
                   @close="fechar">
            <div v-if="!drillSeasons?.length" class="text-center text-slate-500 py-10">
                Nenhuma safra ativa.
            </div>
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="s in drillSeasons" :key="s.id" class="py-3">
                    <div class="font-medium text-slate-900">{{ s.nome }}</div>
                    <div class="mt-1 text-xs text-slate-500">
                        De {{ dataBR(s.data_inicio) }}
                        <span v-if="s.data_fim"> até {{ dataBR(s.data_fim) }}</span>
                        <span v-else class="text-emerald-600"> — ainda aberta</span>
                    </div>
                </li>
            </ul>
        </KpiDrawer>
    </AdminLayout>
</template>
