<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { dataBR } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';
import { useConfirm } from '@/composables/useConfirm.js';

const { confirm } = useConfirm();

defineProps({ crops: Array, seasons: Array });
useAutoReload(['crops', 'seasons'], 20000);

const cropForm = useForm({ nome: '', variedade: '', ciclo_dias: null, unidade_producao: 'kg' });
const seasonForm = useForm({ nome: '', data_inicio: '', data_fim: '' });

const showCropForm = ref(false);
const showSeasonForm = ref(false);

function saveCrop() {
    cropForm.post(route('admin.agricola.culturas.store'), {
        preserveScroll: true, only: ['crops'],
        onSuccess: () => { showCropForm.value = false; cropForm.reset(); cropForm.unidade_producao = 'kg'; },
    });
}
function saveSeason() {
    seasonForm.post(route('admin.agricola.safras.store'), {
        preserveScroll: true, only: ['seasons'],
        onSuccess: () => { showSeasonForm.value = false; seasonForm.reset(); },
    });
}
async function delCrop(id) {
    if (await confirm({ title: 'Excluir cultura', message: 'Excluir esta cultura do catálogo?', variant: 'danger' })) {
        router.delete(route('admin.agricola.culturas.destroy', id), { preserveScroll: true, only: ['crops'] });
    }
}
async function delSeason(id) {
    if (await confirm({ title: 'Excluir safra', message: 'Excluir esta safra do calendário?', variant: 'danger' })) {
        router.delete(route('admin.agricola.safras.destroy', id), { preserveScroll: true, only: ['seasons'] });
    }
}
</script>

<template>
    <Head title="Culturas e safras" />
    <AdminLayout>
        <template #page-title>Agrícola</template>
        <PageHeader title="Culturas e safras" subtitle="Catálogo de culturas plantadas e calendário de safras">
            <template #actions>
                <Link :href="route('admin.agricola.index')" class="btn-outline">Voltar</Link>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Culturas -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Culturas</h2>
                    <button @click="showCropForm = !showCropForm" class="btn-primary btn-sm">+ Nova</button>
                </div>
                <div v-if="showCropForm" class="card-body border-b bg-slate-50 grid gap-3 sm:grid-cols-2">
                    <div><InputLabel value="Nome" /><input v-model="cropForm.nome" class="form-input" placeholder="Ex: Café Arábica"></div>
                    <div><InputLabel value="Variedade" /><input v-model="cropForm.variedade" class="form-input"></div>
                    <div><InputLabel value="Ciclo (dias)" /><input type="number" v-model="cropForm.ciclo_dias" class="form-input"></div>
                    <div>
                        <InputLabel value="Unidade" />
                        <select v-model="cropForm.unidade_producao" class="form-select">
                            <option value="kg">kg</option><option value="ton">ton</option><option value="sc">sc</option><option value="un">un</option><option value="l">l</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2 flex justify-end gap-2">
                        <button @click="showCropForm = false" class="btn-outline">Cancelar</button>
                        <button @click="saveCrop" :disabled="cropForm.processing" class="btn-primary">Adicionar</button>
                    </div>
                </div>
                <ul class="divide-y divide-slate-100">
                    <li v-for="c in crops" :key="c.id" class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <div class="font-medium">{{ c.nome }}</div>
                            <div class="text-xs text-slate-500">
                                {{ c.variedade || 'sem variedade' }} · ciclo: {{ c.ciclo_dias || '?' }} dias · un. {{ c.unidade_producao }}
                            </div>
                        </div>
                        <button @click="delCrop(c.id)" class="text-red-600 text-sm hover:underline">Excluir</button>
                    </li>
                    <li v-if="!crops.length" class="px-5 py-6 text-sm text-slate-500 text-center">Nenhuma cultura cadastrada.</li>
                </ul>
            </div>

            <!-- Safras -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Safras</h2>
                    <button @click="showSeasonForm = !showSeasonForm" class="btn-primary btn-sm">+ Nova</button>
                </div>
                <div v-if="showSeasonForm" class="card-body border-b bg-slate-50 grid gap-3">
                    <div><InputLabel value="Nome" /><input v-model="seasonForm.nome" class="form-input" placeholder="Ex: Safra 2025/2026"></div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><InputLabel value="Início" /><input type="date" v-model="seasonForm.data_inicio" class="form-input"></div>
                        <div><InputLabel value="Fim (opcional)" /><input type="date" v-model="seasonForm.data_fim" class="form-input"></div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button @click="showSeasonForm = false" class="btn-outline">Cancelar</button>
                        <button @click="saveSeason" :disabled="seasonForm.processing" class="btn-primary">Adicionar</button>
                    </div>
                </div>
                <ul class="divide-y divide-slate-100">
                    <li v-for="s in seasons" :key="s.id" class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <div class="font-medium">{{ s.nome }}</div>
                            <div class="text-xs text-slate-500">{{ dataBR(s.data_inicio) }} → {{ s.data_fim ? dataBR(s.data_fim) : 'em andamento' }}</div>
                        </div>
                        <button @click="delSeason(s.id)" class="text-red-600 text-sm hover:underline">Excluir</button>
                    </li>
                    <li v-if="!seasons.length" class="px-5 py-6 text-sm text-slate-500 text-center">Nenhuma safra cadastrada.</li>
                </ul>
            </div>
        </div>
    </AdminLayout>
</template>
