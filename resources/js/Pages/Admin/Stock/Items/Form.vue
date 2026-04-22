<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import InputMoney from '@/Components/InputMoney.vue';
import BarcodeScanner from '@/Components/BarcodeScanner.vue';
import { useToast } from '@/composables/useToast.js';

const { toast } = useToast();
const props = defineProps({ item: Object, categories: Array });
const isEdit = !!props.item;

const form = useForm({
    category_id: props.item?.category_id ?? null,
    codigo: props.item?.codigo ?? '',
    codigo_barras: props.item?.codigo_barras ?? '',
    nome: props.item?.nome ?? '',
    descricao: props.item?.descricao ?? '',
    unidade: props.item?.unidade ?? 'un',
    marca: props.item?.marca ?? '',
    estoque_minimo: props.item?.estoque_minimo ?? 0,
    estoque_maximo: props.item?.estoque_maximo ?? null,
    custo_medio: props.item?.custo_medio ?? 0,
    tipo: props.item?.tipo ?? 'insumo',
    registro_ms: props.item?.registro_ms ?? '',
    is_active: props.item?.is_active ?? true,
});

const showScanner = ref(false);
const lookupLoading = ref(false);
const produtoExistente = ref(null); // produto já cadastrado encontrado no lookup local

async function onBarcodeDetected(code) {
    showScanner.value = false;
    form.codigo_barras = code;
    lookupLoading.value = true;

    try {
        // 1) Lookup local — produto já cadastrado?
        const localResp = await fetch(route('admin.estoque.itens.lookup-barcode') + '?code=' + encodeURIComponent(code), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const local = await localResp.json();

        if (local.found && local.item) {
            // Se é um NOVO item e o produto já existe, avisar e oferecer atalho pra movimento de estoque
            if (!isEdit) {
                produtoExistente.value = local.item;
                toast({ variant: 'warning', message: `"${local.item.nome}" já está cadastrado.` });
                return;
            }
            // Se é edit, apenas avisar
            toast({ variant: 'info', message: `Este código já está associado a "${local.item.nome}".` });
        } else {
            toast({ variant: 'success', message: `Código ${code} lido. Produto não existe ainda.` });
        }

        // 2) Fallback: Open Food Facts pra auto-preencher campos (só se novo + nome vazio)
        if (!isEdit && !form.nome) {
            try {
                const r = await fetch(`https://world.openfoodfacts.org/api/v2/product/${encodeURIComponent(code)}.json`);
                const j = await r.json();
                if (j.status === 1 && j.product) {
                    const p = j.product;
                    form.nome = p.product_name_pt || p.product_name || form.nome;
                    if (p.brands && !form.marca) form.marca = p.brands.split(',')[0].trim();
                    toast({ variant: 'success', message: `Produto encontrado na base pública: ${form.nome}` });
                } else {
                    toast({ variant: 'info', message: 'Código lido, mas produto não encontrado na base pública. Preencha manualmente.' });
                }
            } catch (_) { /* silencioso, não é crítico */ }
        }
    } catch (_) {
        toast({ variant: 'warning', message: 'Falha ao consultar produto. Preencha manualmente.' });
    } finally {
        lookupLoading.value = false;
    }
}

function irParaMovimentacao() {
    // Redireciona pra tela de movimentos com o item pré-selecionado
    window.location.href = produtoExistente.value.movement_url;
}
function irParaEdicao() {
    window.location.href = produtoExistente.value.edit_url;
}

function submit() {
    if (isEdit) form.put(route('admin.estoque.itens.update', props.item.id));
    else form.post(route('admin.estoque.itens.store'));
}

const unidades = ['un', 'kg', 'g', 'l', 'ml', 'sc', 'cx', 'pc', 'm', 'm2', 'm3'];
</script>

<template>
    <Head :title="isEdit ? 'Editar item' : 'Novo item'" />
    <AdminLayout>
        <PageHeader :title="isEdit ? 'Editar item de estoque' : 'Novo item de estoque'">
            <template #actions>
                <Link :href="route('admin.estoque.itens.index')" class="btn-outline">Voltar</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="space-y-6 max-w-3xl">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Identificação</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Código interno" />
                        <input v-model="form.codigo" required class="form-input" placeholder="Ex: RAC-001">
                        <InputError :message="form.errors.codigo" />
                    </div>
                    <div>
                        <InputLabel value="Código de barras (EAN/UPC)" />
                        <div class="flex gap-2">
                            <input v-model="form.codigo_barras" class="form-input flex-1 font-mono" placeholder="Ex: 7891234567890">
                            <button type="button" @click="showScanner = true"
                                    class="btn-outline flex items-center gap-1.5 flex-shrink-0"
                                    :disabled="lookupLoading">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9zM15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Escanear
                            </button>
                        </div>
                        <p v-if="lookupLoading" class="text-xs text-slate-500 mt-1">Buscando produto na base pública...</p>
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="form.tipo" class="form-select" required>
                            <option value="insumo">Insumo agrícola</option>
                            <option value="medicamento">Medicamento veterinário</option>
                            <option value="racao">Ração</option>
                            <option value="ferramenta">Ferramenta</option>
                            <option value="peca">Peça / componente</option>
                            <option value="combustivel">Combustível</option>
                            <option value="material">Material diverso</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Nome" />
                        <input v-model="form.nome" required class="form-input">
                        <InputError :message="form.errors.nome" />
                    </div>
                    <div>
                        <InputLabel value="Categoria" />
                        <select v-model="form.category_id" class="form-select">
                            <option :value="null">—</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.nome }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Marca" />
                        <input v-model="form.marca" class="form-input">
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Descrição" />
                        <textarea v-model="form.descricao" rows="2" class="form-textarea"></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Estoque</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel value="Unidade" />
                        <select v-model="form.unidade" class="form-select" required>
                            <option v-for="u in unidades" :key="u" :value="u">{{ u }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Estoque mínimo" />
                        <input type="number" step="0.001" min="0" v-model="form.estoque_minimo" class="form-input">
                    </div>
                    <div>
                        <InputLabel value="Estoque máximo (opcional)" />
                        <input type="number" step="0.001" min="0" v-model="form.estoque_maximo" class="form-input">
                    </div>
                    <div>
                        <InputLabel value="Custo médio (R$/un)" />
                        <InputMoney v-model="form.custo_medio" />
                    </div>
                    <div v-if="form.tipo === 'medicamento'" class="sm:col-span-2">
                        <InputLabel value="Registro no MAPA/MS" />
                        <input v-model="form.registro_ms" class="form-input">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_active" class="rounded">
                        Item ativo
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Link :href="route('admin.estoque.itens.index')" class="btn-outline">Cancelar</Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">Salvar</button>
            </div>
        </form>

        <BarcodeScanner v-if="showScanner"
                        @detected="onBarcodeDetected"
                        @close="showScanner = false" />

        <!-- Modal: Produto já cadastrado -->
        <Teleport to="body">
            <div v-if="produtoExistente" class="fixed inset-0 z-[55] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="produtoExistente = null"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <div class="flex items-start gap-3 mb-4">
                        <div class="h-10 w-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-slate-900">Produto já cadastrado</h3>
                            <p class="text-sm text-slate-500 mt-1">Este código de barras já está vinculado a um item existente do seu estoque.</p>
                        </div>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-4 mb-4 space-y-1">
                        <div class="font-semibold text-slate-900">{{ produtoExistente.nome }}</div>
                        <div class="text-sm text-slate-600">
                            <span class="font-mono">{{ produtoExistente.codigo }}</span>
                            <span v-if="produtoExistente.marca"> · {{ produtoExistente.marca }}</span>
                            <span v-if="produtoExistente.category?.nome"> · {{ produtoExistente.category.nome }}</span>
                        </div>
                        <div class="text-sm pt-2 border-t border-slate-200">
                            Saldo atual:
                            <strong :class="produtoExistente.saldo_atual > 0 ? 'text-emerald-700' : 'text-slate-500'">
                                {{ Number(produtoExistente.saldo_atual).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }} {{ produtoExistente.unidade }}
                            </strong>
                        </div>
                    </div>
                    <p class="text-sm text-slate-600 mb-4">
                        Você só precisa <strong>dar entrada em estoque</strong>. Deseja ir direto para a tela de movimentação?
                    </p>
                    <div class="flex flex-col sm:flex-row justify-end gap-2">
                        <button @click="produtoExistente = null" class="btn-outline">Cancelar</button>
                        <button @click="irParaEdicao" class="btn-outline">Editar cadastro</button>
                        <button @click="irParaMovimentacao" class="btn-primary">Entrada em estoque →</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
