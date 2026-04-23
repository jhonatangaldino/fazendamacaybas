<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import InputMasked from '@/Components/InputMasked.vue';
import { apenasDigitos, apenasAlfaNum, validarCpf, validarCnpj } from '@/utils/br-validators.js';

const props = defineProps({ partner: Object });
const isEdit = !!props.partner;

const form = useForm({
    tipo: props.partner?.tipo ?? 'fornecedor',
    pessoa: props.partner?.pessoa ?? 'pj',
    nome: props.partner?.nome ?? '',
    nome_fantasia: props.partner?.nome_fantasia ?? '',
    documento: props.partner?.documento ?? '',
    inscricao_estadual: props.partner?.inscricao_estadual ?? '',
    email: props.partner?.email ?? '',
    telefone: props.partner?.telefone ?? '',
    celular: props.partner?.celular ?? '',
    cep: props.partner?.cep ?? '',
    endereco: props.partner?.endereco ?? '',
    numero: props.partner?.numero ?? '',
    complemento: props.partner?.complemento ?? '',
    bairro: props.partner?.bairro ?? '',
    cidade: props.partner?.cidade ?? '',
    estado: props.partner?.estado ?? '',
    observacoes: props.partner?.observacoes ?? '',
    is_active: props.partner?.is_active ?? true,
});

// ═════════════════════════════════════════════════════════════════════
// F3 · UX anti-erro orientada por domínio — PARCEIROS
//
// Espelha a matriz D4 (PartnerController::validateDomainCoherence):
//
//   pessoa=pf → CPF obrigatório, 11 dígitos, DV válido
//   pessoa=pj → CNPJ obrigatório, 14 caracteres (alfanumérico 2026-ready),
//               DV válido
//
// O backend D4 permanece como 2ª camada. Aqui impedimos que o usuário
// sequer veja campos incoerentes (IE/nome fantasia em PF) e bloqueamos
// o submit com DV inválido.
// ═════════════════════════════════════════════════════════════════════

const isPF = computed(() => form.pessoa === 'pf');
const isPJ = computed(() => form.pessoa === 'pj');

const labelNome = computed(() => (isPF.value ? 'Nome completo' : 'Razão social'));
const placeholderNome = computed(() =>
    isPF.value ? 'Ex.: João da Silva' : 'Ex.: Agropecuária Macaybas Ltda.',
);

const labelDocumento = computed(() => (isPF.value ? 'CPF' : 'CNPJ'));
const placeholderDocumento = computed(() => (isPF.value ? '000.000.000-00' : '00.000.000/0000-00'));
const maskDocumento = computed(() => (isPF.value ? '###.###.###-##' : '##.###.###/####-##'));

// Validação live do documento (usa validadores que espelham o backend)
const docDigits = computed(() =>
    isPF.value ? apenasDigitos(form.documento) : apenasAlfaNum(form.documento),
);
const docCompleto = computed(() => docDigits.value.length === (isPF.value ? 11 : 14));
const docValido = computed(() => {
    if (!docCompleto.value) return false;
    return isPF.value ? validarCpf(form.documento) : validarCnpj(form.documento);
});

/** Mensagem contextual sobre o documento (vazia / incompleto / inválido / OK). */
const docStatus = computed(() => {
    const len = docDigits.value.length;
    const expected = isPF.value ? 11 : 14;
    if (len === 0) return null;
    if (len < expected) {
        return { tone: 'info', texto: `Faltam ${expected - len} ${isPF.value ? 'dígito(s)' : 'caractere(s)'}…` };
    }
    if (len > expected) {
        return {
            tone: 'warn',
            texto: isPF.value
                ? 'Documento tem mais de 11 dígitos. Se é um CNPJ, troque o tipo para "Pessoa jurídica".'
                : 'Documento excede 14 caracteres.',
        };
    }
    // len === expected
    return docValido.value
        ? { tone: 'ok', texto: `${isPF.value ? 'CPF' : 'CNPJ'} válido.` }
        : { tone: 'err', texto: `${isPF.value ? 'CPF' : 'CNPJ'} inválido — dígitos verificadores não conferem.` };
});

const dicaPessoa = computed(() =>
    isPF.value
        ? {
              titulo: 'Pessoa física',
              texto: 'Cadastro de produtor rural, prestador autônomo ou cliente individual. CPF é obrigatório; campos empresariais (razão social, nome fantasia, inscrição estadual) não se aplicam.',
          }
        : {
              titulo: 'Pessoa jurídica',
              texto: 'Cadastro de empresa ou cooperativa. CNPJ é obrigatório (aceita formato alfanumérico da Resolução CGSIM 2026). Nome fantasia e inscrição estadual são opcionais mas úteis na emissão de documentos.',
          },
);

// ── Reset de campos incompatíveis ao trocar pessoa ───────────────────
watch(
    () => form.pessoa,
    (novo, antigo) => {
        if (novo === antigo) return;

        // Se o documento atual não é válido pra nova modalidade → limpa.
        // (evita manter CPF em PJ ou CNPJ em PF)
        const len = apenasAlfaNum(form.documento).length;
        const esperado = novo === 'pf' ? 11 : 14;
        if (len !== esperado) {
            form.documento = '';
        }

        // Campos exclusivos de PJ — limpa ao virar PF
        if (novo === 'pf') {
            form.nome_fantasia = '';
            form.inscricao_estadual = '';
        }
    },
);

// Trava o submit se documento exigido está inválido/vazio
const podeSalvar = computed(() => {
    // Documento sempre obrigatório (D4): backend aceita vazio no validator base
    // mas validateDomainCoherence rejeita. Aqui replicamos.
    if (!docValido.value) return false;
    return !form.processing;
});

function submit() {
    if (isEdit) form.put(route('admin.parceiros.update', props.partner.id));
    else form.post(route('admin.parceiros.store'));
}
</script>

<template>
    <Head :title="isEdit ? 'Editar parceiro' : 'Novo parceiro'" />
    <AdminLayout>
        <PageHeader :title="isEdit ? 'Editar parceiro' : 'Novo parceiro'">
            <template #actions>
                <Link :href="route('admin.parceiros.index')" class="btn-outline">Voltar</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="space-y-6 max-w-4xl">
            <!-- Dica contextual por pessoa (F3 — UX guiada) -->
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                <div class="font-medium">{{ dicaPessoa.titulo }}</div>
                <div class="mt-1 text-blue-800">{{ dicaPessoa.texto }}</div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Identificação</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <!-- Pessoa (PF/PJ) dirige TODA a UX abaixo -->
                    <div>
                        <InputLabel value="Pessoa" />
                        <select v-model="form.pessoa" class="form-select" required>
                            <option value="pf">Pessoa Física</option>
                            <option value="pj">Pessoa Jurídica</option>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Define o tipo de documento (CPF ou CNPJ) e os campos do cadastro.</p>
                    </div>
                    <div>
                        <InputLabel value="Relação comercial" />
                        <select v-model="form.tipo" class="form-select" required>
                            <option value="fornecedor">Fornecedor</option>
                            <option value="cliente">Cliente</option>
                            <option value="ambos">Fornecedor e cliente</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel :value="labelNome" />
                        <input
                            v-model="form.nome"
                            required
                            class="form-input"
                            :placeholder="placeholderNome"
                        >
                        <InputError :message="form.errors.nome" />
                    </div>

                    <!-- Nome fantasia: EXCLUSIVO de PJ -->
                    <div v-if="isPJ" class="sm:col-span-2">
                        <InputLabel value="Nome fantasia (opcional)" />
                        <input
                            v-model="form.nome_fantasia"
                            class="form-input"
                            placeholder="Como a empresa é conhecida no mercado"
                        >
                    </div>

                    <!-- Documento: CPF (11) ou CNPJ (14) conforme pessoa -->
                    <div>
                        <InputLabel :value="labelDocumento + ' (obrigatório)'" />
                        <InputMasked
                            v-model="form.documento"
                            :mask="maskDocumento"
                            :placeholder="placeholderDocumento"
                            required
                        />
                        <InputError :message="form.errors.documento" />
                        <!-- Feedback live de DV -->
                        <p
                            v-if="docStatus"
                            class="text-xs mt-1 flex items-center gap-1"
                            :class="{
                                'text-slate-500': docStatus.tone === 'info',
                                'text-amber-700': docStatus.tone === 'warn',
                                'text-red-600':   docStatus.tone === 'err',
                                'text-emerald-700': docStatus.tone === 'ok',
                            }"
                        >
                            <svg v-if="docStatus.tone === 'ok'" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <svg v-else-if="docStatus.tone === 'err'" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                            {{ docStatus.texto }}
                        </p>
                    </div>

                    <!-- Inscrição estadual: EXCLUSIVO de PJ -->
                    <div v-if="isPJ">
                        <InputLabel value="Inscrição estadual (opcional)" />
                        <input
                            v-model="form.inscricao_estadual"
                            class="form-input"
                            placeholder="Ex.: 123.456.789.012 ou ISENTO"
                        >
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Contato</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="E-mail" />
                        <input type="email" v-model="form.email" class="form-input" placeholder="contato@exemplo.com">
                    </div>
                    <div>
                        <InputLabel :value="isPF ? 'Telefone' : 'Telefone comercial'" />
                        <InputMasked v-model="form.telefone" :mask="['(##) ####-####', '(##) #####-####']" placeholder="(00) 00000-0000" />
                    </div>
                    <div>
                        <InputLabel value="Celular" />
                        <InputMasked v-model="form.celular" mask="(##) #####-####" placeholder="(00) 00000-0000" />
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Endereço</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-2"><InputLabel value="CEP" /><InputMasked v-model="form.cep" mask="#####-###" placeholder="00000-000" /></div>
                    <div class="sm:col-span-4"><InputLabel value="Rua" /><input v-model="form.endereco" class="form-input"></div>
                    <div class="sm:col-span-2"><InputLabel value="Número" /><input v-model="form.numero" class="form-input"></div>
                    <div class="sm:col-span-4"><InputLabel value="Complemento" /><input v-model="form.complemento" class="form-input"></div>
                    <div class="sm:col-span-3"><InputLabel value="Bairro" /><input v-model="form.bairro" class="form-input"></div>
                    <div class="sm:col-span-2"><InputLabel value="Cidade" /><input v-model="form.cidade" class="form-input"></div>
                    <div><InputLabel value="UF" /><input v-model="form.estado" maxlength="2" class="form-input uppercase" placeholder="MG"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-body space-y-3">
                    <div>
                        <InputLabel value="Observações" />
                        <textarea v-model="form.observacoes" rows="3" class="form-textarea"></textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_active" class="rounded">
                        Parceiro ativo
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Link :href="route('admin.parceiros.index')" class="btn-outline">Cancelar</Link>
                <button
                    type="submit"
                    class="btn-primary"
                    :disabled="!podeSalvar"
                    :title="!docValido ? `Informe um ${labelDocumento} válido` : ''"
                >
                    Salvar
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
