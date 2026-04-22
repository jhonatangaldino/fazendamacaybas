<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputDate from '@/Components/InputDate.vue';
import { brl } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({
    filters: Object,
    financeiro: Object,
    rebanho: Object,
    estoque_critico: Array,
    produtividade: Array,
    manutencoes: Array,
    tarefas_status: Object,
});

useAutoReload(['financeiro', 'rebanho', 'estoque_critico', 'produtividade', 'manutencoes', 'tarefas_status'], 30000);

const filtros = reactive({ ...props.filters });

function filtrar() { router.get(route('admin.relatorios.index'), filtros, { preserveState: true, replace: true }); }

function percent(part, total) {
    if (!total) return 0;
    return Math.round((part / total) * 100);
}

function maxValueIn(arr, key) {
    return Math.max(...arr.map(a => Number(a[key] ?? 0)), 1);
}
</script>

<template>
    <Head title="Relatórios" />
    <AdminLayout>
        <template #page-title>Relatórios</template>

        <PageHeader title="Relatórios" subtitle="Visão consolidada da operação por período" />

        <!-- Filtro período -->
        <div class="card mb-6">
            <div class="card-body grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="form-label">De</label>
                    <InputDate v-model="filtros.from" :max="filtros.to || undefined" @update:modelValue="filtrar" />
                </div>
                <div>
                    <label class="form-label">Até</label>
                    <InputDate v-model="filtros.to" :min="filtros.from || undefined" @update:modelValue="filtrar" />
                </div>
                <div class="flex items-end">
                    <span class="text-sm text-slate-500">Atualizado a cada 30s automaticamente</span>
                </div>
            </div>
        </div>

        <!-- Financeiro -->
        <div class="card mb-6">
            <div class="card-header"><h2 class="card-title">Financeiro</h2></div>
            <div class="card-body">
                <div class="grid gap-4 sm:grid-cols-3 mb-6">
                    <div class="p-4 rounded-lg bg-green-50 border border-green-100">
                        <div class="text-xs text-green-800 uppercase">Receitas</div>
                        <div class="text-2xl font-bold text-green-700 mt-1">{{ brl(financeiro.receitas) }}</div>
                    </div>
                    <div class="p-4 rounded-lg bg-red-50 border border-red-100">
                        <div class="text-xs text-red-800 uppercase">Despesas</div>
                        <div class="text-2xl font-bold text-red-700 mt-1">{{ brl(financeiro.despesas) }}</div>
                    </div>
                    <div class="p-4 rounded-lg" :class="financeiro.saldo >= 0 ? 'bg-macaybas-primary-50 border border-macaybas-primary-100' : 'bg-red-50 border border-red-100'">
                        <div class="text-xs uppercase" :class="financeiro.saldo >= 0 ? 'text-macaybas-primary-800' : 'text-red-800'">Saldo</div>
                        <div class="text-2xl font-bold mt-1" :class="financeiro.saldo >= 0 ? 'text-macaybas-primary-700' : 'text-red-700'">{{ brl(financeiro.saldo) }}</div>
                    </div>
                </div>

                <h3 class="text-sm font-semibold text-slate-700 mb-3">Despesas por categoria</h3>
                <div v-if="financeiro.despesas_por_categoria.length" class="space-y-2">
                    <div v-for="cat in financeiro.despesas_por_categoria" :key="cat.categoria" class="flex items-center gap-3">
                        <div class="w-40 text-sm text-slate-700 truncate">{{ cat.categoria }}</div>
                        <div class="flex-1 bg-slate-100 rounded-full h-5 relative">
                            <div class="h-5 rounded-full bg-macaybas-primary"
                                 :style="{ width: percent(cat.total, maxValueIn(financeiro.despesas_por_categoria, 'total')) + '%' }"></div>
                        </div>
                        <div class="w-28 text-right text-sm font-semibold">{{ brl(cat.total) }}</div>
                    </div>
                </div>
                <div v-else class="text-sm text-slate-500">Sem despesas no período.</div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Rebanho -->
            <div class="card">
                <div class="card-header"><h2 class="card-title">Rebanho</h2></div>
                <div class="card-body">
                    <div class="flex items-baseline gap-2 mb-4">
                        <div class="text-3xl font-bold text-macaybas-primary">{{ rebanho.total }}</div>
                        <div class="text-sm text-slate-500">animais ativos</div>
                    </div>

                    <div class="flex gap-4 text-sm mb-4">
                        <div class="flex-1 p-3 rounded-lg bg-blue-50">
                            <div class="text-xs text-blue-800">Fêmeas</div>
                            <div class="font-bold text-blue-700">{{ rebanho.por_sexo.F ?? 0 }}</div>
                        </div>
                        <div class="flex-1 p-3 rounded-lg bg-indigo-50">
                            <div class="text-xs text-indigo-800">Machos</div>
                            <div class="font-bold text-indigo-700">{{ rebanho.por_sexo.M ?? 0 }}</div>
                        </div>
                    </div>

                    <h3 class="text-sm font-semibold text-slate-700 mb-2">Por espécie</h3>
                    <div v-if="rebanho.por_especie.length" class="space-y-2">
                        <div v-for="e in rebanho.por_especie" :key="e.especie" class="flex items-center gap-3 text-sm">
                            <div class="w-24 truncate">{{ e.especie }}</div>
                            <div class="flex-1 bg-slate-100 rounded-full h-4">
                                <div class="h-4 rounded-full bg-macaybas-secondary"
                                     :style="{ width: percent(e.total, maxValueIn(rebanho.por_especie, 'total')) + '%' }"></div>
                            </div>
                            <div class="w-12 text-right font-semibold">{{ e.total }}</div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-slate-500">Nenhum animal cadastrado.</div>
                </div>
            </div>

            <!-- Tarefas por status -->
            <div class="card">
                <div class="card-header"><h2 class="card-title">Tarefas</h2></div>
                <div class="card-body">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="p-3 rounded-lg bg-yellow-50">
                            <div class="text-xs text-yellow-800 uppercase">Pendentes</div>
                            <div class="text-2xl font-bold text-yellow-700">{{ tarefas_status.pendente ?? 0 }}</div>
                        </div>
                        <div class="p-3 rounded-lg bg-blue-50">
                            <div class="text-xs text-blue-800 uppercase">Em andamento</div>
                            <div class="text-2xl font-bold text-blue-700">{{ tarefas_status.em_andamento ?? 0 }}</div>
                        </div>
                        <div class="p-3 rounded-lg bg-green-50">
                            <div class="text-xs text-green-800 uppercase">Concluídas</div>
                            <div class="text-2xl font-bold text-green-700">{{ tarefas_status.concluida ?? 0 }}</div>
                        </div>
                        <div class="p-3 rounded-lg bg-red-50">
                            <div class="text-xs text-red-800 uppercase">Atrasadas</div>
                            <div class="text-2xl font-bold text-red-700">{{ tarefas_status.atrasada ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estoque crítico -->
            <div class="card lg:col-span-2">
                <div class="card-header"><h2 class="card-title">Estoque abaixo do mínimo</h2></div>
                <div class="card-body">
                    <div v-if="estoque_critico.length" class="overflow-x-auto">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-right">Saldo</th>
                                    <th class="text-right">Mínimo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="i in estoque_critico" :key="i.nome">
                                    <td>{{ i.nome }}</td>
                                    <td class="text-right text-red-700 font-semibold">
                                        {{ Number(i.saldo ?? 0).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }} {{ i.unidade }}
                                    </td>
                                    <td class="text-right text-slate-600">
                                        {{ Number(i.estoque_minimo).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }} {{ i.unidade }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-sm text-slate-500 py-4">Nenhum item abaixo do mínimo.</div>
                </div>
            </div>

            <!-- Produtividade agrícola -->
            <div class="card">
                <div class="card-header"><h2 class="card-title">Produtividade agrícola</h2></div>
                <div class="card-body">
                    <div v-if="produtividade.length" class="space-y-3">
                        <div v-for="p in produtividade" :key="p.talhao + p.cultura" class="flex items-center justify-between text-sm border-b border-slate-100 pb-2">
                            <div>
                                <div class="font-medium">{{ p.talhao }}</div>
                                <div class="text-xs text-slate-500">{{ p.cultura }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold">{{ Number(p.colhido).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) }}</div>
                                <div class="text-xs text-slate-500">{{ Number(p.produtividade_media).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) }}/ha</div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-slate-500">Sem colheitas no período.</div>
                </div>
            </div>

            <!-- Manutenções -->
            <div class="card">
                <div class="card-header"><h2 class="card-title">Manutenções por veículo</h2></div>
                <div class="card-body">
                    <div v-if="manutencoes.length" class="space-y-3">
                        <div v-for="m in manutencoes" :key="m.nome" class="flex items-center justify-between text-sm border-b border-slate-100 pb-2">
                            <div>
                                <div class="font-medium">{{ m.nome }}</div>
                                <div class="text-xs text-slate-500">{{ m.qtd }} ordem(ns)</div>
                            </div>
                            <div class="font-semibold">{{ brl(m.total) }}</div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-slate-500">Sem manutenções no período.</div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
