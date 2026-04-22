<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ email: String, token: String });

const form = useForm({
    email: props.email,
    token: props.token,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <AuthLayout>
        <Head title="Redefinir senha" />

        <h1 class="text-2xl font-bold text-slate-900 mb-6">Redefinir senha</h1>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="E-mail" />
                <TextInput id="email" type="email" v-model="form.email" required readonly />
                <InputError :message="form.errors.email" />
            </div>
            <div>
                <InputLabel for="password" value="Nova senha" />
                <TextInput id="password" type="password" v-model="form.password" required autofocus autocomplete="new-password" />
                <InputError :message="form.errors.password" />
            </div>
            <div>
                <InputLabel for="password_confirmation" value="Confirme a senha" />
                <TextInput id="password_confirmation" type="password" v-model="form.password_confirmation" required autocomplete="new-password" />
            </div>
            <PrimaryButton class="w-full justify-center" :disabled="form.processing">Redefinir senha</PrimaryButton>
        </form>
    </AuthLayout>
</template>
