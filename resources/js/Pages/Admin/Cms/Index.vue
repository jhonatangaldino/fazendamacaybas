<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineProps({ pages: Array });
</script>

<template>
    <Head title="CMS da Landing Page" />
    <AdminLayout>
        <template #page-title>CMS — Landing Page</template>

        <PageHeader
            title="Páginas do site"
            subtitle="Edite os textos, imagens e seções da landing page pública"
        >
            <template #actions>
                <a href="/" target="_blank" class="btn-outline">Abrir site público</a>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link v-for="p in pages" :key="p.id"
                  :href="route('admin.cms.edit', p.id)"
                  class="card hover:ring-macaybas-primary transition">
                <div class="card-body">
                    <div class="flex items-start justify-between mb-3">
                        <h3 class="font-semibold text-slate-900">{{ p.titulo }}</h3>
                        <span :class="p.is_published ? 'badge-green' : 'badge-slate'">
                            {{ p.is_published ? 'Publicada' : 'Rascunho' }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mb-4">/{{ p.slug === 'home' ? '' : p.slug }}</p>
                    <div class="text-xs text-slate-600">
                        <strong>{{ p.sections_count }}</strong> seção(ões)
                    </div>
                </div>
            </Link>
        </div>
    </AdminLayout>
</template>
