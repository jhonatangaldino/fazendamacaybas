<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: String,
});
</script>

<template>
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold text-slate-900 break-words">{{ title }}</h1>
            <p v-if="subtitle" class="text-sm text-slate-500 mt-1">{{ subtitle }}</p>
        </div>
        <!-- flex-wrap permite que botões CTA quebrem em múltiplas linhas em mobile
             em vez de transbordar o viewport. Antes em /admin/rebanho/animais com
             3 botões empilhados (Dashboard leiteiro + Novo animal + Vender),
             os 2 últimos saíam pela direita em iPhone SE. -->
        <div class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
