<script setup>
import { reactive, ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import { dataBR } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ documents: Object, filters: Object, categories: Array });
useAutoReload(['documents'], 20000);

const filtros = reactive({ ...props.filters });
const showUpload = ref(false);
const confirmDelete = ref(null);

const form = useForm({
    category_id: null, titulo: '', descricao: '',
    arquivo: null, data_documento: '', data_vencimento: '',
    is_confidential: false, tags: [],
});

const categoryForm = useForm({ nome: '', cor: '#64748b', icon: 'file' });
const showCategoryForm = ref(false);

function filtrar() { router.get(route('admin.documentos.index'), filtros, { preserveState: true, replace: true }); }

function salvar() {
    form.post(route('admin.documentos.store'), {
        preserveScroll: true, only: ['documents'],
        forceFormData: true,
        onSuccess: () => { showUpload.value = false; form.reset(); },
    });
}

function onFile(e) { form.arquivo = e.target.files?.[0]; }

function doDelete() {
    router.delete(route('admin.documentos.destroy', confirmDelete.value.id), {
        preserveScroll: true, only: ['documents'],
        onSuccess: () => confirmDelete.value = null,
    });
}

function createCategory() {
    categoryForm.post(route('admin.documentos.categorias.store'), {
        preserveScroll: true, only: ['categories'],
        onSuccess: () => { showCategoryForm.value = false; categoryForm.reset(); categoryForm.cor = '#64748b'; categoryForm.icon = 'file'; },
    });
}
function delCategory(id) {
    if (confirm('Excluir categoria?')) {
        router.delete(route('admin.documentos.categorias.destroy', id), { preserveScroll: true, only: ['categories'] });
    }
}

function formatSize(b) {
    if (!b) return '';
    const kb = b / 1024;
    return kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.round(kb)} KB`;
}

const hoje = new Date().toISOString().slice(0, 10);
const isVencido = (d) => d && d < hoje;
const isProxVenc = (d) => {
    if (!d) return false;
    const dt = new Date(d);
    const daqui30 = new Date(); daqui30.setDate(daqui30.getDate() + 30);
    return dt <= daqui30 && dt >= new Date();
};
</script>

<template>
    <Head title="Documentos" />
    <AdminLayout>
        <template #page-title>Documentos</template>
        <PageHeader title="Documentos e arquivos" subtitle="Contratos, notas fiscais, comprovantes e documentos sanitários">
            <template #actions>
                <button @click="showCategoryForm = !showCategoryForm" class="btn-outline">Categorias</button>
                <button @click="showUpload = !showUpload" class="btn-primary">+ Upload</button>
            </template>
        </PageHeader>

        <!-- Form Upload -->
        <div v-if="showUpload" class="card mb-6">
            <div class="card-header"><h2 class="card-title">Enviar documento</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><InputLabel value="Título" /><input v-model="form.titulo" required class="form-input"></div>
                <div>
                    <InputLabel value="Categoria" />
                    <select v-model="form.category_id" class="form-select">
                        <option :value="null">—</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.nome }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Arquivo (até 20 MB)" />
                    <input type="file" @change="onFile" class="form-input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt">
                </div>
                <div><InputLabel value="Data do documento" /><InputDate v-model="form.data_documento" /></div>
                <div><InputLabel value="Data de vencimento" /><InputDate v-model="form.data_vencimento" /></div>
                <div class="sm:col-span-2"><InputLabel value="Descrição" /><textarea v-model="form.descricao" rows="2" class="form-textarea"></textarea></div>
                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_confidential" class="rounded"> Documento confidencial
                    </label>
                </div>
                <div class="sm:col-span-2 flex justify-end gap-2">
                    <button @click="showUpload = false" class="btn-outline">Cancelar</button>
                    <button @click="salvar" :disabled="form.processing" class="btn-primary">
                        {{ form.processing ? `Enviando... ${form.progress?.percentage ?? 0}%` : 'Enviar' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Gestão de categorias -->
        <div v-if="showCategoryForm" class="card mb-6">
            <div class="card-header"><h2 class="card-title">Categorias de documentos</h2></div>
            <div class="card-body space-y-3">
                <div class="flex gap-2 flex-wrap">
                    <span v-for="c in categories" :key="c.id"
                          class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm"
                          :style="{ background: (c.cor || '#64748b') + '20', color: c.cor || '#64748b' }">
                        {{ c.nome }}
                        <button @click="delCategory(c.id)" class="hover:opacity-70">×</button>
                    </span>
                </div>
                <div class="grid gap-3 sm:grid-cols-4 pt-3 border-t border-slate-100">
                    <div class="sm:col-span-2"><InputLabel value="Nome da categoria" /><input v-model="categoryForm.nome" class="form-input"></div>
                    <div><InputLabel value="Cor (hex)" /><input type="color" v-model="categoryForm.cor" class="h-10 w-full rounded border border-slate-300"></div>
                    <div class="flex items-end"><button @click="createCategory" :disabled="categoryForm.processing" class="btn-primary w-full">Adicionar</button></div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-3">
                <input v-model="filtros.search" @keyup.enter="filtrar" placeholder="Buscar por título ou descrição" class="form-input">
                <select v-model="filtros.category_id" @change="filtrar" class="form-select">
                    <option value="">Todas as categorias</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.nome }}</option>
                </select>
                <select v-model="filtros.venc" @change="filtrar" class="form-select">
                    <option value="">Todos</option>
                    <option value="proximos">Vencem em 30 dias</option>
                    <option value="vencidos">Vencidos</option>
                </select>
            </div>
        </div>

        <!-- Grid de documentos -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="d in documents.data" :key="d.id" class="card">
                <div class="card-body">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <div v-if="d.category" class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold"
                                 :style="{ background: (d.category.cor || '#64748b') + '20', color: d.category.cor || '#64748b' }">
                                📄
                            </div>
                            <span v-if="d.category" class="text-xs text-slate-500">{{ d.category.nome }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <span v-if="d.is_confidential" title="Confidencial">🔒</span>
                            <span v-if="isVencido(d.data_vencimento)" class="badge-red text-xs">Vencido</span>
                            <span v-else-if="isProxVenc(d.data_vencimento)" class="badge-yellow text-xs">Próx. venc.</span>
                        </div>
                    </div>
                    <h3 class="font-semibold text-slate-900 mb-1 truncate" :title="d.titulo">{{ d.titulo }}</h3>
                    <p v-if="d.descricao" class="text-xs text-slate-500 line-clamp-2 mb-2">{{ d.descricao }}</p>
                    <div class="text-xs text-slate-500 space-y-1">
                        <div v-if="d.data_documento">📅 {{ dataBR(d.data_documento) }}</div>
                        <div v-if="d.data_vencimento">⏰ Vence em {{ dataBR(d.data_vencimento) }}</div>
                        <div>📎 {{ d.nome_arquivo }} ({{ formatSize(d.size) }})</div>
                    </div>
                    <div class="flex gap-2 mt-3 pt-3 border-t border-slate-100">
                        <a :href="d.url" target="_blank" class="btn-outline btn-sm flex-1 text-center">Abrir</a>
                        <button @click="confirmDelete = d" class="btn-sm text-red-600 hover:underline">Excluir</button>
                    </div>
                </div>
            </div>

            <div v-if="!documents.data.length" class="sm:col-span-2 lg:col-span-3 card p-10 text-center text-slate-500">
                Nenhum documento encontrado.
            </div>
        </div>

        <ConfirmModal :show="!!confirmDelete" title="Excluir documento"
                      :message="`Excluir ${confirmDelete?.titulo}? O arquivo também será removido do servidor.`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
