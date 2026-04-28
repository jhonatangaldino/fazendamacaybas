<script setup>
/**
 * Dashboard inicial por espécie (Bovino/Ave/Suíno/etc.) — abre quando o
 * master clica num submenu de "Rebanho > [Espécie]".
 *
 * Composição:
 *   • Header contextual com emoji + nome + count
 *   • KPIs adaptados ao gestao (individual/lote) e profile (corte/leite/postura)
 *   • Grid de "ações rápidas" geradas de allowed_events da espécie
 *     (peixe não vacina, ave não pesa individual, etc.)
 *   • Atalhos: "Cadastrar [espécie]", "Ver todos da espécie", filtros por lote
 *
 * Esta é a Fase B do redesenho de Rebanho:
 *   - Fase A (commit 31d731c): menu colapsável + submenus dinâmicos
 *   - Fase B (este commit): dashboards por espécie como tela inicial
 *   - Fase C (futuro): forms adaptativos (campos relevantes só pra espécie)
 */
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { emojiEspecie } from '@/utils/emojiEspecie.js';
import { allowedEventsFor, EVENT_CATALOG } from '@/utils/animalProfile.js';

const props = defineProps({
    species: { type: Object, required: true },
    kpis: { type: Object, required: true },
    lots: { type: Array, default: () => [] },
});

const emoji = computed(() => emojiEspecie(props.species.nome));
const isLote = computed(() => props.species.gestao === 'lote');

// Eventos permitidos pra ações rápidas no dashboard. Mostra todos exceto
// 'observacao' e 'mortalidade' (não são "criar fluxo", são complementares).
// Eventos sem entrada no EVENT_CATALOG (catálogo desatualizado vs banco) são
// pulados pra evitar cards vazios — corrigir adicionando ao catalog quando
// for o caso (ex.: exame_toque, controle_leiteiro têm wizards próprios).
const acoesRapidas = computed(() => {
    const eventos = allowedEventsFor(props.species, null);
    return eventos
        .filter(e => !['observacao', 'mortalidade'].includes(e))
        .filter(e => EVENT_CATALOG[e] && EVENT_CATALOG[e].label) // só os com label conhecido
        .map(tipo => ({
            tipo,
            ...EVENT_CATALOG[tipo],
            href: routeForEvento(tipo),
        }))
        .filter(a => a.href); // só mostra se conseguimos rotear
});

function routeForEvento(tipo) {
    // Mapeamento evento → wizard. A maioria vai pro wizard polimórfico
    // EventoRebanho com ?tipo=. Pesagem e Ordenha têm wizards próprios.
    const safeRoute = (name, params) => {
        try { return route(name, params); } catch { return null; }
    };
    const wizardsEspecificos = {
        pesagem:            () => safeRoute('admin.fluxos.pesar-animal'),
        ordenha:            () => safeRoute('admin.fluxos.controle-leiteiro'),
        postura_diaria:     () => null,
        biometria_amostral: () => null,
        qualidade_agua:     () => null,
        alimentacao:        () => null,
    };
    if (wizardsEspecificos[tipo]) {
        return wizardsEspecificos[tipo]();
    }
    const baseEvento = safeRoute('admin.fluxos.evento-rebanho');
    return baseEvento ? `${baseEvento}?tipo=${tipo}` : null;
}

const kpisVisiveis = computed(() => {
    const k = props.kpis;
    const cards = [
        { label: 'Ativos', valor: k.total_ativos, icon: '🐾', color: 'emerald' },
    ];
    if (k.total_ativos > 0) {
        if (k.sexo_m + k.sexo_f > 0) {
            cards.push({ label: 'Machos / Fêmeas', valor: `${k.sexo_m} / ${k.sexo_f}`, icon: '⚥', color: 'sky' });
        }
        if (k.peso_medio !== null) {
            cards.push({ label: 'Peso médio', valor: formatPeso(k.peso_medio), icon: '⚖️', color: 'amber' });
        }
        if (k.lots_count > 0) {
            cards.push({ label: 'Lotes ativos', valor: k.lots_count, icon: '🏷', color: 'slate' });
        }
        cards.push({ label: 'Eventos 7d', valor: k.eventos_7d, icon: '📋', color: 'indigo' });
    }
    if (k.vendidos_mes > 0) {
        cards.push({ label: 'Vendidos no mês', valor: k.vendidos_mes, icon: '💰', color: 'emerald' });
    }
    if (k.baixas_mes > 0) {
        cards.push({ label: 'Baixas no mês', valor: k.baixas_mes, icon: '⚰️', color: 'rose' });
    }
    return cards;
});

function formatPeso(v) {
    return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(v) + ' kg';
}

const COLOR_CLASSES = {
    emerald: 'bg-emerald-50 ring-emerald-200 text-emerald-700',
    sky: 'bg-sky-50 ring-sky-200 text-sky-700',
    amber: 'bg-amber-50 ring-amber-200 text-amber-700',
    slate: 'bg-slate-50 ring-slate-200 text-slate-700',
    indigo: 'bg-indigo-50 ring-indigo-200 text-indigo-700',
    rose: 'bg-rose-50 ring-rose-200 text-rose-700',
};
</script>

<template>
    <Head :title="`Rebanho · ${species.nome}`" />
    <AdminLayout>
        <template #page-title>{{ species.nome }}</template>

        <PageHeader :title="`${emoji} ${species.nome}`"
                    :subtitle="kpis.total_ativos > 0
                        ? `${kpis.total_ativos} ${species.nome.toLowerCase()}(s) ativo(s) — visão geral, ações rápidas e cadastro`
                        : `Nenhum ${species.nome.toLowerCase()} cadastrado ainda — comece pelo botão de cadastro abaixo`">
            <template #actions>
                <Link :href="route('admin.rebanho.animais.index', { species_id: species.id })" class="btn-outline">
                    📋 Ver todos
                </Link>
                <Link :href="route('admin.rebanho.animais.create', { species_id: species.id })" class="btn-primary">
                    + Cadastrar {{ species.nome.toLowerCase() }}
                </Link>
            </template>
        </PageHeader>

        <!-- KPIs adaptados -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-6">
            <div v-for="card in kpisVisiveis" :key="card.label"
                 class="rounded-xl ring-1 p-4"
                 :class="COLOR_CLASSES[card.color] ?? COLOR_CLASSES.slate">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] uppercase tracking-widest font-semibold opacity-80">{{ card.label }}</span>
                    <span class="text-base">{{ card.icon }}</span>
                </div>
                <div class="mt-1 text-2xl font-serif font-bold">{{ card.valor }}</div>
            </div>
        </div>

        <!-- Ações rápidas contextualizadas -->
        <div v-if="acoesRapidas.length > 0" class="rounded-2xl bg-white ring-1 ring-slate-200 p-5 mb-6">
            <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">
                Ações rápidas {{ isLote ? '(em lote)' : '(individuais)' }}
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                <Link v-for="acao in acoesRapidas" :key="acao.tipo"
                      :href="acao.href"
                      class="group flex items-center gap-3 p-3 rounded-xl ring-1 ring-slate-200 bg-white hover:ring-macaybas-primary-400 hover:bg-macaybas-primary-50 hover:shadow-sm transition">
                    <span class="text-2xl flex-shrink-0">{{ acao.icon }}</span>
                    <span class="text-sm font-medium text-slate-800 group-hover:text-macaybas-primary-800">{{ acao.label }}</span>
                </Link>
            </div>
            <p class="text-xs text-slate-500 mt-3">
                Ações específicas dessa espécie. Eventos como observação ou mortalidade aparecem dentro dos animais individualmente.
            </p>
        </div>

        <!-- Atalho por lotes (se houver) -->
        <div v-if="lots.length > 0" class="rounded-2xl bg-white ring-1 ring-slate-200 p-5 mb-6">
            <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">
                Lotes ({{ lots.length }})
            </h3>
            <div class="flex flex-wrap gap-2">
                <Link v-for="lot in lots" :key="lot.id"
                      :href="route('admin.rebanho.animais.index', { species_id: species.id, lot_id: lot.id })"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 ring-1 ring-slate-200 hover:bg-macaybas-primary-50 hover:text-macaybas-primary-800 hover:ring-macaybas-primary-200 transition">
                    🏷 {{ lot.nome }}
                </Link>
            </div>
        </div>

        <!-- Empty state — quando ainda não tem nenhum animal cadastrado -->
        <div v-if="kpis.total_ativos === 0" class="rounded-2xl bg-macaybas-primary-50 ring-1 ring-macaybas-primary-200 p-8 text-center">
            <div class="text-5xl mb-3">{{ emoji }}</div>
            <h3 class="text-lg font-semibold text-macaybas-primary-900 mb-1">Comece a usar {{ species.nome }}</h3>
            <p class="text-sm text-macaybas-primary-800 mb-4">
                Cadastre o primeiro {{ species.nome.toLowerCase() }} e comece a registrar pesagens, vacinas, eventos e venda.
            </p>
            <Link :href="route('admin.rebanho.animais.create', { species_id: species.id })"
                  class="btn-primary inline-flex items-center gap-2">
                + Cadastrar primeiro {{ species.nome.toLowerCase() }}
            </Link>
        </div>
    </AdminLayout>
</template>
