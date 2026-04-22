<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMoney from '@/Components/InputMoney.vue';
import AvatarUpload from '@/Components/AvatarUpload.vue';

const props = defineProps({ animal: Object, species: Array, lots: Array, farms: Array, partners: Array });
const isEdit = !!props.animal;

const form = useForm({
    farm_id: props.animal?.farm_id ?? props.farms[0]?.id ?? null,
    species_id: props.animal?.species_id ?? props.species[0]?.id ?? '',
    breed_id: props.animal?.breed_id ?? null,
    lot_id: props.animal?.lot_id ?? null,
    identificacao: props.animal?.identificacao ?? '',
    nome: props.animal?.nome ?? '',
    numero_registro: props.animal?.numero_registro ?? '',
    sexo: props.animal?.sexo ?? 'F',
    data_nascimento: props.animal?.data_nascimento ?? '',
    peso_nascimento: props.animal?.peso_nascimento ?? '',
    peso_atual: props.animal?.peso_atual ?? '',
    origem: props.animal?.origem ?? 'nascido',
    partner_id: props.animal?.partner_id ?? null,
    data_aquisicao: props.animal?.data_aquisicao ?? '',
    valor_aquisicao: props.animal?.valor_aquisicao ?? '',
    status: props.animal?.status ?? 'ativo',
    categoria: props.animal?.categoria ?? '',
    observacoes: props.animal?.observacoes ?? '',
});

const racas = computed(() => {
    const sp = props.species.find((s) => s.id === form.species_id);
    return sp?.breeds ?? [];
});

function submit() {
    if (isEdit) form.put(route('admin.rebanho.animais.update', props.animal.id));
    else form.post(route('admin.rebanho.animais.store'));
}
</script>

<template>
    <Head :title="isEdit ? 'Editar animal' : 'Novo animal'" />
    <AdminLayout>
        <PageHeader :title="isEdit ? 'Editar animal' : 'Novo animal'">
            <template #actions>
                <Link :href="route('admin.rebanho.animais.index')" class="btn-outline">Voltar</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="space-y-6 max-w-4xl">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Identificação</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div><InputLabel value="Brinco / identificação" /><input v-model="form.identificacao" required class="form-input"><InputError :message="form.errors.identificacao" /></div>
                    <div><InputLabel value="Nome (opcional)" /><input v-model="form.nome" class="form-input"></div>
                    <div>
                        <InputLabel value="Espécie" />
                        <select v-model="form.species_id" class="form-select" required>
                            <option v-for="s in species" :key="s.id" :value="s.id">{{ s.nome }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Raça" />
                        <select v-model="form.breed_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="r in racas" :key="r.id" :value="r.id">{{ r.nome }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Sexo" />
                        <select v-model="form.sexo" class="form-select" required>
                            <option value="F">Fêmea</option>
                            <option value="M">Macho</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Lote" />
                        <select v-model="form.lot_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="l in lots" :key="l.id" :value="l.id">{{ l.nome }}</option>
                        </select>
                    </div>
                    <div><InputLabel value="Data de nascimento" /><InputDate v-model="form.data_nascimento" /></div>
                    <div><InputLabel value="Peso ao nascer (kg)" /><input type="number" step="0.01" v-model="form.peso_nascimento" class="form-input"></div>
                    <div><InputLabel value="Peso atual (kg)" /><input type="number" step="0.01" v-model="form.peso_atual" class="form-input"></div>
                    <div>
                        <InputLabel value="Status" />
                        <select v-model="form.status" class="form-select">
                            <option value="ativo">Ativo</option>
                            <option value="vendido">Vendido</option>
                            <option value="morto">Morto</option>
                            <option value="abatido">Abatido</option>
                            <option value="transferido">Transferido</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Origem</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Origem" />
                        <select v-model="form.origem" class="form-select">
                            <option value="nascido">Nascido na fazenda</option>
                            <option value="compra">Compra</option>
                        </select>
                    </div>
                    <div v-if="form.origem === 'compra'">
                        <InputLabel value="Fornecedor" />
                        <select v-model="form.partner_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.nome }}</option>
                        </select>
                    </div>
                    <div v-if="form.origem === 'compra'"><InputLabel value="Data de aquisição" /><InputDate v-model="form.data_aquisicao" /></div>
                    <div v-if="form.origem === 'compra'"><InputLabel value="Valor de aquisição" /><InputMoney v-model="form.valor_aquisicao" /></div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <InputLabel value="Observações" />
                    <textarea v-model="form.observacoes" rows="3" class="form-textarea"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Link :href="route('admin.rebanho.animais.index')" class="btn-outline">Cancelar</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">Salvar</button>
            </div>
        </form>
    </AdminLayout>
</template>
