<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineProps({
    cliente: { type: Object, required: true },
    pages: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="`CMS · ${cliente.nome}`" />
    <MasterLayout>
        <template #page-title>CMS · {{ cliente.nome }}</template>

        <!-- Breadcrumb -->
        <nav class="text-sm text-slate-500 mb-4 flex items-center gap-1.5">
            <Link :href="route('master.tenants.index')" class="hover:text-slate-900">Clientes</Link>
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span>{{ cliente.nome }}</span>
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-900 font-medium">CMS</span>
        </nav>

        <PageHeader
            :title="`Páginas da landing de ${cliente.nome}`"
            subtitle="Edite os textos, imagens e seções da landing page do cliente"
        >
            <template #actions>
                <Link :href="route('master.clientes.cms.menus.index', cliente.id)" class="btn-outline">Menus</Link>
                <Link :href="route('master.clientes.cms.settings', cliente.id)" class="btn-outline">Configurações</Link>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="p in pages"
                :key="p.id"
                :href="route('master.clientes.cms.edit', [cliente.id, p.id])"
                class="card hover:ring-macaybas-primary transition"
            >
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

        <div v-if="pages.length === 0" class="rounded-2xl bg-white ring-1 ring-slate-200 p-10 text-center mt-4">
            <h3 class="text-sm font-semibold text-slate-900">Nenhuma página cadastrada</h3>
            <p class="mt-2 text-sm text-slate-600">
                Este cliente ainda não tem páginas de landing.
            </p>
        </div>
    </MasterLayout>
</template>
