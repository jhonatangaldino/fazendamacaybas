<script setup>
/**
 * Master · Usuários de um cliente (tenant).
 *
 * Valor operacional: quando o cliente liga dizendo "esqueci minha senha"
 * ou "o funcionário saiu, bloqueia o acesso dele", o master resolve AQUI
 * sem precisar impersonar. Reset gera senha temporária exibida 1 vez.
 */
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MasterLayout from '@/Layouts/MasterLayout.vue';
import { useInlineCreate } from '@/composables/useInlineCreate.js';
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';

const props = defineProps({
    tenant: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    rolesDisponiveis: { type: Array, default: () => [] },
});

const usersLocal = ref([...props.users]);

// Filtro client-side de busca (sem ida ao backend porque a lista do tenant
// é pequena por natureza — dezenas de usuários, não centenas).
const searchTerm = ref('');
const usersFiltrados = computed(() => {
    const v = (searchTerm.value || '').trim().toLowerCase();
    if (v.length < 3) return usersLocal.value;
    return usersLocal.value.filter(u =>
        (u.name || '').toLowerCase().includes(v)
        || (u.email || '').toLowerCase().includes(v)
        || (u.roles || []).some(r => (r || '').toLowerCase().includes(v))
    );
});

// Resultado do último usuário criado — senha temporária aparece UMA vez
const novoCriado = ref(null);

// Criação inline de usuário do cliente (sem impersonar)
const novoUser = useInlineCreate({
    endpoint: route('master.tenants.users.inline', props.tenant.id),
    initialForm: { name: '', email: '', role: 'funcionario' },
    onCreated: (u) => {
        usersLocal.value = [u, ...usersLocal.value];
        novoCriado.value = {
            email: u.email,
            senha_temporaria: u.senha_temporaria,
            admin_url: u.admin_url,
        };
    },
});
async function copiar(t) { try { await navigator.clipboard.writeText(t); } catch {} }

// Senha temporária recém-gerada — flash session, some ao navegar
const page = usePage();
const resetResult = computed(() => page.props.flash?.reset_password_result ?? null);
const resetVisible = ref(true);

const confirmReset = ref(null);
const confirmToggle = ref(null);
useBodyScrollLock(computed(() => !!(confirmReset.value || confirmToggle.value)));

function doReset() {
    router.post(
        route('master.tenants.users.reset-password', [props.tenant.id, confirmReset.value.id]),
        {},
        { preserveScroll: true, onSuccess: () => { confirmReset.value = null; resetVisible.value = true; } },
    );
}

function doToggle() {
    router.post(
        route('master.tenants.users.toggle', [props.tenant.id, confirmToggle.value.id]),
        {},
        { preserveScroll: true, onSuccess: () => (confirmToggle.value = null) },
    );
}

// copiar() já definido acima — reutilizamos a mesma função.
</script>

<template>
    <Head :title="`Usuários de ${tenant.nome}`" />
    <MasterLayout>
        <template #page-title>Usuários do cliente</template>

        <!-- Breadcrumb simples -->
        <div class="mb-4 text-sm text-slate-500 flex flex-wrap items-center">
            <Link :href="route('master.tenants.index')" class="inline-flex items-center min-h-[36px] px-2 py-1 hover:underline rounded">Clientes</Link>
            <span class="mx-1">›</span>
            <span class="text-slate-700 font-medium">{{ tenant.nome }}</span>
            <span class="mx-2">›</span>
            <span>Usuários</span>
        </div>

        <!-- Resultado pós-reset: senha temporária (some ao navegar) -->
        <div
            v-if="resetResult && resetVisible"
            class="mb-5 rounded-xl border-2 border-amber-300 bg-amber-50 p-5"
        >
            <div class="flex items-start gap-3">
                <span class="text-3xl flex-shrink-0" aria-hidden="true">🔐</span>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-amber-900">Nova senha temporária gerada</div>
                    <div class="text-sm text-amber-800 mt-0.5">
                        📧 Enviada automaticamente por e-mail para {{ resetResult.user_email }}.
                        Fica visível na tabela abaixo até o usuário trocar. Validade: 2 horas.
                    </div>
                    <dl class="mt-3 grid gap-1.5 text-sm">
                        <div class="flex flex-wrap gap-2">
                            <dt class="text-slate-500 w-24">Painel:</dt>
                            <dd class="font-mono text-slate-900 break-all">{{ resetResult.admin_url }}</dd>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <dt class="text-slate-500 w-24">E-mail:</dt>
                            <dd class="font-mono text-slate-900 break-all">{{ resetResult.user_email }}</dd>
                        </div>
                        <div class="flex flex-wrap gap-2 items-center">
                            <dt class="text-slate-500 w-24">Senha:</dt>
                            <dd class="font-mono text-slate-900 font-semibold bg-white px-2 py-0.5 rounded ring-1 ring-amber-300">
                                {{ resetResult.senha_temporaria }}
                            </dd>
                            <button
                                type="button"
                                @click="copiar(resetResult.senha_temporaria)"
                                class="text-xs text-amber-700 hover:underline"
                            >Copiar</button>
                        </div>
                    </dl>
                    <p class="mt-2 text-xs text-slate-600">
                        No próximo login o sistema força o usuário a definir uma senha definitiva.
                        Se a senha expirar antes (2h), o sistema gera uma nova e reenvia por e-mail.
                    </p>
                </div>
                <button
                    type="button"
                    @click="resetVisible = false"
                    class="text-slate-500 hover:text-slate-700 text-xl leading-none"
                    aria-label="Fechar"
                >×</button>
            </div>
        </div>

        <!-- Banner pós criação de usuário novo — senha temp aparece UMA vez -->
        <div v-if="novoCriado" class="mb-5 rounded-xl border-2 border-emerald-300 bg-emerald-50 p-5">
            <div class="flex items-start gap-3">
                <span class="text-3xl flex-shrink-0" aria-hidden="true">✓</span>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-emerald-900">Usuário criado · senha enviada por e-mail</div>
                    <div class="text-sm text-emerald-800 mt-0.5">
                        📧 E-mail de boas-vindas com a senha temporária foi enviado para {{ novoCriado.email }}.
                        Senha visível também na tabela até ser trocada (validade 2h).
                    </div>
                    <dl class="mt-3 grid gap-1.5 text-sm">
                        <div class="flex flex-wrap gap-2">
                            <dt class="text-slate-500 w-24">Painel:</dt>
                            <dd class="font-mono text-slate-900 break-all">{{ novoCriado.admin_url }}</dd>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <dt class="text-slate-500 w-24">E-mail:</dt>
                            <dd class="font-mono text-slate-900 break-all">{{ novoCriado.email }}</dd>
                        </div>
                        <div class="flex flex-wrap gap-2 items-center">
                            <dt class="text-slate-500 w-24">Senha:</dt>
                            <dd class="font-mono text-slate-900 font-semibold bg-white px-2 py-0.5 rounded ring-1 ring-emerald-300">
                                {{ novoCriado.senha_temporaria }}
                            </dd>
                            <button type="button" @click="copiar(novoCriado.senha_temporaria)" class="text-xs text-emerald-700 hover:underline">Copiar</button>
                        </div>
                    </dl>
                </div>
                <button type="button" @click="novoCriado = null" class="text-slate-500 hover:text-slate-700 text-xl leading-none">×</button>
            </div>
        </div>

        <!-- Toolbar: busca + Novo usuário -->
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="relative w-full sm:max-w-md">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input
                    v-model="searchTerm"
                    type="search"
                    placeholder="Buscar por nome, e-mail ou papel (3+ caracteres)"
                    class="w-full pl-10 pr-10 py-2 rounded-lg ring-1 ring-slate-200 bg-white text-sm focus:ring-2 focus:ring-macaybas-primary-500 focus:outline-none"
                >
                <button
                    v-if="searchTerm"
                    type="button"
                    @click="searchTerm = ''"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-sm"
                    title="Limpar busca"
                >✕</button>
            </div>
            <button type="button" @click="novoUser.abrir()" class="btn-primary whitespace-nowrap">
                + Novo usuário
            </button>
        </div>
        <p v-if="searchTerm && searchTerm.length > 0 && searchTerm.length < 3" class="-mt-2 mb-3 text-xs text-slate-500">
            Digite mais {{ 3 - searchTerm.length }} {{ (3 - searchTerm.length) === 1 ? 'caractere' : 'caracteres' }} para filtrar
        </p>
        <p v-else-if="searchTerm" class="-mt-2 mb-3 text-xs text-slate-500">
            Mostrando {{ usersFiltrados.length }} de {{ usersLocal.length }} usuário{{ usersLocal.length === 1 ? '' : 's' }}
        </p>

        <!-- Lista -->
        <div class="card">
            <div class="card-body">
                <div v-if="!usersLocal.length" class="text-center text-slate-500 py-10">
                    Este cliente ainda não tem usuários cadastrados. Clique em "+ Novo usuário" para criar o primeiro.
                </div>

                <!-- Tabela DESKTOP (xl+) — scroll horizontal se preciso -->
                <div v-else class="hidden xl:block overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Papel</th>
                            <th>Acesso</th>
                            <th>Senha temporária</th>
                            <th>Último login</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="!usersFiltrados.length && searchTerm">
                            <td colspan="7" class="text-center py-8 text-slate-500 text-sm">
                                Nenhum usuário encontrado para "{{ searchTerm }}"
                            </td>
                        </tr>
                        <tr v-for="u in usersFiltrados" :key="u.id" :class="!u.is_active ? 'opacity-50' : ''">
                            <td class="font-medium">{{ u.name }}</td>
                            <td class="font-mono text-xs">{{ u.email }}</td>
                            <td>
                                <span v-for="r in u.roles" :key="r.name"
                                      class="inline-block text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700 mr-1"
                                      :title="r.description || r.name">
                                    {{ r.short_name }}
                                </span>
                                <span v-if="!u.roles.length" class="text-xs text-slate-400">sem papel</span>
                            </td>
                            <td>
                                <span v-if="u.is_active" class="badge-green">Ativo</span>
                                <span v-else class="badge-slate">Inativo</span>
                                <span v-if="u.must_change_password" class="ml-1 badge-yellow" title="Próximo login forçará troca de senha">
                                    Senha temp
                                </span>
                            </td>
                            <td>
                                <div v-if="u.temp_password && !u.temp_password_expired" class="inline-flex items-center gap-2">
                                    <code class="px-2 py-1 rounded bg-amber-50 ring-1 ring-amber-200 text-amber-900 text-sm font-mono font-bold tracking-wider">
                                        {{ u.temp_password }}
                                    </code>
                                </div>
                                <span v-else-if="u.temp_password_expired" class="text-xs text-rose-700" :title="`Expirou em ${u.temp_password_expires_at}`">
                                    Expirada
                                </span>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                            <td class="text-xs text-slate-500 whitespace-nowrap">{{ u.last_login_at ?? '—' }}</td>
                            <td class="text-right whitespace-nowrap">
                                <button
                                    @click="confirmReset = u"
                                    class="inline-flex items-center min-h-9 px-3 py-2 mr-2 text-xs text-amber-700 hover:bg-amber-50 rounded-md"
                                    title="Gerar nova senha temporária"
                                >🔑 Resetar</button>
                                <button
                                    @click="confirmToggle = u"
                                    class="inline-flex items-center min-h-9 px-3 py-2 text-xs rounded-md"
                                    :class="u.is_active ? 'text-red-700 hover:bg-red-50' : 'text-emerald-700 hover:bg-emerald-50'"
                                    :title="u.is_active ? 'Bloquear acesso' : 'Reativar acesso'"
                                >{{ u.is_active ? '⛔ Desativar' : '✓ Reativar' }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>

                <!-- Cards MOBILE/iPad (< xl) — sem tabela cortando -->
                <div v-if="usersLocal.length" class="xl:hidden space-y-3">
                    <div v-if="!usersFiltrados.length && searchTerm" class="text-center py-8 text-slate-500 text-sm">
                        Nenhum usuário encontrado para "{{ searchTerm }}"
                    </div>
                    <div v-for="u in usersFiltrados" :key="u.id"
                         class="rounded-xl ring-1 ring-slate-200 p-3.5"
                         :class="!u.is_active ? 'opacity-60 bg-slate-50' : 'bg-white'">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-slate-900 break-words">{{ u.name }}</div>
                                <div class="text-xs font-mono text-slate-500 break-all">{{ u.email }}</div>
                            </div>
                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                <span v-if="u.is_active" class="badge-green text-[10px]">Ativo</span>
                                <span v-else class="badge-slate text-[10px]">Inativo</span>
                                <span v-if="u.must_change_password" class="badge-yellow text-[10px]" title="Forçará troca no próximo login">Senha temp</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-1.5 mb-2">
                            <span v-for="r in u.roles" :key="r.name"
                                  class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700"
                                  :title="r.description || r.name">
                                {{ r.short_name }}
                            </span>
                            <span v-if="!u.roles.length" class="text-xs text-slate-400">sem papel</span>
                        </div>

                        <div v-if="u.temp_password && !u.temp_password_expired" class="mb-2">
                            <div class="text-[10px] text-slate-500 uppercase tracking-wider mb-0.5">Senha temporária</div>
                            <code class="inline-block px-2 py-1 rounded bg-amber-50 ring-1 ring-amber-200 text-amber-900 text-sm font-mono font-bold tracking-wider">{{ u.temp_password }}</code>
                        </div>
                        <div v-else-if="u.temp_password_expired" class="mb-2 text-xs text-rose-700">
                            Senha temp expirada
                        </div>

                        <div class="text-xs text-slate-500 mb-3">
                            Último login: {{ u.last_login_at ?? '—' }}
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button @click="confirmReset = u"
                                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium bg-amber-100 text-amber-900 ring-1 ring-amber-200 hover:bg-amber-200">
                                🔑 Reenviar credenciais
                            </button>
                            <button @click="confirmToggle = u"
                                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium ring-1"
                                    :class="u.is_active
                                        ? 'bg-red-50 text-red-700 ring-red-200 hover:bg-red-100'
                                        : 'bg-emerald-50 text-emerald-700 ring-emerald-200 hover:bg-emerald-100'">
                                {{ u.is_active ? '⛔ Desativar' : '✓ Reativar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmações -->
        <Teleport to="body">
            <div v-if="confirmReset" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="confirmReset = null"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <h3 class="text-lg font-semibold mb-2">Reenviar credenciais de {{ confirmReset.name }}?</h3>
                    <p class="text-sm text-slate-600 mb-5">
                        Uma nova senha temporária de 8 caracteres será gerada e <strong>enviada
                        automaticamente por e-mail</strong> para o usuário. A senha anterior deixa
                        de funcionar imediatamente. A nova fica visível na tabela ao lado até que
                        o usuário troque.
                    </p>
                    <div class="flex justify-end gap-2">
                        <button @click="confirmReset = null" class="btn-outline">Cancelar</button>
                        <button @click="doReset" class="btn-primary bg-amber-600 hover:bg-amber-700">Gerar nova senha</button>
                    </div>
                </div>
            </div>

            <div v-if="confirmToggle" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="confirmToggle = null"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <h3 class="text-lg font-semibold mb-2">
                        {{ confirmToggle.is_active ? 'Desativar' : 'Reativar' }} {{ confirmToggle.name }}?
                    </h3>
                    <p class="text-sm text-slate-600 mb-5">
                        <template v-if="confirmToggle.is_active">
                            Ele <strong>perde o acesso</strong> imediatamente. A conta continua no sistema e pode
                            ser reativada a qualquer momento.
                        </template>
                        <template v-else>
                            Ele volta a poder logar no painel com o mesmo e-mail e senha.
                        </template>
                    </p>
                    <div class="flex justify-end gap-2">
                        <button @click="confirmToggle = null" class="btn-outline">Cancelar</button>
                        <button
                            @click="doToggle"
                            class="btn-primary"
                            :class="confirmToggle.is_active ? 'bg-red-600 hover:bg-red-700' : ''"
                        >{{ confirmToggle.is_active ? 'Desativar' : 'Reativar' }}</button>
                    </div>
                </div>
            </div>

            <!-- Modal: criar usuário novo inline -->
            <div v-if="novoUser.modalAberto.value" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="novoUser.fechar"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto overscroll-contain">
                    <h3 class="text-lg font-semibold mb-1">Novo usuário de {{ tenant.nome }}</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        Uma senha temporária de 8 caracteres é gerada e <strong>enviada por e-mail</strong>
                        ao usuário. Ela fica visível na tabela ao lado até ser trocada (validade 2h).
                        No primeiro acesso o sistema força a troca.
                    </p>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Nome *</label>
                            <input v-model="novoUser.form.value.name" class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 text-sm" placeholder="Ex.: João Silva">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">E-mail *</label>
                            <input v-model="novoUser.form.value.email" type="email" class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 text-sm" placeholder="joao@exemplo.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Papel *</label>
                            <select v-model="novoUser.form.value.role" class="w-full px-3 py-2 rounded-lg ring-1 ring-slate-200 text-sm">
                                <option v-for="r in rolesDisponiveis" :key="r.name || r" :value="r.name || r">
                                    {{ r.short_name || r }}{{ r.description ? ' — ' + r.description : '' }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <p v-if="novoUser.erro.value" class="mt-3 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1.5">
                        {{ novoUser.erro.value }}
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="novoUser.fechar" class="px-4 py-2 text-sm rounded-lg ring-1 ring-slate-200 hover:bg-slate-50">Cancelar</button>
                        <button @click="novoUser.salvar"
                                :disabled="novoUser.salvando.value || !novoUser.form.value.name?.trim() || !novoUser.form.value.email?.trim()"
                                class="px-4 py-2 text-sm rounded-lg bg-emerald-600 text-white font-medium hover:bg-emerald-700 disabled:opacity-50">
                            {{ novoUser.salvando.value ? 'Criando…' : 'Criar usuário' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </MasterLayout>
</template>
