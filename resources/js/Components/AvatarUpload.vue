<script setup>
import { ref, computed } from 'vue';
import { useLoading } from '@/composables/useLoading.js';
import { useConfirm } from '@/composables/useConfirm.js';
import { useToast } from '@/composables/useToast.js';

const props = defineProps({
    url: { type: String, default: null },
    uploadUrl: { type: String, required: true },
    removeUrl: { type: String, default: null },
    name: { type: String, default: '?' },
    size: { type: String, default: 'h-24 w-24' },
    shape: { type: String, default: 'circle' }, // circle | square (animais usam square)
    layout: { type: String, default: 'row' },   // row | stacked (stacked: botões abaixo da foto)
});

const shapeClass = computed(() => props.shape === 'square' ? 'rounded-xl' : 'rounded-full');
const fileInput = ref(null);
function openPicker() { fileInput.value?.click(); }

const emit = defineEmits(['updated']);
const loading = useLoading();
const { confirm } = useConfirm();
const { toast } = useToast();
const error = ref('');
const localUrl = ref(props.url);

function inicial() {
    return (props.name || '?').trim().charAt(0).toUpperCase();
}

async function onFile(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    error.value = '';
    const fd = new FormData();
    fd.append('file', file);

    loading.start('Enviando foto...');
    try {
        const res = await fetch(props.uploadUrl, {
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
            error.value = json.message || 'Falha no upload.';
            return;
        }
        localUrl.value = json.avatar_url + '?t=' + Date.now();
        emit('updated', { avatar_url: json.avatar_url, path: json.path });
    } catch (err) {
        error.value = 'Erro de rede. Tente novamente.';
    } finally {
        loading.finish();
        event.target.value = '';
    }
}

async function remove() {
    if (!props.removeUrl) return;
    const ok = await confirm({
        title: 'Remover foto',
        message: 'Tem certeza que deseja remover a foto atual?',
        confirmText: 'Remover',
        cancelText: 'Cancelar',
        variant: 'danger',
    });
    if (!ok) return;

    loading.start('Removendo foto...');
    try {
        const res = await fetch(props.removeUrl, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });
        const json = await res.json();
        if (!res.ok || !json.ok) {
            error.value = json.message || 'Falha ao remover.';
            toast.error(error.value);
            return;
        }
        localUrl.value = null;
        emit('updated', { avatar_url: null, path: null });
        toast.success('Foto removida.');
    } catch (err) {
        error.value = 'Erro de rede.';
        toast.error(error.value);
    } finally {
        loading.finish();
    }
}
</script>

<template>
    <div :class="layout === 'stacked' ? 'flex flex-col items-center gap-3' : 'flex items-center gap-4'">
        <!-- Foto clicável: um clique no avatar já abre o seletor de arquivo -->
        <button type="button" @click="openPicker"
                class="relative cursor-pointer group"
                :title="localUrl ? 'Clique para trocar' : 'Clique para enviar foto'">
            <img v-if="localUrl"
                 :src="localUrl"
                 class="object-cover ring-2 ring-slate-200 group-hover:ring-macaybas-primary-300 transition-all"
                 :class="[size, shapeClass]">
            <div v-else
                 class="flex items-center justify-center bg-macaybas-primary-100 text-macaybas-primary-800 font-semibold text-3xl ring-2 ring-slate-200 group-hover:ring-macaybas-primary-300 transition-all"
                 :class="[size, shapeClass]">
                {{ inicial() }}
            </div>
            <!-- Overlay com ícone de câmera no hover -->
            <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity"
                 :class="shapeClass">
                <svg class="h-7 w-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9zM15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
        </button>

        <!-- Input oculto único -->
        <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="sr-only" @change="onFile">

        <!-- Controles -->
        <div :class="layout === 'stacked' ? 'flex flex-col items-center gap-1' : 'flex flex-col gap-1'">
            <button type="button" @click="openPicker" class="btn-outline btn-sm">
                {{ localUrl ? 'Trocar foto' : 'Enviar foto' }}
            </button>
            <button v-if="localUrl && removeUrl" type="button" @click="remove"
                    class="text-xs text-red-600 hover:underline">
                Remover
            </button>
            <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
            <p v-else class="text-xs text-slate-500">PNG/JPG/WebP até 5MB</p>
        </div>
    </div>
</template>
