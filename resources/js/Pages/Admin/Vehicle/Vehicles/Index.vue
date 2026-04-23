<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import ActionIcon from '@/Components/ActionIcon.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputMoney from '@/Components/InputMoney.vue';
import InputDate from '@/Components/InputDate.vue';
import InputMasked from '@/Components/InputMasked.vue';
import { brl, dataBR } from '@/utils/format.js';
import { useAutoReload } from '@/composables/useAutoReload.js';

const props = defineProps({ vehicles: Object, filters: Object, farms: Array });
useAutoReload(['vehicles'], 20000);

const filtros = reactive({ ...props.filters });
const editing = ref(null);
const confirmDelete = ref(null);

const form = useForm({
    farm_id: null, tipo: 'trator', nome: '', marca: '', modelo: '',
    ano_fabricacao: null, ano_modelo: null, placa: '', renavam: '', chassi: '',
    cor: '', combustivel: '', medidor: 'km', medidor_atual: 0,
    valor_aquisicao: '', data_aquisicao: '', is_active: true, observacoes: '',
});

// ═════════════════════════════════════════════════════════════════════
// F3 · UX anti-erro orientada por domínio — VEÍCULOS/MÁQUINAS
//
// Espelha a matriz D5 (VehicleController::validateDomainCoherence):
//
//   TIPOS_VEICULO_COM_DOCS   = [caminhao, pick_up, motocicleta]
//                              → placa E RENAVAM OBRIGATÓRIOS (DETRAN)
//
//   TIPOS_IMPLEMENTO         = [implemento]
//                              → placa, RENAVAM, chassi, combustível
//                                NÃO se aplicam (sem motor próprio)
//
//   TIPOS_MAQUINA_AGRICOLA   = [trator, colheitadeira]
//                              → medidor OBRIGATÓRIO (km ou h)
//
//   outros                   → fallback permissivo
//
// Backend D5 permanece como 2ª camada.
// ═════════════════════════════════════════════════════════════════════

const TIPOS_VEICULO_COM_DOCS = ['caminhao', 'pick_up', 'motocicleta'];
const TIPOS_IMPLEMENTO = ['implemento'];
const TIPOS_MAQUINA_AGRICOLA = ['trator', 'colheitadeira'];

const tipoLabel = {
    trator: 'Trator', caminhao: 'Caminhão', pick_up: 'Pick-up',
    motocicleta: 'Motocicleta', implemento: 'Implemento',
    colheitadeira: 'Colheitadeira', outros: 'Outros',
};

const isVeiculoRodoviario = computed(() => TIPOS_VEICULO_COM_DOCS.includes(form.tipo));
const isImplemento = computed(() => TIPOS_IMPLEMENTO.includes(form.tipo));
const isMaquinaAgricola = computed(() => TIPOS_MAQUINA_AGRICOLA.includes(form.tipo));

/** Implementos não têm placa, RENAVAM, chassi ou combustível. */
const showDocumentacao = computed(() => !isImplemento.value);
const showCombustivel = computed(() => !isImplemento.value);

/** Somente veículos rodoviários EXIGEM placa + RENAVAM. */
const requiresPlaca = computed(() => isVeiculoRodoviario.value);
const requiresRenavam = computed(() => isVeiculoRodoviario.value);

/** Máquinas agrícolas exigem medidor (o validator base também, defesa em profundidade). */
const requiresMedidor = computed(() => isMaquinaAgricola.value);

const labelPlaca = computed(() =>
    isVeiculoRodoviario.value ? 'Placa (obrigatória)' : 'Placa (opcional)',
);
const labelRenavam = computed(() =>
    isVeiculoRodoviario.value ? 'RENAVAM (obrigatório)' : 'RENAVAM (opcional)',
);

/** Card azul de dica contextual. */
const dicaTipo = computed(() => {
    if (isVeiculoRodoviario.value) {
        return {
            titulo: `${tipoLabel[form.tipo]} — veículo rodoviário`,
            texto: 'Veículos que circulam em via pública exigem placa E RENAVAM por determinação do DETRAN. Chassi e cor ajudam na identificação legal em caso de sinistro.',
            tone: 'amber',
        };
    }
    if (isImplemento.value) {
        return {
            titulo: 'Implemento agrícola — equipamento rebocado',
            texto: 'Arados, grades, pulverizadores e plantadeiras não têm motor próprio: são tracionados pelo trator. Por isso NÃO possuem placa, RENAVAM, chassi ou combustível. Você pode usar o medidor para controlar horas de uso rebocado.',
            tone: 'slate',
        };
    }
    if (isMaquinaAgricola.value) {
        return {
            titulo: `${tipoLabel[form.tipo]} — máquina agrícola autopropelida`,
            texto: 'Tratores e colheitadeiras usam HORÍMETRO (h) para controle de manutenção preventiva — cada marca recomenda troca de óleo, filtros e revisão por faixas de horas. Placa é opcional (só se circular em via pública).',
            tone: 'emerald',
        };
    }
    return {
        titulo: 'Cadastro genérico',
        texto: 'Registre qualquer bem da frota que não se encaixa nos tipos específicos. Placa, RENAVAM e documentação são opcionais.',
        tone: 'slate',
    };
});

const dicaTone = computed(() => {
    const t = dicaTipo.value.tone;
    return {
        amber:   'border-amber-200 bg-amber-50 text-amber-900',
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-900',
        slate:   'border-slate-200 bg-slate-50 text-slate-700',
    }[t] ?? 'border-blue-200 bg-blue-50 text-blue-900';
});

// ── Watcher: ao trocar tipo, LIMPA campos inválidos para o novo tipo ──
watch(
    () => form.tipo,
    (novo, antigo) => {
        if (novo === antigo) return;

        // Implemento não aceita placa/RENAVAM/chassi/combustível → limpa
        if (TIPOS_IMPLEMENTO.includes(novo)) {
            form.placa = '';
            form.renavam = '';
            form.chassi = '';
            form.combustivel = '';
        }
    },
);

/** Botão "Salvar" — bloqueia quando campos obrigatórios por domínio estão vazios. */
const podeSalvar = computed(() => {
    if (form.processing) return false;
    if (requiresPlaca.value && !String(form.placa || '').trim()) return false;
    if (requiresRenavam.value && !String(form.renavam || '').trim()) return false;
    if (requiresMedidor.value && !form.medidor) return false;
    return true;
});

function novo() {
    form.reset();
    form.tipo = 'trator';
    form.medidor = 'km';
    form.is_active = true;
    editing.value = 'new';
}

function editar(v) {
    // Copia os campos do registro. `?? form[k]` mantém defaults quando o
    // backend devolve null (ex.: implemento sem placa), mas o watcher em
    // `form.tipo` logo abaixo limpa os campos inválidos para o tipo — então
    // ao editar um implemento, placa/renavam/chassi serão zerados mesmo
    // que tenham vindo vazios do banco (UX consistente).
    Object.keys(form.data()).forEach((k) => (form[k] = v[k] ?? form[k]));
    editing.value = v.id;
}

function filtrar() {
    router.get(route('admin.maquinas.veiculos.index'), filtros, { preserveState: true, replace: true });
}

function salvar() {
    const opts = { preserveScroll: true, only: ['vehicles'], onSuccess: () => (editing.value = null) };
    if (editing.value === 'new') form.post(route('admin.maquinas.veiculos.store'), opts);
    else form.put(route('admin.maquinas.veiculos.update', editing.value), opts);
}
function toggle(row) {
    router.post(route('admin.maquinas.veiculos.toggle', row.id), {}, { preserveScroll: true, only: ['vehicles'] });
}
function doDelete() {
    router.delete(route('admin.maquinas.veiculos.destroy', confirmDelete.value.id), {
        preserveScroll: true, only: ['vehicles'],
        onSuccess: () => (confirmDelete.value = null),
    });
}
</script>

<template>
    <Head title="Frota de veículos" />
    <AdminLayout>
        <template #page-title>Máquinas</template>
        <PageHeader title="Frota" subtitle="Veículos, implementos e máquinas agrícolas">
            <template #actions>
                <Link :href="route('admin.maquinas.index')" class="btn-outline">Voltar</Link>
                <button @click="novo" class="btn-primary">+ Novo veículo</button>
            </template>
        </PageHeader>

        <div v-if="editing" class="mb-6 space-y-4">
            <!-- Dica contextual por tipo (F3 — UX guiada) -->
            <div class="rounded-lg border px-4 py-3 text-sm" :class="dicaTone">
                <div class="font-medium">{{ dicaTipo.titulo }}</div>
                <div class="mt-1">{{ dicaTipo.texto }}</div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ editing === 'new' ? 'Novo veículo' : 'Editar veículo' }}</h2>
                </div>
                <div class="card-body grid gap-4 sm:grid-cols-3">
                    <!-- TIPO dirige toda a UX abaixo -->
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="form.tipo" class="form-select" required>
                            <option v-for="(l, v) in tipoLabel" :key="v" :value="v">{{ l }}</option>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Define quais campos são obrigatórios/visíveis.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Nome / apelido" />
                        <input v-model="form.nome" required class="form-input" placeholder="Ex.: Trator JD 5078E — #01">
                    </div>
                    <div><InputLabel value="Marca" /><input v-model="form.marca" class="form-input" placeholder="Ex.: John Deere"></div>
                    <div><InputLabel value="Modelo" /><input v-model="form.modelo" class="form-input" placeholder="Ex.: 5078E"></div>
                    <div><InputLabel value="Ano fab." /><input type="number" v-model="form.ano_fabricacao" class="form-input"></div>

                    <!-- Documentação: ESCONDIDA em implementos -->
                    <div v-if="showDocumentacao">
                        <InputLabel :value="labelPlaca" />
                        <InputMasked
                            v-model="form.placa"
                            mask="AAA#*##"
                            placeholder="ABC-1234 ou ABC1D23"
                            :required="requiresPlaca"
                        />
                        <p v-if="requiresPlaca && !form.placa" class="text-xs text-amber-700 mt-1">Exigida pelo DETRAN.</p>
                    </div>
                    <div v-if="showDocumentacao">
                        <InputLabel :value="labelRenavam" />
                        <input
                            v-model="form.renavam"
                            class="form-input"
                            :required="requiresRenavam"
                            placeholder="11 dígitos"
                        >
                        <p v-if="requiresRenavam && !form.renavam" class="text-xs text-amber-700 mt-1">Documento legal obrigatório.</p>
                    </div>
                    <div v-if="showDocumentacao">
                        <InputLabel value="Chassi (opcional)" />
                        <input v-model="form.chassi" class="form-input">
                    </div>

                    <div><InputLabel value="Cor (opcional)" /><input v-model="form.cor" class="form-input"></div>

                    <!-- Combustível: ESCONDIDO em implementos (sem motor) -->
                    <div v-if="showCombustivel">
                        <InputLabel value="Combustível" />
                        <select v-model="form.combustivel" class="form-select">
                            <option value="">—</option>
                            <option value="diesel">Diesel</option>
                            <option value="gasolina">Gasolina</option>
                            <option value="etanol">Etanol</option>
                            <option value="flex">Flex</option>
                            <option value="eletrico">Elétrico</option>
                        </select>
                    </div>

                    <div>
                        <InputLabel :value="requiresMedidor ? 'Medidor (obrigatório)' : 'Medidor'" />
                        <select v-model="form.medidor" class="form-select" required>
                            <option value="km">Quilometragem (km)</option>
                            <option value="h">Horímetro (h)</option>
                        </select>
                        <p v-if="isMaquinaAgricola" class="text-xs text-emerald-700 mt-1">
                            Recomendado: horímetro (h) para controle de manutenção.
                        </p>
                        <p v-else-if="isImplemento" class="text-xs text-slate-400 mt-1">
                            Opcional — útil se você controla horas rebocadas.
                        </p>
                    </div>
                    <div>
                        <InputLabel value="Leitura atual" />
                        <input type="number" step="0.01" v-model="form.medidor_atual" class="form-input">
                    </div>

                    <div>
                        <InputLabel value="Fazenda" />
                        <select v-model="form.farm_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.nome }}</option>
                        </select>
                    </div>
                    <div><InputLabel value="Valor de aquisição" /><InputMoney v-model="form.valor_aquisicao" /></div>
                    <div><InputLabel value="Data de aquisição" /><InputDate v-model="form.data_aquisicao" /></div>
                    <div class="sm:col-span-3">
                        <InputLabel value="Observações" />
                        <textarea v-model="form.observacoes" rows="2" class="form-textarea"></textarea>
                    </div>
                    <div class="sm:col-span-3 flex justify-between items-center">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" v-model="form.is_active" class="rounded"> Ativo
                        </label>
                        <div class="flex gap-2">
                            <button @click="editing = null" class="btn-outline">Cancelar</button>
                            <button
                                @click="salvar"
                                :disabled="!podeSalvar"
                                class="btn-primary"
                                :title="!podeSalvar && !form.processing ? 'Preencha os campos obrigatórios para o tipo escolhido' : ''"
                            >
                                Salvar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body grid gap-3 sm:grid-cols-4">
                <input v-model="filtros.search" @keyup.enter="filtrar" placeholder="Nome, placa ou modelo" class="form-input sm:col-span-2">
                <select v-model="filtros.tipo" @change="filtrar" class="form-select">
                    <option value="">Todos os tipos</option>
                    <option v-for="(l, v) in tipoLabel" :key="v" :value="v">{{ l }}</option>
                </select>
                <select v-model="filtros.status" @change="filtrar" class="form-select">
                    <option value="ativos">Ativos</option>
                    <option value="inativos">Inativos</option>
                </select>
            </div>
        </div>

        <DataTable
            :columns="[
                { key: 'nome', label: 'Nome' },
                { key: 'tipo', label: 'Tipo' },
                { key: 'placa', label: 'Placa' },
                { key: 'marca', label: 'Marca/Modelo' },
                { key: 'ano_fabricacao', label: 'Ano' },
                { key: 'medidor_atual', label: 'Medidor' },
                { key: 'is_active', label: 'Status' },
                { key: 'acoes', label: '', align: 'right' },
            ]"
            :rows="vehicles.data"
        >
            <template #cell-tipo="{ row }"><span class="badge-slate">{{ tipoLabel[row.tipo] ?? row.tipo }}</span></template>
            <template #cell-marca="{ row }">{{ row.marca }} {{ row.modelo ? '/ ' + row.modelo : '' }}</template>
            <template #cell-medidor_atual="{ row }">{{ Number(row.medidor_atual).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) }} {{ row.medidor }}</template>
            <template #cell-is_active="{ row }">
                <button @click="toggle(row)" :class="row.is_active ? 'badge-green' : 'badge-slate'" class="cursor-pointer">{{ row.is_active ? 'Ativo' : 'Inativo' }}</button>
            </template>
            <template #cell-acoes="{ row }">
                <div class="flex gap-1 justify-end">
                    <ActionIcon type="edit" title="Editar veículo" @click="editar(row)" />
                    <ActionIcon type="delete" title="Excluir veículo" @click="confirmDelete = row" />
                </div>
            </template>
        </DataTable>

        <ConfirmModal :show="!!confirmDelete" title="Excluir veículo"
                      :message="`Excluir ${confirmDelete?.nome}? Se houver manutenções, será apenas desativado.`"
                      @cancel="confirmDelete = null" @confirm="doDelete" />
    </AdminLayout>
</template>
