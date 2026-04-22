<script setup>
import { Link } from '@inertiajs/vue3';
import GlobalLoading from '@/Components/GlobalLoading.vue';
</script>

<template>
    <GlobalLoading />
    <div class="min-h-screen grid lg:grid-cols-2 bg-slate-50">
        <aside class="hidden lg:flex relative bg-gradient-to-br from-macaybas-primary-900 via-macaybas-primary-800 to-macaybas-accent-800 text-white p-12 flex-col justify-between">
            <div>
                <Link href="/" class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-full bg-white text-macaybas-primary-900 flex items-center justify-center font-serif text-2xl font-bold">M</div>
                    <div>
                        <div class="font-serif text-xl font-bold">Fazenda Macaybas</div>
                        <div class="text-xs text-macaybas-secondary-300 tracking-widest uppercase">Sistema de gestão</div>
                    </div>
                </Link>
            </div>
            <blockquote class="space-y-4">
                <p class="font-serif text-3xl leading-relaxed">
                    "Gerir a fazenda é cuidar do todo — do pasto ao resultado, da pessoa ao processo."
                </p>
                <footer class="text-sm text-macaybas-secondary-200 uppercase tracking-[0.3em]">
                    Sistema Macaybas
                </footer>
            </blockquote>
            <div class="text-xs text-macaybas-primary-200">
                &copy; {{ new Date().getFullYear() }} Fazenda Macaybas
            </div>
        </aside>

        <main class="flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-md bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-8">
                <slot />
            </div>
        </main>
    </div>
</template>
