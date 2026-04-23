<script setup>
/**
 * ClientCmsHeader — cabeçalho contextual padronizado das telas do CMS
 * por cliente (/master/clientes/{id}/cms, .../menus, .../configuracoes,
 * .../pagina/N).
 *
 * Papel UX: resolver a confusão "CMS global vs CMS do cliente". Em todas
 * as telas de CMS do cliente aparece o mesmo cabeçalho:
 *   • Breadcrumb "← Voltar para clientes"
 *   • Título "CMS — Cliente: {nome}"
 *   • Subtítulo "/c/{slug} · URL pública da landing"
 *   • CTA "Ver página pública" (abre /c/{slug} em nova aba)
 *
 * Este componente é visual puro — não faz requisições, não altera rotas,
 * não mexe em dados. Recebe `cliente` já serializado pelo backend com
 * {id, nome, slug, landing_url}.
 *
 * Prop opcional `section` renderiza um texto após o nome para indicar em
 * que subseção do CMS o master está ("Páginas", "Menus", "Configurações").
 */
import { Link } from '@inertiajs/vue3';

defineProps({
    cliente: {
        type: Object,
        required: true,
        // Esperado: { id, nome, slug, landing_url }
    },
    section: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="mb-6">
        <!-- Voltar para clientes -->
        <div class="mb-3">
            <Link
                :href="route('master.tenants.index')"
                class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900 transition"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Voltar para clientes
            </Link>
        </div>

        <!-- Linha do título + CTA -->
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <!-- Badge discreto identifica o escopo -->
                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-xs font-medium ring-1 ring-slate-200 mb-2">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Editando um cliente específico
                </div>

                <h1 class="text-2xl font-serif font-bold text-slate-900 leading-tight">
                    <span class="text-slate-500">CMS — Cliente:</span>
                    <span>{{ cliente.nome }}</span>
                    <span v-if="section" class="text-slate-400 font-normal">· {{ section }}</span>
                </h1>

                <p class="mt-1 text-sm text-slate-500 flex items-center gap-2 flex-wrap">
                    <code class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded">/c/{{ cliente.slug }}</code>
                    <span class="text-slate-400">·</span>
                    <span class="text-slate-600 truncate">{{ cliente.landing_url }}</span>
                </p>
            </div>

            <!-- CTA primário: ver página pública -->
            <a
                :href="cliente.landing_url"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-macaybas-primary text-white text-sm font-medium hover:bg-macaybas-primary-700 shadow-sm whitespace-nowrap"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                Ver página pública
            </a>
        </div>
    </div>
</template>
