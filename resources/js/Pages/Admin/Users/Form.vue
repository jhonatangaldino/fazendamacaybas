<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import InputMasked from '@/Components/InputMasked.vue';
import AvatarUpload from '@/Components/AvatarUpload.vue';

const props = defineProps({
    user: Object,
    roles: Array,
});

// Em CADASTRO novo, a senha NÃO é coletada — sistema gera automaticamente
// e envia por email. Em EDIÇÃO, mantém o campo opcional para reset manual.
const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    cpf: props.user?.cpf ?? '',
    telefone: props.user?.telefone ?? '',
    cargo: props.user?.cargo ?? '',
    password: '',
    is_active: props.user?.is_active ?? true,
    must_change_password: props.user?.must_change_password ?? false,
    roles: props.user?.roles ?? [],
});

const isEdit = !!props.user;

function submit() {
    if (isEdit) {
        form.put(route('admin.users.update', props.user.id));
    } else {
        form.post(route('admin.users.store'));
    }
}
</script>

<template>
    <Head :title="isEdit ? `Editar usuário` : 'Novo usuário'" />
    <AdminLayout>
        <template #page-title>{{ isEdit ? 'Editar usuário' : 'Novo usuário' }}</template>
        <PageHeader
            :title="isEdit ? 'Editar usuário' : 'Novo usuário'"
            :subtitle="isEdit ? 'Atualize as informações abaixo' : 'Cadastre um novo usuário do sistema'"
        >
            <template #actions>
                <Link :href="route('admin.users.index')" class="btn-outline">Voltar</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="space-y-6 max-w-3xl">
            <div v-if="isEdit" class="card">
                <div class="card-header"><h2 class="card-title">Foto do perfil</h2></div>
                <div class="card-body">
                    <AvatarUpload
                        :url="user.avatar_url"
                        :name="form.name || user.name"
                        :upload-url="route('admin.users.avatar.upload', user.id)"
                        :remove-url="route('admin.users.avatar.remove', user.id)"
                    />
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Dados pessoais</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel value="Nome completo" />
                        <TextInput v-model="form.name" required autofocus />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="E-mail" />
                        <TextInput type="email" v-model="form.email" required />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div>
                        <InputLabel value="CPF" />
                        <InputMasked v-model="form.cpf" mask="###.###.###-##" placeholder="000.000.000-00" />
                        <InputError :message="form.errors.cpf" />
                    </div>
                    <div>
                        <InputLabel value="Telefone" />
                        <InputMasked v-model="form.telefone" :mask="['(##) ####-####', '(##) #####-####']" placeholder="(31) 99999-9999" />
                        <InputError :message="form.errors.telefone" />
                    </div>
                    <div>
                        <InputLabel value="Cargo" />
                        <TextInput v-model="form.cargo" placeholder="Ex: Gerente operacional" />
                        <InputError :message="form.errors.cargo" />
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Acesso</h2></div>
                <div class="card-body space-y-4">
                    <!-- CADASTRO NOVO: aviso explicando que senha é automática -->
                    <div v-if="!isEdit" class="rounded-lg bg-emerald-50 border border-emerald-200 p-4">
                        <div class="flex items-start gap-3">
                            <span class="text-2xl">📧</span>
                            <div class="text-sm text-emerald-900">
                                <p class="font-semibold mb-1">Senha gerada automaticamente</p>
                                <p>O sistema cria uma senha temporária de 8 caracteres e envia para o e-mail informado.
                                   No primeiro acesso, o usuário será obrigado a definir uma nova senha.
                                   A senha temporária expira em 2 horas.</p>
                            </div>
                        </div>
                    </div>

                    <!-- EDIÇÃO: campo opcional para reset manual (admin pode digitar senha custom se quiser) -->
                    <div v-if="isEdit">
                        <InputLabel value="Nova senha (deixe em branco para manter a atual)" />
                        <PasswordInput v-model="form.password" autocomplete="new-password" />
                        <InputError :message="form.errors.password" />
                        <p class="form-help">Mínimo 8 caracteres, com letras, números e símbolos. Deixe em branco para manter a senha atual.</p>
                    </div>

                    <label v-if="isEdit" class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="form.must_change_password" class="rounded border-slate-300 text-macaybas-primary focus:ring-macaybas-primary">
                        Forçar troca de senha no próximo login
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="form.is_active" class="rounded border-slate-300 text-macaybas-primary focus:ring-macaybas-primary">
                        Usuário ativo
                    </label>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Perfis de acesso</h2></div>
                <div class="card-body">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label v-for="r in roles" :key="r.id" class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 cursor-pointer hover:border-macaybas-primary">
                            <input type="checkbox" :value="r.name" v-model="form.roles"
                                   class="mt-1 rounded border-slate-300 text-macaybas-primary focus:ring-macaybas-primary">
                            <div>
                                <div class="font-semibold text-slate-900 text-sm">{{ r.short_name || r.name }}</div>
                                <div v-if="r.description" class="text-xs text-slate-500 mt-0.5">{{ r.description }}</div>
                            </div>
                        </label>
                    </div>
                    <InputError :message="form.errors.roles" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Link :href="route('admin.users.index')" class="btn-outline">Cancelar</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ isEdit ? 'Salvar alterações' : 'Criar usuário' }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
