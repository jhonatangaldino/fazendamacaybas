<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';

const props = defineProps({
    atividades: Object,
    filtros: Object,
    tenants: Array,
    eventos: Array,
    subject_types: Array,
    metricas: Object,
});

// Estado local dos filtros (debounce simples)
const filtros = ref({
    tenant_id: props.filtros.tenant_id ?? '',
    causer_id: props.filtros.causer_id ?? '',
    event: props.filtros.event ?? '',
    subject_type: props.filtros.subject_type ?? '',
    data_inicio: props.filtros.data_inicio ?? '',
    data_fim: props.filtros.data_fim ?? '',
});

let timer = null;
function aplicarFiltros() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const params = {};
        for (const [k, v] of Object.entries(filtros.value)) {
            if (v !== '' && v !== null && v !== undefined) params[k] = v;
        }
        router.get(route('master.atividades.index'), params, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
}

watch(filtros, aplicarFiltros, { deep: true });

function limparFiltros() {
    filtros.value = { tenant_id: '', causer_id: '', event: '', subject_type: '', data_inicio: '', data_fim: '' };
}

const eventLabel = {
    created: { label: 'Criado', color: 'bg-emerald-100 text-emerald-800' },
    updated: { label: 'Atualizado', color: 'bg-amber-100 text-amber-800' },
    deleted: { label: 'Excluído', color: 'bg-red-100 text-red-800' },
    restored: { label: 'Restaurado', color: 'bg-blue-100 text-blue-800' },
};

function eventBadge(ev) {
    return eventLabel[ev] || { label: ev || '—', color: 'bg-slate-100 text-slate-700' };
}
</script>

<template>
    <Head title="Auditoria · Plataforma" />
    <MasterLayout>
        <template #page-title>Auditoria</template>

        <!-- Cabeçalho + métricas -->
        <div class="mb-6">
            <h2 class="text-xl font-serif font-bold text-slate-900">Auditoria da plataforma</h2>
            <p class="mt-1 text-sm text-slate-600">
                Histórico de tudo que acontece no sistema: criações, edições, exclusões.
                Use os filtros abaixo para investigar tenant, usuário, módulo ou período.
            </p>

            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-xl bg-white ring-1 ring-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Hoje</div>
                    <div class="text-2xl font-bold text-slate-900 mt-1">{{ metricas.hoje }}</div>
                </div>
                <div class="rounded-xl bg-white ring-1 ring-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Últimos 7 dias</div>
                    <div class="text-2xl font-bold text-slate-900 mt-1">{{ metricas.ultimos_7d }}</div>
                </div>
                <div class="rounded-xl bg-white ring-1 ring-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Últimos 30 dias</div>
                    <div class="text-2xl font-bold text-slate-900 mt-1">{{ metricas.ultimos_30d }}</div>
                </div>
                <div class="rounded-xl bg-white ring-1 ring-slate-200 p-4">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Total</div>
                    <div class="text-2xl font-bold text-slate-900 mt-1">{{ metricas.total }}</div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="mb-4 rounded-xl bg-white ring-1 ring-slate-200 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Cliente</label>
                    <select v-model="filtros.tenant_id" class="w-full px-2 py-1.5 rounded-lg ring-1 ring-slate-200 text-sm">
                        <option value="">Todos</option>
                        <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.nome }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Tipo de evento</label>
                    <select v-model="filtros.event" class="w-full px-2 py-1.5 rounded-lg ring-1 ring-slate-200 text-sm">
                        <option value="">Todos</option>
                        <option v-for="e in eventos" :key="e" :value="e">{{ eventBadge(e).label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Módulo</label>
                    <select v-model="filtros.subject_type" class="w-full px-2 py-1.5 rounded-lg ring-1 ring-slate-200 text-sm">
                        <option value="">Todos</option>
                        <option v-for="s in subject_types" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">De</label>
                        <input v-model="filtros.data_inicio" type="date" class="w-full px-2 py-1.5 rounded-lg ring-1 ring-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Até</label>
                        <input v-model="filtros.data_fim" type="date" class="w-full px-2 py-1.5 rounded-lg ring-1 ring-slate-200 text-sm">
                    </div>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between">
                <span class="text-xs text-slate-500">Total filtrado: <strong>{{ atividades.total }}</strong></span>
                <button @click="limparFiltros" class="text-xs text-slate-700 hover:underline">Limpar filtros</button>
            </div>
        </div>

        <!-- Lista -->
        <div class="rounded-xl bg-white ring-1 ring-slate-200 overflow-hidden">
            <!-- Desktop: tabela -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-left text-xs uppercase tracking-wider text-slate-600">
                            <th class="px-4 py-2.5">Quando</th>
                            <th class="px-4 py-2.5">Cliente</th>
                            <th class="px-4 py-2.5">Quem</th>
                            <th class="px-4 py-2.5">Ação</th>
                            <th class="px-4 py-2.5">O quê</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="a in atividades.data" :key="a.id" class="hover:bg-slate-50">
                            <td class="px-4 py-2.5 text-xs text-slate-700 font-mono whitespace-nowrap">{{ a.created_at_br }}</td>
                            <td class="px-4 py-2.5 text-sm">{{ a.tenant_nome || '—' }}</td>
                            <td class="px-4 py-2.5 text-sm">
                                <div v-if="a.causer">
                                    <div class="text-slate-900">{{ a.causer.name }}</div>
                                    <div class="text-xs text-slate-500">{{ a.causer.email }}</div>
                                </div>
                                <span v-else class="text-slate-400">sistema</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold"
                                      :class="eventBadge(a.event).color">
                                    {{ eventBadge(a.event).label }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-sm">
                                <span class="font-mono text-xs bg-slate-100 px-1.5 py-0.5 rounded">{{ a.subject_type }}</span>
                                <span v-if="a.subject_id" class="ml-1 text-xs text-slate-500">#{{ a.subject_id }}</span>
                                <div v-if="a.description" class="text-xs text-slate-500 mt-0.5">{{ a.description }}</div>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <Link :href="route('master.atividades.show', a.id)" class="text-xs text-emerald-700 hover:underline">Detalhes →</Link>
                            </td>
                        </tr>
                        <tr v-if="!atividades.data.length">
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">
                                Nenhuma atividade encontrada com os filtros atuais.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile: cards -->
            <div class="md:hidden divide-y divide-slate-200">
                <div v-for="a in atividades.data" :key="a.id" class="p-4 space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs text-slate-500 font-mono">{{ a.created_at_br }}</div>
                            <div class="font-semibold text-sm text-slate-900 mt-0.5">{{ a.tenant_nome || '—' }}</div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold flex-shrink-0"
                              :class="eventBadge(a.event).color">
                            {{ eventBadge(a.event).label }}
                        </span>
                    </div>
                    <div class="text-xs text-slate-700">
                        <span v-if="a.causer">{{ a.causer.name }}</span>
                        <span v-else class="text-slate-400">(sistema)</span>
                        ·
                        <span class="font-mono">{{ a.subject_type }}{{ a.subject_id ? '#' + a.subject_id : '' }}</span>
                    </div>
                    <div v-if="a.description" class="text-xs text-slate-500">{{ a.description }}</div>
                    <Link :href="route('master.atividades.show', a.id)" class="text-xs text-emerald-700 hover:underline">Detalhes →</Link>
                </div>
                <div v-if="!atividades.data.length" class="p-12 text-center text-sm text-slate-500">
                    Nenhuma atividade encontrada.
                </div>
            </div>

            <!-- Paginação -->
            <div v-if="atividades.last_page > 1" class="px-4 py-3 border-t border-slate-200 flex items-center justify-between">
                <span class="text-xs text-slate-500">
                    Página {{ atividades.current_page }} de {{ atividades.last_page }}
                </span>
                <div class="flex gap-2">
                    <Link
                        v-for="link in atividades.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1 rounded text-xs',
                            link.active ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700',
                            !link.url ? 'opacity-40 pointer-events-none' : ''
                        ]"
                        preserve-scroll preserve-state
                    />
                </div>
            </div>
        </div>
    </MasterLayout>
</template>
