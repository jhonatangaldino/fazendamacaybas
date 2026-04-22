<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    section: { type: Object, required: true },
});

const emit = defineEmits(['save', 'publish', 'toggle-active']);

const local = ref(JSON.parse(JSON.stringify(props.section)));

const fields = computed(() => {
    // Define campos editáveis por tipo de seção.
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
            { key: 'items', label: 'Áreas de atuação', type: 'items', schema: { icon: 'Ícone (nome)', titulo: 'Título', descricao: 'Descrição' } },
        ],
        gallery: [
            { key: 'subtitulo', label: 'Subtítulo', type: 'text' },
            { key: 'titulo', label: 'Título', type: 'text' },
            { key: 'imagens', label: 'Imagens', type: 'items', schema: { path: 'Caminho da imagem', legenda: 'Legenda' } },
        ],
        testimonials: [
            { key: 'titulo', label: 'Título', type: 'text' },
            { key: 'subtitulo', label: 'Subtítulo', type: 'text' },
            { key: 'items', label: 'Depoimentos', type: 'items', schema: { nome: 'Nome', cargo: 'Cargo/Localização', texto: 'Texto', foto: 'Foto' } },
        ],
        contact: [
            { key: 'titulo', label: 'Título', type: 'text' },
            { key: 'subtitulo', label: 'Subtítulo', type: 'text' },
            { key: 'email', label: 'E-mail de contato', type: 'text' },
            { key: 'telefone', label: 'Telefone', type: 'text' },
            { key: 'endereco', label: 'Endereço (usado no mapa do Google)', type: 'text' },
        ],
    };
    return map[local.value.type] ?? [];
});

function save() { emit('save', local.value); }
function publish() { emit('publish', local.value); }
function toggleActive() { emit('toggle-active', local.value); }

async function uploadImage(event, key) {
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
    if (json.path) {
        local.value.draft_data = { ...local.value.draft_data, [key]: json.path };
    }
}

function addItem(key, schema) {
    const blank = Object.fromEntries(Object.keys(schema).map((k) => [k, '']));
    local.value.draft_data[key] = [...(local.value.draft_data[key] ?? []), blank];
}

function removeItem(key, idx) {
    local.value.draft_data[key] = local.value.draft_data[key].filter((_, i) => i !== idx);
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
                        <img :src="`/storage/${local.draft_data[field.key]}`" class="h-20 w-20 object-cover rounded-lg ring-1 ring-slate-200">
                        <button type="button" @click="local.draft_data[field.key] = null" class="text-sm text-red-600 hover:underline">Remover</button>
                    </div>
                    <input type="file" accept="image/*" @change="uploadImage($event, field.key)" class="text-sm">
                    <p class="form-help">Formato: JPG, PNG ou WebP. Tamanho máximo: 5MB.</p>
                </div>

                <div v-else-if="field.type === 'items'" class="space-y-3">
                    <div v-for="(item, i) in (local.draft_data[field.key] ?? [])" :key="i" class="rounded-lg border border-slate-200 p-3 space-y-2 relative bg-slate-50">
                        <button type="button" @click="removeItem(field.key, i)" class="absolute top-2 right-2 text-red-600 text-xs">Remover</button>
                        <div v-for="(label, subKey) in field.schema" :key="subKey">
                            <label class="text-xs text-slate-600 font-medium">{{ label }}</label>
                            <input v-model="local.draft_data[field.key][i][subKey]" class="form-input mt-1">
                        </div>
                    </div>
                    <button type="button" @click="addItem(field.key, field.schema)" class="btn-outline btn-sm">+ Adicionar item</button>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                <button @click="save" class="btn-outline">Salvar rascunho</button>
                <button @click="publish" class="btn-primary">Salvar e publicar</button>
            </div>
        </div>
    </div>
</template>
