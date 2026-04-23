<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import GlobalLoading from '@/Components/GlobalLoading.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import ToastContainer from '@/Components/ToastContainer.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <Head title="Plataforma · Master" />
    <GlobalLoading />
    <ToastContainer />
    <FlashMessages />

    <div class="min-h-screen bg-slate-900 text-white flex flex-col">
        <!-- Topbar minimalista — sinal visual forte de "área plataforma" -->
        <header class="h-16 flex items-center justify-between px-6 border-b border-slate-700">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-full bg-white text-slate-900 flex items-center justify-center font-serif text-lg font-bold">M</div>
                <div>
                    <div class="font-serif font-semibold leading-tight">Plataforma</div>
                    <div class="text-[10px] text-slate-400 uppercase tracking-[0.25em]">Área Master</div>
                </div>
            </div>

            <div class="flex items-center gap-4 text-sm">
                <span class="text-slate-400 hidden sm:block">{{ user?.name }}</span>
                <button
                    @click="logout"
                    class="text-slate-300 hover:text-white px-3 py-1.5 rounded-md hover:bg-slate-800"
                >Sair</button>
            </div>
        </header>

        <main class="flex-1 flex items-center justify-center p-6">
            <div class="max-w-2xl w-full text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs uppercase tracking-widest mb-6">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                    Fase M1 · Base da área master
                </div>

                <h1 class="text-3xl sm:text-4xl font-serif font-bold mb-3">Master Dashboard</h1>
                <p class="text-slate-400 leading-relaxed">
                    Área de administração da plataforma SaaS. As funcionalidades serão implementadas
                    incrementalmente:
                </p>

                <ul class="mt-6 grid gap-2 text-sm text-slate-300 text-left max-w-md mx-auto">
                    <li class="flex items-baseline gap-2"><span class="text-slate-500">M2</span> Layout dedicado + sidebar</li>
                    <li class="flex items-baseline gap-2"><span class="text-slate-500">M3</span> CRUD de tenants</li>
                    <li class="flex items-baseline gap-2"><span class="text-slate-500">M4</span> Fazendas e primeiro usuário por tenant</li>
                    <li class="flex items-baseline gap-2"><span class="text-slate-500">M5</span> Impersonação controlada</li>
                    <li class="flex items-baseline gap-2"><span class="text-slate-500">M6</span> Planos, assinaturas, cobranças</li>
                    <li class="flex items-baseline gap-2"><span class="text-slate-500">M7</span> Migração do CMS institucional</li>
                </ul>
            </div>
        </main>
    </div>
</template>
