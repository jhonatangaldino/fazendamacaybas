<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

defineProps({ status: String });

const form = useForm({ email: '' });
function submit() { form.post(route('password.email')); }
</script>

<template>
    <AuthLayout>
        <Head title="Recuperar senha" />

        <h1 class="text-2xl font-bold text-slate-900 mb-2">Recuperar senha</h1>
        <p class="text-sm text-slate-600 mb-6">Informe seu e-mail e enviaremos um link para você redefinir a senha.</p>

        <div v-if="status" class="mb-4 rounded-lg bg-green-50 text-green-800 text-sm px-4 py-3">{{ status }}</div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="E-mail" />
                <TextInput id="email" type="email" v-model="form.email" required autofocus />
                <InputError :message="form.errors.email" />
            </div>
            <PrimaryButton class="w-full justify-center" :disabled="form.processing">Enviar link</PrimaryButton>
        </form>

        <div class="mt-6 text-center">
            <Link :href="route('login')" class="text-sm text-macaybas-primary hover:underline">Voltar ao login</Link>
        </div>
    </AuthLayout>
</template>
