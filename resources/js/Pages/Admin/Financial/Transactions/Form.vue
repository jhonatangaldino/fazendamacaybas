<script setup>
import { computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import InputMoney from '@/Components/InputMoney.vue';
import InputDate from '@/Components/InputDate.vue';

const props = defineProps({
    transaction: Object,
    accounts: Array,
    categoriasReceita: Array,
    categoriasDespesa: Array,
    costCenters: Array,
    partners: Array,
});

const form = useForm({
    account_id: props.transaction?.account_id ?? props.accounts[0]?.id ?? '',
    tipo: props.transaction?.tipo ?? 'despesa',
    category_id: props.transaction?.category_id ?? null,
    cost_center_id: props.transaction?.cost_center_id ?? null,
    partner_id: props.transaction?.partner_id ?? null,
    descricao: props.transaction?.descricao ?? '',
    observacoes: props.transaction?.observacoes ?? '',
    valor: props.transaction?.valor ?? '',
    data_vencimento: props.transaction?.data_vencimento ?? new Date().toISOString().slice(0, 10),
    data_pagamento: props.transaction?.data_pagamento ?? '',
    status: props.transaction?.status ?? 'pendente',
    forma_pagamento: props.transaction?.forma_pagamento ?? '',
    numero_documento: props.transaction?.numero_documento ?? '',
});

const isEdit = !!props.transaction;
const categorias = computed(() => form.tipo === 'receita' ? props.categoriasReceita : props.categoriasDespesa);

function submit() {
    if (isEdit) form.put(route('admin.financeiro.transacoes.update', props.transaction.id));
    else form.post(route('admin.financeiro.transacoes.store'));
}
</script>

<template>
    <Head :title="isEdit ? 'Editar lançamento' : 'Novo lançamento'" />
    <AdminLayout>
        <PageHeader :title="isEdit ? 'Editar lançamento' : 'Novo lançamento'">
            <template #actions>
                <Link :href="route('admin.financeiro.transacoes.index')" class="btn-outline">Voltar</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="space-y-6 max-w-4xl">
            <div class="card">
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="form.tipo" class="form-select">
                            <option value="receita">Receita</option>
                            <option value="despesa">Despesa</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Conta" />
                        <select v-model="form.account_id" class="form-select" required>
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.nome }}</option>
                        </select>
                        <InputError :message="form.errors.account_id" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Descrição" />
                        <input v-model="form.descricao" required class="form-input">
                        <InputError :message="form.errors.descricao" />
                    </div>
                    <div>
                        <InputLabel value="Valor" />
                        <InputMoney v-model="form.valor" />
                        <InputError :message="form.errors.valor" />
                    </div>
                    <div>
                        <InputLabel value="Vencimento" />
                        <InputDate v-model="form.data_vencimento" />
                        <InputError :message="form.errors.data_vencimento" />
                    </div>
                    <div>
                        <InputLabel value="Categoria" />
                        <select v-model="form.category_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nome }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Centro de custo" />
                        <select v-model="form.cost_center_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="c in costCenters" :key="c.id" :value="c.id">{{ c.codigo }} — {{ c.nome }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Parceiro (fornecedor / cliente)" />
                        <select v-model="form.partner_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.nome }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Status" />
                        <select v-model="form.status" class="form-select">
                            <option value="pendente">Pendente</option>
                            <option value="pago">Pago</option>
                            <option value="atrasado">Atrasado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                    <div v-if="form.status === 'pago'">
                        <InputLabel value="Data de pagamento" />
                        <InputDate v-model="form.data_pagamento" />
                    </div>
                    <div v-if="form.status === 'pago'">
                        <InputLabel value="Forma de pagamento" />
                        <select v-model="form.forma_pagamento" class="form-select">
                            <option value="">—</option>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="pix">PIX</option>
                            <option value="cartao">Cartão</option>
                            <option value="boleto">Boleto</option>
                            <option value="transferencia">Transferência</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Nº documento" />
                        <input v-model="form.numero_documento" class="form-input">
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Observações" />
                        <textarea v-model="form.observacoes" rows="3" class="form-textarea"></textarea>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Link :href="route('admin.financeiro.transacoes.index')" class="btn-outline">Cancelar</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">Salvar</button>
            </div>
        </form>
    </AdminLayout>
</template>
