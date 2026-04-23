<script setup>
/**
 * Master/Cms/Menus/Index.vue — hotfix pós-M7
 *
 * Elimina o débito técnico identificado em M7: o MenuController::index()
 * renderizava 'Master/Cms/Menus/Index' que não existia no disco.
 *
 * Escopo mínimo funcional: listagem de menus + CRUD de items inline.
 * Reorder drag-drop fica para iteração futura (a rota
 * master.cms.menus.reorder existe mas não é acionada por esta view).
 */

import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import ClientCmsHeader from '@/Components/ClientCmsHeader.vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    cliente: { type: Object, required: true },
    menus: { type: Array, default: () => [] },
});

// Form de adicionar item — apenas um menu em "modo adicionar" por vez
const adding = ref(null);
const addForm = useForm({
    label: '',
    url: '',
    target: '_self',
    icon: '',
});

function openAdd(menu) {
    adding.value = menu.id;
    addForm.reset();
}
function cancelAdd() {
    adding.value = null;
    addForm.reset();
    addForm.clearErrors();
}
function saveAdd(menu) {
    addForm.post(route('master.clientes.cms.menus.items.store', [props.cliente.id, menu.id]), {
        preserveScroll: true,
        onSuccess: () => { cancelAdd(); },
    });
}

// Edição inline — apenas um item em "modo editar" por vez
const editingId = ref(null);
const editForm = useForm({
    label: '',
    url: '',
    target: '_self',
    icon: '',
    is_active: true,
});

function openEdit(item) {
    editingId.value = item.id;
    editForm.label = item.label;
    editForm.url = item.url;
    editForm.target = item.target || '_self';
    editForm.icon = item.icon || '';
    editForm.is_active = Boolean(item.is_active);
    editForm.clearErrors();
}
function cancelEdit() {
    editingId.value = null;
    editForm.clearErrors();
}
function saveEdit(item) {
    editForm.put(route('master.clientes.cms.menus.items.update', [props.cliente.id, item.id]), {
        preserveScroll: true,
        onSuccess: () => { cancelEdit(); },
    });
}

function toggleActive(item) {
    // Reaproveita updateItem com payload completo e flip do is_active
    router.put(route('master.clientes.cms.menus.items.update', [props.cliente.id, item.id]), {
        label: item.label,
        url: item.url,
        target: item.target,
        icon: item.icon,
        is_active: ! item.is_active,
    }, { preserveScroll: true });
}

function removeItem(item) {
    if (! confirm(`Remover "${item.label}"?`)) return;
    router.delete(route('master.clientes.cms.menus.items.destroy', [props.cliente.id, item.id]), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`CMS — Cliente: ${cliente.nome} · Menus`" />
    <MasterLayout>
        <template #page-title>CMS — {{ cliente.nome }}</template>

        <ClientCmsHeader :cliente="cliente" section="Menus" />

        <div class="flex flex-wrap items-center gap-2 mb-6">
            <Link :href="route('master.clientes.cms.index', cliente.id)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white ring-1 ring-slate-200 text-sm text-slate-700 hover:bg-slate-50">
                <Icon name="document" :size="16" />
                Páginas
            </Link>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-900 text-white text-sm font-medium">
                <Icon name="menu" :size="16" />
                Menus
            </span>
            <Link :href="route('master.clientes.cms.settings', cliente.id)"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white ring-1 ring-slate-200 text-sm text-slate-700 hover:bg-slate-50">
                <Icon name="cog" :size="16" />
                Configurações
            </Link>
        </div>

        <div v-if="menus.length === 0" class="rounded-2xl bg-white ring-1 ring-slate-200 p-10 text-center">
            <h3 class="text-sm font-semibold text-slate-900">Nenhum menu cadastrado</h3>
            <p class="mt-1 text-sm text-slate-500">
                Os menus são criados via seeder. Se esta mensagem aparecer em produção, verifique
                a tabela <code>cms_menus</code>.
            </p>
        </div>

        <div v-for="menu in menus" :key="menu.id" class="mb-6 rounded-2xl bg-white ring-1 ring-slate-200 overflow-hidden">
            <!-- Cabeçalho do menu -->
            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-semibold text-slate-900 truncate">{{ menu.nome }}</div>
                    <div class="text-xs text-slate-500">
                        Local: <code class="px-1.5 py-0.5 rounded bg-slate-200 text-slate-700">{{ menu.local }}</code>
                        · {{ menu.items.length }} item(ns)
                    </div>
                </div>
                <button
                    v-if="adding !== menu.id"
                    @click="openAdd(menu)"
                    v-tooltip="`Adicionar novo item a ${menu.nome}`"
                    class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800"
                >
                    <Icon name="plus" :size="16" />
                    Adicionar item
                </button>
            </div>

            <!-- Tabela de items -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-white text-slate-600">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Rótulo</th>
                            <th class="px-4 py-2 text-left font-medium">URL</th>
                            <th class="px-4 py-2 text-left font-medium hidden sm:table-cell">Target</th>
                            <th class="px-4 py-2 text-left font-medium">Status</th>
                            <th class="px-4 py-2 text-right font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template v-for="item in menu.items" :key="item.id">
                            <!-- Linha em modo leitura -->
                            <tr v-if="editingId !== item.id" class="hover:bg-slate-50/60">
                                <td class="px-4 py-2 font-medium text-slate-900">{{ item.label }}</td>
                                <td class="px-4 py-2">
                                    <code class="text-xs text-slate-700 break-all">{{ item.url }}</code>
                                </td>
                                <td class="px-4 py-2 text-slate-600 hidden sm:table-cell">
                                    <span class="text-xs">{{ item.target || '_self' }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <span
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium ring-1"
                                        :class="item.is_active
                                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                            : 'bg-slate-100 text-slate-600 ring-slate-200'"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="item.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                        {{ item.is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button
                                            @click="toggleActive(item)"
                                            :title="item.is_active ? 'Desativar' : 'Reativar'"
                                            class="p-1.5 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900"
                                        >
                                            <svg v-if="item.is_active" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                            <svg v-else class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </button>
                                        <button
                                            @click="openEdit(item)"
                                            title="Editar"
                                            class="p-1.5 rounded-md hover:bg-slate-100 text-slate-600 hover:text-slate-900"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button
                                            @click="removeItem(item)"
                                            title="Remover"
                                            class="p-1.5 rounded-md hover:bg-rose-50 text-slate-600 hover:text-rose-700"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M10 3h4a1 1 0 011 1v3H9V4a1 1 0 011-1z"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Linha em modo edição -->
                            <tr v-else class="bg-amber-50/40">
                                <td class="px-4 py-2">
                                    <input
                                        v-model="editForm.label"
                                        type="text"
                                        class="w-full px-2 py-1 rounded ring-1 ring-slate-200 text-sm focus:ring-2 focus:ring-slate-900 focus:outline-none"
                                        :class="editForm.errors.label ? 'ring-red-400' : ''"
                                    >
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model="editForm.url"
                                        type="text"
                                        class="w-full px-2 py-1 rounded ring-1 ring-slate-200 text-sm font-mono focus:ring-2 focus:ring-slate-900 focus:outline-none"
                                        :class="editForm.errors.url ? 'ring-red-400' : ''"
                                    >
                                </td>
                                <td class="px-4 py-2 hidden sm:table-cell">
                                    <select
                                        v-model="editForm.target"
                                        class="px-2 py-1 rounded ring-1 ring-slate-200 text-sm focus:ring-2 focus:ring-slate-900 focus:outline-none"
                                    >
                                        <option value="_self">_self</option>
                                        <option value="_blank">_blank</option>
                                    </select>
                                </td>
                                <td class="px-4 py-2">
                                    <label class="inline-flex items-center gap-1.5 text-xs cursor-pointer">
                                        <input type="checkbox" v-model="editForm.is_active" class="h-4 w-4 rounded border-slate-300">
                                        <span>Ativo</span>
                                    </label>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button
                                            @click="saveEdit(item)"
                                            :disabled="editForm.processing"
                                            class="px-2 py-1 rounded-md bg-slate-900 text-white text-xs font-medium hover:bg-slate-800 disabled:opacity-60"
                                        >Salvar</button>
                                        <button
                                            @click="cancelEdit"
                                            class="px-2 py-1 rounded-md text-xs text-slate-600 hover:bg-slate-100"
                                        >Cancelar</button>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="menu.items.length === 0 && adding !== menu.id">
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">
                                Menu sem items. Clique em <strong>"Adicionar item"</strong> acima.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Form de adicionar item -->
            <div v-if="adding === menu.id" class="p-4 border-t border-slate-200 bg-slate-50">
                <form @submit.prevent="saveAdd(menu)" class="grid gap-3 sm:grid-cols-4">
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Rótulo *</label>
                        <input
                            v-model="addForm.label"
                            type="text"
                            class="w-full px-2 py-1.5 rounded ring-1 ring-slate-200 text-sm focus:ring-2 focus:ring-slate-900 focus:outline-none"
                            :class="addForm.errors.label ? 'ring-red-400' : ''"
                            placeholder="Ex.: Home"
                        >
                        <p v-if="addForm.errors.label" class="mt-1 text-xs text-red-600">{{ addForm.errors.label }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">URL *</label>
                        <input
                            v-model="addForm.url"
                            type="text"
                            class="w-full px-2 py-1.5 rounded ring-1 ring-slate-200 text-sm font-mono focus:ring-2 focus:ring-slate-900 focus:outline-none"
                            :class="addForm.errors.url ? 'ring-red-400' : ''"
                            placeholder="/ ou https://..."
                        >
                        <p v-if="addForm.errors.url" class="mt-1 text-xs text-red-600">{{ addForm.errors.url }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Target</label>
                        <select
                            v-model="addForm.target"
                            class="w-full px-2 py-1.5 rounded ring-1 ring-slate-200 text-sm focus:ring-2 focus:ring-slate-900 focus:outline-none"
                        >
                            <option value="_self">Mesma aba</option>
                            <option value="_blank">Nova aba</option>
                        </select>
                    </div>
                    <div class="sm:col-span-4 flex items-center gap-2">
                        <button
                            type="submit"
                            :disabled="addForm.processing"
                            class="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 disabled:opacity-60"
                        >{{ addForm.processing ? 'Adicionando…' : 'Adicionar' }}</button>
                        <button
                            type="button"
                            @click="cancelAdd"
                            class="px-3 py-1.5 rounded-lg text-sm text-slate-700 hover:bg-slate-100"
                        >Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Nota sobre reorder -->
        <p class="text-xs text-slate-500 text-center mt-4">
            A ordem dos items segue o campo <code>order_column</code> do banco.
            Reordenação via arrastar-e-soltar será adicionada em iteração futura.
        </p>
    </MasterLayout>
</template>
