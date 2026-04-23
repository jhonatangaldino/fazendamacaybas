<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import ClientCmsHeader from '@/Components/ClientCmsHeader.vue';

defineProps({
    cliente: { type: Object, required: true },
    pages: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="`CMS — Cliente: ${cliente.nome}`" />
    <MasterLayout>
        <template #page-title>CMS — {{ cliente.nome }}</template>

        <ClientCmsHeader :cliente="cliente" section="Páginas" />

        <!-- Sub-navegação entre as seções do CMS deste cliente -->
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-900 text-white text-sm font-medium">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Páginas
            </span>
            <Link :href="route('master.clientes.cms.menus.index', cliente.id)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white ring-1 ring-slate-200 text-sm text-slate-700 hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                Menus
            </Link>
            <Link :href="route('master.clientes.cms.settings', cliente.id)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white ring-1 ring-slate-200 text-sm text-slate-700 hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Configurações
            </Link>
        </div>

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
