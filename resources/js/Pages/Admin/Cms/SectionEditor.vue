<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    section: { type: Object, required: true },
});

const emit = defineEmits(['save', 'publish', 'toggle-active']);

const local = ref(JSON.parse(JSON.stringify(props.section)));

const fields = computed(() => {
    const map = {
        hero: [
            { key: 'eyebrow', label: 'Texto acima do título', type: 'text' },
            { key: 'titulo', label: 'Título principal', type: 'text' },
            { key: 'subtitulo', label: 'Subtítulo', type: 'textarea' },
            { key: 'cta_texto', label: 'Botão: texto', type: 'text' },
            { key: 'cta_link', label: 'Botão: link', type: 'text' },
            { key: 'imagem_fundo', label: 'Imagem de fundo', type: 'image' },
            { key: 'overlay_opacity', label: 'Opacidade do overlay (0 a 1)', type: 'number', step: 0.05 },
        ],
        about: [
            { key: 'subtitulo', label: 'Subtítulo curto', type: 'text' },
            { key: 'titulo', label: 'Título', type: 'text' },
            { key: 'texto', label: 'Texto institucional', type: 'textarea', rows: 8 },
            { key: 'cta_texto', label: 'Botão: texto', type: 'text' },
            { key: 'cta_link', label: 'Botão: link', type: 'text' },
            { key: 'imagem', label: 'Imagem', type: 'image' },
        ],
        features: [
            { key: 'subtitulo', label: 'Subtítulo curto', type: 'text' },
            { key: 'titulo', label: 'Título', type: 'text' },
            {
                key: 'items', label: 'Áreas de atuação', type: 'items',
                schema: [
                    { key: 'icon', label: 'Ícone (nome)', type: 'text' },
                    { key: 'titulo', label: 'Título', type: 'text' },
                    { key: 'descricao', label: 'Descrição', type: 'textarea' },
                ],
            },
        ],
        gallery: [
            { key: 'subtitulo', label: 'Subtítulo', type: 'text' },
            { key: 'titulo', label: 'Título', type: 'text' },
            {
                key: 'imagens', label: 'Imagens da galeria', type: 'items',
                schema: [
                    { key: 'path', label: 'Imagem', type: 'image' },
                    { key: 'legenda', label: 'Legenda', type: 'text' },
                ],
            },
        ],
        testimonials: [
            { key: 'titulo', label: 'Título', type: 'text' },
            { key: 'subtitulo', label: 'Subtítulo', type: 'text' },
            {
                key: 'items', label: 'Depoimentos', type: 'items',
                schema: [
                    { key: 'foto', label: 'Foto', type: 'image' },
                    { key: 'nome', label: 'Nome', type: 'text' },
                    { key: 'cargo', label: 'Cargo/Localização', type: 'text' },
                    { key: 'texto', label: 'Depoimento', type: 'textarea' },
                ],
            },
        ],
        contact: [
            { key: 'titulo', label: 'Título da seção', type: 'text' },
            { key: 'subtitulo', label: 'Subtítulo / chamada', type: 'textarea' },
            {
                key: '__info', label: '', type: 'info',
                message: 'Endereço, e-mail e telefone exibidos nesta seção vêm de Admin → Configurações do site → Contato. Alterações lá refletem no mapa e nas informações de contato automaticamente.',
            },
        ],
    };
    return map[local.value.type] ?? [];
});

function save() { emit('save', local.value); }
function publish() { emit('publish', local.value); }
function toggleActive() { emit('toggle-active', local.value); }

async function uploadTo(event, cb) {
    const file = event.target.files?.[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    const res = await fetch(route('admin.cms.upload-image'), {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const json = await res.json();
    if (json.path) cb(json.path);
    event.target.value = '';
}

function uploadMainImage(event, key) {
    uploadTo(event, (path) => {
        local.value.draft_data = { ...local.value.draft_data, [key]: path };
    });
}

function uploadItemImage(event, fieldKey, itemIndex, subKey) {
    uploadTo(event, (path) => {
        if (!Array.isArray(local.value.draft_data[fieldKey])) local.value.draft_data[fieldKey] = [];
        local.value.draft_data[fieldKey][itemIndex][subKey] = path;
    });
}

function addItem(field) {
    const blank = Object.fromEntries(field.schema.map((s) => [s.key, '']));
    if (!Array.isArray(local.value.draft_data[field.key])) local.value.draft_data[field.key] = [];
    local.value.draft_data[field.key].push(blank);
}

function removeItem(fieldKey, idx) {
    local.value.draft_data[fieldKey] = local.value.draft_data[fieldKey].filter((_, i) => i !== idx);
}

function moveItem(fieldKey, idx, delta) {
    const arr = local.value.draft_data[fieldKey];
    const newIdx = idx + delta;
    if (newIdx < 0 || newIdx >= arr.length) return;
    [arr[idx], arr[newIdx]] = [arr[newIdx], arr[idx]];
}
</script>

<template>
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ local.nome }}</h2>
                <p class="text-xs text-slate-500 mt-0.5">Tipo: {{ local.type }}</p>
            </div>
            <div class="flex items-center gap-2">
                <span v-if="local.has_draft" class="badge-yellow">Rascunho não publicado</span>
                <button @click="toggleActive" class="btn-sm btn-outline">
                    {{ local.is_active ? 'Desativar' : 'Ativar' }}
                </button>
            </div>
        </div>

        <div class="card-body space-y-4">
            <div v-for="field in fields" :key="field.key">
                <div v-if="field.type === 'info'"
                     class="rounded-lg bg-macaybas-primary-50 border border-macaybas-primary-200 p-3 text-sm text-macaybas-primary-900">
                    <strong>ℹ️ </strong>{{ field.message }}
                </div>

                <template v-else>
                    <label class="form-label">{{ field.label }}</label>

                    <input v-if="field.type === 'text'"
                           v-model="local.draft_data[field.key]"
                           class="form-input" />

                    <textarea v-else-if="field.type === 'textarea'"
                              v-model="local.draft_data[field.key]"
                              :rows="field.rows || 4"
                              class="form-textarea"></textarea>

                    <input v-else-if="field.type === 'number'"
                           type="number" :step="field.step || 1"
                           v-model.number="local.draft_data[field.key]"
                           class="form-input" />

                    <div v-else-if="field.type === 'image'" class="space-y-2">
                        <div v-if="local.draft_data[field.key]" class="flex items-center gap-3">
                            <img :src="`/storage/${local.draft_data[field.key]}`" class="h-24 w-24 object-cover rounded-lg ring-1 ring-slate-200">
                            <button type="button" @click="local.draft_data[field.key] = null" class="text-sm text-red-600 hover:underline">Remover</button>
                        </div>
                        <input type="file" accept="image/*" @change="uploadMainImage($event, field.key)" class="text-sm">
                        <p class="form-help">JPG, PNG ou WebP — até 5MB.</p>
                    </div>

                    <div v-else-if="field.type === 'items'" class="space-y-3">
                        <div v-for="(item, i) in (local.draft_data[field.key] ?? [])" :key="i"
                             class="rounded-lg border border-slate-200 bg-slate-50 p-4 relative">
                            <div class="absolute top-2 right-2 flex gap-1">
                                <button type="button" @click="moveItem(field.key, i, -1)" :disabled="i === 0"
                                        class="text-xs px-2 py-0.5 rounded bg-white border border-slate-300 disabled:opacity-30">↑</button>
                                <button type="button" @click="moveItem(field.key, i, 1)"
                                        :disabled="i === (local.draft_data[field.key].length - 1)"
                                        class="text-xs px-2 py-0.5 rounded bg-white border border-slate-300 disabled:opacity-30">↓</button>
                                <button type="button" @click="removeItem(field.key, i)"
                                        class="text-xs px-2 py-0.5 rounded bg-red-50 text-red-600 border border-red-200">Remover</button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2 mt-4">
                                <template v-for="sub in field.schema" :key="sub.key">
                                    <div v-if="sub.type === 'image'" class="sm:col-span-2">
                                        <label class="text-xs text-slate-600 font-medium">{{ sub.label }}</label>
                                        <div class="mt-1 flex items-center gap-3">
                                            <img v-if="item[sub.key]"
                                                 :src="`/storage/${item[sub.key]}`"
                                                 class="h-24 w-24 object-cover rounded-lg ring-1 ring-slate-200">
                                            <div v-else class="h-24 w-24 rounded-lg bg-slate-200 flex items-center justify-center text-slate-400 text-xs">
                                                sem imagem
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <input type="file" accept="image/*"
                                                       @change="uploadItemImage($event, field.key, i, sub.key)"
                                                       class="text-xs">
                                                <button v-if="item[sub.key]" type="button" @click="item[sub.key] = null"
                                                        class="text-xs text-red-600 hover:underline self-start">Remover imagem</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else-if="sub.type === 'textarea'" class="sm:col-span-2">
                                        <label class="text-xs text-slate-600 font-medium">{{ sub.label }}</label>
                                        <textarea v-model="item[sub.key]" rows="2" class="form-textarea mt-1 text-sm"></textarea>
                                    </div>
                                    <div v-else>
                                        <label class="text-xs text-slate-600 font-medium">{{ sub.label }}</label>
                                        <input v-model="item[sub.key]" class="form-input mt-1 text-sm">
                                    </div>
                                </template>
                            </div>
                        </div>
                        <button type="button" @click="addItem(field)" class="btn-outline btn-sm">+ Adicionar item</button>
                    </div>
                </template>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                <button @click="save" class="btn-outline">Salvar rascunho</button>
                <button @click="publish" class="btn-primary">Salvar e publicar</button>
            </div>
        </div>
    </div>
</template>
