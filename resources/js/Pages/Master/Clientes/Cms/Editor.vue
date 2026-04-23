<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SectionEditor from './SectionEditor.vue';
import { useConfirm } from '@/composables/useConfirm.js';

const { confirm } = useConfirm();

const props = defineProps({
    cliente: { type: Object, required: true },
    page: Object,
    sections: Array,
});

const activeSection = ref(props.sections[0]?.id ?? null);
const localSections = ref(JSON.parse(JSON.stringify(props.sections)));

const hasDrafts = computed(() => localSections.value.some((s) => s.has_draft));

const meta = useForm({
    titulo: props.page.titulo,
    meta_title: props.page.meta_title,
    meta_description: props.page.meta_description,
    meta_keywords: props.page.meta_keywords,
});

function saveMeta() {
    meta.put(route('master.clientes.cms.update-page', [props.cliente.id, props.page.id]), { preserveScroll: true });
}

function saveSection(section) {
    router.put(route('master.clientes.cms.section.draft', [props.cliente.id, section.id]), {
        draft_data: section.draft_data,
        nome: section.nome,
        is_active: section.is_active,
    }, { preserveScroll: true });
}

function publishSection(section) {
    router.post(route('master.clientes.cms.section.publish', [props.cliente.id, section.id]), {}, { preserveScroll: true });
}

async function publishAll() {
    const ok = await confirm({
        title: 'Publicar alterações',
        message: 'Todas as alterações em rascunho ficarão visíveis no site público. Deseja publicar agora?',
        confirmText: 'Publicar',
        variant: 'primary',
        icon: 'question',
    });
    if (ok) router.post(route('master.clientes.cms.publish-all', [props.cliente.id, props.page.id]));
}

function toggleActive(section) {
    router.post(route('master.clientes.cms.section.toggle', [props.cliente.id, section.id]), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Editar página — ${page.titulo} · ${cliente.nome}`" />
    <MasterLayout>
        <template #page-title>CMS · {{ cliente.nome }} · {{ page.titulo }}</template>

        <PageHeader :title="page.titulo" :subtitle="`Edite as seções desta página (cliente: ${cliente.nome})`">
            <template #actions>
                <Link :href="route('master.clientes.cms.index', cliente.id)" class="btn-outline">Voltar</Link>
                <a :href="`/${page.slug === 'home' ? '' : page.slug}`" target="_blank" class="btn-outline">Ver site</a>
                <button @click="publishAll" :disabled="!hasDrafts" class="btn-primary">
                    Publicar alterações
                </button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[300px,1fr]">
            <!-- Sidebar de seções -->
            <aside class="card">
                <div class="card-header"><h2 class="card-title">Seções</h2></div>
                <ul class="p-2 space-y-1">
                    <li v-for="s in localSections" :key="s.id">
                        <button
                            @click="activeSection = s.id"
                            :class="[activeSection === s.id ? 'bg-macaybas-primary-50 text-macaybas-primary-800 ring-1 ring-macaybas-primary-200' : 'hover:bg-slate-100']"
                            class="w-full text-left px-3 py-2 rounded-lg text-sm flex items-center justify-between gap-2"
                        >
                            <span class="truncate">{{ s.nome }}</span>
                            <span class="flex items-center gap-1">
                                <span v-if="s.has_draft" class="badge-yellow text-[10px]">Rascunho</span>
                                <span v-if="!s.is_active" class="badge-slate text-[10px]">Off</span>
                            </span>
                        </button>
                    </li>
                </ul>
            </aside>

            <!-- Editor da seção ativa -->
            <div class="space-y-6">
                <SectionEditor
                    v-for="s in localSections"
                    v-show="activeSection === s.id"
                    :key="s.id"
                    :cliente="cliente"
                    :section="s"
                    @save="saveSection"
                    @publish="publishSection"
                    @toggle-active="toggleActive"
                />

                <!-- Meta SEO -->
                <div class="card">
                    <div class="card-header"><h2 class="card-title">SEO e metadados da página</h2></div>
                    <div class="card-body grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="form-label">Título da página</label>
                            <input v-model="meta.titulo" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Meta title (SEO)</label>
                            <input v-model="meta.meta_title" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Meta keywords</label>
                            <input v-model="meta.meta_keywords" class="form-input">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label">Meta description</label>
                            <textarea v-model="meta.meta_description" rows="3" class="form-textarea"></textarea>
                        </div>
                        <div class="sm:col-span-2 flex justify-end">
                            <button @click="saveMeta" :disabled="meta.processing" class="btn-primary">Salvar metadados</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </MasterLayout>
</template>
