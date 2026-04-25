<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import InputError from '@/Components/InputError.vue';

defineProps({ canResetPassword: Boolean, status: String });

const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post(route('login'), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <AuthLayout>
        <Head title="Entrar" />

        <h1 class="text-2xl font-bold text-slate-900 mb-2">Entrar no sistema</h1>
        <p class="text-sm text-slate-600 mb-6">Use seu e-mail e senha cadastrados.</p>

        <div v-if="status" class="mb-4 rounded-lg bg-green-50 text-green-800 text-sm px-4 py-3">{{ status }}</div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="E-mail" />
                <TextInput id="email" type="email" v-model="form.email" required autofocus autocomplete="username" />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Senha" />
                <PasswordInput id="password" v-model="form.password" required autocomplete="current-password" />
                <InputError :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" v-model="form.remember" class="rounded border-slate-300 text-macaybas-primary focus:ring-macaybas-primary">
                    Lembrar-me
                </label>
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm text-macaybas-primary hover:underline">
                    Esqueci a senha
                </Link>
            </div>

            <PrimaryButton class="w-full justify-center" :disabled="form.processing">Entrar</PrimaryButton>
        </form>
    </AuthLayout>
</template>
