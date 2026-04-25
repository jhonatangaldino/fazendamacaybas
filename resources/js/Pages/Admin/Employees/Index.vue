<script setup>
import { computed, reactive, ref, watch, onMounted } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputMoney from '@/Components/InputMoney.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMasked from '@/Components/InputMasked.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import { brl, dataBR, cpfMask, telefoneMask, hojeBR } from '@/utils/format.js';
import { apenasDigitos, apenasAlfaNum, validarCpf, validarCnpj } from '@/utils/br-validators.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ employees: Object, filters: Object, farms: Array, setores: Array });
useAutoReload(['employees'], 25000);

const filtros = reactive({ ...props.filters });
const editing = ref(null);
const desligamento = ref(null);
const desligForm = useForm({ data_demissao: '', motivo_demissao: '' });

const form = useForm({
    farm_id: null, nome: '', cpf: '', rg: '', data_nascimento: '',
    telefone: '', celular: '', email: '',
    setor: '', funcao: '', salario: '',
    data_admissao: '', data_demissao: '',
    cep: '', endereco: '', numero: '', bairro: '', cidade: '', estado: '',
    observacoes: '', is_active: true,
    // F3 · D9: tipo de contrato dirige campos obrigatórios.
    // Não é persistido (schema não tem coluna), apenas enviado para
    // ativar a matriz D9 no backend.
    tipo_contrato: 'clt',
});

// ═════════════════════════════════════════════════════════════════════
// F3 · UX anti-erro orientada por domínio — FUNCIONÁRIOS
//
// Espelha a matriz D9 (EmployeeController::validateDomainCoherence):
//
//   clt       → CPF válido (11d) + data_admissao
//   pj        → CNPJ válido (14c) no campo cpf (schema compartilha coluna)
//   diarista  → CPF válido (11d) — sem vínculo contínuo
//   safrista  → CPF válido (11d) + data_admissao + data_demissao (período)
//
// IMPORTANTE sobre o campo cpf:
//   O schema `employees.cpf` é VARCHAR(14) — acomoda CNPJ para PJ.
//   Mesma estratégia usada em `partners.documento`.
//
// Backend D9 permanece como 2ª camada. Aqui impedimos o erro antes
// do submit com watchers + required dinâmico + validação live de DV.
// ═════════════════════════════════════════════════════════════════════

const TIPO_OPTIONS = [
    { value: 'clt',       label: 'CLT (vínculo formal)' },
    { value: 'pj',        label: 'PJ (prestação via empresa)' },
    { value: 'diarista',  label: 'Diarista (serviço eventual)' },
    { value: 'safrista',  label: 'Safrista (contrato por safra)' },
];

const TIPO_LABEL = Object.fromEntries(TIPO_OPTIONS.map((o) => [o.value, o.label]));

const isCLT       = computed(() => form.tipo_contrato === 'clt');
const isPJ        = computed(() => form.tipo_contrato === 'pj');
const isDiarista  = computed(() => form.tipo_contrato === 'diarista');
const isSafrista  = computed(() => form.tipo_contrato === 'safrista');
const isPF        = computed(() => isCLT.value || isDiarista.value || isSafrista.value);

// Requeridos dinâmicos — espelham exatamente a D9
const requiresAdmissao = computed(() => isCLT.value || isSafrista.value);
const requiresDemissao = computed(() => isSafrista.value);
const requiresDocumento = computed(() => true); // todos os 4 tipos exigem algum documento

const labelDocumento = computed(() => (isPJ.value ? 'CNPJ (obrigatório)' : 'CPF (obrigatório)'));
const maskDocumento = computed(() => (isPJ.value ? '##.###.###/####-##' : '###.###.###-##'));
const placeholderDoc = computed(() => (isPJ.value ? '00.000.000/0000-00' : '000.000.000-00'));

const labelSalario = computed(() => {
    if (isCLT.value) return 'Salário mensal';
    if (isPJ.value) return 'Valor mensal do contrato';
    if (isDiarista.value) return 'Valor da diária';
    if (isSafrista.value) return 'Salário mensal durante safra';
    return 'Salário';
});

const labelAdmissao = computed(() => {
    if (isSafrista.value) return 'Início da safra (obrigatório)';
    if (isCLT.value)      return 'Data de admissão (obrigatória)';
    return 'Data de admissão (opcional)';
});

const labelDemissao = computed(() => {
    if (isSafrista.value) return 'Fim da safra (obrigatório)';
    return 'Data de desligamento';
});

/** Validação live do documento (mesmo algoritmo do backend). */
const docDigits = computed(() =>
    isPJ.value ? apenasAlfaNum(form.cpf) : apenasDigitos(form.cpf),
);
const docValido = computed(() => {
    if (!form.cpf) return false;
    const esperado = isPJ.value ? 14 : 11;
    if (docDigits.value.length !== esperado) return false;
    return isPJ.value ? validarCnpj(form.cpf) : validarCpf(form.cpf);
});

const docStatus = computed(() => {
    const len = docDigits.value.length;
    const esperado = isPJ.value ? 14 : 11;
    if (len === 0) return null;
    if (len < esperado) {
        return { tone: 'info', texto: `Faltam ${esperado - len} ${isPJ.value ? 'caractere(s)' : 'dígito(s)'}…` };
    }
    if (len > esperado) {
        return {
            tone: 'warn',
            texto: isPJ.value
                ? 'Documento excede 14 caracteres.'
                : 'Documento tem mais de 11 dígitos. Se é CNPJ, troque o tipo para "PJ".',
        };
    }
    return docValido.value
        ? { tone: 'ok', texto: `${isPJ.value ? 'CNPJ' : 'CPF'} válido.` }
        : { tone: 'err', texto: `${isPJ.value ? 'CNPJ' : 'CPF'} inválido — dígitos verificadores não conferem.` };
});

/** Card contextual por tipo — explica o vínculo em linguagem humana. */
const dicaTipo = computed(() => {
    if (isCLT.value) {
        return {
            titulo: 'CLT — vínculo formal',
            texto: 'Funcionário com carteira assinada. Gera holerite mensal, FGTS (8%), INSS, férias e 13º. Exige CPF válido e data de admissão para contagem de tempo de serviço.',
            tone: 'emerald',
        };
    }
    if (isPJ.value) {
        return {
            titulo: 'PJ — prestação via empresa',
            texto: 'Prestador de serviço como pessoa jurídica. Emite nota fiscal, sem vínculo empregatício. O campo CPF é usado para armazenar o CNPJ (14 caracteres, aceita alfanumérico CGSIM 2026).',
            tone: 'amber',
        };
    }
    if (isDiarista.value) {
        return {
            titulo: 'Diarista — serviço eventual',
            texto: 'Trabalho sob demanda, sem vínculo contínuo. Paga-se por dia trabalhado via recibo. Exige CPF para declaração de pagamento (RPA). Data de admissão é opcional — útil para registrar a primeira contratação.',
            tone: 'slate',
        };
    }
    if (isSafrista.value) {
        return {
            titulo: 'Safrista — contrato por tempo determinado',
            texto: 'Contrato atrelado ao ciclo da safra (plantio → colheita). Tem carteira assinada como CLT, mas com data fim definida. Exige CPF, início (admissão) E fim (desligamento previsto) da safra.',
            tone: 'indigo',
        };
    }
    return null;
});

const dicaTone = computed(() => {
    const t = dicaTipo.value?.tone;
    return {
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-900',
        amber:   'border-amber-200 bg-amber-50 text-amber-900',
        slate:   'border-slate-200 bg-slate-50 text-slate-700',
        indigo:  'border-indigo-200 bg-indigo-50 text-indigo-900',
    }[t] ?? 'border-blue-200 bg-blue-50 text-blue-900';
});

// ── Watcher: trocar tipo limpa documento incompatível ────────────────
watch(
    () => form.tipo_contrato,
    (novo, antigo) => {
        if (novo === antigo) return;

        // Se o documento atual não bate com o formato esperado → limpa
        const esperado = novo === 'pj' ? 14 : 11;
        const atualLen = apenasAlfaNum(form.cpf).length;
        if (atualLen !== esperado) {
            form.cpf = '';
        }

        // Safrista sempre tem data de fim planejada
        // Não-safrista ativo normalmente não tem data de demissão preenchida
        // (só preenche via modal "Desligar"). Limpa ao sair de safrista para
        // não confundir — o usuário preenche via fluxo de desligamento.
        if (antigo === 'safrista' && novo !== 'safrista') {
            form.data_demissao = '';
        }
    },
);

/**
 * Heurística de inferência do tipo_contrato na edição.
 * Schema não persiste tipo_contrato — inferimos dos dados disponíveis
 * ao abrir o form de edição. O usuário pode corrigir manualmente.
 */
function inferirTipo(e) {
    const docLen = apenasAlfaNum(e.cpf ?? '').length;
    if (docLen === 14) return 'pj';
    if (docLen === 11) {
        // 11 dígitos: decidir entre clt/diarista/safrista pela presença de datas
        if (e.data_admissao && e.data_demissao) return 'safrista';
        if (e.data_admissao) return 'clt';
        return 'diarista';
    }
    // doc vazio ou inconsistente → default CLT
    return 'clt';
}

function novo() {
    form.reset();
    form.tipo_contrato = 'clt';
    form.is_active = true;
    editing.value = 'new';
}

// Hub v3 — auto-abrir form ao chegar pelo Hub com `?novo=1`
onMounted(() => {
    const qs = new URLSearchParams(window.location.search);
    if (qs.get('novo') === '1') {
        novo();
    }
});

function editar(e) {
    Object.keys(form.data()).forEach((k) => (form[k] = e[k] ?? form[k]));
    form.tipo_contrato = inferirTipo(e);
    editing.value = e.id;
}

function filtrar() {
    router.get(route('admin.funcionarios.index'), filtros, { preserveState: true, replace: true });
}

/** Bloqueia submit quando campos obrigatórios por domínio não estão OK. */
const podeSalvar = computed(() => {
    if (form.processing) return false;
    if (!form.nome) return false;
    if (requiresDocumento.value && !docValido.value) return false;
    if (requiresAdmissao.value && !form.data_admissao) return false;
    if (requiresDemissao.value && !form.data_demissao) return false;
    return true;
});

function salvar() {
    const opts = { preserveScroll: true, only: ['employees'], onSuccess: () => (editing.value = null) };
    if (editing.value === 'new') form.post(route('admin.funcionarios.store'), opts);
    else form.put(route('admin.funcionarios.update', editing.value), opts);
}
function toggle(row) {
    router.post(route('admin.funcionarios.toggle', row.id), {}, { preserveScroll: true, only: ['employees'] });
}

function abrirDesligamento(row) {
    desligamento.value = row;
    desligForm.reset();
    desligForm.clearErrors();
    desligForm.data_demissao = hojeBR();
}
function confirmarDesligamento() {
    desligForm.delete(route('admin.funcionarios.destroy', desligamento.value.id), {
        preserveScroll: true, only: ['employees'],
        onSuccess: () => { desligamento.value = null; desligForm.reset(); },
    });
}
</script>

<template>
    <Head title="Funcionários" />
    <AdminLayout>
        <template #page-title>Funcionários</template>
        <PageHeader title="Funcionários" subtitle="Cadastro completo com contratos, setores e cargos">
            <template #actions>
                <button @click="novo" class="btn-primary">+ Novo funcionário</button>
            </template>
        </PageHeader>

        <div v-if="editing" class="mb-6 space-y-4">
            <!-- Dica contextual por tipo (F3 — UX guiada) -->
            <div
                v-if="dicaTipo"
                class="rounded-lg border px-4 py-3 text-sm"
                :class="dicaTone"
            >
                <div class="font-medium">{{ dicaTipo.titulo }}</div>
                <div class="mt-1">{{ dicaTipo.texto }}</div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ editing === 'new' ? 'Novo funcionário' : 'Editar funcionário' }}</h2>
                </div>
                <div class="card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- TIPO DE CONTRATO dirige toda a UX abaixo -->
                    <div>
                        <InputLabel value="Tipo de contrato" />
                        <select v-model="form.tipo_contrato" class="form-select" required>
                            <option v-for="o in TIPO_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Define documento (CPF/CNPJ) e datas obrigatórias.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Nome completo" />
                        <input v-model="form.nome" required class="form-input" placeholder="Ex.: José da Silva">
                    </div>

                    <div>
                        <InputLabel value="Fazenda" />
                        <select v-model="form.farm_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.nome }}</option>
                        </select>
                    </div>

                    <!-- Documento: CPF (11) ou CNPJ (14) conforme tipo -->
                    <div>
                        <InputLabel :value="labelDocumento" />
                        <InputMasked
                            v-model="form.cpf"
                            :mask="maskDocumento"
                            :placeholder="placeholderDoc"
                            required
                        />
                        <p
                            v-if="docStatus"
                            class="text-xs mt-1 flex items-center gap-1"
                            :class="{
                                'text-slate-500':   docStatus.tone === 'info',
                                'text-amber-700':   docStatus.tone === 'warn',
                                'text-red-600':     docStatus.tone === 'err',
                                'text-emerald-700': docStatus.tone === 'ok',
                            }"
                        >
                            <svg v-if="docStatus.tone === 'ok'" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <svg v-else-if="docStatus.tone === 'err'" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                            {{ docStatus.texto }}
                        </p>
                    </div>

                    <div v-if="isPF">
                        <InputLabel value="RG (opcional)" />
                        <input v-model="form.rg" class="form-input">
                    </div>
                    <div v-if="isPF">
                        <InputLabel value="Data de nascimento" />
                        <InputDate v-model="form.data_nascimento" />
                    </div>

                    <div><InputLabel value="Telefone" /><InputMasked v-model="form.telefone" :mask="['(##) ####-####', '(##) #####-####']" /></div>
                    <div><InputLabel value="Celular" /><InputMasked v-model="form.celular" mask="(##) #####-####" /></div>
                    <div><InputLabel value="E-mail" /><input type="email" v-model="form.email" class="form-input"></div>

                    <div><InputLabel value="Setor" /><input v-model="form.setor" class="form-input" placeholder="Ex.: Pecuária"></div>
                    <div><InputLabel value="Função / cargo" /><input v-model="form.funcao" class="form-input" placeholder="Ex.: Vaqueiro"></div>
                    <div>
                        <InputLabel :value="labelSalario" />
                        <InputMoney v-model="form.salario" />
                    </div>

                    <!-- Admissão: obrigatória para CLT e safrista -->
                    <div>
                        <InputLabel :value="labelAdmissao" />
                        <InputDate
                            v-model="form.data_admissao"
                            :max="form.data_demissao || undefined"
                            :required="requiresAdmissao"
                        />
                        <p v-if="requiresAdmissao && !form.data_admissao" class="text-xs text-amber-700 mt-1">
                            Obrigatória para {{ isCLT ? 'CLT' : 'safristas' }}.
                        </p>
                    </div>

                    <!-- Demissão: OBRIGATÓRIA só para safrista (fim da safra planejado).
                         Outros tipos têm desligamento via modal — esconde aqui para não confundir. -->
                    <div v-if="isSafrista">
                        <InputLabel :value="labelDemissao" />
                        <InputDate
                            v-model="form.data_demissao"
                            :min="form.data_admissao || undefined"
                            required
                        />
                        <p v-if="!form.data_demissao" class="text-xs text-amber-700 mt-1">
                            Obrigatória — contrato de safra tem prazo definido.
                        </p>
                    </div>
                    <!-- Para funcionários já desligados editando, manter visível como info -->
                    <div v-else-if="form.data_demissao">
                        <InputLabel value="Data de desligamento" />
                        <InputDate v-model="form.data_demissao" :min="form.data_admissao || undefined" />
                        <p class="text-xs text-slate-500 mt-1">Funcionário desligado. Use reativar para limpar.</p>
                    </div>

                    <div><InputLabel value="CEP" /><InputMasked v-model="form.cep" mask="#####-###" /></div>
                    <div class="sm:col-span-2"><InputLabel value="Endereço" /><input v-model="form.endereco" class="form-input"></div>
                    <div><InputLabel value="Número" /><input v-model="form.numero" class="form-input"></div>
                    <div><InputLabel value="Bairro" /><input v-model="form.bairro" class="form-input"></div>
                    <div><InputLabel value="Cidade" /><input v-model="form.cidade" class="form-input"></div>
                    <div><InputLabel value="UF" /><input v-model="form.estado" maxlength="2" class="form-input uppercase"></div>

                    <div class="sm:col-span-3">
                        <InputLabel value="Observações" />
                        <textarea v-model="form.observacoes" rows="2" class="form-textarea"></textarea>
                    </div>
                    <div class="sm:col-span-3 flex justify-between items-center">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" v-model="form.is_active" class="rounded"> Ativo
                        </label>
                        <div class="flex gap-2">
                            <button @click="editing = null" class="btn-outline">Cancelar</button>
                            <button
                                @click="salvar"
                                :disabled="!podeSalvar"
                                class="btn-primary"
                                :title="!podeSalvar && !form.processing ? 'Preencha os campos obrigatórios para o tipo de contrato' : ''"
                            >
                                Salvar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <input v-model="filtros.search" @keyup.enter="filtrar" placeholder="Nome ou CPF" class="form-input">
                <select v-model="filtros.setor" @change="filtrar" class="form-select">
                    <option value="">Todos os setores</option>
                    <option v-for="s in setores" :key="s" :value="s">{{ s }}</option>
                </select>
                <select v-model="filtros.status" @change="filtrar" class="form-select">
                    <option value="ativos">Ativos</option>
                    <option value="inativos">Inativos</option>
                </select>
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'nome', label: 'Nome' },
                { key: 'cpf', label: 'CPF', format: cpfMask },
                { key: 'funcao', label: 'Cargo' },
                { key: 'setor', label: 'Setor' },
                { key: 'celular', label: 'Celular', format: telefoneMask },
                { key: 'data_admissao', label: 'Admissão', format: dataBR },
                { key: 'salario', label: 'Salário', align: 'right', format: brl },
                { key: 'is_active', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="employees.data"
        >
            <template #cell-is_active="{ row }">
                <span v-if="row.is_active" class="badge-green">Ativo</span>
                <span v-else class="badge-slate" :title="row.data_demissao ? `Desligado em ${dataBR(row.data_demissao)}` : 'Desligado'">
                    Inativo<span v-if="row.data_demissao" class="ml-1 text-[11px] text-slate-500">({{ dataBR(row.data_demissao) }})</span>
                </span>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-1 justify-end">
                    <ActionIcon type="edit" title="Editar funcionário" @click="editar(row)" />
                    <ActionIcon v-if="row.is_active" type="power-off" title="Desligar funcionário (exige data)" @click="abrirDesligamento(row)" />
                    <ActionIcon v-else type="reactivate" title="Reativar funcionário" @click="toggle(row)" />
                </div>
            </template>
        </DataTable>

        <!-- Modal: Desligar funcionário com data obrigatória -->
        <Teleport to="body">
            <div v-if="desligamento" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="desligamento = null"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-slate-900">Desligar funcionário</h3>
                        <p class="text-sm text-slate-500 mt-1">Informe a data de desligamento de <strong>{{ desligamento.nome }}</strong>. O registro será mantido — apenas marcado como inativo.</p>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Data do desligamento *" />
                            <InputDate
                                v-model="desligForm.data_demissao"
                                :min="desligamento.data_admissao || null"
                                :max="new Date().toISOString().slice(0,10)"
                                required
                            />
                            <p v-if="desligForm.errors.data_demissao" class="text-xs text-red-600 mt-1">{{ desligForm.errors.data_demissao }}</p>
                            <p v-if="desligamento.data_admissao" class="text-xs text-slate-500 mt-1">
                                Admissão: {{ dataBR(desligamento.data_admissao) }} — a data de desligamento não pode ser anterior a ela nem futura.
                            </p>
                        </div>
                        <div>
                            <InputLabel value="Motivo (opcional)" />
                            <textarea v-model="desligForm.motivo_demissao" rows="2" class="form-textarea"
                                      placeholder="Ex: pedido de demissão, reestruturação, etc."></textarea>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="desligamento = null" class="btn-outline">Cancelar</button>
                        <button @click="confirmarDesligamento" :disabled="desligForm.processing"
                                class="btn-primary bg-red-600 hover:bg-red-700">
                            {{ desligForm.processing ? 'Desligando...' : 'Confirmar desligamento' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
