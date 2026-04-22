<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputMasked from '@/Components/InputMasked.vue';

const props = defineProps({ settings: Object });

const local = ref(JSON.parse(JSON.stringify(props.settings)));

function save() {
    const payload = [];
    for (const group of Object.values(local.value)) {
        for (const s of group) {
            payload.push({ key: s.key, value: s.value });
        }
    }
    router.put(route('admin.cms.settings.update'), { settings: payload }, { preserveScroll: true });
}

async function uploadImage(event, key) {
    const file = event.target.files?.[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('key', key);
    fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    await fetch(route('admin.cms.settings.upload'), {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    router.reload({ only: ['settings'] });
}

const groupLabels = {
    identidade: 'Identidade visual',
    contato: 'Contato',
    social: 'Redes sociais',
    tema: 'Tema / cores',
    seo: 'SEO',
};

// Detecta tipo especial por chave (para aplicar máscara correta)
function fieldKind(s) {
    if (s.type === 'image') return 'image';
    if (s.type === 'text') return 'textarea';
    if (s.key.startsWith('tema.cor')) return 'color';
    if (s.key === 'contato.telefone') return 'telefone';
    if (s.key === 'contato.whatsapp') return 'whatsapp';
    return 'text';
}
</script>

<template>
    <Head title="Configurações do site" />
    <AdminLayout>
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
                            <img v-if="s.value" :src="`/storage/${s.value}`"
                                 class="h-20 rounded-lg ring-1 ring-slate-200 object-contain bg-slate-50 px-3 py-1">
                            <input type="file" accept="image/*" @change="uploadImage($event, s.key)" class="text-sm">
                        </div>

                        <input v-else-if="fieldKind(s) === 'color'"
                               type="color" v-model="s.value"
                               class="h-10 w-full rounded-lg border border-slate-300 cursor-pointer">

                        <InputMasked v-else-if="fieldKind(s) === 'telefone'"
                                     v-model="s.value"
                                     :mask="['(##) ####-####', '(##) #####-####']"
                                     placeholder="(31) 99999-9999" />

                        <InputMasked v-else-if="fieldKind(s) === 'whatsapp'"
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
    </AdminLayout>
</template>
