<script setup>
/**
 * Assistente guiado — Anexar documento.
 * 4 passos: tipo → arquivo → detalhes (datas adaptadas) → sucesso.
 * Campos do passo 3 mudam pelo tipo (GTA tem validade curta sugerida, contrato 2 anos).
 */
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import WizardStepper from '@/Components/WizardStepper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputDate from '@/Components/InputDate.vue';
import { dataBR, hojeBR } from '@/utils/format.js';

const props = defineProps({
    tipos: { type: Array, required: true },
});

const PASSOS = [
    { n: 1, titulo: 'Tipo',     icon: '📋' },
    { n: 2, titulo: 'Arquivo',  icon: '📎' },
    { n: 3, titulo: 'Detalhes', icon: '📝' },
    { n: 4, titulo: 'Pronto!',  icon: '✅' },
];

const passo = ref(1);
const sucesso = ref(null);

const form = useForm({
    tipo: null,
    titulo: '',
    descricao: '',
    arquivo: null,
    data_documento: hojeBR(),
    data_vencimento: '',
});

const arquivoNome = ref('');
const arquivoPreview = ref(null);
const arquivoErro = ref(null); // erro inline (substitui alert nativo)

const tipoSelecionado = computed(() => props.tipos.find(t => t.id === form.tipo));

// Sugestão automática de vencimento conforme tipo
watch(() => form.tipo, (novoTipo) => {
    const t = props.tipos.find(x => x.id === novoTipo);
    if (t?.pede_validade && t.sugestao_validade_dias) {
        const d = new Date();
        d.setDate(d.getDate() + t.sugestao_validade_dias);
        form.data_vencimento = d.toISOString().slice(0, 10);
    } else {
        form.data_vencimento = '';
    }
});

function onArquivoChange(e) {
    const f = e.target.files?.[0];
    arquivoErro.value = null;
    if (!f) return;
    if (f.size > 20 * 1024 * 1024) {
        arquivoErro.value = 'Arquivo acima de 20 MB. Comprima o arquivo ou divida em partes menores.';
        e.target.value = '';
        return;
    }
    form.arquivo = f;
    arquivoNome.value = f.name;
    if (f.type.startsWith('image/')) {
        const r = new FileReader();
        r.onload = (ev) => { arquivoPreview.value = ev.target.result; };
        r.readAsDataURL(f);
    } else {
        arquivoPreview.value = null;
    }
}

const podeAvancar1 = computed(() => !!form.tipo);
const podeAvancar2 = computed(() => !!form.arquivo);
const podeAvancar3 = computed(() => form.titulo.trim().length >= 2);

function avancar() { if (passo.value < 4) passo.value++; }
function voltar()  { if (passo.value > 1) passo.value--; }

function confirmar() {
    form.post(route('admin.fluxos.anexar-documento.store'), {
        forceFormData: true,
        preserveScroll: false,
        onSuccess: () => {
            sucesso.value = {
                titulo: form.titulo,
                tipo: tipoSelecionado.value?.rotulo,
                vencimento: form.data_vencimento,
            };
            passo.value = 4;
        },
    });
}

function reiniciar() {
    form.reset();
    form.data_documento = hojeBR();
    arquivoNome.value = '';
    arquivoPreview.value = null;
    sucesso.value = null;
    passo.value = 1;
}
</script>

<template>
    <Head title="Anexar documento" />
    <AdminLayout>
        <template #page-title>Assistente · Documento</template>

        <PageHeader title="Anexar documento" subtitle="Diga o tipo primeiro — adaptamos o resto do formulário pra você.">
            <template #actions>
                <Link :href="route('admin.documentos.index')" class="btn-outline">Sair</Link>
            </template>
        </PageHeader>

        <WizardStepper :passos="PASSOS" :passo="passo" />

        <!-- PASSO 1 — Tipo -->
        <div v-if="passo === 1" class="card max-w-4xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Que tipo de documento?</h2>
                <p class="text-base text-slate-600">Cada tipo tem validade e formato diferente. A gente adapta os próximos passos.</p>

                <div class="grid gap-3 sm:grid-cols-2">
                    <button v-for="t in tipos" :key="t.id" type="button" @click="form.tipo = t.id"
                        :data-tipo="t.id"
                        class="text-left rounded-xl border-2 p-4 transition-all hover:border-macaybas-primary"
                        :class="form.tipo === t.id ? 'border-macaybas-primary bg-emerald-50' : 'border-slate-200 bg-white'">
                        <div class="flex items-start gap-3">
                            <span class="text-3xl">{{ t.icone }}</span>
                            <div class="flex-1">
                                <div class="font-bold text-slate-900">{{ t.rotulo }}</div>
                                <div class="text-sm text-slate-600 mt-1">{{ t.desc }}</div>
                            </div>
                        </div>
                    </button>
                </div>

                <div class="flex justify-end pt-4 border-t">
                    <button @click="avancar" :disabled="!podeAvancar1" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 2 — Arquivo -->
        <div v-if="passo === 2" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Anexa o arquivo</h2>
                <p class="text-sm text-slate-600">
                    Aceita: <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">{{ tipoSelecionado?.mimes ?? 'pdf, imagens, docx, xlsx' }}</code> · até 20 MB.
                </p>

                <p v-if="arquivoErro" class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-md px-2.5 py-2">
                    ⚠ {{ arquivoErro }}
                </p>

                <div class="rounded-xl border-2 border-dashed border-slate-300 p-6 text-center bg-slate-50">
                    <input type="file" id="doc-file" data-cy="input-arquivo" @change="onArquivoChange" class="hidden"
                           :accept="(tipoSelecionado?.mimes ?? '').split(',').map(e => '.' + e.trim()).join(',')" />
                    <label for="doc-file" class="cursor-pointer inline-block">
                        <div v-if="!arquivoNome">
                            <div class="text-4xl mb-2">📎</div>
                            <div class="font-semibold text-slate-700">Clique para escolher</div>
                            <div class="text-xs text-slate-500 mt-1">ou arraste e solte aqui</div>
                        </div>
                        <div v-else class="space-y-2">
                            <div v-if="arquivoPreview"><img :src="arquivoPreview" class="max-h-48 mx-auto rounded" /></div>
                            <div v-else class="text-4xl">📄</div>
                            <div class="font-semibold text-emerald-700 break-all">{{ arquivoNome }}</div>
                            <div class="text-xs text-slate-500">Clique pra trocar</div>
                        </div>
                    </label>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="avancar" :disabled="!podeAvancar2" class="btn-primary px-6 py-2.5">Continuar →</button>
                </div>
            </div>
        </div>

        <!-- PASSO 3 — Detalhes adaptados -->
        <div v-if="passo === 3" class="card max-w-2xl mx-auto">
            <div class="card-body space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Detalhes do {{ tipoSelecionado?.rotulo?.toLowerCase() }}</h2>

                <div>
                    <InputLabel value="Como nomear este documento? *" />
                    <input v-model="form.titulo" data-cy="input-titulo" class="form-input text-lg py-3"
                        :placeholder="tipoSelecionado?.id === 'gta' ? 'Ex.: GTA Curral Norte → Frigorífico' : 'Ex.: Licença IBAMA 2026'" />
                </div>

                <div>
                    <InputLabel value="Data do documento" />
                    <InputDate v-model="form.data_documento" />
                </div>

                <div v-if="tipoSelecionado?.pede_validade">
                    <InputLabel value="Data de vencimento" />
                    <InputDate v-model="form.data_vencimento" />
                    <p class="text-xs text-slate-500 mt-1">
                        Sugerido <strong>{{ tipoSelecionado?.sugestao_validade_dias }} dias</strong> a partir de hoje — ajuste se for diferente.
                    </p>
                </div>

                <div>
                    <InputLabel value="Observações (opcional)" />
                    <textarea v-model="form.descricao" rows="2" class="form-textarea"
                        placeholder="Detalhes que ajudem a localizar depois"></textarea>
                </div>

                <div class="flex justify-between pt-4 border-t">
                    <button @click="voltar" class="btn-outline">← Voltar</button>
                    <button @click="confirmar" :disabled="!podeAvancar3 || form.processing"
                        data-cy="confirmar"
                        class="btn-primary px-8 py-2.5">
                        {{ form.processing ? 'Enviando…' : '✓ Anexar documento' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- PASSO 4 — Sucesso -->
        <div v-if="passo === 4 && sucesso" class="card max-w-2xl mx-auto" data-cy="passo-sucesso">
            <div class="card-body text-center py-8 space-y-4">
                <div class="text-5xl">📂</div>
                <h2 class="text-2xl font-semibold text-slate-900">Documento anexado!</h2>
                <p class="text-slate-700">
                    <strong>{{ sucesso.titulo }}</strong> ({{ sucesso.tipo }})
                </p>
                <div v-if="sucesso.vencimento" class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm">
                    ⏰ Vence em <strong>{{ dataBR(sucesso.vencimento) }}</strong> — sistema vai te lembrar quando se aproximar.
                </div>
                <div class="flex flex-col sm:flex-row gap-2 justify-center pt-3">
                    <button @click="reiniciar" class="btn-primary">Anexar outro</button>
                    <Link :href="route('admin.documentos.index')" class="btn-outline">Ver todos</Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
