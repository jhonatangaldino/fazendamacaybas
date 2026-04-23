<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMoney from '@/Components/InputMoney.vue';
import AvatarUpload from '@/Components/AvatarUpload.vue';

const props = defineProps({ animal: Object, species: Array, lots: Array, farms: Array, partners: Array });
const isEdit = !!props.animal;

const form = useForm({
    farm_id: props.animal?.farm_id ?? props.farms[0]?.id ?? null,
    species_id: props.animal?.species_id ?? props.species[0]?.id ?? '',
    breed_id: props.animal?.breed_id ?? null,
    lot_id: props.animal?.lot_id ?? null,
    identificacao: props.animal?.identificacao ?? '',
    nome: props.animal?.nome ?? '',
    numero_registro: props.animal?.numero_registro ?? '',
    sexo: props.animal?.sexo ?? 'F',
    data_nascimento: props.animal?.data_nascimento ?? '',
    peso_nascimento: props.animal?.peso_nascimento ?? '',
    origem: props.animal?.origem ?? 'nascido',
    partner_id: props.animal?.partner_id ?? null,
    data_aquisicao: props.animal?.data_aquisicao ?? '',
    valor_aquisicao: props.animal?.valor_aquisicao ?? '',
    status: props.animal?.status ?? 'ativo',
    categoria: props.animal?.categoria ?? '',
    observacoes: props.animal?.observacoes ?? '',
});

// ═════════════════════════════════════════════════════════════════════
// F3 · UX anti-erro orientada por domínio
//
// Espelha a matriz D2 do backend (AnimalController::CATEGORIAS_POR_PROFILE
// + PROFILES_EXIGEM_LOTE/NOME/DATA_NASC). Aqui no front, o objetivo é
// IMPEDIR o usuário de selecionar combinações que o backend rejeitaria:
//
//   - aves/peixe → esconde campos individuais, força lote
//   - cão/gato   → exige nome, categorias de pet
//   - bovino     → brinco obrigatório, categorias corte/leite
//
// O backend D2 permanece como segunda camada de defesa (nunca confiar
// só em validação client-side).
// ═════════════════════════════════════════════════════════════════════

/** Categorias válidas por profile — espelho de CATEGORIAS_POR_PROFILE. */
const CATEGORIAS_POR_PROFILE = {
    ruminante_corte:  [{ value: 'corte', label: 'Corte' }, { value: 'reproducao', label: 'Reprodução' }, { value: 'misto', label: 'Misto (corte+leite)' }],
    ruminante_leite:  [{ value: 'leite', label: 'Leite' }, { value: 'reproducao', label: 'Reprodução' }, { value: 'misto', label: 'Misto (leite+corte)' }],
    ruminante_lan:    [{ value: 'corte', label: 'Corte' }, { value: 'reproducao', label: 'Reprodução' }, { value: 'misto', label: 'Misto' }],
    equino:           [{ value: 'trabalho', label: 'Trabalho / serviço' }, { value: 'esporte', label: 'Esporte' }, { value: 'reproducao', label: 'Reprodução' }],
    suino:            [{ value: 'corte', label: 'Corte' }, { value: 'reproducao', label: 'Reprodução' }],
    ave_postura:      [{ value: 'postura', label: 'Postura (ovos)' }],
    ave_corte:        [{ value: 'corte', label: 'Corte' }],
    aquicultura_lote: [], // peixe: sem categoria individual
    apicultura:       [],
    pet:              [{ value: 'companhia', label: 'Companhia' }, { value: 'servico', label: 'Serviço / trabalho' }, { value: 'pet', label: 'Pet' }],
};

/** Perfis que exigem lote (não aceitam animal individual). */
const PROFILES_EXIGEM_LOTE = ['ave_postura', 'ave_corte', 'aquicultura_lote', 'apicultura'];

/** Perfis que exigem nome (pets). */
const PROFILES_EXIGEM_NOME = ['pet'];

/** Perfis que exigem data de nascimento (manejo individual com ciclo etário). */
const PROFILES_EXIGEM_DATA_NASC = ['ruminante_corte', 'ruminante_leite', 'ruminante_lan', 'equino', 'suino', 'pet'];

/** Fallback de categorias quando profile é desconhecido (retrocompat). */
const CATEGORIAS_FALLBACK = [
    { value: 'leite', label: 'Leite' },
    { value: 'corte', label: 'Corte' },
    { value: 'reproducao', label: 'Reprodução' },
    { value: 'misto', label: 'Misto' },
    { value: 'pet', label: 'Pet' },
    { value: 'servico', label: 'Serviço / trabalho' },
];

const selectedSpecies = computed(() =>
    props.species.find((s) => s.id === form.species_id) ?? null,
);

const profile = computed(() => selectedSpecies.value?.profile ?? null);
const gestao = computed(() => selectedSpecies.value?.gestao ?? null);
const nomeEspecie = computed(() => selectedSpecies.value?.nome ?? 'a espécie');

const isLote = computed(() => gestao.value === 'lote');
const isPet = computed(() => profile.value === 'pet');

const requiresLote = computed(() => PROFILES_EXIGEM_LOTE.includes(profile.value));
const requiresNome = computed(() => PROFILES_EXIGEM_NOME.includes(profile.value));
const requiresDataNasc = computed(() => PROFILES_EXIGEM_DATA_NASC.includes(profile.value));

const categoriasDisponiveis = computed(() => {
    const p = profile.value;
    if (p && Object.prototype.hasOwnProperty.call(CATEGORIAS_POR_PROFILE, p)) {
        return CATEGORIAS_POR_PROFILE[p];
    }
    return CATEGORIAS_FALLBACK;
});

/** Quando a lista de categorias do profile é vazia, o campo some. */
const showCategoria = computed(() => {
    const p = profile.value;
    // Se profile tem entrada explícita vazia → esconde (aquicultura/apicultura).
    // Se profile é desconhecido → mantém (retrocompat).
    if (p && Object.prototype.hasOwnProperty.call(CATEGORIAS_POR_PROFILE, p)) {
        return CATEGORIAS_POR_PROFILE[p].length > 0;
    }
    return true;
});

/** Nome, peso ao nascer e número de registro só fazem sentido individualmente. */
const showNome = computed(() => !isLote.value);
const showPesoNascimento = computed(() => !isLote.value);
const showNumeroRegistro = computed(() => !isLote.value && !isPet.value);

const labelIdentificacao = computed(() => {
    if (isLote.value) return 'Identificação do lote';
    if (isPet.value) return 'Identificação (microchip, coleira, etc.)';
    return 'Brinco / identificação';
});

const placeholderIdentificacao = computed(() => {
    if (isLote.value) return 'Ex.: LOTE-AVES-2026-001';
    if (isPet.value) return 'Ex.: chip 982000123456789';
    return 'Ex.: 12345';
});

const racas = computed(() => selectedSpecies.value?.breeds ?? []);

/** Mensagem contextual que orienta o usuário sobre a espécie escolhida. */
const dicaEspecie = computed(() => {
    if (!profile.value) return null;

    if (isLote.value) {
        return {
            tone: 'info',
            titulo: `${nomeEspecie.value} é manejado em lote`,
            texto: 'Você está cadastrando um LOTE (não um animal individual). Peso, nome e número de registro não se aplicam. Acompanhamento será por biometria amostral e mortalidade agregada.',
        };
    }
    if (isPet.value) {
        return {
            tone: 'info',
            titulo: `${nomeEspecie.value} é um animal de companhia`,
            texto: 'Pets são identificados pelo nome (obrigatório). Não há categoria produtiva (corte/leite). Eventos aceitos: pesagem, vacinação, medicação, castração.',
        };
    }
    if (profile.value === 'equino') {
        return {
            tone: 'info',
            titulo: `${nomeEspecie.value} — animal de serviço/esporte`,
            texto: 'Equinos usam categorias trabalho, esporte ou reprodução. Eventos aceitos incluem ferrageamento.',
        };
    }
    if (profile.value === 'ruminante_leite') {
        return {
            tone: 'info',
            titulo: `${nomeEspecie.value} — vocação leiteira`,
            texto: 'Eventos de ordenha e secagem estão disponíveis. Ao marcar categoria "Leite", ganho de peso não é prioritário — acompanhamento de produção diária é.',
        };
    }
    if (profile.value === 'ruminante_corte') {
        return {
            tone: 'info',
            titulo: `${nomeEspecie.value} — ciclo de corte`,
            texto: 'Foco em ganho de peso e reprodução. Categoria "Misto" libera também acompanhamento de leite (útil para raças duplo-propósito).',
        };
    }
    return null;
});

// ── Reset de campos incompatíveis ao trocar espécie ──────────────────
watch(
    () => form.species_id,
    () => {
        // Se a categoria atual não está disponível no novo profile → limpa
        if (form.categoria) {
            const ok = categoriasDisponiveis.value.some((c) => c.value === form.categoria);
            if (!ok || !showCategoria.value) {
                form.categoria = '';
            }
        }
        // Se a raça atual não pertence à nova espécie → limpa
        if (form.breed_id && !racas.value.some((r) => r.id === form.breed_id)) {
            form.breed_id = null;
        }
        // Campos que só existem individualmente → limpa para lote
        if (isLote.value) {
            form.nome = '';
            form.peso_nascimento = '';
            form.numero_registro = '';
        }
    },
);

function submit() {
    if (isEdit) form.put(route('admin.rebanho.animais.update', props.animal.id));
    else form.post(route('admin.rebanho.animais.store'));
}
</script>

<template>
    <Head :title="isEdit ? 'Editar animal' : 'Novo animal'" />
    <AdminLayout>
        <PageHeader :title="isEdit ? 'Editar animal' : 'Novo animal'">
            <template #actions>
                <Link :href="route('admin.rebanho.animais.index')" class="btn-outline">Voltar</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="space-y-6 max-w-4xl">
            <!-- Foto do animal — só faz sentido após criação (precisa de ID pra upload) -->
            <div v-if="isEdit && !isLote" class="card">
                <div class="card-header"><h2 class="card-title">Foto do animal</h2></div>
                <div class="card-body">
                    <AvatarUpload
                        :url="animal.photo_url"
                        :name="animal.identificacao"
                        size="h-32 w-32"
                        shape="square"
                        :upload-url="route('admin.rebanho.animais.foto.upload', animal.id)"
                        :remove-url="route('admin.rebanho.animais.foto.remove', animal.id)"
                    />
                </div>
            </div>
            <div v-else-if="!isEdit && !isLote" class="card bg-slate-50">
                <div class="card-body text-sm text-slate-500">
                    📷 A foto poderá ser adicionada após salvar o cadastro inicial.
                </div>
            </div>

            <!-- Dica contextual por espécie (F3 — UX guiada) -->
            <div
                v-if="dicaEspecie"
                class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900"
            >
                <div class="font-medium">{{ dicaEspecie.titulo }}</div>
                <div class="mt-1 text-blue-800">{{ dicaEspecie.texto }}</div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Identificação</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <!-- Espécie sempre primeiro: dirige toda a UX abaixo -->
                    <div>
                        <InputLabel value="Espécie" />
                        <select v-model="form.species_id" class="form-select" required>
                            <option v-for="s in species" :key="s.id" :value="s.id">{{ s.nome }}</option>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Define o perfil de manejo (individual ou lote) e os campos do formulário.</p>
                    </div>
                    <div>
                        <InputLabel value="Raça" />
                        <select v-model="form.breed_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="r in racas" :key="r.id" :value="r.id">{{ r.nome }}</option>
                        </select>
                    </div>

                    <div>
                        <InputLabel :value="labelIdentificacao" />
                        <input
                            v-model="form.identificacao"
                            required
                            class="form-input"
                            :placeholder="placeholderIdentificacao"
                        >
                        <InputError :message="form.errors.identificacao" />
                    </div>

                    <div v-if="showNome">
                        <InputLabel :value="requiresNome ? 'Nome' : 'Nome (opcional)'" />
                        <input
                            v-model="form.nome"
                            class="form-input"
                            :required="requiresNome"
                            :placeholder="requiresNome ? 'Ex.: Rex' : ''"
                        >
                        <InputError :message="form.errors.nome" />
                    </div>

                    <div>
                        <InputLabel value="Sexo" />
                        <select v-model="form.sexo" class="form-select" required>
                            <option value="F">Fêmea</option>
                            <option value="M">Macho</option>
                        </select>
                        <p v-if="isLote" class="text-xs text-slate-400 mt-1">Para lotes mistos, informe o sexo predominante.</p>
                    </div>

                    <div>
                        <InputLabel :value="requiresLote ? 'Lote' : 'Lote (opcional)'" />
                        <select
                            v-model="form.lot_id"
                            class="form-select"
                            :required="requiresLote"
                        >
                            <option :value="null">—</option>
                            <option v-for="l in lots" :key="l.id" :value="l.id">{{ l.nome }}</option>
                        </select>
                        <p v-if="requiresLote && !form.lot_id && lots.length === 0" class="text-xs text-red-600 mt-1">
                            Nenhum lote cadastrado. Crie um lote antes de cadastrar aves, peixes ou apicultura.
                        </p>
                        <p v-else-if="requiresLote" class="text-xs text-slate-400 mt-1">
                            Obrigatório para {{ nomeEspecie.toLowerCase() }}.
                        </p>
                    </div>

                    <div>
                        <InputLabel :value="requiresDataNasc ? 'Data de nascimento' : 'Data de nascimento (opcional)'" />
                        <InputDate v-model="form.data_nascimento" :required="requiresDataNasc" />
                        <p v-if="requiresDataNasc" class="text-xs text-slate-400 mt-1">Usada para calcular idade, desmame, ciclo de cobertura e abate.</p>
                    </div>

                    <div v-if="showPesoNascimento">
                        <InputLabel value="Peso ao nascer (kg)" />
                        <input type="number" step="0.01" v-model="form.peso_nascimento" class="form-input">
                        <p class="text-xs text-slate-400 mt-1">Valor único (imutável). Pesagens seguintes vão em <em>Histórico ⚖</em>.</p>
                    </div>

                    <div v-if="isEdit && !isLote" class="sm:col-span-1">
                        <InputLabel value="Peso atual" />
                        <div class="form-input bg-slate-50 text-slate-700 font-mono flex items-center justify-between">
                            <span v-if="animal?.peso_atual">{{ Number(animal.peso_atual).toLocaleString('pt-BR', { minimumFractionDigits: 1 }) }} kg</span>
                            <span v-else class="text-slate-400">Sem pesagem</span>
                            <Link :href="route('admin.rebanho.animais.show', animal.id)" class="text-xs text-macaybas-primary hover:underline">Ver histórico →</Link>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Derivado da última pesagem — não é editável aqui.</p>
                    </div>

                    <div v-if="showCategoria">
                        <InputLabel value="Categoria de uso" />
                        <select v-model="form.categoria" class="form-select">
                            <option value="">—</option>
                            <option v-for="c in categoriasDisponiveis" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Define o tipo de acompanhamento compatível com {{ nomeEspecie.toLowerCase() }}.</p>
                    </div>

                    <div v-if="showNumeroRegistro">
                        <InputLabel value="Número de registro (opcional)" />
                        <input v-model="form.numero_registro" class="form-input" placeholder="Ex: ABCZ 123456">
                    </div>

                    <div>
                        <InputLabel value="Status" />
                        <select v-model="form.status" class="form-select">
                            <option value="ativo">Ativo</option>
                            <option value="vendido">Vendido</option>
                            <option value="morto">Morto</option>
                            <option value="abatido">Abatido</option>
                            <option value="transferido">Transferido</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Origem</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Origem" />
                        <select v-model="form.origem" class="form-select">
                            <option value="nascido">{{ isLote ? 'Formado na fazenda' : 'Nascido na fazenda' }}</option>
                            <option value="compra">Compra</option>
                        </select>
                    </div>
                    <div v-if="form.origem === 'compra'">
                        <InputLabel value="Fornecedor" />
                        <select v-model="form.partner_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.nome }}</option>
                        </select>
                    </div>
                    <div v-if="form.origem === 'compra'"><InputLabel value="Data de aquisição" /><InputDate v-model="form.data_aquisicao" /></div>
                    <div v-if="form.origem === 'compra'"><InputLabel value="Valor de aquisição" /><InputMoney v-model="form.valor_aquisicao" /></div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <InputLabel value="Observações" />
                    <textarea v-model="form.observacoes" rows="3" class="form-textarea"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Link :href="route('admin.rebanho.animais.index')" class="btn-outline">Cancelar</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">Salvar</button>
            </div>
        </form>
    </AdminLayout>
</template>
