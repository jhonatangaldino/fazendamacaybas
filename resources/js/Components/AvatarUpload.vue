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
});

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
    <div class="flex items-center gap-4">
        <div class="relative">
            <img v-if="localUrl"
                 :src="localUrl"
                 class="rounded-full object-cover ring-2 ring-slate-200"
                 :class="size">
            <div v-else
                 class="rounded-full flex items-center justify-center bg-macaybas-primary-100 text-macaybas-primary-800 font-semibold text-3xl ring-2 ring-slate-200"
                 :class="size">
                {{ inicial() }}
            </div>
        </div>
        <div class="flex flex-col gap-1">
            <label class="btn-outline btn-sm cursor-pointer">
                <span>{{ localUrl ? 'Trocar foto' : 'Enviar foto' }}</span>
                <input type="file" accept="image/png,image/jpeg,image/webp,image/gif"
                       class="sr-only"
                       @change="onFile">
            </label>
            <button v-if="localUrl && removeUrl" type="button" @click="remove"
                    class="text-xs text-red-600 hover:underline text-left">
                Remover
            </button>
            <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
            <p v-else class="text-xs text-slate-500">PNG/JPG/WebP até 5MB</p>
        </div>
    </div>
</template>
