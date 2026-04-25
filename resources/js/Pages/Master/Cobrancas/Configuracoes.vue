<script setup>
/**
 * Configurações de cobrança SaaS — chave PIX usada nas mensalidades.
 *
 * 4 campos: tipo_chave, chave, nome_recebedor, cidade_recebedor.
 * Os limites de tamanho de nome (25) e cidade (15) vêm do padrão BR Code (EMV).
 */
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const form = useForm({
    tipo_chave: props.config.tipo_chave,
    chave: props.config.chave,
    nome_recebedor: props.config.nome_recebedor,
    cidade_recebedor: props.config.cidade_recebedor,
});

const TIPOS = [
    { v: 'email',     nome: 'E-mail',     placeholder: 'cobranca@suaempresa.com.br' },
    { v: 'cpf',       nome: 'CPF',        placeholder: '123.456.789-00 ou 12345678900' },
    { v: 'cnpj',      nome: 'CNPJ',       placeholder: '12.345.678/0001-99 ou 12345678000199' },
    { v: 'telefone',  nome: 'Telefone',   placeholder: '+55 31 99999-9999' },
    { v: 'aleatoria', nome: 'Aleatória',  placeholder: '32 caracteres do banco emissor' },
];

const tipoAtual = computed(() => TIPOS.find(t => t.v === form.tipo_chave) ?? TIPOS[0]);

function salvar() {
    form.put(route('master.cobrancas.configuracoes.update'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Configurações de cobrança · Plataforma" />
    <MasterLayout>
        <template #page-title>Configurações de cobrança</template>

        <div class="max-w-3xl mx-auto">
            <div class="mb-6 flex items-start justify-between">
                <div>
                    <h2 class="text-xl font-serif font-bold text-slate-900">Chave PIX para cobranças</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Estes dados aparecem no QR Code e no copia-e-cola enviados aos clientes.
                    </p>
                </div>
                <Link :href="route('master.cobrancas.index')" class="text-sm text-slate-600 hover:text-slate-900">
                    ← Cobranças
                </Link>
            </div>

            <form @submit.prevent="salvar" class="bg-white rounded-2xl ring-1 ring-slate-200 p-6 sm:p-8 space-y-5">
                <!-- Tipo de chave -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Tipo de chave</label>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                        <button v-for="t in TIPOS" :key="t.v" type="button"
                            @click="form.tipo_chave = t.v"
                            :class="[
                                'px-3 py-2 rounded-lg ring-1 text-sm font-medium transition',
                                form.tipo_chave === t.v
                                    ? 'ring-2 ring-macaybas-primary-700 bg-macaybas-primary-50 text-macaybas-primary-900'
                                    : 'ring-slate-200 bg-white text-slate-700 hover:ring-macaybas-primary-300',
                            ]"
                        >{{ t.nome }}</button>
                    </div>
                </div>

                <!-- Chave -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Chave PIX</label>
                    <input v-model="form.chave"
                        type="text"
                        :placeholder="tipoAtual.placeholder"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono focus:border-macaybas-primary-500 focus:ring-macaybas-primary-200">
                    <p v-if="form.errors.chave" class="mt-1 text-xs text-rose-700">{{ form.errors.chave }}</p>
                </div>

                <!-- Nome -->
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Nome do recebedor
                            <span class="text-xs font-normal text-slate-500">(máx. 25 caracteres)</span>
                        </label>
                        <input v-model="form.nome_recebedor"
                            type="text"
                            maxlength="25"
                            placeholder="Ex: FAZENDA MACAYBAS"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase">
                        <div class="flex items-center justify-between mt-1">
                            <p v-if="form.errors.nome_recebedor" class="text-xs text-rose-700">{{ form.errors.nome_recebedor }}</p>
                            <span class="text-xs text-slate-400 ml-auto">{{ form.nome_recebedor?.length ?? 0 }}/25</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Cidade do recebedor
                            <span class="text-xs font-normal text-slate-500">(máx. 15)</span>
                        </label>
                        <input v-model="form.cidade_recebedor"
                            type="text"
                            maxlength="15"
                            placeholder="Ex: JANAUBA"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase">
                        <div class="flex items-center justify-between mt-1">
                            <p v-if="form.errors.cidade_recebedor" class="text-xs text-rose-700">{{ form.errors.cidade_recebedor }}</p>
                            <span class="text-xs text-slate-400 ml-auto">{{ form.cidade_recebedor?.length ?? 0 }}/15</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-amber-50 ring-1 ring-amber-200 p-3 text-sm text-amber-900">
                    💡 O nome e a cidade aparecem no app PIX do cliente quando ele confirma o pagamento.
                    Use sem acentos para garantir compatibilidade entre todos os bancos.
                </div>

                <div class="pt-2 flex items-center justify-end gap-3">
                    <Link :href="route('master.cobrancas.index')"
                        class="px-4 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-100">
                        Cancelar
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-5 py-2 rounded-lg bg-macaybas-primary-700 text-white text-sm font-semibold hover:bg-macaybas-primary-800 shadow-sm disabled:opacity-60">
                        <span v-if="form.processing">Salvando…</span>
                        <span v-else>Salvar configurações</span>
                    </button>
                </div>
            </form>
        </div>
    </MasterLayout>
</template>
