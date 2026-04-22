<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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
                <TextInput id="current_password" type="password" v-model="form.current_password" required />
                <InputError :message="form.errors.current_password" />
            </div>
            <div>
                <InputLabel for="password" value="Nova senha" />
                <TextInput id="password" type="password" v-model="form.password" required autofocus />
                <InputError :message="form.errors.password" />
                <p class="form-help">Mínimo 8 caracteres, com letras, números e símbolos.</p>
            </div>
            <div>
                <InputLabel for="password_confirmation" value="Confirme a nova senha" />
                <TextInput id="password_confirmation" type="password" v-model="form.password_confirmation" required />
            </div>
            <PrimaryButton class="w-full justify-center" :disabled="form.processing">Salvar nova senha</PrimaryButton>
        </form>
    </AuthLayout>
</template>
