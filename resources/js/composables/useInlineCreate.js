import { ref } from 'vue';

/**
 * useInlineCreate — padrão reutilizável para criar dependências
 * dentro de qualquer wizard/form SEM SAIR do fluxo.
 *
 * Princípio do produto: se o usuário precisar de uma entidade que não
 * existe (parceiro, categoria, item, lote, local…), ele cria aqui mesmo
 * via modal; o sistema salva via endpoint JSON (sem redirect), fecha o
 * modal, adiciona o recém-criado à lista local e já seleciona.
 *
 * Uso típico:
 *   const { modalAberto, form, erro, salvando, abrir, salvar, fechar } =
 *     useInlineCreate({
 *         endpoint: route('admin.parceiros.inline'),
 *         initialForm: { nome: '', tipo: 'cliente' },
 *     });
 *   // No onSuccess do salvar, a função passada recebe o recurso criado:
 *   //   const onCreated = (novo) => { listaLocal.push(novo); form.partner_id = novo.id }
 *
 * Laravel aceita o token de CSRF via:
 *   X-CSRF-TOKEN (do meta name="csrf-token")
 *
 * Usamos esse header (estável entre browsers, não depende de cookie encoding).
 */
function csrfHeader() {
    const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return meta ? { 'X-CSRF-TOKEN': meta } : {};
}

export function useInlineCreate({ endpoint, initialForm = {}, onCreated = null }) {
    const modalAberto = ref(false);
    const form = ref({ ...initialForm });
    const erro = ref(null);
    const salvando = ref(false);

    function abrir(extraInit = null) {
        form.value = { ...initialForm, ...(extraInit || {}) };
        erro.value = null;
        modalAberto.value = true;
    }

    function fechar() {
        modalAberto.value = false;
        erro.value = null;
    }

    async function salvar() {
        salvando.value = true;
        erro.value = null;
        try {
            const resp = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeader(),
                },
                body: JSON.stringify(form.value),
            });
            if (!resp.ok) {
                // Tenta extrair erro de validação (422) do Laravel
                if (resp.status === 422) {
                    const body = await resp.json().catch(() => null);
                    const first = body?.errors ? Object.values(body.errors)[0] : null;
                    throw new Error(Array.isArray(first) ? first[0] : (first || 'Dados inválidos.'));
                }
                throw new Error(`Falha ao salvar (${resp.status}).`);
            }
            const criado = await resp.json();
            if (onCreated) onCreated(criado);
            modalAberto.value = false;
            return criado;
        } catch (e) {
            erro.value = e.message || 'Erro.';
            throw e;
        } finally {
            salvando.value = false;
        }
    }

    return { modalAberto, form, erro, salvando, abrir, fechar, salvar };
}
