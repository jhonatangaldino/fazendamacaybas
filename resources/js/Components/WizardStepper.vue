<script setup>
/**
 * WizardStepper — barra visual de passos compartilhada pelos assistentes guiados.
 *
 * Design intencional:
 * - Círculos com emoji do passo (pensar como fazendeiro, não como formulário)
 * - Check verde pros passos já concluídos
 * - Ring destacado no passo atual
 * - Conectores que preenchem à medida que avança
 * - Mobile-friendly: encurta labels em telas pequenas via truncate
 *
 * Uso:
 *   <WizardStepper :passos="[{ n: 1, titulo: 'O animal', icon: '🐄' }, ...]"
 *                  :passo="passoAtual" />
 */
defineProps({
    passos: { type: Array, required: true },
    passo: { type: Number, required: true },
});
</script>

<template>
    <div class="mb-8 sm:mb-10">
        <div class="flex items-start justify-between max-w-3xl mx-auto">
            <template v-for="(p, i) in passos" :key="p.n">
                <div class="flex items-center flex-col min-w-0">
                    <div
                        class="h-10 w-10 sm:h-12 sm:w-12 rounded-full flex items-center justify-center text-base font-bold transition-all flex-shrink-0"
                        :class="{
                            'bg-macaybas-primary text-white ring-4 ring-emerald-100': passo === p.n,
                            'bg-emerald-500 text-white': passo > p.n,
                            'bg-slate-200 text-slate-500': passo < p.n,
                        }"
                    >
                        <span v-if="passo > p.n">✓</span>
                        <span v-else class="text-base sm:text-lg">{{ p.icon }}</span>
                    </div>
                    <span
                        class="text-[11px] sm:text-sm mt-2 font-medium text-center leading-tight px-1 max-w-[70px] sm:max-w-none"
                        :class="passo === p.n ? 'text-macaybas-primary font-semibold' : 'text-slate-500'"
                    >{{ p.titulo }}</span>
                </div>
                <div
                    v-if="i < passos.length - 1"
                    class="flex-1 h-1 mx-1 sm:mx-2 mt-5 sm:mt-6 rounded-full"
                    :class="passo > p.n ? 'bg-emerald-400' : 'bg-slate-200'"
                ></div>
            </template>
        </div>
    </div>
</template>
