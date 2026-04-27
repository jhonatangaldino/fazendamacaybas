<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';

const props = defineProps({
    atividade: Object,
});

const propertiesPretty = computed(() => {
    try { return JSON.stringify(props.atividade.properties, null, 2); }
    catch { return ''; }
});

const eventLabel = {
    created: 'Criado',
    updated: 'Atualizado',
    deleted: 'Excluído',
    restored: 'Restaurado',
};
</script>

<template>
    <Head title="Atividade · Detalhes" />
    <MasterLayout>
        <template #page-title>Atividade · Detalhes</template>

        <div class="mb-4 flex items-center gap-3">
            <Link :href="route('master.atividades.index')" class="text-sm text-slate-700 hover:underline">← Voltar</Link>
        </div>

        <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-6 space-y-5 max-w-3xl">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-xs uppercase tracking-wider text-slate-500">Atividade #{{ atividade.id }}</div>
                    <h2 class="text-lg font-bold text-slate-900 mt-1">
                        {{ eventLabel[atividade.event] || atividade.event }}
                        ·
                        {{ atividade.subject_type_curto }}
                        <span v-if="atividade.subject_id">#{{ atividade.subject_id }}</span>
                    </h2>
                </div>
                <div class="text-right">
                    <div class="text-xs text-slate-500">Quando</div>
                    <div class="text-sm font-mono text-slate-900">{{ atividade.created_at }}</div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="text-xs uppercase text-slate-500">Cliente (tenant)</div>
                    <div class="mt-1 font-medium">{{ atividade.tenant_nome || 'Plataforma (master)' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-slate-500">Quem fez</div>
                    <div v-if="atividade.causer" class="mt-1">
                        <div class="font-medium">{{ atividade.causer.name }}</div>
                        <div class="text-xs text-slate-500">{{ atividade.causer.email }}</div>
                    </div>
                    <div v-else class="mt-1 text-slate-400">Sistema</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-slate-500">Tabela</div>
                    <div class="mt-1 font-mono text-xs">{{ atividade.subject_type }}</div>
                </div>
                <div v-if="atividade.batch_uuid">
                    <div class="text-xs uppercase text-slate-500">Lote</div>
                    <div class="mt-1 font-mono text-xs">{{ atividade.batch_uuid }}</div>
                </div>
            </div>

            <div v-if="atividade.description">
                <div class="text-xs uppercase text-slate-500">Descrição</div>
                <div class="mt-1 text-sm">{{ atividade.description }}</div>
            </div>

            <div v-if="atividade.properties && Object.keys(atividade.properties).length">
                <div class="text-xs uppercase text-slate-500 mb-1">Detalhes (antes/depois)</div>
                <pre class="bg-slate-900 text-slate-100 text-xs p-4 rounded-lg overflow-x-auto">{{ propertiesPretty }}</pre>
            </div>
        </div>
    </MasterLayout>
</template>
