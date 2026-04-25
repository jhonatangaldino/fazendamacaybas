<script setup>
import { computed, watch, onMounted, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMoney from '@/Components/InputMoney.vue';
import AvatarUpload from '@/Components/AvatarUpload.vue';
import { router } from '@inertiajs/vue3';
import { emojiEspecie } from '@/utils/emojiEspecie.js';

const props = defineProps({ animal: Object, species: Array, lots: Array, locations: { type: Array, default: () => [] }, farms: Array, partners: Array });
const isEdit = !!props.animal;

const form = useForm({
    farm_id: props.animal?.farm_id ?? props.farms[0]?.id ?? null,
    species_id: props.animal?.species_id ?? props.species[0]?.id ?? '',
    breed_id: props.animal?.breed_id ?? null,
    lot_id: props.animal?.lot_id ?? null,
    location_id: props.animal?.location_id ?? null,
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

// Foto no cadastro inicial — preview local imediato + upload pós-create.
// Solução pragmática sem alterar o store controller: guarda File ref aqui,
// após o create bem-sucedido faz upload via rota /foto.upload existente.
const fotoFile = ref(null);
const fotoPreview = ref(null);
const fotoErro = ref(null); // erro inline (substitui alert nativo)

function onFotoChange(e) {
    const f = e.target.files?.[0];
    fotoErro.value = null;
    if (!f) return;
    if (f.size > 5 * 1024 * 1024) {
        fotoErro.value = 'Imagem acima de 5 MB. Reduza o tamanho ou escolha outra foto.';
        e.target.value = ''; // limpa input para permitir nova seleção
        return;
    }
    fotoFile.value = f;
    const reader = new FileReader();
    reader.onload = (ev) => { fotoPreview.value = ev.target.result; };
    reader.readAsDataURL(f);
}

function removerFoto() {
    fotoFile.value = null;
    fotoPreview.value = null;
}

function submit() {
    if (isEdit) {
        form.put(route('admin.rebanho.animais.update', props.animal.id));
        return;
    }
    // Create + foto na MESMA request (atomicidade). Inertia useForm aceita
    // File via transform — backend trata 'foto' como UploadedFile opcional.
    form.transform((d) => {
        const out = { ...d };
        if (fotoFile.value) out.foto = fotoFile.value;
        return out;
    }).post(route('admin.rebanho.animais.store'), {
        forceFormData: true, // multipart pra suportar o File
    });
}

// Emoji/placeholder coerente com a espécie escolhida — usado quando
// não há foto. Antes o sistema mostrava ícone genérico; agora respeita
// a espécie (ex.: cabra pra caprino, gato pra pet felino, peixe pra aquicultura).
const emojiSelecionado = computed(() => emojiEspecie(selectedSpecies.value?.nome));

// ═════ Listas locais + modais inline de criação ═════
// Princípio: se faltar lote ou local no momento do cadastro, o usuário
// NÃO sai do form. Abre modal, cria, o novo item entra na lista e
// fica selecionado automaticamente. Funciona para cliente zero-data
// (peixe, pet, bovino — qualquer tipo de fazenda).
const lotsLocal = ref([...props.lots]);
const locationsLocal = ref([...(props.locations ?? [])]);

// CSRF helper — endpoints inline usam fetch puro (não Inertia), então
// precisam do token CSRF manual. Laravel aceita:
//   X-CSRF-TOKEN: <hash> (do meta name="csrf-token")
//   OU X-XSRF-TOKEN: <cookie XSRF-TOKEN decodificado>
// Usamos o primeiro (meta) porque é mais estável — cookie pode vir
// URL-encoded em formas diferentes entre browsers.
function csrfHeader() {
    const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return meta ? { 'X-CSRF-TOKEN': meta } : {};
}

const novoLoteAberto = ref(false);
const novoLoteForm = ref({ nome: '', codigo: '' });
const novoLoteError = ref(null);
const salvandoLote = ref(false);
function abrirNovoLote() {
    novoLoteForm.value = { nome: '', codigo: '' };
    novoLoteError.value = null;
    novoLoteAberto.value = true;
}
async function salvarNovoLote() {
    salvandoLote.value = true;
    novoLoteError.value = null;
    try {
        const resp = await fetch(route('admin.rebanho.lotes.inline'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
            body: JSON.stringify(novoLoteForm.value),
        });
        if (!resp.ok) throw new Error('Falha ao criar lote (' + resp.status + ').');
        const lote = await resp.json();
        lotsLocal.value = [...lotsLocal.value, lote];
        form.lot_id = lote.id;        // já seleciona o recém-criado
        novoLoteAberto.value = false;
    } catch (e) {
        novoLoteError.value = e.message || 'Erro.';
    } finally {
        salvandoLote.value = false;
    }
}

const novoLocalAberto = ref(false);
const novoLocalForm = ref({ nome: '', codigo: '', tipo: 'pasto' });
const novoLocalError = ref(null);
const salvandoLocal = ref(false);
function abrirNovoLocal() {
    novoLocalForm.value = { nome: '', codigo: '', tipo: 'pasto' };
    novoLocalError.value = null;
    novoLocalAberto.value = true;
}
async function salvarNovoLocal() {
    salvandoLocal.value = true;
    novoLocalError.value = null;
    try {
        const resp = await fetch(route('admin.rebanho.locais.inline'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
            body: JSON.stringify(novoLocalForm.value),
        });
        if (!resp.ok) throw new Error('Falha ao criar local (' + resp.status + ').');
        const local = await resp.json();
        locationsLocal.value = [...locationsLocal.value, local];
        form.location_id = local.id;
        novoLocalAberto.value = false;
    } catch (e) {
        novoLocalError.value = e.message || 'Erro.';
    } finally {
        salvandoLocal.value = false;
    }
}

// ═════ Hub v3 · Modo "Registrar nascimento" ═════
// Quando o usuário chega pelo card "Registrar nascimento" (?origem=nascimento),
// mostramos um banner contextual explicando o fluxo e pré-configuramos a
// origem para 'nascido'. Isso dá clareza visual (não é um cadastro aleatório,
// é o nascimento de um animal na fazenda) sem precisar de wizard separado.
const modoNascimento = ref(false);
onMounted(() => {
    if (!isEdit) {
        const qs = new URLSearchParams(window.location.search);
        if (qs.get('origem') === 'nascimento') {
            modoNascimento.value = true;
            form.origem = 'nascido';
            if (!form.data_nascimento) form.data_nascimento = new Date().toISOString().slice(0, 10);
        }
    }
});
</script>

<template>
    <Head :title="modoNascimento ? 'Registrar nascimento' : (isEdit ? 'Editar animal' : 'Novo animal')" />
    <AdminLayout>
        <PageHeader
            :title="modoNascimento ? 'Registrar nascimento' : (isEdit ? 'Editar animal' : 'Novo animal')"
            :subtitle="modoNascimento ? 'Cadastre o novo animal que acabou de nascer na fazenda.' : ''"
        >
            <template #actions>
                <Link :href="route('admin.rebanho.animais.index')" class="btn-outline">Voltar</Link>
            </template>
        </PageHeader>

        <!-- Banner contextual quando veio do card "Registrar nascimento" do Hub -->
        <div v-if="modoNascimento"
             class="mb-6 rounded-xl border-2 border-pink-200 bg-pink-50 px-5 py-4 flex items-start gap-3 max-w-4xl">
            <span class="text-3xl flex-shrink-0" aria-hidden="true">🐣</span>
            <div>
                <div class="font-semibold text-pink-900">Bem-vindo ao rebanho!</div>
                <div class="text-sm text-pink-800 mt-0.5">
                    Você está cadastrando um animal que <strong>nasceu aqui na fazenda</strong>. Preencha identificação, espécie e sexo — os campos essenciais para criação.
                    Se preferir, pode informar peso ao nascer e pais para enriquecer o histórico.
                </div>
            </div>
        </div>

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
            <!-- Foto no cadastro INICIAL — file input + preview.
                 Se o usuário não escolher foto, placeholder usa o emoji correto
                 da espécie (cabra, gato, peixe, etc.), nunca um genérico errado. -->
            <div v-else-if="!isEdit && !isLote" class="card">
                <div class="card-header"><h2 class="card-title">Foto do animal (opcional)</h2></div>
                <div class="card-body flex items-center gap-4">
                    <div class="h-32 w-32 rounded-lg bg-slate-50 ring-1 ring-slate-200 flex items-center justify-center overflow-hidden flex-shrink-0">
                        <img v-if="fotoPreview" :src="fotoPreview" class="h-full w-full object-cover" alt="Preview">
                        <span v-else class="text-6xl" :title="nomeEspecie">{{ emojiSelecionado }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <label class="btn-outline cursor-pointer inline-flex items-center gap-2">
                            <span>{{ fotoPreview ? 'Trocar foto' : '📷 Escolher foto' }}</span>
                            <input type="file" accept="image/*" @change="onFotoChange" class="hidden">
                        </label>
                        <button v-if="fotoPreview" type="button" @click="removerFoto" class="ml-2 text-sm text-red-700 hover:underline">
                            Remover
                        </button>
                        <p class="text-xs text-slate-500 mt-2">
                            Se não tiver foto agora, o sistema usa o ícone da espécie ({{ nomeEspecie }}). Você pode adicionar depois na ficha do animal.
                        </p>
                        <p v-if="fotoErro" class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-md px-2 py-1.5 mt-2">
                            ⚠ {{ fotoErro }}
                        </p>
                    </div>
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

                    <!-- LOTE com criação inline (não manda o usuário para outra tela) -->
                    <div>
                        <InputLabel :value="requiresLote ? 'Lote (grupo lógico)' : 'Lote (opcional)'" />
                        <select
                            v-model="form.lot_id"
                            class="form-select"
                            :required="requiresLote"
                        >
                            <option :value="null">—</option>
                            <option v-for="l in lotsLocal" :key="l.id" :value="l.id">{{ l.nome }}</option>
                        </select>
                        <div class="mt-1 flex items-center gap-2">
                            <button type="button" @click="abrirNovoLote"
                                    class="text-xs text-macaybas-primary hover:underline">
                                + Criar lote novo
                            </button>
                            <span v-if="lotsLocal.length === 0" class="text-xs text-amber-700">
                                Nenhum lote ainda — crie o primeiro aqui.
                            </span>
                        </div>
                        <p v-if="requiresLote" class="text-xs text-slate-400 mt-1">
                            Obrigatório para {{ nomeEspecie.toLowerCase() }}.
                        </p>
                        <p v-else class="text-xs text-slate-400 mt-1">
                            🐄 Agrupamento para manejo (ex.: Bezerros 2026, Vacas em lactação).
                        </p>
                    </div>

                    <!-- PASTO / LOCAL com criação inline -->
                    <div>
                        <InputLabel value="Pasto / Local (opcional)" />
                        <select v-model="form.location_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="l in locationsLocal" :key="l.id" :value="l.id">
                                {{ l.nome }}<span v-if="l.tipo"> · {{ l.tipo }}</span>
                            </option>
                        </select>
                        <InputError :message="form.errors.location_id" />
                        <div class="mt-1 flex items-center gap-2">
                            <button type="button" @click="abrirNovoLocal"
                                    class="text-xs text-macaybas-primary hover:underline">
                                + Criar pasto/local novo
                            </button>
                            <span v-if="locationsLocal.length === 0" class="text-xs text-amber-700">
                                Nenhum local ainda — crie aqui.
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">
                            📍 Onde o animal está fisicamente (pasto, piquete, curral, baia, tanque).
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

        <!-- Modal inline: criar LOTE sem sair do form (fetch JSON, zero navigation) -->
        <Teleport to="body">
            <div v-if="novoLoteAberto" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novoLoteAberto = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold mb-1">Novo lote (grupo)</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        Crie o grupo de manejo aqui mesmo — depois o animal já fica associado.
                    </p>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Nome do lote *" />
                            <input v-model="novoLoteForm.nome" class="form-input" placeholder="Ex.: Bezerros 2026" required>
                        </div>
                        <div>
                            <InputLabel value="Código (opcional)" />
                            <input v-model="novoLoteForm.codigo" class="form-input" placeholder="Ex.: BZ-26">
                        </div>
                    </div>
                    <p v-if="novoLoteError" class="mt-3 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1.5">
                        {{ novoLoteError }}
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoLoteAberto = false" class="btn-outline">Cancelar</button>
                        <button @click="salvarNovoLote" :disabled="salvandoLote || !novoLoteForm.nome.trim()" class="btn-primary">
                            {{ salvandoLote ? 'Salvando…' : 'Criar lote' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal inline: criar LOCAL/PASTO sem sair do form -->
            <div v-if="novoLocalAberto" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novoLocalAberto = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold mb-1">Novo pasto / local</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        Onde o animal fica fisicamente. Pasto, piquete, curral, baia ou tanque.
                    </p>
                    <div class="space-y-3">
                        <div>
                            <InputLabel value="Nome *" />
                            <input v-model="novoLocalForm.nome" class="form-input" placeholder="Ex.: Pasto Norte" required>
                        </div>
                        <div>
                            <InputLabel value="Tipo *" />
                            <div class="grid grid-cols-3 gap-2">
                                <button v-for="t in ['pasto','piquete','curral','baia','tanque','galpao']"
                                        :key="t" type="button"
                                        @click="novoLocalForm.tipo = t"
                                        class="px-2 py-1.5 rounded border text-sm capitalize"
                                        :class="novoLocalForm.tipo === t ? 'border-macaybas-primary bg-emerald-50 text-emerald-800 font-semibold' : 'border-slate-200'">
                                    {{ t }}
                                </button>
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Código (opcional)" />
                            <input v-model="novoLocalForm.codigo" class="form-input" placeholder="Ex.: P-N">
                        </div>
                    </div>
                    <p v-if="novoLocalError" class="mt-3 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1.5">
                        {{ novoLocalError }}
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoLocalAberto = false" class="btn-outline">Cancelar</button>
                        <button @click="salvarNovoLocal" :disabled="salvandoLocal || !novoLocalForm.nome.trim()" class="btn-primary">
                            {{ salvandoLocal ? 'Salvando…' : 'Criar local' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
