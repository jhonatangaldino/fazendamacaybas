<script setup>
/**
 * Hub do módulo Rebanho — visão geral por espécie.
 *
 * Antes esta rota redirecionava direto pra lista geral de animais (mistura
 * todas as espécies, sem contexto). Agora mostra cards das espécies ativas
 * do tenant + atalhos pras outras telas (Lotes, Locais, Controle Leiteiro).
 *
 * Cada card leva pro DASHBOARD da espécie (`/admin/rebanho/especies/{slug}`),
 * que tem KPIs + ações rápidas + lista filtrada — fluxo coeso.
 */
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { emojiEspecie } from '@/utils/emojiEspecie.js';

const props = defineProps({
    totalAnimais: { type: Number, default: 0 },
    totalLotes: { type: Number, default: 0 },
    totalLocais: { type: Number, default: 0 },
    temManejoLeiteiro: { type: Boolean, default: false },
});

// tenantSpecies vem via HandleInertiaRequests (com animals_count por espécie)
const page = usePage();
const especies = computed(() => page.props.tenantSpecies || []);

// Atalhos secundários (não são espécies)
const atalhos = computed(() => {
    const base = [
        { titulo: 'Lotes', subtitulo: 'Agrupamento por finalidade', href: route('admin.rebanho.lotes.index'), emoji: '🏷', count: props.totalLotes },
        { titulo: 'Locais', subtitulo: 'Pastos, baias, currais', href: route('admin.rebanho.locais.index'), emoji: '📍', count: props.totalLocais },
    ];
    if (props.temManejoLeiteiro) {
        base.push({
            titulo: 'Controle leiteiro',
            subtitulo: 'Produção diária DROVET',
            href: route('admin.rebanho.controle-leiteiro.dashboard'),
            emoji: '🥛',
            count: null,
        });
    }
    return base;
});
</script>

<template>
    <Head title="Rebanho" />
    <AdminLayout>
        <template #page-title>Rebanho</template>

        <PageHeader title="Rebanho" subtitle="Selecione uma espécie pra ver KPIs, ações e animais.">
        </PageHeader>

        <!-- KPI total -->
        <div v-if="totalAnimais > 0"
             class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-3 inline-flex items-center gap-3">
            <span class="text-2xl">📊</span>
            <div>
                <div class="text-xs text-emerald-700 uppercase tracking-wider">Total ativo</div>
                <div class="text-2xl font-semibold text-emerald-900">
                    {{ totalAnimais.toLocaleString('pt-BR') }}
                    <span class="text-sm font-normal text-emerald-700">{{ totalAnimais === 1 ? 'animal' : 'animais' }}</span>
                </div>
            </div>
        </div>

        <!-- Cards de espécies -->
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">Por espécie</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-8">
            <Link v-for="esp in especies" :key="esp.id"
                  :href="route('admin.rebanho.especies.dashboard', esp.slug)"
                  class="rounded-xl border border-slate-200 bg-white p-4 hover:border-macaybas-primary hover:shadow-md transition flex flex-col items-center text-center gap-1.5">
                <span class="text-3xl" aria-hidden="true">{{ emojiEspecie(esp.nome) }}</span>
                <div class="font-semibold text-slate-900 text-sm">{{ esp.nome }}</div>
                <div class="text-xs"
                     :class="esp.animals_count > 0 ? 'text-macaybas-primary font-semibold' : 'text-slate-400'">
                    {{ esp.animals_count > 0 ? `${esp.animals_count} ${esp.gestao === 'lote' ? 'em lotes' : (esp.animals_count === 1 ? 'animal' : 'animais')}` : 'Nenhum cadastrado' }}
                </div>
            </Link>
        </div>

        <!-- Atalhos -->
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">Outras telas</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <Link v-for="a in atalhos" :key="a.titulo"
                  :href="a.href"
                  class="rounded-xl border border-slate-200 bg-white p-4 hover:border-macaybas-primary hover:shadow-md transition flex items-center gap-3">
                <span class="text-3xl" aria-hidden="true">{{ a.emoji }}</span>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-slate-900">{{ a.titulo }}</div>
                    <div class="text-xs text-slate-500">{{ a.subtitulo }}</div>
                </div>
                <span v-if="a.count !== null && a.count > 0"
                      class="text-sm font-semibold text-macaybas-primary">{{ a.count }}</span>
            </Link>
        </div>
    </AdminLayout>
</template>
