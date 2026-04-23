<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';

const props = defineProps({
    tenant: { type: Object, required: true },
    farm: { type: Object, default: null },
});

const isEdit = computed(() => !!props.farm?.id);

const form = useForm({
    nome: props.farm?.nome ?? '',
    cidade: props.farm?.cidade ?? '',
    estado: props.farm?.estado ?? '',
    is_active: props.farm ? Boolean(props.farm.is_active) : true,
});

function submit() {
    if (isEdit.value) {
        form.put(route('master.tenants.farms.update', [props.tenant.id, props.farm.id]));
    } else {
        form.post(route('master.tenants.farms.store', props.tenant.id));
    }
}

// Força UF maiúscula enquanto digita (aceita minúscula, mostra maiúscula)
function handleEstadoInput(e) {
    form.estado = e.target.value.toUpperCase().slice(0, 2);
}
</script>

<template>
    <Head :title="(isEdit ? 'Editar fazenda' : 'Nova fazenda') + ' · ' + tenant.nome" />
    <MasterLayout>
        <template #page-title>{{ isEdit ? 'Editar fazenda' : 'Nova fazenda' }}</template>

        <!-- Breadcrumb -->
        <nav class="text-sm text-slate-500 mb-4 flex items-center gap-1.5 flex-wrap">
            <Link :href="route('master.tenants.index')" class="hover:text-slate-900">Clientes</Link>
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span>{{ tenant.nome }}</span>
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <Link :href="route('master.tenants.farms.index', tenant.id)" class="hover:text-slate-900">Fazendas</Link>
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-900 font-medium">{{ isEdit ? 'Editar' : 'Nova' }}</span>
        </nav>

        <!-- Header -->
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl font-serif font-bold text-slate-900">
                    {{ isEdit ? `Editar "${farm.nome}"` : 'Cadastrar nova fazenda' }}
                </h2>
                <p class="mt-1 text-sm text-slate-600">
                    Vinculada a <strong>{{ tenant.nome }}</strong>. Campos básicos; detalhes (CNPJ, endereço, área) poderão ser preenchidos pelo cliente na área operacional.
                </p>
            </div>
            <Link
                :href="route('master.tenants.farms.index', tenant.id)"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-sm hover:bg-slate-200"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Voltar
            </Link>
        </div>

        <!-- Form -->
        <form @submit.prevent="submit" class="max-w-xl">
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-6 space-y-5">
                <!-- Nome -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">
                        Nome <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.nome"
                        type="text"
                        autocomplete="off"
                        class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm"
                        :class="form.errors.nome ? 'ring-red-400' : ''"
                        placeholder="Ex.: Fazenda Sede"
                    >
                    <p v-if="form.errors.nome" class="mt-1 text-xs text-red-600">{{ form.errors.nome }}</p>
                </div>

                <!-- Cidade + UF -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Cidade</label>
                        <input
                            v-model="form.cidade"
                            type="text"
                            autocomplete="off"
                            class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm"
                            :class="form.errors.cidade ? 'ring-red-400' : ''"
                            placeholder="Ex.: Janaúba"
                        >
                        <p v-if="form.errors.cidade" class="mt-1 text-xs text-red-600">{{ form.errors.cidade }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">UF</label>
                        <input
                            :value="form.estado"
                            @input="handleEstadoInput"
                            type="text"
                            maxlength="2"
                            autocomplete="off"
                            class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none text-sm uppercase font-mono"
                            :class="form.errors.estado ? 'ring-red-400' : ''"
                            placeholder="MG"
                        >
                        <p v-if="form.errors.estado" class="mt-1 text-xs text-red-600">{{ form.errors.estado }}</p>
                    </div>
                </div>

                <!-- Ativa -->
                <div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                        >
                        <span class="text-sm text-slate-700">
                            Fazenda ativa
                            <span class="block text-xs text-slate-500">
                                Fazendas inativas não aparecem no seletor dos usuários deste cliente.
                            </span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Ações -->
            <div class="mt-6 flex items-center justify-end gap-3">
                <Link
                    :href="route('master.tenants.farms.index', tenant.id)"
                    class="px-4 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-100"
                >Cancelar</Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 disabled:opacity-60 disabled:cursor-wait"
                >
                    {{ form.processing
                        ? 'Salvando…'
                        : (isEdit ? 'Salvar alterações' : 'Criar fazenda') }}
                </button>
            </div>
        </form>
    </MasterLayout>
</template>
