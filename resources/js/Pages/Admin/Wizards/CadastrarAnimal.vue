<script setup>
/**
 * Wizard de cadastro de animal — adapta passos pelo modo:
 *   cadastro     → 4 passos (espécie → identificação → lote/local → confere)
 *   compra       → 5 passos (+ fornecedor + valor)
 *   nascimento   → 5 passos (+ mãe + peso ao nascer)
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMoney from '@/Components/InputMoney.vue';
import { brl, dataBR, hojeBR } from '@/utils/format.js';
import { emojiEspecie } from '@/utils/emojiEspecie.js';
import { useInlineCreate } from '@/composables/useInlineCreate.js';

const props = defineProps({
    modo: { type: String, required: true }, // cadastro | compra | nascimento
    species: { type: Array, required: true },
    lots: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    partners: { type: Array, default: () => null },
    maes: { type: Array, default: () => null },
});

const TITULOS = {
    cadastro: { titulo: 'Cadastrar animal', subtitulo: 'Adicionar animal por doação, pedigree ou importação.' },
    compra: { titulo: 'Comprar animal', subtitulo: 'Registra a compra com fornecedor e valor de aquisição.' },
    nascimento: { titulo: 'Registrar nascimento', subtitulo: 'A cria entra no rebanho — vincula à mãe e anota peso.' },
};

const PASSOS_BASE = [
    { n: 1, titulo: 'Espécie',     icon: '🐾' },
    { n: 2, titulo: 'Identificação', icon: '🆔' },
    { n: 3, titulo: 'Onde fica',   icon: '📍' },
];

const PASSOS = computed(() => {
    const base = [...PASSOS_BASE];
    if (props.modo === 'compra') {
        base.push({ n: 4, titulo: 'Fornecedor', icon: '🤝' });
        base.push({ n: 5, titulo: 'Pronto!', icon: '✅' });
    } else if (props.modo === 'nascimento') {
        base.push({ n: 4, titulo: 'Origem', icon: '🐣' });
        base.push({ n: 5, titulo: 'Pronto!', icon: '✅' });
    } else {
        base.push({ n: 4, titulo: 'Pronto!', icon: '✅' });
    }
    return base;
});

const ULTIMO_PASSO_INPUT = computed(() => props.modo === 'cadastro' ? 3 : 4);
const PASSO_SUCESSO = computed(() => props.modo === 'cadastro' ? 4 : 5);

const passo = ref(1);
const sucesso = ref(null);
const lotsLocal = ref([...props.lots]);
const locationsLocal = ref([...props.locations]);

const form = useForm({
    species_id: null,
    breed_id: null,
    identificacao: '',
    nome: '',
    sexo: '',
    data_nascimento: props.modo === 'nascimento' ? hojeBR() : '',
    lot_id: null,
    location_id: null,
    categoria: null,
    observacoes: '',
    // compra
    partner_id: null,
    data_aquisicao: hojeBR(),
    valor_aquisicao: 0,
    // nascimento
    mae_id: null,
    peso_nascimento: 0,
    // ─── Auditoria 2026-04-27: cadastro AGREGADO (lote em massa) ────────
    // Ativado automaticamente quando species.gestao = "lote" (ave, peixe, abelha)
    agregado: false,
    lot_nome: '',
    quantidade: 0,
    peso_medio_kg: 0,
    data_inicio: hojeBR(),
    finalidade: null,
});

const speciesSelecionada = computed(() => props.species.find(s => s.id === form.species_id));
const racas = computed(() => speciesSelecionada.value?.breeds ?? []);
const lotesFiltrados = computed(() => lotsLocal.value);

// Detecta automaticamente espécies de gestão por LOTE (aves, peixes, abelhas)
// e ativa o fluxo agregado que NÃO cadastra 1 row por animal.
const isGestaoLote = computed(() => speciesSelecionada.value?.gestao === 'lote');

// Quando o usuário escolhe espécie de lote, garante que o flag agregado
// fique sincronizado com a escolha. Senão limpa o flag.
import { watch as _watch } from 'vue';
_watch(isGestaoLote, (novo) => {
    form.agregado = novo;
    if (novo) {
        // sugere finalidade default por profile
        const p = speciesSelecionada.value?.profile;
        if (p === 'ave_postura') form.finalidade = 'postura';
        else if (p === 'aquicultura_lote') form.finalidade = 'engorda';
        else form.finalidade = 'engorda';
    }
});

const novoLote = useInlineCreate({
    endpoint: route('admin.rebanho.lotes.inline'),
    initialForm: { nome: '', species_id: null },
    onCreated: (l) => {
        lotsLocal.value = [...lotsLocal.value, l];
        form.lot_id = l.id;
    },
});
const novoLocal = useInlineCreate({
    endpoint: route('admin.rebanho.locais.inline'),
    initialForm: { nome: '', tipo: 'pasto' },
    onCreated: (l) => {
        locationsLocal.value = [...locationsLocal.value, l];
        form.location_id = l.id;
    },
});

const podeAvancar1 = computed(() => !!form.species_id);
const podeAvancar2 = computed(() => {
    // Modo agregado: passo 2 pede nome do lote + quantidade (não brinco/sexo)
    if (isGestaoLote.value) {
        return form.lot_nome.trim().length > 1 && form.quantidade >= 1;
    }
    return !!form.identificacao && !!form.sexo;
});
const podeAvancar3 = computed(() => true); // lote/local opcionais
const podeAvancar4 = computed(() => {
    if (props.modo === 'compra') return !!form.partner_id && form.valor_aquisicao > 0;
    if (props.modo === 'nascimento') return !!form.data_nascimento;
    return true;
});

function avancar() {
    if (passo.value < PASSO_SUCESSO.value) passo.value++;
}
function voltar()  { if (passo.value > 1) passo.value--; }

function confirmar() {
    const snap = {
        especie: speciesSelecionada.value?.nome,
        identificacao: isGestaoLote.value ? form.lot_nome : form.identificacao,
        nome: isGestaoLote.value ? `${form.quantidade} unidades` : form.nome,
        sexo: form.sexo === 'F' ? 'Fêmea' : 'Macho',
        modo: props.modo,
        valor: form.valor_aquisicao,
        peso: form.peso_nascimento,
        agregado: isGestaoLote.value,
        quantidade: form.quantidade,
        emoji: emojiEspecie(speciesSelecionada.value?.nome),
    };
    const url = route('admin.fluxos.cadastrar-animal.store', { modo: props.modo });
    form.post(url, {
        preserveScroll: false,
        onSuccess: () => {
            sucesso.value = snap;
            passo.value = PASSO_SUCESSO.value;
        },
    });
}

function reiniciar() {
    form.reset();
    form.data_aquisicao = hojeBR();
    sucesso.value = null;
    passo.value = 1;
}
</script>

<template>
    <Head :title="TITULOS[modo].titulo" />
    <AdminLayout>
        <template #page-title>{{ TITULOS[modo].titulo }}</template>

        <PageHeader :title="TITULOS[modo].titulo" :subtitle="TITULOS[modo].subtitulo">
            <template #actions>
                <Link :href="route('admin.rebanho.animais.index')" class="btn-outline">Sair</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- PASSO 1 — Espécie -->
        <div v-if="passo === 1" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Que tipo de animal?</h2>
                <p class="text-base text-slate-600">A espécie define quais perguntas vamos fazer depois.</p>

                <div class="grid gap-3 sm:grid-cols-3">
                    <button v-for="s in species" :key="s.id" type="button" @click="form.species_id = s.id"
                        :data-species-id="s.id"
                        class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary"
                        :class="form.species_id === s.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                        <div class="text-3xl mb-1">{{ emojiEspecie(s.nome) }}</div>
                        <div class="font-bold text-slate-900">{{ s.nome }}</div>
                        <div class="text-xs text-slate-500 mt-0.5">{{ s.gestao || s.profile }}</div>
                    </button>
                </div>

                <div class="flex justify-end pt-4 border-t">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2 — Identificação -->
        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <!-- ─── MODO AGREGADO (aves, peixes, abelhas) ─────────────────── -->
                <template v-if="isGestaoLote">
                    <div>
                        <h2 class="text-2xl font-semibold text-slate-900">Quantos animais? Sem identificação individual.</h2>
                        <p class="text-base text-slate-600 mt-1">
                            Para <strong>{{ speciesSelecionada?.nome?.toLowerCase() }}</strong>, o sistema gerencia o
                            lote em massa — não precisa cadastrar 1 a 1 nem usar brinco.
                        </p>
                    </div>

                    <div>
                        <InputLabel value="Nome do lote *" />
                        <input v-model="form.lot_nome" type="text" maxlength="120"
                               class="form-input text-lg py-3"
                               :placeholder="speciesSelecionada?.profile === 'aquicultura_lote' ? 'Ex.: Tanque 1 — Tilápias' : 'Ex.: Galpão Norte — Frangos lote 5'" />
                        <p class="text-xs text-slate-500 mt-1">
                            Ajuda você a identificar este grupo no sistema (galpão, tanque, baia, etc.).
                        </p>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Quantidade *" />
                            <input v-model.number="form.quantidade" type="number" step="1" min="1"
                                   class="form-input text-2xl py-3 font-mono font-bold" placeholder="500" />
                            <p class="text-xs text-slate-500 mt-1">Total de cabeças/cabecinhas/peixes/aves do lote.</p>
                        </div>
                        <div>
                            <InputLabel value="Peso médio em kg (opcional)" />
                            <input v-model.number="form.peso_medio_kg" type="number" step="0.001" min="0"
                                   class="form-input py-3 font-mono" placeholder="0,045" />
                            <p class="text-xs text-slate-500 mt-1">Peso por unidade. Ex.: pintinho 45g = 0,045.</p>
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Finalidade do lote" />
                        <select v-model="form.finalidade" class="form-select py-3">
                            <option value="engorda">Engorda</option>
                            <option value="postura">Postura (ovos)</option>
                            <option value="reproducao">Reprodução / matrizeiro</option>
                            <option value="recria">Recria</option>
                            <option value="leite">Leite</option>
                            <option value="corte">Corte</option>
                            <option value="outros">Outros</option>
                        </select>
                    </div>
                </template>

                <!-- ─── MODO INDIVIDUAL (bovinos, equinos, suínos, ovinos, caprinos) ─── -->
                <template v-else>
                    <h2 class="text-2xl font-semibold text-slate-900">
                        <span v-if="modo === 'nascimento'">Como vai chamar a cria?</span>
                        <span v-else>Como identificar o animal?</span>
                    </h2>

                    <div>
                        <InputLabel value="Brinco / identificação *" />
                        <input v-model="form.identificacao" data-cy="input-identificacao"
                            class="form-input text-lg py-3" placeholder="Ex.: 12345" />
                    </div>
                    <div>
                        <InputLabel value="Nome (opcional)" />
                        <input v-model="form.nome" class="form-input" placeholder="Ex.: Mimosa" />
                    </div>

                    <div>
                        <InputLabel value="Sexo *" />
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="form.sexo = 'F'" data-sexo="F"
                                class="rounded-lg border-2 p-3 text-base font-medium"
                                :class="form.sexo === 'F' ? 'border-pink-400 bg-pink-50 text-pink-900' : 'border-slate-200'">
                                ♀ Fêmea
                            </button>
                            <button type="button" @click="form.sexo = 'M'" data-sexo="M"
                                class="rounded-lg border-2 p-3 text-base font-medium"
                                :class="form.sexo === 'M' ? 'border-blue-400 bg-blue-50 text-blue-900' : 'border-slate-200'">
                                ♂ Macho
                            </button>
                        </div>
                    </div>
                </template>

                <div v-if="racas.length" class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Raça (opcional)" />
                        <select v-model="form.breed_id" class="form-select">
                            <option :value="null">— Sem raça definida —</option>
                            <option v-for="r in racas" :key="r.id" :value="r.id">{{ r.nome }}</option>
                        </select>
                    </div>
                    <div v-if="modo !== 'nascimento'">
                        <InputLabel value="Data de nascimento (opcional)" />
                        <InputDate v-model="form.data_nascimento" :max="hojeBR()" />
                    </div>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar2" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 3 — Lote / Local -->
        <div v-if="passo === 3" class="card max-w-3xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Onde vai ficar?</h2>
                <p class="text-base text-slate-600">Lote = grupo lógico (ex.: Bezerros 2026). Pasto/local = lugar físico.</p>

                <div>
                    <InputLabel value="Lote (opcional)" />
                    <select v-model="form.lot_id" class="form-select">
                        <option :value="null">— Sem lote definido —</option>
                        <option v-for="l in lotesFiltrados" :key="l.id" :value="l.id">{{ l.nome }}</option>
                    </select>
                    <button type="button" @click="novoLote.abrir()" data-cy="abrir-lote"
                        class="inline-flex items-center min-h-9 px-3 py-2 mt-1 text-xs text-macaybas-primary hover:bg-macaybas-primary-50 rounded-md">
                        + Criar lote novo
                    </button>
                </div>

                <div>
                    <InputLabel value="Pasto / local (opcional)" />
                    <select v-model="form.location_id" class="form-select">
                        <option :value="null">— Sem local definido —</option>
                        <option v-for="l in locationsLocal" :key="l.id" :value="l.id">{{ l.nome }}</option>
                    </select>
                    <button type="button" @click="novoLocal.abrir()" data-cy="abrir-local"
                        class="inline-flex items-center min-h-9 px-3 py-2 mt-1 text-xs text-macaybas-primary hover:bg-macaybas-primary-50 rounded-md">
                        + Criar pasto/local novo
                    </button>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 4 (modo compra) — Fornecedor + valor -->
        <div v-if="passo === 4 && modo === 'compra'" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Para quem comprou? Quanto pagou?</h2>

                <div>
                    <InputLabel value="Fornecedor *" />
                    <select v-model="form.partner_id" class="form-select">
                        <option :value="null">— Selecione —</option>
                        <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.nome }}</option>
                    </select>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Valor da aquisição *" />
                        <InputMoney v-model="form.valor_aquisicao" data-cy="input-valor"
                            class="text-xl py-3 font-mono" />
                    </div>
                    <div>
                        <InputLabel value="Data da compra" />
                        <InputDate v-model="form.data_aquisicao" :max="hojeBR()" />
                    </div>
                </div>
                <div>
                    <InputLabel value="Observações (opcional)" />
                    <textarea v-model="form.observacoes" rows="2" class="form-textarea"
                        placeholder="Nota fiscal, GTA, observações da compra"></textarea>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="!podeAvancar4 || form.processing"
                        data-cy="confirmar"
                        class="btn-primary px-8 py-2.5">
                        {{ form.processing ? 'Salvando…' : '✓ Registrar compra' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO 4 (modo nascimento) — Mãe + peso ao nascer -->
        <div v-if="passo === 4 && modo === 'nascimento'" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">De quem é a cria?</h2>

                <div>
                    <InputLabel value="Mãe (opcional)" />
                    <select v-model="form.mae_id" class="form-select">
                        <option :value="null">— Não vincular mãe —</option>
                        <option v-for="m in maes" :key="m.id" :value="m.id">
                            {{ m.identificacao }}<template v-if="m.nome"> · {{ m.nome }}</template>
                        </option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Lista mostra fêmeas ativas. Vínculo ajuda em pedigree e linhagem.</p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Data do nascimento *" />
                        <InputDate v-model="form.data_nascimento" :max="hojeBR()" />
                    </div>
                    <div>
                        <InputLabel value="Peso ao nascer (kg)" />
                        <input v-model="form.peso_nascimento" type="number" step="0.01" min="0"
                            class="form-input font-mono" placeholder="Ex.: 35.5" />
                    </div>
                </div>

                <div>
                    <InputLabel value="Observações (opcional)" />
                    <textarea v-model="form.observacoes" rows="2" class="form-textarea"
                        placeholder="Parto normal, gemelar, complicações"></textarea>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="!podeAvancar4 || form.processing"
                        data-cy="confirmar"
                        class="btn-primary px-8 py-2.5">
                        {{ form.processing ? 'Salvando…' : '✓ Registrar nascimento' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO 4 (modo cadastro) — Confirmação direta -->
        <div v-if="passo === 4 && modo === 'cadastro'" class="card max-w-2xl mx-auto">
            <div class="card-body text-center py-8 space-y-4" v-if="!sucesso">
                <h2 class="text-2xl font-semibold text-slate-900">Confere e salva</h2>
                <div class="rounded-xl border-2 border-emerald-200 bg-emerald-50 p-4 text-left text-sm">
                    <ul class="text-emerald-900 space-y-1.5">
                        <li>{{ emojiEspecie(speciesSelecionada?.nome) }} <strong>{{ speciesSelecionada?.nome }}</strong></li>
                        <li>🆔 Brinco: <strong>{{ form.identificacao }}</strong>{{ form.nome ? ' · ' + form.nome : '' }}</li>
                        <li>{{ form.sexo === 'F' ? '♀' : '♂' }} {{ form.sexo === 'F' ? 'Fêmea' : 'Macho' }}</li>
                    </ul>
                </div>
                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="form.processing" data-cy="confirmar"
                        class="btn-primary px-8 py-2.5">
                        {{ form.processing ? 'Salvando…' : '✓ Cadastrar animal' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO SUCESSO -->
        <div v-if="passo === PASSO_SUCESSO && sucesso" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
            <div class="card-body text-center py-8 space-y-4">
                <div class="text-5xl">{{ sucesso.emoji ?? '🐾' }}</div>
                <h2 class="text-2xl font-semibold text-slate-900">
                    <span v-if="sucesso.modo === 'compra'">Compra registrada!</span>
                    <span v-else-if="sucesso.modo === 'nascimento'">Nascimento registrado!</span>
                    <span v-else>Animal cadastrado!</span>
                </h2>
                <p class="text-slate-700">
                    <strong>{{ sucesso.identificacao }}</strong> · {{ sucesso.especie }} {{ sucesso.sexo?.toLowerCase() }}
                    <span v-if="sucesso.nome">({{ sucesso.nome }})</span>
                </p>
                <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-left text-sm">
                    <p>✓ Animal entrou no rebanho ativo</p>
                    <p v-if="sucesso.modo === 'compra' && sucesso.valor > 0">✓ Valor de aquisição registrado: <strong>{{ brl(sucesso.valor) }}</strong></p>
                    <p v-if="sucesso.modo === 'nascimento' && sucesso.peso > 0">✓ Peso ao nascer: <strong>{{ sucesso.peso }} kg</strong></p>
                    <p>✓ Pode pesar, vacinar e mover este animal a partir de agora</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 justify-center pt-3">
                    <button @click="reiniciar" class="btn-primary">Cadastrar outro</button>
                    <Link :href="route('admin.rebanho.animais.index')" class="btn-outline">Ver rebanho</Link>
                </div>
            </div>
        </div>

        <!-- Modais inline -->
        <Teleport to="body">
            <div v-if="novoLote.modalAberto.value" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novoLote.fechar"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold mb-3">Novo lote</h3>
                    <div>
                        <InputLabel value="Nome do lote *" />
                        <input v-model="novoLote.form.value.nome" data-cy="lote-nome"
                            class="form-input" placeholder="Ex.: Bezerros 2026" />
                    </div>
                    <p v-if="novoLote.erro.value" class="mt-3 text-xs text-red-700">{{ novoLote.erro.value }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoLote.fechar" class="btn-outline">Cancelar</button>
                        <button @click="novoLote.salvar" data-cy="lote-salvar"
                            :disabled="novoLote.salvando.value || !novoLote.form.value.nome?.trim()"
                            class="btn-primary">
                            {{ novoLote.salvando.value ? 'Salvando…' : 'Cadastrar' }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="novoLocal.modalAberto.value" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novoLocal.fechar"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold mb-3">Novo pasto / local</h3>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Nome *" />
                            <input v-model="novoLocal.form.value.nome" data-cy="local-nome"
                                class="form-input" placeholder="Ex.: Pasto Norte, Curral 1" />
                        </div>
                        <div>
                            <InputLabel value="Tipo" />
                            <select v-model="novoLocal.form.value.tipo" class="form-select">
                                <option value="pasto">Pasto</option>
                                <option value="curral">Curral</option>
                                <option value="piquete">Piquete</option>
                                <option value="baia">Baia</option>
                                <option value="tanque">Tanque</option>
                                <option value="galpao">Galpão</option>
                            </select>
                        </div>
                    </div>
                    <p v-if="novoLocal.erro.value" class="mt-3 text-xs text-red-700">{{ novoLocal.erro.value }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoLocal.fechar" class="btn-outline">Cancelar</button>
                        <button @click="novoLocal.salvar" data-cy="local-salvar"
                            :disabled="novoLocal.salvando.value || !novoLocal.form.value.nome?.trim()"
                            class="btn-primary">
                            {{ novoLocal.salvando.value ? 'Salvando…' : 'Cadastrar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
