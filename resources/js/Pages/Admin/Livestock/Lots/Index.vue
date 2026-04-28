<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import { useConfirm } from '@/composables/useConfirm.js';

const { confirm } = useConfirm();

const props = defineProps({
    lots: Object,
    filters: Object,
    finalidades: Object,
});

const filtros = reactive({ ...props.filters });

function filtrar() {
    router.get(route('admin.rebanho.lotes.index'), filtros, { preserveState: true, replace: true });
}

async function excluir(l) {
    const ok = await confirm({
        title: 'Excluir lote',
        message: `Excluir o lote "${l.nome}"? Animais associados não serão excluídos, mas perdem o vínculo com este grupo.`,
        confirmText: 'Excluir lote',
        variant: 'danger',
    });
    if (! ok) return;
    router.delete(route('admin.rebanho.lotes.destroy', l.id), { preserveScroll: true });
}

// Toggle ativo/inativo direto da lista — backend já tinha rota,
// faltava conectar o badge. Agora é botão clicável.
function toggleAtivo(lote) {
    router.post(route('admin.rebanho.lotes.toggle', lote.id), {}, {
        preserveScroll: true, preserveState: true, only: ['lots'],
    });
}
</script>

<template>
    <Head title="Lotes (grupos de animais)" />
    <AdminLayout>
        <template #page-title>Rebanho · Lotes</template>

        <PageHeader
            title="Lotes (grupos lógicos de animais)"
            subtitle="Um lote é um grupo com critério comum — não é um lugar físico."
        >
            <template #actions>
                <Link :href="route('admin.rebanho.locais.index')" class="btn-outline">Ver locais</Link>
                <Link :href="route('admin.rebanho.lotes.create')" class="btn-primary">+ Novo lote</Link>
            </template>
        </PageHeader>

        <div class="card mb-6 bg-slate-50 border border-slate-200">
            <div class="card-body text-sm text-slate-700 space-y-1">
                <div>🐄 <strong>Lote</strong> = grupo lógico: "vacas leiteiras", "engorda 2026/Q1", "descarte"…</div>
                <div>📍 <strong>Local</strong> = lugar físico (pasto, piquete): abra em <Link :href="route('admin.rebanho.locais.index')" class="inline-flex items-center align-middle min-h-[32px] px-2 py-1 underline rounded">Locais</Link>.</div>
                <div class="text-slate-500 text-xs mt-1">
                    Um mesmo lote pode estar em locais diferentes. Um local pode receber animais de vários lotes.
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <input v-model="filtros.q" @keyup.enter="filtrar"
                       placeholder="Buscar por nome ou código…"
                       class="form-input">
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'nome', label: 'Nome' },
                { key: 'finalidade', label: 'Finalidade' },
                { key: 'codigo', label: 'Código' },
                { key: 'animais_count', label: 'Animais', align: 'right' },
                { key: 'status', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="lots.data"
            empty-text="Nenhum lote cadastrado ainda. Cadastre o primeiro para agrupar seus animais por finalidade."
        >
            <template #cell-finalidade="{ row }">
                <span v-if="row.finalidade" class="badge-blue">{{ finalidades[row.finalidade] ?? row.finalidade }}</span>
                <span v-else class="text-slate-400">—</span>
            </template>
            <template #cell-animais_count="{ row }">
                <!-- Lote agregado (Ave/Peixe): mostra efetivo ATUAL (quantidade_atual)
                     com indicação de baixas se decrementou. Lote convencional:
                     mostra animais_count (Animals individuais vinculados). -->
                <template v-if="row.species && row.species.gestao === 'lote'">
                    <span class="font-mono font-semibold">{{ row.quantidade_atual ?? 0 }}</span>
                    <span v-if="row.quantidade_inicial && row.quantidade_atual !== null && row.quantidade_atual !== row.quantidade_inicial"
                          class="ml-1 text-xs text-amber-700"
                          :title="`Inicial: ${row.quantidade_inicial} · Baixas: ${row.quantidade_inicial - row.quantidade_atual}`">
                        / {{ row.quantidade_inicial }}
                    </span>
                </template>
                <span v-else class="font-mono">{{ row.animais_count ?? 0 }}</span>
            </template>
            <template #cell-status="{ row }">
                <button @click="toggleAtivo(row)"
                        :class="row.is_active ? 'badge-toggle-green' : 'badge-toggle-slate'"
                        :title="row.is_active ? 'Clique para desativar' : 'Clique para ativar'">
                    {{ row.is_active ? 'ativo' : 'inativo' }}
                </button>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-1 justify-end">
                    <Link :href="route('admin.rebanho.lotes.edit', row.id)" class="inline-flex">
                        <ActionIcon type="edit" title="Editar" />
                    </Link>
                    <ActionIcon type="delete" title="Excluir" @click="excluir(row)" />
                </div>
            </template>
        </DataTable>

        <!-- Paginação (faltava — backend pagina 30/pg) -->
        <div v-if="lots.links && lots.links.length > 3" class="mt-4 flex gap-2 flex-wrap justify-end">
            <Link v-for="link in lots.links" :key="link.label"
                  :href="link.url ?? '#'"
                  v-html="link.label"
                  :class="['btn-outline btn-sm', link.active ? '!bg-macaybas-primary !text-white !border-transparent' : '', !link.url ? 'opacity-40 pointer-events-none' : '']" />
        </div>
    </AdminLayout>
</template>
