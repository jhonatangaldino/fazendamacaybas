<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import GlobalLoading from '@/Components/GlobalLoading.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import ToastContainer from '@/Components/ToastContainer.vue';

const props = defineProps({
    farms: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const submitting = ref(null);

function choose(farmId) {
    if (submitting.value) return;
    submitting.value = farmId;
    router.post(route('admin.fazenda.switch'), { farm_id: farmId }, {
        onFinish: () => { submitting.value = null; },
    });
}

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <Head title="Selecionar fazenda" />
    <GlobalLoading />
    <ToastContainer />
    <FlashMessages />

    <div class="min-h-screen bg-slate-50 flex flex-col">
        <header class="h-16 flex items-center justify-between px-6 border-b border-slate-200 bg-white">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-full bg-macaybas-primary-900 text-white flex items-center justify-center font-serif text-lg font-bold">M</div>
                <div class="font-serif text-slate-900 font-semibold">Macaybas</div>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-slate-500 hidden sm:block">{{ user?.name }}</span>
                <button @click="logout" class="text-slate-600 hover:text-red-600">Sair</button>
            </div>
        </header>

        <main class="flex-1 flex items-start justify-center p-6 sm:p-12">
            <div class="w-full max-w-4xl">
                <div class="mb-8 text-center sm:text-left">
                    <h1 class="text-2xl font-serif font-bold text-slate-900">Escolha a fazenda</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Você tem acesso a mais de uma fazenda. Selecione a que deseja operar agora.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="farm in farms"
                        :key="farm.id"
                        @click="choose(farm.id)"
                        :disabled="submitting !== null"
                        class="group relative flex flex-col justify-between text-left p-5 rounded-2xl bg-white ring-1 ring-slate-200 hover:ring-macaybas-primary hover:shadow-md transition disabled:opacity-60 disabled:cursor-wait"
                    >
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <div class="h-10 w-10 rounded-xl bg-macaybas-primary-50 text-macaybas-primary-800 flex items-center justify-center">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                    </svg>
                                </div>
                                <span v-if="farm.is_current" class="text-[10px] uppercase tracking-widest font-semibold text-macaybas-primary-700 bg-macaybas-primary-50 px-2 py-0.5 rounded-full">
                                    Atual
                                </span>
                            </div>

                            <h2 class="mt-4 text-lg font-serif font-bold text-slate-900 line-clamp-2">{{ farm.nome }}</h2>
                            <p v-if="farm.cidade || farm.estado" class="mt-1 text-xs text-slate-500">
                                {{ [farm.cidade, farm.estado].filter(Boolean).join(' — ') }}
                            </p>
                        </div>

                        <div class="mt-6 flex items-center justify-between">
                            <span class="text-sm font-medium text-macaybas-primary-800 group-hover:text-macaybas-primary-900">
                                {{ submitting === farm.id ? 'Entrando…' : 'Selecionar' }}
                            </span>
                            <svg class="h-4 w-4 text-slate-400 group-hover:text-macaybas-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </button>
                </div>
            </div>
        </main>
    </div>
</template>
