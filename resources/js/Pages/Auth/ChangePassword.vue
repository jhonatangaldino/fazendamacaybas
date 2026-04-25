<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ forced: Boolean });

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.put(route('password.change'), { onFinish: () => form.reset() });
}
</script>

<template>
    <AuthLayout>
        <Head title="Alterar senha" />

        <h1 class="text-2xl font-bold text-slate-900 mb-2">
            {{ forced ? 'Defina sua senha' : 'Alterar senha' }}
        </h1>
        <p v-if="forced" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3 mb-6">
            Este é o seu primeiro acesso. Defina uma senha pessoal forte para continuar.
        </p>

        <form @submit.prevent="submit" class="space-y-4">
            <div v-if="!forced">
                <InputLabel for="current_password" value="Senha atual" />
                <PasswordInput id="current_password" v-model="form.current_password" required autocomplete="current-password" />
                <InputError :message="form.errors.current_password" />
            </div>
            <div>
                <InputLabel for="password" value="Nova senha" />
                <PasswordInput id="password" v-model="form.password" required autofocus autocomplete="new-password" />
                <InputError :message="form.errors.password" />
                <p class="form-help">Mínimo 8 caracteres, com letras, números e símbolos.</p>
            </div>
            <div>
                <InputLabel for="password_confirmation" value="Confirme a nova senha" />
                <PasswordInput id="password_confirmation" v-model="form.password_confirmation" required autocomplete="new-password" />
            </div>
            <PrimaryButton class="w-full justify-center" :disabled="form.processing">Salvar nova senha</PrimaryButton>
        </form>
    </AuthLayout>
</template>
