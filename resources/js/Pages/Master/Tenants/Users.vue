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

const props = defineProps({
    tenant: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    rolesDisponiveis: { type: Array, default: () => [] },
});

const usersLocal = ref([...props.users]);

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
        <div class="mb-4 text-sm text-slate-500">
            <Link :href="route('master.tenants.index')" class="hover:underline">Clientes</Link>
            <span class="mx-2">›</span>
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
                    <div class="font-semibold text-amber-900">Senha temporária gerada — guarde agora</div>
                    <div class="text-sm text-amber-800 mt-0.5">
                        Envie estas informações para {{ resetResult.user_email }}. Ela só aparece UMA vez.
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
                        No próximo login, o sistema pedirá ao usuário para trocar esta senha por uma definitiva.
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
                    <div class="font-semibold text-emerald-900">Usuário criado — guarde a senha agora</div>
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

        <!-- Toolbar com botão "Novo usuário" -->
        <div class="mb-4 flex justify-end">
            <button type="button" @click="novoUser.abrir()" class="btn-primary">
                + Novo usuário
            </button>
        </div>

        <!-- Lista -->
        <div class="card">
            <div class="card-body">
                <div v-if="!usersLocal.length" class="text-center text-slate-500 py-10">
                    Este cliente ainda não tem usuários cadastrados. Clique em "+ Novo usuário" para criar o primeiro.
                </div>
                <table v-else class="table-base">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Papel</th>
                            <th>Acesso</th>
                            <th>Último login</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="u in usersLocal" :key="u.id" :class="!u.is_active ? 'opacity-50' : ''">
                            <td class="font-medium">{{ u.name }}</td>
                            <td class="font-mono text-xs">{{ u.email }}</td>
                            <td>
                                <span v-for="r in u.roles" :key="r"
                                      class="inline-block text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700 mr-1">
                                    {{ r }}
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
                            <td class="text-xs text-slate-500">{{ u.last_login_at ?? '—' }}</td>
                            <td class="text-right">
                                <button
                                    @click="confirmReset = u"
                                    class="text-xs text-amber-700 hover:underline mr-3"
                                    title="Gerar nova senha temporária"
                                >🔑 Resetar senha</button>
                                <button
                                    @click="confirmToggle = u"
                                    class="text-xs hover:underline"
                                    :class="u.is_active ? 'text-red-700' : 'text-emerald-700'"
                                    :title="u.is_active ? 'Bloquear acesso' : 'Reativar acesso'"
                                >{{ u.is_active ? '⛔ Desativar' : '✓ Reativar' }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Confirmações -->
        <Teleport to="body">
            <div v-if="confirmReset" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="confirmReset = null"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold mb-2">Resetar senha de {{ confirmReset.name }}?</h3>
                    <p class="text-sm text-slate-600 mb-5">
                        Uma nova senha temporária será gerada. Ela será exibida <strong>apenas uma vez</strong>
                        aqui para você copiar e enviar ao usuário. No próximo login ele será obrigado a trocar.
                    </p>
                    <div class="flex justify-end gap-2">
                        <button @click="confirmReset = null" class="btn-outline">Cancelar</button>
                        <button @click="doReset" class="btn-primary bg-amber-600 hover:bg-amber-700">Gerar nova senha</button>
                    </div>
                </div>
            </div>

            <div v-if="confirmToggle" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="confirmToggle = null"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
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
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold mb-1">Novo usuário de {{ tenant.nome }}</h3>
                    <p class="text-sm text-slate-500 mb-4">
                        Uma senha temporária será gerada. Ela aparece aqui <strong>uma vez</strong> para você
                        copiar e enviar ao usuário. No primeiro login ele troca a senha.
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
                                <option v-for="r in rolesDisponiveis" :key="r" :value="r">{{ r }}</option>
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
