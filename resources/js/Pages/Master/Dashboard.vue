<script setup>
/**
 * Dashboard da plataforma SaaS — KPIs operacionais reais.
 * Substitui o placeholder anterior ("estrutura base M2") agora que
 * todos os módulos (Tenants, Planos, Cobranças, Impersonate, Usuários)
 * estão entregues.
 */
import { Head, Link } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';

defineProps({
    kpis: {
        type: Object,
        default: () => ({
            tenants: { total: 0, ativos: 0, inativos: 0 },
            cobrancas: { pendentes: 0, atrasadas: 0, total_pago_mes: 0, total_pendente: 0 },
            usuarios: { total: 0, masters: 0, clientes: 0 },
        }),
    },
    ultimosTenants: { type: Array, default: () => [] },
});

function brl(v) {
    return `R$ ${Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
}
</script>

<template>
    <Head title="Painel · Plataforma" />
    <MasterLayout>
        <template #page-title>Painel</template>

        <div class="max-w-6xl mx-auto">
            <div class="mb-6">
                <h2 class="text-2xl font-serif font-bold text-slate-900">Painel da Plataforma</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Visão geral operacional dos clientes, cobranças e usuários.
                </p>
            </div>

            <!-- KPIs · Clientes -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <Link
                    :href="route('master.tenants.index')"
                    class="rounded-xl bg-white ring-1 ring-slate-200 p-5 hover:shadow-md hover:ring-slate-300 transition"
                >
                    <div class="text-xs uppercase tracking-wider font-semibold text-slate-500">Clientes ativos</div>
                    <div class="mt-2 text-3xl font-bold text-emerald-700">{{ kpis.tenants.ativos }}</div>
                    <div class="text-xs text-slate-500 mt-1">
                        de {{ kpis.tenants.total }} total<span v-if="kpis.tenants.inativos > 0"> · {{ kpis.tenants.inativos }} inativo{{ kpis.tenants.inativos === 1 ? '' : 's' }}</span>
                    </div>
                </Link>

                <Link
                    :href="route('master.cobrancas.index')"
                    class="rounded-xl bg-white ring-1 ring-slate-200 p-5 hover:shadow-md transition"
                    :class="kpis.cobrancas.atrasadas > 0 ? 'ring-red-300' : ''"
                >
                    <div class="text-xs uppercase tracking-wider font-semibold"
                         :class="kpis.cobrancas.atrasadas > 0 ? 'text-red-700' : 'text-slate-500'">
                        {{ kpis.cobrancas.atrasadas > 0 ? '⚠ Cobranças atrasadas' : 'Cobranças pendentes' }}
                    </div>
                    <div class="mt-2 text-3xl font-bold"
                         :class="kpis.cobrancas.atrasadas > 0 ? 'text-red-700' : 'text-amber-700'">
                        {{ kpis.cobrancas.atrasadas > 0 ? kpis.cobrancas.atrasadas : kpis.cobrancas.pendentes }}
                    </div>
                    <div class="text-xs text-slate-500 mt-1">
                        {{ brl(kpis.cobrancas.total_pendente) }} a receber
                    </div>
                </Link>

                <div class="rounded-xl bg-white ring-1 ring-slate-200 p-5">
                    <div class="text-xs uppercase tracking-wider font-semibold text-slate-500">Recebido no mês</div>
                    <div class="mt-2 text-3xl font-bold text-emerald-700">{{ brl(kpis.cobrancas.total_pago_mes) }}</div>
                    <div class="text-xs text-slate-500 mt-1">Cobranças pagas este mês</div>
                </div>

                <div class="rounded-xl bg-white ring-1 ring-slate-200 p-5">
                    <div class="text-xs uppercase tracking-wider font-semibold text-slate-500">Usuários</div>
                    <div class="mt-2 text-3xl font-bold text-slate-800">{{ kpis.usuarios.total }}</div>
                    <div class="text-xs text-slate-500 mt-1">
                        {{ kpis.usuarios.clientes }} em clientes · {{ kpis.usuarios.masters }} master{{ kpis.usuarios.masters === 1 ? '' : 's' }}
                    </div>
                </div>
            </div>

            <!-- Últimos clientes + atalhos -->
            <div class="grid lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 rounded-xl bg-white ring-1 ring-slate-200 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-xs uppercase tracking-wider font-semibold text-slate-500">Últimos clientes cadastrados</div>
                        <Link :href="route('master.tenants.index')" class="text-xs text-slate-500 hover:underline">Ver todos</Link>
                    </div>
                    <div v-if="ultimosTenants.length === 0" class="text-sm text-slate-500 py-6 text-center">
                        Nenhum cliente cadastrado ainda. <Link :href="route('master.tenants.create')" class="text-emerald-700 hover:underline">Cadastrar o primeiro</Link>.
                    </div>
                    <ul v-else class="divide-y divide-slate-100">
                        <li v-for="t in ultimosTenants" :key="t.id" class="py-3 flex items-center gap-3">
                            <div class="h-9 w-9 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600 text-xs font-bold flex-shrink-0">
                                {{ t.nome.charAt(0).toUpperCase() }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <Link :href="route('master.tenants.edit', t.id)" class="font-medium text-slate-900 hover:underline truncate block">
                                    {{ t.nome }}
                                </Link>
                                <div class="text-xs text-slate-500 font-mono">{{ t.slug }}</div>
                            </div>
                            <span v-if="t.is_active" class="text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-700">Ativo</span>
                            <span v-else class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-600">Inativo</span>
                            <span class="text-xs text-slate-400 whitespace-nowrap">{{ t.created_at }}</span>
                        </li>
                    </ul>
                </div>

                <div class="rounded-xl bg-white ring-1 ring-slate-200 p-5">
                    <div class="text-xs uppercase tracking-wider font-semibold text-slate-500 mb-4">Ações rápidas</div>
                    <div class="space-y-2">
                        <Link :href="route('master.tenants.create')"
                              class="flex items-center gap-3 p-3 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 hover:bg-emerald-100 transition">
                            <span class="text-2xl" aria-hidden="true">➕</span>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-emerald-900">Cadastrar novo cliente</div>
                                <div class="text-xs text-emerald-700">Onboarding completo em 1 clique</div>
                            </div>
                        </Link>
                        <Link :href="route('master.planos.index')"
                              class="flex items-center gap-3 p-3 rounded-lg bg-white ring-1 ring-slate-200 hover:bg-slate-50 transition">
                            <span class="text-2xl" aria-hidden="true">💳</span>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-slate-800">Gerenciar planos</div>
                                <div class="text-xs text-slate-500">Criar, editar, ativar/desativar</div>
                            </div>
                        </Link>
                        <Link :href="route('master.cobrancas.index')"
                              class="flex items-center gap-3 p-3 rounded-lg bg-white ring-1 ring-slate-200 hover:bg-slate-50 transition">
                            <span class="text-2xl" aria-hidden="true">🧾</span>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-slate-800">Ver cobranças</div>
                                <div class="text-xs text-slate-500">Pendentes, pagas, atrasadas</div>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </MasterLayout>
</template>
