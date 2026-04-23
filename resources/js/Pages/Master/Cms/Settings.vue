<script setup>
import { ref } from 'vue';
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

const props = defineProps({ settings: Object });

const local = ref(JSON.parse(JSON.stringify(props.settings)));
const uploadError = ref({});
const uploadLoading = ref({});

function save() {
    const payload = [];
    for (const group of Object.values(local.value)) {
        for (const s of group) {
            payload.push({ key: s.key, value: s.value });
        }
    }
    router.put(route('master.cms.settings.update'), { settings: payload }, { preserveScroll: true });
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
        const res = await fetch(route('master.cms.settings.upload'), {
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
        const res = await fetch(route('master.cms.settings.remove-file'), {
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
        }
    } catch (err) { /* ignore */ }
}

const groupLabels = {
    identidade: 'Identidade visual',
    contato: 'Contato',
    social: 'Redes sociais',
    tema: 'Tema / cores',
    seo: 'SEO',
};

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
</script>

<template>
    <Head title="Configurações do site" />
    <MasterLayout>
        <template #page-title>Configurações do site</template>

        <PageHeader title="Configurações" subtitle="Logo, cores, contatos, redes sociais e SEO — esses dados alimentam a landing page" />

        <div class="space-y-6 max-w-4xl">
            <div v-for="(group, key) in local" :key="key" class="card">
                <div class="card-header"><h2 class="card-title">{{ groupLabels[key] ?? key }}</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div v-for="s in group" :key="s.key" :class="fieldKind(s) === 'textarea' ? 'sm:col-span-2' : ''">
                        <label class="form-label">{{ s.label }}</label>

                        <textarea v-if="fieldKind(s) === 'textarea'"
                                  v-model="s.value" rows="3" class="form-textarea"></textarea>

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
                                     placeholder="(31) 99999-9999" />

                        <input v-else v-model="s.value" class="form-input">

                        <p v-if="s.description" class="form-help">{{ s.description }}</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button @click="save" class="btn-primary">Salvar configurações</button>
            </div>
        </div>
    </MasterLayout>
</template>
