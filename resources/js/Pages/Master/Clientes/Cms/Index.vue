<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import ClientCmsHeader from '@/Components/ClientCmsHeader.vue';
import Icon from '@/Components/Icon.vue';

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
                <Icon name="document" :size="16" />
                Páginas
            </span>
            <Link :href="route('master.clientes.cms.menus.index', cliente.id)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white ring-1 ring-slate-200 text-sm text-slate-700 hover:bg-slate-50">
                <Icon name="menu" :size="16" />
                Menus
            </Link>
            <Link :href="route('master.clientes.cms.settings', cliente.id)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white ring-1 ring-slate-200 text-sm text-slate-700 hover:bg-slate-50">
                <Icon name="cog" :size="16" />
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
