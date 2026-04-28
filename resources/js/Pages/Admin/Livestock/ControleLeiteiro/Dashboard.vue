<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    mes_ref:           { type: String, required: true },
    mes_label:         { type: String, required: true },
    mes_anterior:      { type: String, required: true },
    mes_posterior:     { type: String, required: true },
    mes_atual:         { type: String, required: true },
    data_controle_br:  { type: String, required: true },
    linhas:            { type: Array,  required: true },
    contagem:          { type: Object, required: true },
    historico:         { type: Array,  required: true },
    totais:            { type: Object, required: true },
    species:           { type: Object, default: null },     // {id, nome, slug}
    label_femea:       { type: String, default: 'Vacas' },   // Búfalas / Cabras / Ovelhas
    label_cria_f:      { type: String, default: 'Bezerras' },
});

const isMesAtual = computed(() => props.mes_ref === props.mes_atual);

// Maior valor do histórico para escala das barras
const maxHistorico = computed(() => {
    const max = Math.max(...props.historico.map(h => h.total_litros), 1);
    return max;
});

function pctBarra(litros) {
    return Math.max(2, Math.round((litros / maxHistorico.value) * 100));
}

function fmtNum(n, casas = 1) {
    if (n == null) return '—';
    return Number(n).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas });
}
</script>

<template>
    <Head title="Controle Leiteiro — Dashboard mensal" />
    <AdminLayout>
        <template #page-title>Rebanho · Controle Leiteiro</template>

        <PageHeader
            :title="`Controle Leiteiro${species ? ' · '+species.nome : ''} · ${mes_label}`"
            :subtitle="`Quadro mensal DROVET+ — produção por ${label_femea.toLowerCase().slice(0,-1)}, contagem de categorias e histórico`"
        >
            <template #actions>
                <Link :href="route('admin.rebanho.controle-leiteiro.dashboard', species ? { mes: mes_anterior, species_id: species.id } : { mes: mes_anterior })"
                      class="btn-outline" title="Mês anterior">←</Link>
                <Link v-if="!isMesAtual" :href="route('admin.rebanho.controle-leiteiro.dashboard', species ? { species_id: species.id } : {})"
                      class="btn-outline">Hoje</Link>
                <Link :href="route('admin.rebanho.controle-leiteiro.dashboard', species ? { mes: mes_posterior, species_id: species.id } : { mes: mes_posterior })"
                      class="btn-outline" title="Mês seguinte">→</Link>
                <Link :href="route('admin.fluxos.controle-leiteiro')" class="btn-primary">
                    + Novo controle do mês
                </Link>
            </template>
        </PageHeader>

        <!-- ═══ KPIs do mês ═══ -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wider font-semibold text-slate-500">Total do mês</div>
                <div class="text-3xl font-bold text-emerald-700 mt-1">
                    {{ fmtNum(totais.total_litros_mes, 1) }}
                    <span class="text-base text-slate-500">L</span>
                </div>
                <div class="text-xs text-slate-500 mt-1">litros produzidos</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wider font-semibold text-slate-500">{{ label_femea }} ordenhadas</div>
                <div class="text-3xl font-bold text-slate-900 mt-1">
                    {{ totais.vacas_ordenhadas }}
                </div>
                <div class="text-xs text-slate-500 mt-1">com ao menos 1 controle</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wider font-semibold text-slate-500">Média por vaca</div>
                <div class="text-3xl font-bold text-slate-900 mt-1">
                    {{ fmtNum(totais.media_por_vaca, 1) }}
                    <span class="text-base text-slate-500">L</span>
                </div>
                <div class="text-xs text-slate-500 mt-1">produção média mensal</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wider font-semibold text-slate-500">Top produtora</div>
                <template v-if="totais.top_produtora">
                    <div class="text-lg font-bold text-amber-700 mt-1 truncate" :title="totais.top_produtora.numero">
                        🏆 {{ totais.top_produtora.numero }}
                    </div>
                    <div class="text-xs text-slate-600 mt-1">
                        <strong>{{ fmtNum(totais.top_produtora.total_litros) }} L</strong>
                        no mês
                    </div>
                </template>
                <template v-else>
                    <div class="text-lg text-slate-400 mt-1">—</div>
                    <div class="text-xs text-slate-400 mt-1">sem registros</div>
                </template>
            </div>
        </div>

        <!-- ═══ QUADRO PRINCIPAL · vacas em lactação no mês ═══ -->
        <div class="card mb-5">
            <div class="card-body">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-slate-900">
                        🥛 Produção por vaca — {{ mes_label }}
                    </h3>
                    <span class="text-xs text-slate-500">
                        {{ linhas.length }} {{ linhas.length === 1 ? 'vaca registrada' : 'vacas registradas' }}
                    </span>
                </div>

                <div v-if="linhas.length === 0" class="text-center py-10 text-slate-500">
                    Nenhum controle leiteiro registrado neste mês.
                    <div class="mt-3">
                        <Link :href="route('admin.fluxos.controle-leiteiro')" class="text-macaybas-primary font-semibold">
                            Registrar primeiro controle do mês →
                        </Link>
                    </div>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b-2 border-slate-200 bg-slate-50">
                                <th class="px-3 py-2 font-semibold text-slate-700">Vaca</th>
                                <th class="px-3 py-2 font-semibold text-slate-700">Raça / Lote</th>
                                <th class="px-3 py-2 font-semibold text-slate-700 text-right w-20">1ª (L)</th>
                                <th class="px-3 py-2 font-semibold text-slate-700 text-right w-20">2ª (L)</th>
                                <th class="px-3 py-2 font-semibold text-slate-700 text-right w-20">3ª (L)</th>
                                <th class="px-3 py-2 font-semibold text-slate-700 text-right w-24 bg-emerald-50">TOTAL</th>
                                <th class="px-3 py-2 font-semibold text-slate-700">OBS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="l in linhas" :key="l.animal_id"
                                class="border-b border-slate-100 hover:bg-slate-50">
                                <td class="px-3 py-2">
                                    <Link :href="route('admin.rebanho.animais.show', l.animal_id)"
                                          class="font-semibold text-macaybas-primary hover:underline">
                                        {{ l.numero }}
                                    </Link>
                                    <div v-if="l.nome" class="text-xs text-slate-500">{{ l.nome }}</div>
                                </td>
                                <td class="px-3 py-2 text-slate-600">
                                    <div>{{ l.raca || '—' }}</div>
                                    <div class="text-xs text-slate-400">{{ l.lote || '—' }}</div>
                                </td>
                                <td class="px-3 py-2 text-right font-mono">{{ l.ordenhas['1ª'] > 0 ? fmtNum(l.ordenhas['1ª']) : '—' }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ l.ordenhas['2ª'] > 0 ? fmtNum(l.ordenhas['2ª']) : '—' }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ l.ordenhas['3ª'] > 0 ? fmtNum(l.ordenhas['3ª']) : '—' }}</td>
                                <td class="px-3 py-2 text-right font-mono font-bold text-emerald-700 bg-emerald-50">
                                    {{ fmtNum(l.total_litros) }}
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-600 italic max-w-xs truncate" :title="l.observacoes">
                                    {{ l.observacoes || '—' }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-300 bg-slate-100 font-bold">
                                <td class="px-3 py-2" colspan="5">TOTAL DO MÊS</td>
                                <td class="px-3 py-2 text-right text-emerald-700 bg-emerald-100">
                                    {{ fmtNum(totais.total_litros_mes) }} L
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- ═══ CONTAGEM DE CATEGORIAS · DROVET ═══ -->
        <div class="card mb-5">
            <div class="card-body">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-slate-900">
                        🐄 Categorias do rebanho · {{ data_controle_br }}
                    </h3>
                    <span class="text-xs text-slate-500">
                        Total ativo: <strong>{{ contagem.total_geral }}</strong> animais
                    </span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <div class="rounded-xl ring-2 ring-amber-200 bg-amber-50 p-4">
                        <div class="text-2xl mb-1">🥛</div>
                        <div class="text-xs uppercase tracking-wider font-semibold text-amber-800">Em lactação</div>
                        <div class="text-3xl font-bold text-amber-900 mt-1">{{ contagem.vacas_lactacao }}</div>
                        <div class="text-xs text-amber-700 mt-1">{{ label_femea.toLowerCase() }} produzindo</div>
                    </div>
                    <div class="rounded-xl ring-2 ring-sky-200 bg-sky-50 p-4">
                        <div class="text-2xl mb-1">💧</div>
                        <div class="text-xs uppercase tracking-wider font-semibold text-sky-800">{{ label_femea }} Secas</div>
                        <div class="text-3xl font-bold text-sky-900 mt-1">{{ contagem.vacas_secas }}</div>
                        <div class="text-xs text-sky-700 mt-1">não estão dando leite</div>
                    </div>
                    <div class="rounded-xl ring-2 ring-emerald-200 bg-emerald-50 p-4">
                        <div class="text-2xl mb-1">🐄</div>
                        <div class="text-xs uppercase tracking-wider font-semibold text-emerald-800">Novilhas</div>
                        <div class="text-3xl font-bold text-emerald-900 mt-1">{{ contagem.novilhas }}</div>
                        <div class="text-xs text-emerald-700 mt-1">acima de 1 ano</div>
                    </div>
                    <div class="rounded-xl ring-2 ring-pink-200 bg-pink-50 p-4">
                        <div class="text-2xl mb-1">🐮</div>
                        <div class="text-xs uppercase tracking-wider font-semibold text-pink-800">{{ label_cria_f }}</div>
                        <div class="text-3xl font-bold text-pink-900 mt-1">{{ contagem.bezerras }}</div>
                        <div class="text-xs text-pink-700 mt-1">filhotes fêmeas até 1 ano</div>
                    </div>
                    <div class="rounded-xl ring-2 ring-slate-200 bg-slate-50 p-4">
                        <div class="text-2xl mb-1">🐂</div>
                        <div class="text-xs uppercase tracking-wider font-semibold text-slate-700">Machos</div>
                        <div class="text-3xl font-bold text-slate-900 mt-1">{{ contagem.machos }}</div>
                        <div class="text-xs text-slate-600 mt-1">touros, garrotes, bezerros</div>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-3">
                    💡 Categorização automática baseada em sexo, idade e eventos de manejo (secagem, controle leiteiro).
                </p>
            </div>
        </div>

        <!-- ═══ HISTÓRICO 12 MESES ═══ -->
        <div class="card">
            <div class="card-body">
                <h3 class="font-bold text-slate-900 mb-4">
                    📈 Histórico — últimos 12 meses
                </h3>

                <div class="space-y-2">
                    <div v-for="h in historico" :key="h.mes"
                         class="flex items-center gap-3 group"
                         :class="{ 'opacity-60': h.total_litros === 0 }">
                        <div class="w-12 text-xs font-semibold text-slate-600 flex-shrink-0">
                            {{ h.mes_label }}
                        </div>
                        <div class="flex-1 relative h-8 bg-slate-100 rounded-md overflow-hidden">
                            <div class="absolute inset-y-0 left-0 bg-gradient-to-r from-emerald-400 to-emerald-600 rounded-md transition-all"
                                 :style="`width: ${pctBarra(h.total_litros)}%`"></div>
                            <div class="absolute inset-0 flex items-center px-3 text-xs font-bold"
                                 :class="h.total_litros > 0 ? 'text-white' : 'text-slate-400'">
                                {{ fmtNum(h.total_litros) }} L
                            </div>
                        </div>
                        <div class="w-24 text-right text-xs text-slate-500 flex-shrink-0">
                            {{ h.vacas_ordenhadas }} {{ h.vacas_ordenhadas === 1 ? 'vaca' : 'vacas' }}
                        </div>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4">
                    Comparativo mensal — útil pra acompanhar sazonalidade (ex.: queda na seca, pico na cheia).
                </p>
            </div>
        </div>
    </AdminLayout>
</template>
