<script setup>
/**
 * Wizard "Gerar faturas" — 5 passos:
 *   1. Cliente   — escolhe tenant
 *   2. Tipo      — mensal | unica
 *   3. Período   — mês/ano início e mês/ano fim
 *   4. Resumo    — preview vindo do backend (sem persistir)
 *   5. Confirmar — POST para store(), redireciona para listagem com flash
 */
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import { brl } from '@/utils/format.js';

const props = defineProps({
    tenants: { type: Array, default: () => [] },
    planos: { type: Array, default: () => [] },
    /** Vem do card "Subscription" do tenant — quando setado, pula passo 1 */
    prefillTenantId: { type: Number, default: null },
});

const PASSOS = [
    { n: 1, titulo: 'Cliente',  icon: '👤' },
    { n: 2, titulo: 'Tipo',     icon: '🧾' },
    { n: 3, titulo: 'Período',  icon: '📅' },
    { n: 4, titulo: 'Resumo',   icon: '📋' },
    { n: 5, titulo: 'Confirmar', icon: '✅' },
];

const hoje = new Date();
const anoAtual = hoje.getFullYear();
const mesAtual = hoje.getMonth() + 1;

// Quando vem com tenant pré-selecionado, pula direto pro passo 2 (Tipo).
const passo = ref(props.prefillTenantId ? 2 : 1);

const form = useForm({
    tenant_id: props.prefillTenantId || null,
    plan_id: null,
    tipo: 'mensal',
    mes_inicio: mesAtual,
    ano_inicio: anoAtual,
    mes_fim: mesAtual,
    ano_fim: anoAtual,
});

const tenantSelecionado = computed(() =>
    props.tenants.find(t => t.id === form.tenant_id) ?? null
);

const planoSelecionado = computed(() =>
    props.planos.find(p => p.id === form.plan_id) ?? null
);

// Quando troca tenant, sugere o plano atual dele (mas master pode trocar no passo 3).
// `immediate: true` garante que o pré-fill via prefillTenantId também dispara o auto-plano.
watch(() => form.tenant_id, (id) => {
    if (! id) return;
    const t = props.tenants.find(x => x.id === id);
    if (t?.plano?.id) {
        form.plan_id = t.plano.id;
    }
}, { immediate: true });

// MESES e ANOS
const MESES = [
    { v: 1, nome: 'Janeiro' }, { v: 2, nome: 'Fevereiro' }, { v: 3, nome: 'Março' },
    { v: 4, nome: 'Abril' },   { v: 5, nome: 'Maio' },      { v: 6, nome: 'Junho' },
    { v: 7, nome: 'Julho' },   { v: 8, nome: 'Agosto' },    { v: 9, nome: 'Setembro' },
    { v:10, nome: 'Outubro' }, { v:11, nome: 'Novembro' },  { v:12, nome: 'Dezembro' },
];
const ANOS = Array.from({ length: 6 }, (_, i) => anoAtual - 1 + i);

const periodoValido = computed(() => {
    const ini = form.ano_inicio * 100 + form.mes_inicio;
    const fim = form.ano_fim * 100 + form.mes_fim;
    return ini <= fim;
});

const totalMeses = computed(() => {
    if (! periodoValido.value) return 0;
    return ((form.ano_fim - form.ano_inicio) * 12) + (form.mes_fim - form.mes_inicio) + 1;
});

// Preview vindo do backend
const preview = ref(null);
const carregandoPreview = ref(false);
const erroPreview = ref(null);

async function carregarPreview() {
    if (! periodoValido.value) {
        erroPreview.value = 'Período inválido: mês/ano inicial maior que final.';
        return;
    }
    carregandoPreview.value = true;
    erroPreview.value = null;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const res = await fetch(route('master.cobrancas.wizard.preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                tenant_id: form.tenant_id,
                plan_id: form.plan_id,
                tipo: form.tipo,
                mes_inicio: form.mes_inicio,
                ano_inicio: form.ano_inicio,
                mes_fim: form.mes_fim,
                ano_fim: form.ano_fim,
            }),
        });
        if (! res.ok) {
            const txt = await res.text();
            throw new Error(`HTTP ${res.status}: ${txt.slice(0, 200)}`);
        }
        preview.value = await res.json();
    } catch (e) {
        erroPreview.value = e.message ?? 'Falha ao calcular o resumo.';
    } finally {
        carregandoPreview.value = false;
    }
}

function podeAvancar() {
    if (passo.value === 1) return form.tenant_id !== null;
    if (passo.value === 2) return ['mensal', 'unica'].includes(form.tipo);
    if (passo.value === 3) return form.plan_id !== null && periodoValido.value;
    if (passo.value === 4) return preview.value !== null;
    return false;
}

function avancar() {
    if (! podeAvancar()) return;
    if (passo.value === 3) {
        passo.value = 4;
        carregarPreview();
        return;
    }
    if (passo.value < 5) passo.value++;
}

function voltar() {
    if (passo.value > 1) passo.value--;
}

function confirmar() {
    form.post(route('master.cobrancas.wizard.store'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Gerar faturas · Plataforma" />
    <MasterLayout>
        <template #page-title>Gerar faturas</template>

        <div class="max-w-4xl mx-auto">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-serif font-bold text-slate-900">Gerar faturas</h2>
                    <p class="text-sm text-slate-600 mt-1">Emita cobranças mensais ou uma única consolidada.</p>
                </div>
                <Link :href="route('master.cobrancas.index')"
                      class="text-sm text-slate-600 hover:text-slate-900">
                    ← Voltar para Cobranças
                </Link>
            </div>

            <WizardStepper :passos="PASSOS" :passo="passo" />

            <div class="bg-white rounded-2xl ring-1 ring-slate-200 p-6 sm:p-8">
                <!-- ───────── PASSO 1 — CLIENTE ───────── -->
                <div v-if="passo === 1">
                    <h3 class="text-lg font-semibold text-slate-900 mb-1">Para qual cliente?</h3>
                    <p class="text-sm text-slate-500 mb-4">Selecione o tenant que vai receber a(s) fatura(s).</p>

                    <div v-if="tenants.length === 0" class="text-sm text-slate-500 p-6 bg-slate-50 rounded-lg text-center">
                        Nenhum cliente ativo cadastrado.
                    </div>

                    <div v-else class="grid sm:grid-cols-2 gap-3">
                        <button
                            v-for="t in tenants" :key="t.id" type="button"
                            @click="form.tenant_id = t.id"
                            :class="[
                                'text-left p-4 rounded-xl ring-1 transition',
                                form.tenant_id === t.id
                                    ? 'ring-2 ring-macaybas-primary-700 bg-macaybas-primary-50'
                                    : 'ring-slate-200 hover:ring-macaybas-primary-300 bg-white'
                            ]"
                        >
                            <div class="font-semibold text-slate-900">{{ t.nome }}</div>
                            <div class="text-xs text-slate-500 font-mono">{{ t.slug }}</div>
                            <div v-if="t.plano" class="text-xs text-slate-600 mt-1.5">
                                Plano atual: <span class="font-medium">{{ t.plano.nome }}</span>
                                · {{ brl(t.plano.preco_mensal) }}/mês
                            </div>
                            <div v-else class="text-xs text-amber-700 mt-1.5">
                                Sem plano vinculado — escolha no próximo passo.
                            </div>
                        </button>
                    </div>
                </div>

                <!-- ───────── PASSO 2 — TIPO ───────── -->
                <div v-else-if="passo === 2">
                    <h3 class="text-lg font-semibold text-slate-900 mb-1">Como cobrar?</h3>
                    <p class="text-sm text-slate-500 mb-4">Escolha o tipo de fatura que será gerado.</p>

                    <div class="grid sm:grid-cols-2 gap-3">
                        <button type="button" @click="form.tipo = 'mensal'"
                            :class="[
                                'text-left p-5 rounded-xl ring-1 transition',
                                form.tipo === 'mensal'
                                    ? 'ring-2 ring-macaybas-primary-700 bg-macaybas-primary-50'
                                    : 'ring-slate-200 hover:ring-macaybas-primary-300 bg-white'
                            ]">
                            <div class="text-2xl mb-2">📆</div>
                            <div class="font-semibold text-slate-900">Mensal</div>
                            <p class="text-sm text-slate-600 mt-1">
                                Uma fatura por mês do período. Vencimento no 5º dia útil de cada mês.
                            </p>
                        </button>

                        <button type="button" @click="form.tipo = 'unica'"
                            :class="[
                                'text-left p-5 rounded-xl ring-1 transition',
                                form.tipo === 'unica'
                                    ? 'ring-2 ring-macaybas-primary-700 bg-macaybas-primary-50'
                                    : 'ring-slate-200 hover:ring-macaybas-primary-300 bg-white'
                            ]">
                            <div class="text-2xl mb-2">📦</div>
                            <div class="font-semibold text-slate-900">Única</div>
                            <p class="text-sm text-slate-600 mt-1">
                                Uma fatura consolidada com a soma do período. Vencimento no 5º dia útil a partir de hoje.
                            </p>
                        </button>
                    </div>
                </div>

                <!-- ───────── PASSO 3 — PERÍODO ───────── -->
                <div v-else-if="passo === 3">
                    <h3 class="text-lg font-semibold text-slate-900 mb-1">Qual período?</h3>
                    <p class="text-sm text-slate-500 mb-4">Defina o plano e o intervalo (mês/ano início → mês/ano fim).</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-xs uppercase tracking-wider font-semibold text-slate-600 mb-1.5">Plano</label>
                            <select v-model.number="form.plan_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-macaybas-primary-500 focus:ring-macaybas-primary-200">
                                <option :value="null">Selecione…</option>
                                <option v-for="p in planos" :key="p.id" :value="p.id">
                                    {{ p.nome }} — {{ brl(p.preco_mensal) }}/mês
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs uppercase tracking-wider font-semibold text-slate-600 mb-1.5">Mês início</label>
                            <select v-model.number="form.mes_inicio"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option v-for="m in MESES" :key="m.v" :value="m.v">{{ m.nome }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wider font-semibold text-slate-600 mb-1.5">Ano início</label>
                            <select v-model.number="form.ano_inicio"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option v-for="a in ANOS" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wider font-semibold text-slate-600 mb-1.5">Mês fim</label>
                            <select v-model.number="form.mes_fim"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option v-for="m in MESES" :key="m.v" :value="m.v">{{ m.nome }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wider font-semibold text-slate-600 mb-1.5">Ano fim</label>
                            <select v-model.number="form.ano_fim"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option v-for="a in ANOS" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="! periodoValido" class="mt-4 text-sm text-rose-700 bg-rose-50 ring-1 ring-rose-200 rounded-lg p-3">
                        Período inválido: o início não pode ser maior que o fim.
                    </div>
                    <div v-else-if="planoSelecionado" class="mt-4 text-sm text-slate-700 bg-slate-50 ring-1 ring-slate-200 rounded-lg p-3">
                        <strong>{{ totalMeses }} mês{{ totalMeses === 1 ? '' : 'es' }}</strong> ·
                        valor estimado: <strong>{{ brl(totalMeses * planoSelecionado.preco_mensal) }}</strong>
                    </div>
                </div>

                <!-- ───────── PASSO 4 — RESUMO ───────── -->
                <div v-else-if="passo === 4">
                    <h3 class="text-lg font-semibold text-slate-900 mb-1">Confirme o resumo</h3>
                    <p class="text-sm text-slate-500 mb-4">Verifique cada fatura antes de gerar.</p>

                    <div v-if="carregandoPreview" class="text-sm text-slate-500 p-6 text-center">
                        Calculando…
                    </div>
                    <div v-else-if="erroPreview" class="text-sm text-rose-700 bg-rose-50 ring-1 ring-rose-200 rounded-lg p-3">
                        {{ erroPreview }}
                    </div>
                    <div v-else-if="preview">
                        <div class="grid sm:grid-cols-3 gap-3 mb-5">
                            <div class="p-4 rounded-lg bg-slate-50 ring-1 ring-slate-200">
                                <div class="text-xs uppercase tracking-wider text-slate-500">Cliente</div>
                                <div class="font-semibold text-slate-900 mt-1 truncate">{{ tenantSelecionado?.nome }}</div>
                            </div>
                            <div class="p-4 rounded-lg bg-slate-50 ring-1 ring-slate-200">
                                <div class="text-xs uppercase tracking-wider text-slate-500">Faturas</div>
                                <div class="font-semibold text-slate-900 mt-1">{{ preview.quantidade }} ({{ preview.tipo === 'mensal' ? 'mensais' : 'única' }})</div>
                            </div>
                            <div class="p-4 rounded-lg bg-macaybas-primary-50 ring-1 ring-macaybas-primary-200">
                                <div class="text-xs uppercase tracking-wider text-macaybas-primary-800">Total</div>
                                <div class="font-bold text-macaybas-primary-900 mt-1 text-lg">{{ brl(preview.valor_total) }}</div>
                            </div>
                        </div>

                        <div class="rounded-xl ring-1 ring-slate-200 overflow-hidden">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left font-medium">Referência</th>
                                        <th class="px-4 py-2.5 text-right font-medium">Valor</th>
                                        <th class="px-4 py-2.5 text-left font-medium">Vencimento</th>
                                        <th class="px-4 py-2.5 text-left font-medium hidden sm:table-cell">Aparece p/ cliente em</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(f, i) in preview.faturas" :key="i">
                                        <td class="px-4 py-2.5 font-medium text-slate-900">{{ f.referencia_label }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono">{{ brl(f.valor) }}</td>
                                        <td class="px-4 py-2.5 text-slate-700">{{ f.vencimento }}</td>
                                        <td class="px-4 py-2.5 text-slate-500 hidden sm:table-cell">{{ f.aparece_em }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ───────── PASSO 5 — CONFIRMAR ───────── -->
                <div v-else-if="passo === 5">
                    <h3 class="text-lg font-semibold text-slate-900 mb-1">Pronto para gerar</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        Vamos criar <strong class="text-slate-900">{{ preview?.quantidade }}</strong>
                        fatura{{ preview?.quantidade === 1 ? '' : 's' }}
                        para <strong class="text-slate-900">{{ tenantSelecionado?.nome }}</strong>,
                        totalizando <strong class="text-macaybas-primary-800">{{ brl(preview?.valor_total ?? 0) }}</strong>.
                    </p>

                    <div class="rounded-lg bg-amber-50 ring-1 ring-amber-200 p-4 text-sm text-amber-900 mb-4">
                        💡 As faturas só aparecem para o cliente nas datas indicadas no resumo
                        (5 dias antes do mês de referência, no caso mensal; ou imediatamente no caso único).
                    </div>

                    <div v-if="form.errors.tenant_id || form.errors.plan_id || form.errors.tipo"
                         class="rounded-lg bg-rose-50 ring-1 ring-rose-200 p-3 text-sm text-rose-700 mb-4">
                        <ul>
                            <li v-for="(msg, k) in form.errors" :key="k">{{ msg }}</li>
                        </ul>
                    </div>
                </div>

                <!-- ───────── NAV ───────── -->
                <div class="mt-8 pt-6 border-t border-slate-200 flex items-center justify-between">
                    <button v-if="passo > 1" type="button" @click="voltar"
                        class="px-4 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-100">
                        ← Voltar
                    </button>
                    <span v-else></span>

                    <button v-if="passo < 5" type="button" @click="avancar" :disabled="!podeAvancar()"
                        class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-macaybas-primary-700 text-white text-sm font-semibold hover:bg-macaybas-primary-800 disabled:opacity-40 disabled:cursor-not-allowed">
                        Avançar →
                    </button>
                    <button v-else type="button" @click="confirmar" :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-macaybas-primary-700 text-white text-sm font-semibold hover:bg-macaybas-primary-800 disabled:opacity-40">
                        <span v-if="form.processing">Gerando…</span>
                        <span v-else>✅ Gerar faturas</span>
                    </button>
                </div>
            </div>
        </div>
    </MasterLayout>
</template>
