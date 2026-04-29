<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import { dataHoraBR } from '@/utils/format.js';
import { useConfirm } from '@/composables/useConfirm.js';

const { confirm } = useConfirm();
const page = usePage();

const props = defineProps({
    users: Object,
    filters: Object,
    roles: Array,
    plan_info: { type: Object, default: () => ({ max_users: 0, usuarios_ativos: 0, plano_nome: null, limite_atingido: false }) },
});

// Controla quais ações cada usuário logado pode fazer:
//   - Apenas dono_fazenda OU admin_master pode editar/excluir outros usuários
//   - Auto-exclusão NUNCA é permitida (mesmo pra dono/master)
//   - Usuário comum só edita o próprio perfil (não aparece na lista)
const usuarioLogado = computed(() => page.props.auth?.user);
const podeGerenciarOutros = computed(() => {
    const roles = usuarioLogado.value?.roles ?? [];
    return roles.includes('admin_master') || roles.includes('dono_fazenda');
});
function podeEditar(row) {
    if (! usuarioLogado.value) return false;
    if (row.id === usuarioLogado.value.id) return true; // edita próprio perfil
    return podeGerenciarOutros.value;
}
function podeExcluir(row) {
    if (! usuarioLogado.value) return false;
    if (row.id === usuarioLogado.value.id) return false; // nunca auto-exclui
    return podeGerenciarOutros.value;
}

const filtros = reactive({ ...props.filters });
const confirmDelete = ref(null);

function filtrar() {
    router.get(route('admin.users.index'), filtros, { preserveState: true, replace: true });
}

function askDelete(user) { confirmDelete.value = user; }
function doDelete() {
    router.delete(route('admin.users.destroy', confirmDelete.value.id), {
        onSuccess: () => (confirmDelete.value = null),
    });
}
async function resetPassword(id) {
    const ok = await confirm({
        title: 'Reenviar credenciais',
        message: 'Uma nova senha temporária de 8 caracteres será gerada e enviada por email para o usuário. A senha anterior deixará de funcionar. Continuar?',
        confirmText: 'Gerar nova senha',
        variant: 'primary',
        icon: 'question',
    });
    if (ok) router.post(route('admin.users.reset-password', id));
}

async function copiarSenha(senha) {
    try {
        await navigator.clipboard.writeText(senha);
        // Feedback visual simples sem dependência externa
        const ev = new CustomEvent('toast', { detail: { tipo: 'success', mensagem: 'Senha copiada' } });
        window.dispatchEvent(ev);
    } catch (e) {
        // Fallback silencioso — alguns browsers bloqueiam clipboard sem HTTPS
    }
}
</script>

<template>
    <Head title="Usuários" />
    <AdminLayout>
        <template #page-title>Usuários</template>

        <PageHeader title="Usuários" subtitle="Gerencie o acesso e os perfis dos usuários do sistema">
            <template #actions>
                <Link v-if="!plan_info.limite_atingido"
                      :href="route('admin.users.create')"
                      class="btn-primary">Novo usuário</Link>
                <button v-else
                        type="button"
                        disabled
                        class="btn-primary opacity-60 cursor-not-allowed"
                        :title="`Limite do plano ${plan_info.plano_nome} atingido (${plan_info.usuarios_ativos}/${plan_info.max_users})`">
                    Limite atingido
                </button>
            </template>
        </PageHeader>

        <!-- Indicador de uso vs limite do plano -->
        <div v-if="plan_info.max_users > 0"
             class="mb-4 rounded-xl px-4 py-3 flex items-center justify-between gap-3"
             :class="plan_info.limite_atingido
                ? 'bg-red-50 ring-1 ring-red-200'
                : (plan_info.usuarios_ativos / plan_info.max_users >= 0.8
                    ? 'bg-amber-50 ring-1 ring-amber-200'
                    : 'bg-slate-50 ring-1 ring-slate-200')">
            <div class="flex items-center gap-2 text-sm">
                <span class="text-xl" aria-hidden="true">{{ plan_info.limite_atingido ? '🚫' : '👥' }}</span>
                <div>
                    <div class="font-semibold text-slate-900">
                        {{ plan_info.usuarios_ativos }} de {{ plan_info.max_users }} usuários ativos
                    </div>
                    <div class="text-xs text-slate-600">
                        Plano <strong>{{ plan_info.plano_nome }}</strong>{{ plan_info.limite_atingido ? ' — limite atingido. Faça upgrade ou desative usuários.' : '' }}
                    </div>
                </div>
            </div>
            <!-- Barra de progresso -->
            <div class="hidden sm:block w-32 h-2 bg-slate-200 rounded-full overflow-hidden">
                <div class="h-full transition-all"
                     :class="plan_info.limite_atingido ? 'bg-red-500' : (plan_info.usuarios_ativos / plan_info.max_users >= 0.8 ? 'bg-amber-500' : 'bg-emerald-500')"
                     :style="`width: ${Math.min(100, (plan_info.usuarios_ativos / plan_info.max_users) * 100)}%`"></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-3">
                <input v-model="filtros.search" @keyup.enter="filtrar" placeholder="Buscar por nome ou e-mail" class="form-input">
                <select v-model="filtros.role" @change="filtrar" class="form-select">
                    <option value="">Todos os perfis</option>
                    <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.description || r.name }}</option>
                </select>
                <select v-model="filtros.status" @change="filtrar" class="form-select">
                    <option value="">Todos</option>
                    <option value="ativos">Ativos</option>
                    <option value="inativos">Inativos</option>
                </select>
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'name', label: 'Nome' },
                { key: 'email', label: 'E-mail' },
                { key: 'cargo', label: 'Cargo' },
                { key: 'roles', label: 'Perfis' },
                { key: 'temp_password', label: 'Senha temporária' },
                { key: 'last_login_at', label: 'Último acesso' },
                { key: 'is_active', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="users.data"
        >
            <template #cell-name="{ row }">
                <div class="flex items-center gap-3">
                    <img v-if="row.avatar_url"
                         :src="row.avatar_url"
                         class="h-9 w-9 rounded-full object-cover ring-1 ring-slate-200 flex-shrink-0">
                    <div v-else
                         class="h-9 w-9 rounded-full flex items-center justify-center bg-macaybas-primary-100 text-macaybas-primary-800 text-sm font-semibold flex-shrink-0">
                        {{ (row.name || '?').charAt(0).toUpperCase() }}
                    </div>
                    <span class="truncate">{{ row.name }}</span>
                </div>
            </template>
            <template #cell-roles="{ row }">
                <div class="flex flex-wrap gap-1.5">
                    <span v-for="r in row.roles" :key="r.name"
                          class="badge-blue"
                          :data-tooltip="r.description">
                        {{ r.short_name || r.name }}
                    </span>
                </div>
            </template>
            <template #cell-temp_password="{ row }">
                <!-- Senha temporária visível ATÉ o user trocá-la (must_change_password=true).
                     Quando expira, mostra "Expirada — clique em Reenviar". -->
                <div v-if="row.temp_password && !row.temp_password_expired" class="inline-flex items-center gap-2">
                    <code class="px-2 py-1 rounded bg-amber-50 ring-1 ring-amber-200 text-amber-900 text-sm font-mono font-bold tracking-wider">
                        {{ row.temp_password }}
                    </code>
                    <button type="button"
                            @click="copiarSenha(row.temp_password)"
                            class="inline-flex items-center min-h-9 px-2 text-xs text-macaybas-primary hover:underline lg:min-h-0"
                            title="Copiar senha">
                        Copiar
                    </button>
                </div>
                <span v-else-if="row.temp_password_expired" class="text-xs text-rose-700">
                    Expirada · use Reenviar →
                </span>
                <span v-else class="text-xs text-slate-400">—</span>
            </template>
            <template #cell-last_login_at="{ row }">
                {{ dataHoraBR(row.last_login_at) }}
            </template>
            <template #cell-is_active="{ row }">
                <span :class="row.is_active ? 'badge-green' : 'badge-slate'">
                    {{ row.is_active ? 'Ativo' : 'Inativo' }}
                </span>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex items-center gap-1 justify-end">
                    <ActionIcon
                        v-if="podeGerenciarOutros && row.id !== usuarioLogado?.id"
                        type="reset-password"
                        title="Resetar senha"
                        @click="resetPassword(row.id)"
                    />
                    <Link
                        v-if="podeEditar(row)"
                        :href="route('admin.users.edit', row.id)"
                        class="inline-flex"
                    >
                        <ActionIcon type="edit" :title="row.id === usuarioLogado?.id ? 'Editar meu perfil' : 'Editar usuário'" />
                    </Link>
                    <ActionIcon
                        v-if="podeExcluir(row)"
                        type="delete"
                        title="Excluir usuário"
                        @click="askDelete(row)"
                    />
                </div>
            </template>
        </DataTable>

        <div v-if="users.links" class="mt-4 flex gap-2 justify-end">
            <Link v-for="link in users.links" :key="link.label"
                  :href="link.url ?? '#'"
                  v-html="link.label"
                  :class="['btn-outline btn-sm', link.active ? '!bg-macaybas-primary !text-white !border-transparent' : '', !link.url ? 'opacity-40 pointer-events-none' : '']" />
        </div>

        <ConfirmModal
            :show="!!confirmDelete"
            title="Excluir usuário"
            :message="`Tem certeza que deseja excluir ${confirmDelete?.name}? Esta ação não pode ser desfeita.`"
            confirm-text="Excluir"
            @cancel="confirmDelete = null"
            @confirm="doDelete"
        />
    </AdminLayout>
</template>
