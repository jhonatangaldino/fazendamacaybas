<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineProps({ title: String, module: String, next: Array });
</script>

<template>
    <Head :title="title" />
    <AdminLayout>
        <template #page-title>{{ title }}</template>

        <PageHeader :title="title" :subtitle="`Módulo '${module}' — estrutura pronta, CRUD completo em sprint seguinte`" />

        <div class="card max-w-3xl">
            <div class="card-body space-y-4">
                <div class="flex items-start gap-3 rounded-lg bg-macaybas-primary-50 p-4 text-sm text-macaybas-primary-900">
                    <svg class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <p class="font-semibold mb-1">Estrutura técnica já pronta</p>
                        <p>As tabelas no banco, os models Eloquent, as permissões e a navegação já estão funcionais. As telas CRUD finais serão incrementadas em sprints seguintes.</p>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-slate-900 mb-2">O que vem a seguir neste módulo:</h3>
                    <ul class="space-y-1.5 text-sm text-slate-600">
                        <li v-for="item in next" :key="item" class="flex gap-2">
                            <span class="text-macaybas-secondary">•</span>
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
