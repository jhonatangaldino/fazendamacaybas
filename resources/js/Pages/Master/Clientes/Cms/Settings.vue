<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import ClientCmsHeader from '@/Components/ClientCmsHeader.vue';
import Icon from '@/Components/Icon.vue';
import Alert from '@/Components/Alert.vue';
import InputMasked from '@/Components/InputMasked.vue';
import { useLoading } from '@/composables/useLoading.js';
import { useConfirm } from '@/composables/useConfirm.js';
import { useToast } from '@/composables/useToast.js';

const loading = useLoading();
const { confirm } = useConfirm();
const { toast } = useToast();

const props = defineProps({
    cliente: { type: Object, required: true },
    settings: Object,
    onboarding_completed: { type: Boolean, default: false },
});

// Clonagem profunda + baseline para dirty tracking.
// `local` é o que o usuário edita; `baseline` é a referência do último save,
// usada para detectar se há alterações pendentes.
const local = ref(JSON.parse(JSON.stringify(props.settings)));
const baseline = ref(JSON.stringify(props.settings));
const uploadError = ref({});
const uploadLoading = ref({});
const showOnboarding = ref(! props.onboarding_completed);
const saving = ref(false);

// Rótulos amigáveis por grupo. Ordem CONTROLADA aqui — itera por essa lista
// em vez de `Object.entries(local.value)` para garantir apresentação consistente
// (Dados da página → Mapa → Contato → resto).
const groupOrder = [
    'identidade',
    'localizacao',
    'contato',
    'social',
    'tema',
    'seo',
];

const groupLabels = {
    identidade: 'Dados da página',
    localizacao: 'Mapa / Localização',
    contato: 'Contato',
    social: 'Redes sociais',
    tema: 'Tema / cores',
    seo: 'SEO',
};

const groupDescriptions = {
    identidade: 'Nome, descrição, logo e favicon da sua página pública.',
    localizacao: 'Configure o mapa. Prioridade: embed manual > latitude/longitude > endereço.',
    contato: 'Informações de contato exibidas na landing.',
    social: 'Links das redes sociais (aparecem no rodapé).',
    tema: 'Cores principais da landing.',
    seo: 'Metadados que aparecem em buscadores.',
};

// Placeholders/ajudas contextuais por chave. Mantido só no frontend —
// não exige migração de dados.
const placeholders = {
    'site.nome': 'Ex.: Fazenda Boa Vista',
    'site.tagline': 'Ex.: Tradição e cuidado com a terra',
    'site.descricao': 'Um parágrafo descrevendo seu negócio',
    'contato.email': 'contato@suafazenda.com.br',
    'contato.telefone': '(31) 3333-3333',
    'contato.whatsapp': '(31) 99999-9999',
    'contato.endereco': 'Endereço completo (cidade/UF)',
    'social.instagram': 'https://instagram.com/seunegocio',
    'social.facebook': 'https://facebook.com/seunegocio',
    'social.youtube': 'https://youtube.com/@seunegocio',
    'landing.map.nome_local': 'Ex.: Fazenda Boa Vista',
    'landing.map.endereco': 'Ex.: Estrada Municipal, km 5, Itabirito/MG',
    'landing.map.latitude': 'Ex.: -20.2567',
    'landing.map.longitude': 'Ex.: -43.8042',
    'landing.map.google_embed': 'Cole o <iframe ...> ou a URL do Google Maps',
    'seo.default_title': 'Título que aparece na aba do navegador',
    'seo.default_description': 'Descrição que o Google mostra nos resultados',
};

function placeholderFor(s) {
    return placeholders[s.key] ?? '';
}

// Grupos renderizados na ordem controlada, removendo os que não vieram do backend.
const orderedGroups = computed(() => {
    const result = [];
    const extra = [];

    for (const key of groupOrder) {
        if (local.value[key]) result.push([key, local.value[key]]);
    }
    // Grupos que o backend mandou mas não listei em groupOrder vão no final.
    for (const key of Object.keys(local.value)) {
        if (! groupOrder.includes(key)) extra.push([key, local.value[key]]);
    }
    return [...result, ...extra];
});

// Dirty state — serializa `local` e compara com baseline. Simples e rápido para
// o volume de settings desta tela (~20 chaves).
const isDirty = computed(() => JSON.stringify(local.value) !== baseline.value);

// Fallback visual do mapa — se TODOS os 5 campos de mapa estão vazios no
// cliente, mostra aviso amigável dentro do grupo "Mapa / Localização".
const mapaVazio = computed(() => {
    const g = local.value['localizacao'];
    if (! g) return false;
    return g.every((s) => ! s.value || String(s.value).trim() === '');
});

/**
 * Checklist de completude — leitura pura dos mesmos dados que a landing já
 * consome. NÃO altera regra de negócio nenhuma; só reflete visualmente se
 * o campo tem valor ou não. Se o cliente preencher, o item fica verde; se
 * apagar, volta a cinza. Tudo em tempo real porque `local` é reativo.
 *
 * Critério propositalmente simples — 3 itens:
 *   • Nome        → site.nome
 *   • Descrição   → site.descricao
 *   • Localização → qualquer um dos 5 landing.map.*
 *
 * Campo não encontrado nos settings recebidos (cliente sem override nem
 * global) é tratado como "vazio" — mesma leitura que MapResolver/view faz.
 */
function findValue(key) {
    for (const grp of Object.values(local.value)) {
        const s = grp.find((x) => x.key === key);
        if (s) return s.value;
    }
    return null;
}

function isFilled(key) {
    const v = findValue(key);
    return v !== null && v !== undefined && String(v).trim() !== '';
}

const completude = computed(() => {
    const nomeOk = isFilled('site.nome');
    const descricaoOk = isFilled('site.descricao');
    const localizacaoOk = [
        'landing.map.endereco',
        'landing.map.latitude',
        'landing.map.longitude',
        'landing.map.google_embed',
    ].some((k) => isFilled(k));

    const items = [
        {
            id: 'nome',
            label: 'Nome da sua página preenchido',
            filled: nomeOk,
            groupKey: 'identidade',
            settingKey: 'site.nome',
            hint: 'Aparece no topo, nas abas do navegador e nos compartilhamentos.',
        },
        {
            id: 'descricao',
            label: 'Descrição preenchida',
            filled: descricaoOk,
            groupKey: 'identidade',
            settingKey: 'site.descricao',
            hint: 'Texto curto que apresenta seu negócio e é usado pelos buscadores.',
        },
        {
            id: 'localizacao',
            label: 'Localização configurada',
            filled: localizacaoOk,
            groupKey: 'localizacao',
            settingKey: 'landing.map.endereco',
            hint: 'Endereço, coordenadas ou embed — o que estiver preenchido já ativa o mapa.',
        },
    ];

    const faltando = items.filter((i) => ! i.filled).length;

    return {
        items,
        faltando,
        total: items.length,
        completo: faltando === 0,
    };
});

// Rola suavemente até o grupo do checklist clicado, priorizando o campo
// específico quando o ID da seção existe (scroll-margin-top cuida do offset).
function focusField(item) {
    const el = document.getElementById('grupo-' + item.groupKey);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Timestamp do último save — alimenta o "salvo há Xs" na barra sticky.
// Atualizado em onSuccess do save e em upload/remove (já persistem).
const lastSavedAt = ref(null);
const tickNow = ref(Date.now());
let tickInterval = null;
function startTick() {
    if (tickInterval) return;
    tickInterval = setInterval(() => { tickNow.value = Date.now(); }, 1000);
}
function stopTick() {
    if (tickInterval) {
        clearInterval(tickInterval);
        tickInterval = null;
    }
}
const savedAgo = computed(() => {
    if (! lastSavedAt.value) return null;
    const diff = Math.max(0, Math.round((tickNow.value - lastSavedAt.value) / 1000));
    if (diff < 5) return 'agora mesmo';
    if (diff < 60) return `há ${diff}s`;
    if (diff < 3600) return `há ${Math.floor(diff / 60)} min`;
    return `há ${Math.floor(diff / 3600)}h`;
});

function save() {
    const payload = [];
    for (const group of Object.values(local.value)) {
        for (const s of group) {
            payload.push({ key: s.key, value: s.value });
        }
    }
    saving.value = true;
    router.put(
        route('master.clientes.cms.settings.update', props.cliente.id),
        { settings: payload },
        {
            preserveScroll: true,
            onSuccess: () => {
                // Recalcula baseline — zera o dirty state após gravação.
                baseline.value = JSON.stringify(local.value);
                // Registra para o "salvo há Xs" na barra sticky.
                lastSavedAt.value = Date.now();
                tickNow.value = Date.now();
                startTick();
                // Se ainda tinha onboarding aberto e salvou, marca como concluído
                // (primeiro save = primeira vez que o cliente interage com o CMS).
                if (showOnboarding.value) {
                    completeOnboarding({ silent: true });
                }
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function completeOnboarding(options = {}) {
    showOnboarding.value = false;
    router.post(
        route('master.clientes.cms.settings.onboarding-complete', props.cliente.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (! options.silent) {
                    toast.info('Tour de primeiro acesso concluído.');
                }
            },
        },
    );
}

async function uploadImage(event, s) {
    const file = event.target.files?.[0];
    if (!file) return;

    uploadError.value = { ...uploadError.value, [s.key]: null };
    uploadLoading.value = { ...uploadLoading.value, [s.key]: true };

    const fd = new FormData();
    fd.append('file', file);
    fd.append('key', s.key);

    loading.start('Enviando ' + (s.label || 'arquivo').toLowerCase() + '...');
    try {
        const res = await fetch(route('master.clientes.cms.settings.upload', props.cliente.id), {
            method: 'POST',
            body: fd,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });

        const json = await res.json();

        if (!res.ok || !json.ok) {
            uploadError.value = { ...uploadError.value, [s.key]: json.message || 'Falha no upload.' };
            return;
        }

        s.value = json.path;
        // Upload altera o baseline também — já foi persistido no servidor.
        baseline.value = JSON.stringify(local.value);
        lastSavedAt.value = Date.now();
        tickNow.value = Date.now();
        startTick();
    } catch (err) {
        uploadError.value = { ...uploadError.value, [s.key]: 'Erro de rede — tente novamente.' };
    } finally {
        loading.finish();
        uploadLoading.value = { ...uploadLoading.value, [s.key]: false };
        event.target.value = '';
    }
}

async function removeImage(s) {
    const ok = await confirm({
        title: 'Remover imagem',
        message: `Deseja realmente remover a ${s.label?.toLowerCase() || 'imagem'}?`,
        confirmText: 'Remover',
        variant: 'danger',
    });
    if (!ok) return;

    const fd = new FormData();
    fd.append('key', s.key);

    try {
        const res = await fetch(route('master.clientes.cms.settings.remove-file', props.cliente.id), {
            method: 'POST',
            body: fd,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });
        const json = await res.json();
        if (res.ok && json.ok) {
            s.value = null;
            baseline.value = JSON.stringify(local.value);
            lastSavedAt.value = Date.now();
            tickNow.value = Date.now();
            startTick();
        }
    } catch (err) { /* ignore */ }
}

function fieldKind(s) {
    if (s.type === 'image') return 'image';
    if (s.type === 'text') return 'textarea';
    if (s.key.startsWith('tema.cor')) return 'color';
    if (s.key === 'contato.telefone') return 'telefone';
    if (s.key === 'contato.whatsapp') return 'whatsapp';
    return 'text';
}

function cacheBusted(path) {
    if (!path) return '';
    return `/storage/${path}?t=${Date.now()}`;
}

// Aviso de saída se houver alterações não salvas (defesa contra perda acidental).
watch(isDirty, (novo) => {
    if (novo) {
        window.onbeforeunload = () => 'Há alterações não salvas.';
    } else {
        window.onbeforeunload = null;
    }
});

onUnmounted(() => {
    stopTick();
    window.onbeforeunload = null;
});
</script>

<template>
    <Head :title="`Site — Cliente: ${cliente.nome} · Configurações`" />
    <MasterLayout>
        <template #page-title>Site — {{ cliente.nome }}</template>

        <ClientCmsHeader :cliente="cliente" section="Configurações" />

        <div class="flex flex-wrap items-center gap-2 mb-6">
            <Link :href="route('master.clientes.cms.index', cliente.id)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white ring-1 ring-slate-200 text-sm text-slate-700 hover:bg-slate-50">
                <Icon name="document" :size="16" />
                Páginas
            </Link>
            <Link :href="route('master.clientes.cms.menus.index', cliente.id)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white ring-1 ring-slate-200 text-sm text-slate-700 hover:bg-slate-50">
                <Icon name="menu" :size="16" />
                Menus
            </Link>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-900 text-white text-sm font-medium">
                <Icon name="cog" :size="16" />
                Configurações
            </span>
        </div>

        <!-- ================== CHECKLIST DE COMPLETUDE ================== -->
        <!-- Leitura pura dos campos existentes; zero lógica nova. Reflete o
             estado que a landing pública já consome. -->
        <div class="max-w-4xl mb-6">
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-5">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h3 class="font-serif text-base font-bold text-slate-900 flex items-center gap-2">
                            <span>Progresso da configuração</span>
                            <span
                                class="text-xs font-sans font-medium px-2 py-0.5 rounded-full"
                                :class="completude.completo
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : 'bg-amber-100 text-amber-800'"
                            >
                                {{ completude.total - completude.faltando }} de {{ completude.total }}
                            </span>
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Estes campos são os mais importantes para a sua página ficar completa.
                        </p>
                    </div>
                    <a
                        :href="cliente.landing_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-macaybas-primary text-white text-sm font-medium hover:bg-macaybas-primary-700 shadow-sm"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        Ver minha página
                    </a>
                </div>

                <ul class="mt-4 space-y-2">
                    <li v-for="item in completude.items" :key="item.id">
                        <button
                            type="button"
                            @click="focusField(item)"
                            class="w-full text-left flex items-start gap-3 px-3 py-2 rounded-lg hover:bg-slate-50 transition"
                            :title="`Ir para o campo '${item.label}'`"
                        >
                            <!-- Círculo verde quando preenchido, cinza vazio quando não -->
                            <span
                                class="mt-0.5 h-5 w-5 rounded-full flex items-center justify-center flex-shrink-0 ring-1"
                                :class="item.filled
                                    ? 'bg-emerald-500 text-white ring-emerald-500'
                                    : 'bg-white text-slate-400 ring-slate-300'"
                            >
                                <svg v-if="item.filled" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-sm font-medium"
                                    :class="item.filled ? 'text-slate-700' : 'text-slate-900'"
                                >
                                    {{ item.label }}
                                </p>
                                <p v-if="item.hint" class="text-xs text-slate-500 mt-0.5">
                                    {{ item.hint }}
                                </p>
                            </div>
                            <span
                                v-if="! item.filled"
                                class="text-xs text-amber-700 bg-amber-50 ring-1 ring-amber-200 rounded-full px-2 py-0.5 flex-shrink-0 self-center"
                            >Pendente</span>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Alerta discreto quando o onboarding já foi fechado mas
                 ainda falta algo essencial. Intencionalmente SÓ visível
                 depois do onboarding — evita redundância no primeiro acesso. -->
            <Alert
                v-if="! showOnboarding && completude.faltando > 0"
                variant="warning"
                :title="`Você ainda não preencheu ${completude.faltando === 1 ? '1 dado essencial' : completude.faltando + ' dados essenciais'} que aparece${completude.faltando === 1 ? '' : 'm'} na sua página.`"
                class="mt-3"
            >
                Complete os itens da lista acima para deixar sua página com a sua cara.
            </Alert>
        </div>

        <!-- ================== ONBOARDING (aparece só no 1º acesso) ================== -->
        <div v-if="showOnboarding" class="max-w-4xl mb-6">
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-6 relative">
                <button
                    type="button"
                    @click="completeOnboarding()"
                    class="absolute top-4 right-4 h-8 w-8 flex items-center justify-center rounded-full hover:bg-amber-100 text-amber-800"
                    aria-label="Fechar tour"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                <div class="flex items-start gap-4">
                    <div class="h-10 w-10 rounded-full bg-amber-400 text-white flex items-center justify-center flex-shrink-0">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-serif text-lg font-bold text-amber-900">
                            Bem-vindo! Vamos configurar sua página em 4 passos.
                        </h3>
                        <p class="mt-1 text-sm text-amber-800">
                            Sua landing já está no ar em
                            <a :href="cliente.landing_url" target="_blank" rel="noopener"
                               class="font-semibold underline hover:text-amber-900">
                                /c/{{ cliente.slug }}
                            </a>.
                            Agora é só deixar com a sua cara:
                        </p>

                        <ol class="mt-3 space-y-2 text-sm text-amber-900">
                            <li class="flex gap-2">
                                <span class="h-5 w-5 rounded-full bg-amber-200 text-amber-900 text-xs font-bold flex items-center justify-center flex-shrink-0">1</span>
                                <span>Em <strong>Dados da página</strong>, edite o nome e a descrição do seu negócio.</span>
                            </li>
                            <li class="flex gap-2">
                                <span class="h-5 w-5 rounded-full bg-amber-200 text-amber-900 text-xs font-bold flex items-center justify-center flex-shrink-0">2</span>
                                <span>Em <strong>Mapa / Localização</strong>, informe o endereço para o mapa aparecer na página.</span>
                            </li>
                            <li class="flex gap-2">
                                <span class="h-5 w-5 rounded-full bg-amber-200 text-amber-900 text-xs font-bold flex items-center justify-center flex-shrink-0">3</span>
                                <span>Em <strong>Contato</strong>, preencha e-mail, telefone e WhatsApp.</span>
                            </li>
                            <li class="flex gap-2">
                                <span class="h-5 w-5 rounded-full bg-amber-200 text-amber-900 text-xs font-bold flex items-center justify-center flex-shrink-0">4</span>
                                <span>Clique em <strong>Salvar alterações</strong> no final da página.</span>
                            </li>
                        </ol>

                        <button
                            type="button"
                            @click="completeOnboarding()"
                            class="mt-4 text-sm font-medium text-amber-900 hover:text-amber-950 underline"
                        >
                            Já entendi, fechar este aviso
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================== GRUPOS DE SETTINGS ================== -->
        <div class="space-y-6 max-w-4xl pb-24">
            <div v-for="[key, group] in orderedGroups" :key="key"
                 :id="'grupo-' + key"
                 class="card scroll-mt-20">
                <div class="card-header">
                    <h2 class="card-title">{{ groupLabels[key] ?? key }}</h2>
                    <p v-if="groupDescriptions[key]" class="text-sm text-slate-500 mt-1">
                        {{ groupDescriptions[key] }}
                    </p>
                </div>
                <div class="card-body space-y-4">
                    <!-- Fallback visual do mapa: todos os 5 campos vazios -->
                    <Alert
                        v-if="key === 'localizacao' && mapaVazio"
                        variant="info"
                        title="Adicione um endereço para exibir o mapa"
                    >
                        Preencha um endereço (ou latitude/longitude, ou um embed do Google Maps) nos campos abaixo para que o mapa apareça na sua página.
                    </Alert>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="s in group" :key="s.key"
                             :class="fieldKind(s) === 'textarea' ? 'sm:col-span-2' : ''">
                            <label class="form-label">{{ s.label }}</label>

                            <textarea v-if="fieldKind(s) === 'textarea'"
                                      v-model="s.value"
                                      rows="3"
                                      :placeholder="placeholderFor(s)"
                                      class="form-textarea"></textarea>

                            <div v-else-if="fieldKind(s) === 'image'" class="space-y-2">
                                <div v-if="s.value" class="flex items-center gap-3">
                                    <img :src="cacheBusted(s.value)"
                                         class="h-20 rounded-lg ring-1 ring-slate-200 object-contain bg-slate-50 px-3 py-1">
                                    <button type="button" @click="removeImage(s)"
                                            v-tooltip="'Remover esta imagem'"
                                            class="inline-flex items-center gap-1.5 text-sm text-red-600 hover:underline">
                                        <Icon name="trash" :size="14" />
                                        Remover
                                    </button>
                                </div>
                                <input type="file"
                                       :disabled="uploadLoading[s.key]"
                                       accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,image/x-icon,.ico"
                                       @change="uploadImage($event, s)"
                                       class="text-sm block">
                                <p v-if="uploadLoading[s.key]" class="text-xs text-macaybas-primary">Enviando...</p>
                                <p v-if="uploadError[s.key]" class="text-xs text-red-600">{{ uploadError[s.key] }}</p>
                                <p v-else class="form-help">PNG, JPG, WebP, SVG ou ICO — até 5MB.</p>
                            </div>

                            <input v-else-if="fieldKind(s) === 'color'"
                                   type="color" v-model="s.value"
                                   class="h-10 w-full rounded-lg border border-slate-300 cursor-pointer">

                            <InputMasked v-else-if="fieldKind(s) === 'telefone' || fieldKind(s) === 'whatsapp'"
                                         v-model="s.value"
                                         :mask="['(##) ####-####', '(##) #####-####']"
                                         :placeholder="placeholderFor(s)" />

                            <input v-else
                                   v-model="s.value"
                                   :placeholder="placeholderFor(s)"
                                   class="form-input">

                            <p v-if="s.description" class="form-help">{{ s.description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================== BARRA STICKY DE SALVAR ================== -->
        <div class="fixed bottom-0 left-0 right-0 z-20 bg-white border-t border-slate-200 shadow-lg">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-3">
                <p class="text-sm" :class="isDirty ? 'text-amber-700 font-medium' : 'text-slate-500'">
                    <template v-if="isDirty">
                        <span class="inline-flex items-center gap-1">
                            <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                            Há alterações não salvas.
                        </span>
                    </template>
                    <template v-else-if="savedAgo">
                        <span class="inline-flex items-center gap-1.5 text-emerald-700">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Alterações publicadas {{ savedAgo }}
                        </span>
                    </template>
                    <template v-else>
                        Tudo salvo.
                    </template>
                </p>
                <button type="button"
                        @click="save"
                        :disabled="! isDirty || saving"
                        class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
                    <template v-if="saving">Salvando...</template>
                    <template v-else>Salvar alterações</template>
                </button>
            </div>
        </div>
    </MasterLayout>
</template>
