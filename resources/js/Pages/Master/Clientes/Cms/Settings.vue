<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
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
</script>

<template>
    <Head title="Configurações do site" />
    <MasterLayout>
        <template #page-title>Configurações do site</template>

        <PageHeader
            title="Configurações"
            subtitle="Edite os dados que alimentam a sua página pública — dados da página, mapa e contato."
        >
            <template #actions>
                <a
                    :href="cliente.landing_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white ring-1 ring-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-macaybas-primary"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    Ver minha página
                </a>
            </template>
        </PageHeader>

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
            <div v-for="[key, group] in orderedGroups" :key="key" class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ groupLabels[key] ?? key }}</h2>
                    <p v-if="groupDescriptions[key]" class="text-sm text-slate-500 mt-1">
                        {{ groupDescriptions[key] }}
                    </p>
                </div>
                <div class="card-body space-y-4">
                    <!-- Fallback visual do mapa: todos os 5 campos vazios -->
                    <div v-if="key === 'localizacao' && mapaVazio"
                         class="rounded-lg bg-slate-50 ring-1 ring-slate-200 px-4 py-3 text-sm text-slate-700">
                        <div class="flex items-start gap-2">
                            <svg class="h-5 w-5 text-slate-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <div>
                                <p class="font-medium">Nenhum dado de mapa configurado ainda.</p>
                                <p class="mt-0.5 text-slate-600">
                                    Adicione um endereço (ou coordenadas, ou um embed do Google Maps) nos campos abaixo para exibir o mapa na sua página.
                                </p>
                            </div>
                        </div>
                    </div>

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
                                            class="text-sm text-red-600 hover:underline">Remover</button>
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
