<script setup>
/**
 * Tela renderizada pelo middleware EnforceFeature quando o tenant tenta
 * acessar um módulo que o plano dele NÃO inclui.
 *
 * Recebe a feature solicitada (key + nome + descricao) e mostra:
 *   - Nome legível do módulo
 *   - Por que está bloqueado
 *   - CTA de "Falar com suporte" (placeholder; troca para Tally/WhatsApp depois)
 *   - Link "Voltar ao início" para sair sem confusão
 */
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    feature: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`${feature.nome} indisponível · Macaybas`" />
    <AdminLayout>
        <template #page-title>Funcionalidade indisponível</template>

        <div class="max-w-xl mx-auto mt-8 sm:mt-12">
            <div class="rounded-2xl bg-white ring-1 ring-amber-200 p-8 sm:p-10 text-center">
                <div class="h-16 w-16 mx-auto rounded-full bg-amber-100 text-amber-700 flex items-center justify-center mb-4">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.948 3.374H4.645c-1.73 0-2.813-1.874-1.948-3.374L10.05 3.378c.866-1.5 3.032-1.5 3.898 0l8.354 12.748zM12 15.75h.008v.008H12v-.008z"/>
                    </svg>
                </div>
                <h2 class="text-xl font-serif font-bold text-slate-900">
                    {{ feature.nome }} não está incluído no seu plano
                </h2>
                <p class="mt-2 text-sm text-slate-600">
                    {{ feature.descricao }}
                </p>

                <div class="mt-6 rounded-lg bg-slate-50 ring-1 ring-slate-100 p-4 text-left text-sm text-slate-700">
                    <p>
                        Para liberar este módulo, é preciso fazer upgrade do plano.
                        Fale com o suporte para ver as opções disponíveis para o seu caso.
                    </p>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <Link :href="route('admin.inicio')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg ring-1 ring-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50">
                        ← Voltar ao início
                    </Link>
                    <a :href="`https://wa.me/553199999999?text=${encodeURIComponent('Olá! Quero ativar ' + feature.nome + ' no meu plano.')}`"
                        target="_blank" rel="noopener"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-macaybas-primary-700 text-white text-sm font-semibold hover:bg-macaybas-primary-800 shadow-sm">
                        Falar com suporte
                    </a>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
