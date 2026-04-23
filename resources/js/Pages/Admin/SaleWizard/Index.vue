<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputMoney from '@/Components/InputMoney.vue';
import InputDate from '@/Components/InputDate.vue';
import { brl, dataBR, cpfMask } from '@/utils/format.js';

const props = defineProps({
    animais: Array,
    partners: Array,
});

// ═════════════════════════════════════════════════════════════════════
// FASE 4 · F4.1 — Fluxo guiado de VENDA DE ANIMAL
//
// 5 PASSOS:
//   1. Selecionar animal (status=ativo, busca por brinco/nome)
//   2. Comprador (parceiro cliente)
//   3. Valor da venda (+ data)
//   4. Revisão (resumo claro)
//   5. Confirmação (POST para admin.rebanho.animais.eventos.store)
//
// Zero reimplementação: o submit vai para a rota de eventos existente
// que já faz validação D1, cria AnimalEvent, atualiza status e dispara
// F2.1 (gera FT receita com marcador ANIMAL_EVENT:<id>).
// ═════════════════════════════════════════════════════════════════════

const PASSOS = [
    { n: 1, titulo: 'Animal', icon: '🐄' },
    { n: 2, titulo: 'Comprador', icon: '🤝' },
    { n: 3, titulo: 'Valor', icon: '💰' },
    { n: 4, titulo: 'Revisão', icon: '📋' },
    { n: 5, titulo: 'Concluído', icon: '✅' },
];

const passo = ref(1);
const busca = ref('');
const selecionado = ref(null);        // objeto animal
const compradorId = ref(null);
const dataVenda = ref(new Date().toISOString().slice(0, 10));
const valor = ref('');
const observacoes = ref('');

const form = useForm({
    tipo: 'venda',
    data: '',
    valor: 0,
    partner_id: null,
    observacoes: '',
});

// ── Passo 1 ─────────────────────────────────────────────────────────
const animaisFiltrados = computed(() => {
    const q = (busca.value ?? '').toLowerCase().trim();
    if (!q) return props.animais;
    return props.animais.filter((a) =>
        (a.identificacao ?? '').toLowerCase().includes(q) ||
        (a.nome ?? '').toLowerCase().includes(q) ||
        (a.breed?.nome ?? '').toLowerCase().includes(q) ||
        (a.lot?.nome ?? '').toLowerCase().includes(q),
    );
});

const podeAvancar1 = computed(() => selecionado.value !== null);

// ── Passo 2 ─────────────────────────────────────────────────────────
const comprador = computed(() =>
    props.partners.find((p) => p.id === compradorId.value) ?? null,
);
const podeAvancar2 = computed(() => compradorId.value !== null);

// ── Passo 3 ─────────────────────────────────────────────────────────
const valorNumerico = computed(() => {
    const n = parseFloat(String(valor.value ?? '').replace(/\D/g, '')) / 100;
    return isNaN(n) ? 0 : n;
});
const dataValida = computed(() => {
    if (!dataVenda.value) return false;
    return dataVenda.value <= new Date().toISOString().slice(0, 10);
});
const podeAvancar3 = computed(() => valorNumerico.value > 0 && dataValida.value);

// ── Passo 4 ─────────────────────────────────────────────────────────
const podeConfirmar = computed(
    () => podeAvancar1.value && podeAvancar2.value && podeAvancar3.value,
);

// ── Labels dinâmicos ────────────────────────────────────────────────
function idadeAnimal(dataNasc) {
    if (!dataNasc) return null;
    const nasc = new Date(dataNasc);
    const hoje = new Date();
    const meses = (hoje.getFullYear() - nasc.getFullYear()) * 12 + (hoje.getMonth() - nasc.getMonth());
    if (meses < 12) return `${meses} meses`;
    const anos = Math.floor(meses / 12);
    const restoMes = meses % 12;
    return restoMes ? `${anos}a ${restoMes}m` : `${anos} ano${anos > 1 ? 's' : ''}`;
}

// ── Navegação ───────────────────────────────────────────────────────
function proximo() {
    if (passo.value === 1 && !podeAvancar1.value) return;
    if (passo.value === 2 && !podeAvancar2.value) return;
    if (passo.value === 3 && !podeAvancar3.value) return;
    if (passo.value < 5) passo.value++;
}
function voltar() {
    if (passo.value > 1) passo.value--;
}
function reiniciar() {
    passo.value = 1;
    busca.value = '';
    selecionado.value = null;
    compradorId.value = null;
    dataVenda.value = new Date().toISOString().slice(0, 10);
    valor.value = '';
    observacoes.value = '';
    form.clearErrors();
}

// ── Submit ──────────────────────────────────────────────────────────
function confirmar() {
    if (!podeConfirmar.value) return;

    // Popula form com os dados coletados nos passos
    form.tipo = 'venda';
    form.data = dataVenda.value;
    form.valor = valorNumerico.value;
    form.partner_id = compradorId.value;
    form.observacoes = observacoes.value;

    form.post(route('admin.rebanho.animais.eventos.store', selecionado.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            passo.value = 5;
        },
        onError: () => {
            // Inertia já renderiza flash error + form.errors; só impede avanço
        },
    });
}
</script>

<template>
    <Head title="Vender animal" />
    <AdminLayout>
        <PageHeader title="Vender animal" subtitle="Fluxo guiado — 5 passos simples">
            <template #actions>
                <Link :href="route('admin.rebanho.animais.index')" class="btn-outline">Cancelar</Link>
            </template>
        </PageHeader>

        <!-- Barra de passos (stepper) -->
        <div class="mb-8">
            <div class="flex items-center justify-between max-w-3xl mx-auto">
                <template v-for="(p, i) in PASSOS" :key="p.n">
                    <div class="flex items-center flex-col">
                        <div
                            class="h-10 w-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all"
                            :class="{
                                'bg-macaybas-primary text-white': passo === p.n,
                                'bg-emerald-500 text-white': passo > p.n,
                                'bg-slate-200 text-slate-500': passo < p.n,
                            }"
                        >
                            <span v-if="passo > p.n">✓</span>
                            <span v-else>{{ p.n }}</span>
                        </div>
                        <span class="text-xs mt-1.5 font-medium"
                              :class="passo === p.n ? 'text-macaybas-primary' : 'text-slate-500'">
                            {{ p.titulo }}
                        </span>
                    </div>
                    <div v-if="i < PASSOS.length - 1" class="flex-1 h-px mx-2 mb-6"
                         :class="passo > p.n ? 'bg-emerald-400' : 'bg-slate-200'"></div>
                </template>
            </div>
        </div>

        <!-- ══════ PASSO 1 — Selecionar animal ══════ -->
        <div v-if="passo === 1" class="card">
            <div class="card-body space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Qual animal você vai vender?</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Lista mostra apenas animais ativos. {{ animais.length }} disponíveis.
                    </p>
                </div>

                <div class="relative">
                    <input
                        v-model="busca"
                        placeholder="Buscar por brinco, nome, raça ou lote…"
                        class="form-input pl-9"
                    >
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                <div v-if="animaisFiltrados.length === 0" class="text-center text-slate-500 py-12">
                    <div v-if="animais.length === 0">Nenhum animal ativo para vender.</div>
                    <div v-else>Nenhum resultado para "{{ busca }}".</div>
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 max-h-[60vh] overflow-y-auto pr-1">
                    <button
                        v-for="a in animaisFiltrados"
                        :key="a.id"
                        type="button"
                        @click="selecionado = a"
                        class="text-left rounded-lg border-2 p-3 transition-all hover:border-macaybas-primary hover:shadow-md"
                        :class="selecionado?.id === a.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'"
                    >
                        <div class="flex items-center gap-3">
                            <img v-if="a.photo_url" :src="a.photo_url" class="h-12 w-12 rounded object-cover flex-shrink-0">
                            <div v-else class="h-12 w-12 rounded bg-slate-100 flex items-center justify-center text-xl flex-shrink-0">
                                {{ a.species?.nome === 'Ave' ? '🐔' : a.species?.nome === 'Peixe' ? '🐟' : a.species?.nome === 'Cão' ? '🐕' : a.species?.nome === 'Gato' ? '🐈' : a.species?.nome === 'Suíno' ? '🐖' : '🐄' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-slate-900 truncate">
                                    {{ a.identificacao }}
                                    <span v-if="a.nome" class="text-sm font-normal text-slate-600">— {{ a.nome }}</span>
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ a.species?.nome ?? '—' }}
                                    <span v-if="a.breed?.nome"> · {{ a.breed.nome }}</span>
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ a.sexo === 'F' ? '♀ Fêmea' : '♂ Macho' }}
                                    <span v-if="idadeAnimal(a.data_nascimento)"> · {{ idadeAnimal(a.data_nascimento) }}</span>
                                    <span v-if="a.peso_atual"> · {{ Number(a.peso_atual).toFixed(1) }} kg</span>
                                </div>
                                <div v-if="a.lot?.nome" class="text-[11px] text-slate-400 mt-0.5">Lote: {{ a.lot.nome }}</div>
                            </div>
                            <div v-if="selecionado?.id === a.id" class="text-emerald-600">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>
                    </button>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-100">
                    <button
                        @click="proximo"
                        :disabled="!podeAvancar1"
                        class="btn-primary"
                        :title="!podeAvancar1 ? 'Selecione um animal para continuar' : ''"
                    >
                        Próximo →
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════ PASSO 2 — Comprador ══════ -->
        <div v-if="passo === 2" class="card">
            <div class="card-body space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Para quem você está vendendo?</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Escolha o comprador (cliente). Precisa estar cadastrado em Parceiros.
                    </p>
                </div>

                <div v-if="partners.length === 0" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <div class="font-medium">Nenhum cliente cadastrado</div>
                    <p class="mt-1">
                        Para registrar uma venda, você precisa ter pelo menos um parceiro marcado como "cliente" ou "ambos".
                    </p>
                    <Link :href="route('admin.parceiros.index')" class="inline-block mt-2 text-amber-900 underline">
                        Cadastrar parceiro →
                    </Link>
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2 max-h-[60vh] overflow-y-auto pr-1">
                    <button
                        v-for="p in partners"
                        :key="p.id"
                        type="button"
                        @click="compradorId = p.id"
                        class="text-left rounded-lg border-2 p-3 transition-all hover:border-macaybas-primary hover:shadow-md"
                        :class="compradorId === p.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'"
                    >
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 rounded-full flex items-center justify-center flex-shrink-0"
                                 :class="p.pessoa === 'pj' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700'">
                                {{ p.pessoa === 'pj' ? '🏢' : '👤' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-slate-900 truncate">{{ p.nome }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ p.pessoa === 'pj' ? 'Pessoa Jurídica' : 'Pessoa Física' }}
                                    <span v-if="p.documento"> · {{ p.documento }}</span>
                                </div>
                                <div v-if="p.celular || p.telefone" class="text-xs text-slate-400 mt-0.5">
                                    📞 {{ p.celular || p.telefone }}
                                </div>
                            </div>
                            <div v-if="compradorId === p.id" class="text-emerald-600">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>
                    </button>
                </div>

                <div class="flex justify-between pt-4 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button
                        @click="proximo"
                        :disabled="!podeAvancar2"
                        class="btn-primary"
                        :title="!podeAvancar2 ? 'Selecione o comprador para continuar' : ''"
                    >
                        Próximo →
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════ PASSO 3 — Valor ══════ -->
        <div v-if="passo === 3" class="card">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Por quanto você vendeu?</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Informe o valor total da venda e a data da transação.
                    </p>
                </div>

                <div class="max-w-md space-y-4">
                    <div>
                        <InputLabel value="Valor da venda (R$)" />
                        <InputMoney v-model="valor" />
                        <p class="text-xs text-slate-400 mt-1">Valor total recebido — entrará no fluxo de caixa como receita.</p>
                    </div>
                    <div>
                        <InputLabel value="Data da venda" />
                        <InputDate v-model="dataVenda" :max="new Date().toISOString().slice(0, 10)" required />
                        <p v-if="!dataValida" class="text-xs text-red-600 mt-1">A data não pode ser futura.</p>
                    </div>
                    <div>
                        <InputLabel value="Observações (opcional)" />
                        <textarea v-model="observacoes" rows="2" class="form-textarea"
                                  placeholder="Ex.: Negociado na feira, pagamento à vista via PIX"></textarea>
                    </div>
                </div>

                <div class="flex justify-between pt-4 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button
                        @click="proximo"
                        :disabled="!podeAvancar3"
                        class="btn-primary"
                        :title="!podeAvancar3 ? 'Informe valor e data válidos' : ''"
                    >
                        Próximo →
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════ PASSO 4 — Revisão ══════ -->
        <div v-if="passo === 4" class="card">
            <div class="card-body space-y-5">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Revise os dados antes de confirmar</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Verifique tudo. Ao confirmar, o sistema vai:
                    </p>
                    <ul class="text-sm text-slate-600 mt-2 space-y-1 list-disc pl-5">
                        <li>Registrar a venda no histórico do animal</li>
                        <li>Marcar o animal como <strong>vendido</strong></li>
                        <li>Criar automaticamente um lançamento de <strong>receita</strong> no financeiro</li>
                    </ul>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Animal -->
                    <div class="rounded-lg border border-slate-200 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold mb-2">🐄 Animal</div>
                        <div class="font-semibold text-slate-900">
                            {{ selecionado?.identificacao }}
                            <span v-if="selecionado?.nome" class="font-normal text-slate-600">— {{ selecionado.nome }}</span>
                        </div>
                        <div class="text-sm text-slate-600 mt-1">
                            {{ selecionado?.species?.nome }}
                            <span v-if="selecionado?.breed?.nome"> · {{ selecionado.breed.nome }}</span>
                        </div>
                        <div class="text-sm text-slate-500 mt-1">
                            {{ selecionado?.sexo === 'F' ? 'Fêmea' : 'Macho' }}
                            <span v-if="selecionado?.peso_atual"> · {{ Number(selecionado.peso_atual).toFixed(1) }} kg</span>
                        </div>
                        <button @click="passo = 1" class="text-xs text-macaybas-primary mt-2 hover:underline">
                            Trocar animal
                        </button>
                    </div>

                    <!-- Comprador -->
                    <div class="rounded-lg border border-slate-200 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold mb-2">🤝 Comprador</div>
                        <div class="font-semibold text-slate-900">{{ comprador?.nome }}</div>
                        <div class="text-sm text-slate-600 mt-1">
                            {{ comprador?.pessoa === 'pj' ? 'Pessoa Jurídica' : 'Pessoa Física' }}
                            <span v-if="comprador?.documento"> · {{ comprador.documento }}</span>
                        </div>
                        <div v-if="comprador?.celular || comprador?.telefone" class="text-sm text-slate-500 mt-1">
                            📞 {{ comprador?.celular || comprador?.telefone }}
                        </div>
                        <button @click="passo = 2" class="text-xs text-macaybas-primary mt-2 hover:underline">
                            Trocar comprador
                        </button>
                    </div>

                    <!-- Valor e data -->
                    <div class="rounded-lg border-2 border-emerald-200 bg-emerald-50 p-4 sm:col-span-2">
                        <div class="text-xs uppercase tracking-wide text-emerald-700 font-semibold mb-2">💰 Valor da venda</div>
                        <div class="flex items-baseline gap-3">
                            <div class="text-3xl font-bold text-emerald-900">{{ brl(valorNumerico) }}</div>
                            <div class="text-sm text-emerald-700">em {{ dataBR(dataVenda) }}</div>
                        </div>
                        <div v-if="observacoes" class="mt-3 text-sm text-emerald-800 border-t border-emerald-200 pt-2">
                            <strong>Observações:</strong> {{ observacoes }}
                        </div>
                        <button @click="passo = 3" class="text-xs text-emerald-700 mt-2 hover:underline">
                            Trocar valor
                        </button>
                    </div>
                </div>

                <!-- Erros eventuais do backend -->
                <div v-if="Object.keys(form.errors).length > 0 || $page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                    <div class="font-medium">Erro ao confirmar:</div>
                    <ul class="mt-1 list-disc pl-5">
                        <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
                        <li v-if="$page.props.flash?.error">{{ $page.props.flash.error }}</li>
                    </ul>
                </div>

                <div class="flex justify-between pt-4 border-t border-slate-100">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button
                        @click="confirmar"
                        :disabled="!podeConfirmar || form.processing"
                        class="btn-primary"
                    >
                        <span v-if="form.processing">Registrando venda...</span>
                        <span v-else>✓ Confirmar venda</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════ PASSO 5 — Concluído ══════ -->
        <div v-if="passo === 5" class="card">
            <div class="card-body text-center py-12 space-y-4">
                <div class="inline-flex h-20 w-20 rounded-full bg-emerald-100 items-center justify-center">
                    <svg class="h-12 w-12 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Venda registrada!</h2>
                    <p class="text-slate-600 mt-2">
                        <strong>{{ selecionado?.identificacao }}</strong> foi vendido para
                        <strong>{{ comprador?.nome }}</strong> por <strong>{{ brl(valorNumerico) }}</strong>.
                    </p>
                    <p class="text-sm text-slate-500 mt-2">
                        O lançamento de receita foi criado automaticamente no financeiro.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row justify-center gap-2 pt-4">
                    <button @click="reiniciar" class="btn-primary">Registrar outra venda</button>
                    <Link :href="route('admin.rebanho.animais.show', selecionado.id)" class="btn-outline">
                        Ver histórico do animal
                    </Link>
                    <Link :href="route('admin.financeiro.transacoes.index')" class="btn-outline">
                        Ver no financeiro →
                    </Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
