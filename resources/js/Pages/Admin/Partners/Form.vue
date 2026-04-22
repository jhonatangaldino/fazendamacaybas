<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import InputMasked from '@/Components/InputMasked.vue';

const props = defineProps({ partner: Object });
const isEdit = !!props.partner;

const form = useForm({
    tipo: props.partner?.tipo ?? 'fornecedor',
    pessoa: props.partner?.pessoa ?? 'pj',
    nome: props.partner?.nome ?? '',
    nome_fantasia: props.partner?.nome_fantasia ?? '',
    documento: props.partner?.documento ?? '',
    inscricao_estadual: props.partner?.inscricao_estadual ?? '',
    email: props.partner?.email ?? '',
    telefone: props.partner?.telefone ?? '',
    celular: props.partner?.celular ?? '',
    cep: props.partner?.cep ?? '',
    endereco: props.partner?.endereco ?? '',
    numero: props.partner?.numero ?? '',
    complemento: props.partner?.complemento ?? '',
    bairro: props.partner?.bairro ?? '',
    cidade: props.partner?.cidade ?? '',
    estado: props.partner?.estado ?? '',
    observacoes: props.partner?.observacoes ?? '',
    is_active: props.partner?.is_active ?? true,
});

function submit() {
    if (isEdit) form.put(route('admin.parceiros.update', props.partner.id));
    else form.post(route('admin.parceiros.store'));
}
</script>

<template>
    <Head :title="isEdit ? 'Editar parceiro' : 'Novo parceiro'" />
    <AdminLayout>
        <PageHeader :title="isEdit ? 'Editar parceiro' : 'Novo parceiro'">
            <template #actions>
                <Link :href="route('admin.parceiros.index')" class="btn-outline">Voltar</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="space-y-6 max-w-4xl">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Identificação</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="form.tipo" class="form-select">
                            <option value="fornecedor">Fornecedor</option>
                            <option value="cliente">Cliente</option>
                            <option value="ambos">Ambos</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Pessoa" />
                        <select v-model="form.pessoa" class="form-select">
                            <option value="pf">Pessoa Física</option>
                            <option value="pj">Pessoa Jurídica</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel :value="form.pessoa === 'pf' ? 'Nome completo' : 'Razão social'" />
                        <input v-model="form.nome" required class="form-input">
                        <InputError :message="form.errors.nome" />
                    </div>
                    <div v-if="form.pessoa === 'pj'" class="sm:col-span-2">
                        <InputLabel value="Nome fantasia" />
                        <input v-model="form.nome_fantasia" class="form-input">
                    </div>
                    <div>
                        <InputLabel :value="form.pessoa === 'pf' ? 'CPF' : 'CNPJ'" />
                        <InputMasked v-model="form.documento"
                                     :mask="form.pessoa === 'pf' ? '###.###.###-##' : '##.###.###/####-##'"
                                     :placeholder="form.pessoa === 'pf' ? '000.000.000-00' : '00.000.000/0000-00'" />
                        <InputError :message="form.errors.documento" />
                    </div>
                    <div v-if="form.pessoa === 'pj'">
                        <InputLabel value="Inscrição estadual" />
                        <input v-model="form.inscricao_estadual" class="form-input">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Contato</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div><InputLabel value="E-mail" /><input type="email" v-model="form.email" class="form-input"></div>
                    <div><InputLabel value="Telefone" /><InputMasked v-model="form.telefone" :mask="['(##) ####-####', '(##) #####-####']" /></div>
                    <div><InputLabel value="Celular" /><InputMasked v-model="form.celular" mask="(##) #####-####" /></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Endereço</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-2"><InputLabel value="CEP" /><InputMasked v-model="form.cep" mask="#####-###" /></div>
                    <div class="sm:col-span-4"><InputLabel value="Rua" /><input v-model="form.endereco" class="form-input"></div>
                    <div class="sm:col-span-2"><InputLabel value="Número" /><input v-model="form.numero" class="form-input"></div>
                    <div class="sm:col-span-4"><InputLabel value="Complemento" /><input v-model="form.complemento" class="form-input"></div>
                    <div class="sm:col-span-3"><InputLabel value="Bairro" /><input v-model="form.bairro" class="form-input"></div>
                    <div class="sm:col-span-2"><InputLabel value="Cidade" /><input v-model="form.cidade" class="form-input"></div>
                    <div><InputLabel value="UF" /><input v-model="form.estado" maxlength="2" class="form-input uppercase"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-body space-y-3">
                    <div>
                        <InputLabel value="Observações" />
                        <textarea v-model="form.observacoes" rows="3" class="form-textarea"></textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_active" class="rounded">
                        Parceiro ativo
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Link :href="route('admin.parceiros.index')" class="btn-outline">Cancelar</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">Salvar</button>
            </div>
        </form>
    </AdminLayout>
</template>
