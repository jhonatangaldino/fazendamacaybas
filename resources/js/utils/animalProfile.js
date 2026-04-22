/**
 * Regras contextuais de manejo por espécie/categoria.
 * Peixe não vacina, implemento não abastece — forms e ações adaptam-se ao tipo.
 *
 * Princípio: ver feedback_context_aware_forms.md (memória).
 * Fonte da verdade: animal_species.allowed_events (JSON no banco) + categoria do animal.
 * Este helper traduz a configuração do banco pras decisões de UI.
 */

// Catálogo global de eventos com rótulo/ícone/cor
export const EVENT_CATALOG = {
    pesagem:              { label: 'Pesagem',             icon: '⚖️', action: 'scale',      color: 'info'    },
    vacinacao:            { label: 'Vacinação',           icon: '💉', action: 'syringe',    color: 'success' },
    medicacao:            { label: 'Medicação',           icon: '💊', action: 'syringe',    color: 'success' },
    vermifugacao:         { label: 'Vermifugação',        icon: '🪱', action: 'syringe',    color: 'warning' },
    reproducao:           { label: 'Reprodução',          icon: '❤️', action: 'heart',      color: 'danger'  },
    movimentacao:         { label: 'Movimentação',        icon: '🔄', action: 'reactivate', color: 'slate'   },
    ordenha:              { label: 'Ordenha',             icon: '🥛', action: 'scale',      color: 'info'    },
    secagem:              { label: 'Secagem',             icon: '🌾', action: 'scale',      color: 'warning' },
    tosquia:              { label: 'Tosquia',             icon: '✂️', action: 'scale',      color: 'info'    },
    ferrageamento:        { label: 'Ferrageamento',       icon: '🧲', action: 'scale',      color: 'slate'   },
    castracao:            { label: 'Castração',           icon: '🔪', action: 'syringe',    color: 'warning' },
    postura_diaria:       { label: 'Postura (ovos)',      icon: '🥚', action: 'scale',      color: 'info'    },
    biometria_amostral:   { label: 'Biometria amostral',  icon: '📏', action: 'scale',      color: 'info'    },
    qualidade_agua:       { label: 'Qualidade da água',   icon: '💧', action: 'scale',      color: 'info'    },
    alimentacao:          { label: 'Alimentação',         icon: '🌽', action: 'scale',      color: 'success' },
    mortalidade:          { label: 'Mortalidade',         icon: '⚰️', action: 'delete',     color: 'danger'  },
    observacao:           { label: 'Observação',          icon: '📝', action: 'edit',       color: 'slate'   },
};

/**
 * Retorna os tipos de eventos permitidos para um animal dado sua espécie + categoria.
 * Prioriza allowed_events da species; se ausente, infere pelo profile.
 */
export function allowedEventsFor(species, categoria) {
    const fromDb = species?.allowed_events;
    if (Array.isArray(fromDb) && fromDb.length) return fromDb;

    // Fallback por profile (caso algo venha sem o JSON)
    const byProfile = {
        ruminante_corte:  ['pesagem', 'vacinacao', 'medicacao', 'vermifugacao', 'reproducao', 'movimentacao', 'observacao'],
        ruminante_leite:  ['pesagem', 'vacinacao', 'medicacao', 'vermifugacao', 'reproducao', 'movimentacao', 'ordenha', 'secagem', 'observacao'],
        ruminante_lan:    ['pesagem', 'vacinacao', 'medicacao', 'vermifugacao', 'reproducao', 'movimentacao', 'tosquia', 'observacao'],
        suino:            ['pesagem', 'vacinacao', 'medicacao', 'vermifugacao', 'reproducao', 'movimentacao', 'observacao'],
        equino:           ['pesagem', 'vacinacao', 'medicacao', 'vermifugacao', 'ferrageamento', 'reproducao', 'movimentacao', 'observacao'],
        pet:              ['pesagem', 'vacinacao', 'medicacao', 'vermifugacao', 'castracao', 'observacao'],
        ave_lote:         ['biometria_amostral', 'alimentacao', 'mortalidade', 'vacinacao', 'movimentacao', 'observacao'],
        ave_postura:      ['postura_diaria', 'biometria_amostral', 'alimentacao', 'mortalidade', 'vacinacao', 'observacao'],
        aquicultura_lote: ['biometria_amostral', 'alimentacao', 'qualidade_agua', 'mortalidade', 'movimentacao', 'observacao'],
        roedor_pequeno:   ['pesagem', 'vacinacao', 'reproducao', 'observacao'],
    };
    return byProfile[species?.profile] || ['observacao'];
}

/**
 * Ações rápidas na linha da tabela — apenas o que é diário/frequente.
 * Venda, abate, exclusão NÃO são rápidas (são eventos ocasionais com fluxo próprio).
 */
export function tableActionsFor(species, categoria) {
    const allowed = new Set(allowedEventsFor(species, categoria));
    const actions = [];

    // Vacinação/vermifugação — rotina sanitária, alta frequência
    if (allowed.has('vacinacao')) {
        actions.push({ key: 'vacinacao', icon: 'syringe', title: 'Registrar vacinação' });
    }

    // Pesagem / biometria — controle de ganho
    if (allowed.has('pesagem')) {
        actions.push({ key: 'pesagem', icon: 'scale', title: 'Registrar pesagem' });
    } else if (allowed.has('biometria_amostral')) {
        actions.push({ key: 'biometria_amostral', icon: 'scale', title: 'Registrar biometria' });
    }

    // Ordenha pra vacas/búfalas/cabras leiteiras
    if (allowed.has('ordenha') && categoria === 'leite') {
        actions.push({ key: 'ordenha', icon: 'scale', title: 'Registrar ordenha' });
    }

    // Postura pra aves de postura
    if (allowed.has('postura_diaria')) {
        actions.push({ key: 'postura_diaria', icon: 'scale', title: 'Registrar postura' });
    }

    return actions;
}

/**
 * Configuração da venda por perfil — unidade de mercado, conversões e labels.
 * Seguindo prática real do campo:
 *   - Bovino de corte/abate: arroba (@) = 15kg de carcaça, valor em R$/@
 *   - Bovino leite vivo: cabeça ou kg
 *   - Peixe: kg (biometria) ou unidade
 *   - Aves: kg vivo ou unidade (postura: dúzia de ovos)
 */
export function vendaConfigFor(species, categoria) {
    const profile = species?.profile;

    if (profile === 'ruminante_corte' || (profile === 'ruminante_leite' && categoria === 'corte')) {
        return {
            modo: 'arroba',
            label: 'Venda em arroba (carcaça)',
            unidades: [
                { v: 'arroba', l: 'Arroba (@ = 15 kg)' },
                { v: 'kg', l: 'Quilograma (peso vivo)' },
                { v: 'cabeca', l: 'Cabeça (valor fixo)' },
            ],
            unidadePadrao: 'arroba',
            kgPorUnidade: { arroba: 15, kg: 1, cabeca: null },
            perguntaPesoMedio: true, // pede peso médio por cabeça
        };
    }
    if (profile === 'ruminante_leite' || profile === 'ruminante_lan') {
        return {
            modo: 'kg',
            label: 'Venda por kg ou cabeça',
            unidades: [
                { v: 'kg', l: 'Quilograma (peso vivo)' },
                { v: 'arroba', l: 'Arroba (@ = 15 kg)' },
                { v: 'cabeca', l: 'Cabeça (valor fixo)' },
            ],
            unidadePadrao: 'kg',
            kgPorUnidade: { arroba: 15, kg: 1, cabeca: null },
            perguntaPesoMedio: true,
        };
    }
    if (profile === 'suino') {
        return {
            modo: 'kg',
            label: 'Venda por kg (peso vivo ou carcaça)',
            unidades: [
                { v: 'kg', l: 'Quilograma' },
                { v: 'cabeca', l: 'Cabeça' },
            ],
            unidadePadrao: 'kg',
            kgPorUnidade: { kg: 1, cabeca: null },
            perguntaPesoMedio: true,
        };
    }
    if (profile === 'aquicultura_lote') {
        return {
            modo: 'kg',
            label: 'Venda por kg ou unidade',
            unidades: [
                { v: 'kg', l: 'Quilograma' },
                { v: 'un', l: 'Unidade (peixe)' },
            ],
            unidadePadrao: 'kg',
            kgPorUnidade: { kg: 1, un: null },
            perguntaPesoMedio: false, // lote direto — informar quantidade total
        };
    }
    if (profile === 'ave_postura' || profile === 'ave_lote') {
        return {
            modo: 'kg',
            label: 'Venda de aves',
            unidades: [
                { v: 'kg', l: 'Quilograma (vivo)' },
                { v: 'un', l: 'Unidade (ave)' },
                { v: 'duzia', l: 'Dúzia (ovos)' },
            ],
            unidadePadrao: 'un',
            kgPorUnidade: { kg: 1, un: null, duzia: null },
            perguntaPesoMedio: false,
        };
    }
    if (profile === 'equino' || profile === 'pet' || profile === 'roedor_pequeno') {
        return {
            modo: 'cabeca',
            label: 'Venda por unidade',
            unidades: [{ v: 'cabeca', l: 'Cabeça' }],
            unidadePadrao: 'cabeca',
            kgPorUnidade: { cabeca: null },
            perguntaPesoMedio: false,
        };
    }

    // default conservador
    return {
        modo: 'cabeca',
        label: 'Venda por cabeça',
        unidades: [{ v: 'cabeca', l: 'Cabeça' }],
        unidadePadrao: 'cabeca',
        kgPorUnidade: { cabeca: null },
        perguntaPesoMedio: false,
    };
}

/**
 * Rotula o modo de registro (individual ou lote) — usado pra alertar o usuário no form.
 */
export function isLoteSpecies(species) {
    return species?.gestao === 'lote';
}

/**
 * Lista de eventos formatada para dropdowns do modal "Novo evento".
 */
export function eventOptionsFor(species, categoria) {
    return allowedEventsFor(species, categoria).map((tipo) => ({
        value: tipo,
        label: (EVENT_CATALOG[tipo]?.icon || '') + ' ' + (EVENT_CATALOG[tipo]?.label || tipo),
    }));
}
