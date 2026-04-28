<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import InputMoney from '@/Components/InputMoney.vue';
import InputDate from '@/Components/InputDate.vue';
import { hojeBR } from '@/utils/format.js';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';
import { useToast } from '@/composables/useToast';

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
    data_vencimento: props.transaction?.data_vencimento ?? hojeBR(),
    data_pagamento: props.transaction?.data_pagamento ?? '',
    status: props.transaction?.status ?? 'pendente',
    forma_pagamento: props.transaction?.forma_pagamento ?? '',
    numero_documento: props.transaction?.numero_documento ?? '',
});

const isEdit = !!props.transaction;

// ═════════════════════════════════════════════════════════════════════
// F3 · UX anti-erro orientada por domínio — LANÇAMENTOS FINANCEIROS
//
// Espelha a matriz D6 (FinancialTransactionController):
//
//   tipo=receita → categorias com tipo='financeiro_receita'
//   tipo=despesa → categorias com tipo='financeiro_despesa'
//
// Aqui no front, impedimos que o usuário:
//   - veja categorias do tipo oposto ao selecionado
//   - mantenha category_id "órfão" ao trocar de receita ↔ despesa
//   - escolha descrição vaga para lançamentos de alto impacto contábil
//
// O backend D6 permanece como 2ª camada.
// ═════════════════════════════════════════════════════════════════════

const isReceita = computed(() => form.tipo === 'receita');
const isDespesa = computed(() => form.tipo === 'despesa');

// Usa versão local pra refletir categorias criadas inline durante o lançamento.
// Inicializadas a partir de props mas atualizam ao criar nova categoria.
const categorias = computed(() =>
    isReceita.value ? categoriasLocais.value.receita : categoriasLocais.value.despesa,
);

const labelValor = computed(() => (isReceita.value ? 'Valor a receber (entrada)' : 'Valor a pagar (saída)'));
const labelParceiro = computed(() => (isReceita.value ? 'Cliente' : 'Fornecedor'));
const placeholderDescricao = computed(() =>
    isReceita.value
        ? 'Ex.: Venda de 20 cabeças Nelore ao frigorífico'
        : 'Ex.: Compra de ração para bezerros — Cooperativa',
);

/** Card contextual por tipo — ajuda o usuário a não classificar errado. */
const dicaTipo = computed(() =>
    isReceita.value
        ? {
              titulo: 'Lançamento de RECEITA (entrada)',
              texto: 'Registros de dinheiro que a fazenda vai receber (venda de animais, produção, leite, arrendamento). A lista de categorias abaixo é filtrada automaticamente para mostrar apenas categorias de receita.',
              tone: 'emerald',
          }
        : {
              titulo: 'Lançamento de DESPESA (saída)',
              texto: 'Registros de dinheiro que a fazenda vai pagar (ração, medicamentos, combustível, salários, manutenção). A lista de categorias abaixo é filtrada automaticamente para mostrar apenas categorias de despesa.',
              tone: 'rose',
          },
);

// Aviso amigável quando não há categorias do tipo escolhido
const semCategoriasDoTipo = computed(() => categorias.value.length === 0);

// Aviso amigável quando tenta pagar sem data/forma
const statusPago = computed(() => form.status === 'pago');
const formaPagamentoRecomendada = computed(() => statusPago.value && !form.forma_pagamento);

// Detecta lançamento gerado automaticamente por integração F2.x
// (não são editáveis no campo `tipo` — mudaria a coerência contábil).
const geradoPorIntegracao = computed(() => {
    const doc = props.transaction?.numero_documento ?? '';
    return /^(ANIMAL_EVENT|STOCK_MOVEMENT|FIELD_APP|MAINTENANCE|HARVEST):/.test(doc);
});
const origemIntegracao = computed(() => {
    const doc = props.transaction?.numero_documento ?? '';
    const m = doc.match(/^(ANIMAL_EVENT|STOCK_MOVEMENT|FIELD_APP|MAINTENANCE|HARVEST):(\d+)/);
    if (!m) return null;
    const label = {
        ANIMAL_EVENT: 'venda de animal',
        STOCK_MOVEMENT: 'entrada de estoque',
        FIELD_APP: 'aplicação agrícola',
        MAINTENANCE: 'manutenção de máquina',
        HARVEST: 'colheita agrícola',
    }[m[1]];
    return { label, id: m[2], marker: m[1] };
});

// ── Watcher: ao trocar tipo, LIMPA category_id (evita id órfão do outro tipo) ──
watch(
    () => form.tipo,
    (novo, antigo) => {
        if (novo === antigo) return;
        // Se a categoria atual não está na nova lista → limpa
        if (form.category_id) {
            const novaLista = novo === 'receita' ? props.categoriasReceita : props.categoriasDespesa;
            const aindaValida = novaLista.some((c) => c.id === form.category_id);
            if (!aindaValida) {
                form.category_id = null;
            }
        }
    },
);

// ── Watcher: ao marcar status=pago, sugere data de hoje se não informada ──
watch(
    () => form.status,
    (novo) => {
        if (novo === 'pago' && !form.data_pagamento) {
            form.data_pagamento = hojeBR();
        }
    },
);

function submit() {
    if (isEdit) form.put(route('admin.financeiro.transacoes.update', props.transaction.id));
    else form.post(route('admin.financeiro.transacoes.store'));
}

// ─── Criar categoria inline (sem sair do form) ─────────────────────
// Antes a UI dizia "Peça ao admin pra criar". Mas o cliente É admin do
// próprio tenant — pode criar direto. Modal inline + AJAX.
const { toast } = useToast();
const novaCategoriaAberta = ref(false);
const novaCategoriaForm = ref({ nome: '', tipo: '' });
const salvandoCategoria = ref(false);
const categoriasLocais = ref({
    receita: [...(props.categoriasReceita || [])],
    despesa: [...(props.categoriasDespesa || [])],
});

useBodyScrollLock(novaCategoriaAberta);

function abrirNovaCategoria() {
    // Tipo é fixo pelo contexto (despesa ou receita); user só preenche o nome
    novaCategoriaForm.value = {
        nome: '',
        tipo: isReceita.value ? 'financeiro_receita' : 'financeiro_despesa',
    };
    novaCategoriaAberta.value = true;
}

async function salvarNovaCategoria() {
    if (!novaCategoriaForm.value.nome.trim() || salvandoCategoria.value) return;
    salvandoCategoria.value = true;
    try {
        const csrf = document.querySelector('meta[name=csrf-token]')?.content;
        const resp = await fetch(route('admin.financeiro.categorias.inline'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(novaCategoriaForm.value),
        });
        if (!resp.ok) {
            const erro = await resp.json().catch(() => ({}));
            throw new Error(erro?.message || 'Falha ao criar categoria');
        }
        const cat = await resp.json();
        // Adiciona à lista local (categorias do tipo correto) e auto-seleciona
        const bucket = isReceita.value ? 'receita' : 'despesa';
        categoriasLocais.value[bucket].push({ id: cat.id, nome: cat.nome });
        categoriasLocais.value[bucket].sort((a, b) => a.nome.localeCompare(b.nome, 'pt-BR'));
        form.category_id = cat.id;
        novaCategoriaAberta.value = false;
        toast.success(`Categoria "${cat.nome}" criada e selecionada.`);
    } catch (e) {
        toast.error(e.message || 'Erro ao criar categoria.');
    } finally {
        salvandoCategoria.value = false;
    }
}

function handleEscNovaCategoria(e) {
    if (e.key === 'Escape' && novaCategoriaAberta.value) {
        e.stopPropagation();
        novaCategoriaAberta.value = false;
    }
}
onMounted(() => window.addEventListener('keydown', handleEscNovaCategoria));
onBeforeUnmount(() => window.removeEventListener('keydown', handleEscNovaCategoria));
</script>

<template>
    <Head :title="isEdit ? 'Editar lançamento' : 'Novo lançamento'" />
    <AdminLayout>
        <template #page-title>{{ isEdit ? 'Editar lançamento' : 'Novo lançamento' }}</template>
        <PageHeader :title="isEdit ? 'Editar lançamento' : 'Novo lançamento'">
            <template #actions>
                <Link :href="route('admin.financeiro.transacoes.index')" class="btn-outline">Voltar</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="space-y-6 max-w-4xl">
            <!-- Aviso: lançamento automático de integração cross-módulo -->
            <div
                v-if="isEdit && geradoPorIntegracao && origemIntegracao"
                class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900"
            >
                <div class="flex items-start gap-2">
                    <svg class="h-5 w-5 mt-0.5 flex-shrink-0 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <div>
                        <div class="font-medium">Lançamento gerado automaticamente</div>
                        <div class="mt-1 text-indigo-800">
                            Este lançamento foi criado pela integração de <strong>{{ origemIntegracao.label }}</strong>
                            (marcador <span class="font-mono text-xs">{{ origemIntegracao.marker }}:{{ origemIntegracao.id }}</span>).
                            Alterar o tipo receita↔despesa aqui quebra a correspondência contábil com a origem —
                            ajuste valor, conta ou data se necessário, mas evite trocar o tipo.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dica contextual por tipo (F3 — UX guiada) -->
            <div
                class="rounded-lg border px-4 py-3 text-sm"
                :class="dicaTipo.tone === 'emerald'
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
                    : 'border-rose-200 bg-rose-50 text-rose-900'"
            >
                <div class="font-medium">{{ dicaTipo.titulo }}</div>
                <div class="mt-1" :class="dicaTipo.tone === 'emerald' ? 'text-emerald-800' : 'text-rose-800'">
                    {{ dicaTipo.texto }}
                </div>
            </div>

            <div class="card">
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <!-- TIPO dirige toda a UX abaixo -->
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="form.tipo" class="form-select" required>
                            <option value="receita">Receita (entrada de dinheiro)</option>
                            <option value="despesa">Despesa (saída de dinheiro)</option>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Define quais categorias ficam disponíveis abaixo.</p>
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
                        <input v-model="form.descricao" required class="form-input" :placeholder="placeholderDescricao">
                        <InputError :message="form.errors.descricao" />
                    </div>

                    <div>
                        <InputLabel :value="labelValor" />
                        <InputMoney v-model="form.valor" />
                        <InputError :message="form.errors.valor" />
                    </div>
                    <div>
                        <InputLabel value="Vencimento" />
                        <InputDate v-model="form.data_vencimento" />
                        <InputError :message="form.errors.data_vencimento" />
                    </div>

                    <div>
                        <InputLabel :value="'Categoria de ' + (isReceita ? 'receita' : 'despesa')" />
                        <select v-model="form.category_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nome }}</option>
                        </select>

                        <!-- Botão pra criar categoria SEM sair do form (inline AJAX) -->
                        <div class="mt-1 flex items-center gap-3 flex-wrap">
                            <button type="button" @click="abrirNovaCategoria"
                                    class="inline-flex items-center gap-1 text-xs text-macaybas-primary hover:underline">
                                <span class="text-base leading-none">＋</span>
                                Criar nova categoria de {{ isReceita ? 'receita' : 'despesa' }}
                            </button>
                            <p v-if="!categorias.length" class="text-xs text-amber-700">
                                Nenhuma categoria cadastrada ainda — crie a primeira aqui.
                            </p>
                            <p v-else-if="!form.category_id" class="text-xs text-slate-400">
                                Categoria ajuda a agrupar no fluxo de caixa e no DRE.
                            </p>
                        </div>
                        <InputError :message="form.errors.category_id" />
                    </div>
                    <div>
                        <InputLabel value="Centro de custo (opcional)" />
                        <select v-model="form.cost_center_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="c in costCenters" :key="c.id" :value="c.id">{{ c.codigo }} — {{ c.nome }}</option>
                        </select>
                    </div>

                    <div>
                        <InputLabel :value="labelParceiro + ' (opcional)'" />
                        <select v-model="form.partner_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.nome }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Status" />
                        <select v-model="form.status" class="form-select">
                            <option value="pendente">Pendente</option>
                            <option value="pago">{{ isReceita ? 'Recebido' : 'Pago' }}</option>
                            <option value="atrasado">Atrasado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>

                    <div v-if="statusPago">
                        <InputLabel :value="isReceita ? 'Data de recebimento' : 'Data de pagamento'" />
                        <InputDate v-model="form.data_pagamento" required />
                    </div>
                    <div v-if="statusPago">
                        <InputLabel :value="isReceita ? 'Forma de recebimento' : 'Forma de pagamento'" />
                        <select v-model="form.forma_pagamento" class="form-select">
                            <option value="">—</option>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="pix">PIX</option>
                            <option value="cartao">Cartão</option>
                            <option value="boleto">Boleto</option>
                            <option value="transferencia">Transferência</option>
                            <option value="cheque">Cheque</option>
                        </select>
                        <p v-if="formaPagamentoRecomendada" class="text-xs text-amber-700 mt-1">
                            Recomendado preencher — ajuda em relatórios de conciliação.
                        </p>
                    </div>

                    <div>
                        <InputLabel value="Nº documento / NF (opcional)" />
                        <input
                            v-model="form.numero_documento"
                            class="form-input"
                            :readonly="geradoPorIntegracao"
                            :class="geradoPorIntegracao ? 'bg-slate-50 text-slate-500 cursor-not-allowed' : ''"
                        >
                        <p v-if="geradoPorIntegracao" class="text-xs text-slate-500 mt-1">
                            Marcador de integração — não editar (garante idempotência).
                        </p>
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

        <!-- Modal: criar categoria inline (AJAX, fetch JSON, zero navigation).
             useBodyScrollLock previne pull-to-refresh + scroll do body. -->
        <Teleport to="body">
            <div v-if="novaCategoriaAberta" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novaCategoriaAberta = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">
                                Nova categoria de {{ isReceita ? 'receita' : 'despesa' }}
                            </h3>
                            <p class="text-xs text-slate-500 mt-1">
                                Crie agora — não precisa sair desta tela. Já fica disponível pra escolher.
                            </p>
                        </div>
                        <button @click="novaCategoriaAberta = false"
                                class="text-slate-400 hover:text-slate-600 text-2xl leading-none -mt-1"
                                aria-label="Fechar">&times;</button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Nome da categoria *" />
                            <input v-model="novaCategoriaForm.nome" type="text" maxlength="120" required
                                   :placeholder="isReceita ? 'Ex.: Venda de bezerros' : 'Ex.: Ração, Vacinas, Combustível'"
                                   class="form-input"
                                   @keyup.enter="salvarNovaCategoria">
                        </div>
                        <p class="text-xs text-slate-500">
                            Tipo: <strong>{{ isReceita ? 'Receita' : 'Despesa' }}</strong> (definido pelo contexto desta tela)
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 mt-6">
                        <button type="button" @click="novaCategoriaAberta = false" class="btn-outline">Cancelar</button>
                        <button type="button" @click="salvarNovaCategoria"
                                :disabled="salvandoCategoria || !novaCategoriaForm.nome.trim()"
                                class="btn-primary">
                            {{ salvandoCategoria ? 'Salvando…' : 'Criar categoria' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
