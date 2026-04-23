/**
 * v-tooltip — tooltip consistente em todo o sistema.
 *
 * Uso:
 *   <button v-tooltip="'Editar cadastro'">...</button>
 *   <a v-tooltip.bottom="'Voltar'">...</a>
 *   <button v-tooltip="{ text: 'Confirmar', placement: 'right', delay: 200 }">...</button>
 *
 * Padrão único:
 *   - delay default: 400ms (evita flash ao passar o mouse rápido)
 *   - posição default: top (modifier: .top .bottom .left .right)
 *   - cor: slate-900 com texto branco
 *   - radius, sombra, tipografia alinhados com o resto do sistema
 *
 * Acessibilidade:
 *   - aplica `aria-label` no elemento se ainda não houver (para leitores de tela)
 *   - dispara no mouseenter + focus (funciona com teclado)
 *   - remove o `title` nativo (evita tooltip duplo)
 *
 * NÃO depende de bibliotecas externas. Zero Floating UI / Popper — um tooltip
 * simples resolve 100% dos usos do sistema. Se no futuro quisermos o tooltip
 * "inteligente" com detecção de borda, basta trocar aqui.
 */

const CLASSES = [
    'pointer-events-none',
    'fixed',
    'z-[1000]',
    'px-2',
    'py-1',
    'rounded-md',
    'bg-slate-900',
    'text-white',
    'text-xs',
    'font-medium',
    'shadow-lg',
    'whitespace-nowrap',
    'transition-opacity',
    'duration-150',
    'select-none',
];

function parseBinding(binding) {
    const value = binding.value;
    const defaults = {
        text: '',
        placement: 'top',
        delay: 400,
    };

    // v-tooltip="'texto'"  OU  v-tooltip="{ text, placement, delay }"
    if (typeof value === 'string') defaults.text = value;
    else if (value && typeof value === 'object') Object.assign(defaults, value);

    // Modifier tem prioridade (v-tooltip.bottom="'x'")
    for (const p of ['top', 'bottom', 'left', 'right']) {
        if (binding.modifiers[p]) defaults.placement = p;
    }

    return defaults;
}

function positionTooltip(tip, host, placement) {
    const rect = host.getBoundingClientRect();
    const th = tip.offsetHeight;
    const tw = tip.offsetWidth;
    const GAP = 6;

    let top = 0, left = 0;
    switch (placement) {
        case 'bottom':
            top = rect.bottom + GAP;
            left = rect.left + rect.width / 2 - tw / 2;
            break;
        case 'left':
            top = rect.top + rect.height / 2 - th / 2;
            left = rect.left - tw - GAP;
            break;
        case 'right':
            top = rect.top + rect.height / 2 - th / 2;
            left = rect.right + GAP;
            break;
        case 'top':
        default:
            top = rect.top - th - GAP;
            left = rect.left + rect.width / 2 - tw / 2;
    }
    // Clamp no viewport
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    left = Math.max(8, Math.min(left, vw - tw - 8));
    top = Math.max(8, Math.min(top, vh - th - 8));

    tip.style.top = `${top}px`;
    tip.style.left = `${left}px`;
}

function showTooltip(el, text, placement) {
    hideTooltip(el); // idempotência
    const tip = document.createElement('div');
    tip.className = CLASSES.join(' ');
    tip.textContent = text;
    tip.style.opacity = '0';
    document.body.appendChild(tip);
    positionTooltip(tip, el, placement);
    // próxima frame para permitir transição
    requestAnimationFrame(() => {
        tip.style.opacity = '1';
    });
    el._tooltipEl = tip;
}

function hideTooltip(el) {
    if (el._tooltipTimer) {
        clearTimeout(el._tooltipTimer);
        el._tooltipTimer = null;
    }
    if (el._tooltipEl) {
        el._tooltipEl.remove();
        el._tooltipEl = null;
    }
}

function attach(el, binding) {
    const { text, placement, delay } = parseBinding(binding);
    if (! text) return;

    // Acessibilidade: preserva aria-label, remove title nativo
    if (! el.getAttribute('aria-label')) {
        el.setAttribute('aria-label', text);
    }
    if (el.hasAttribute('title')) {
        el._originalTitle = el.getAttribute('title');
        el.removeAttribute('title');
    }

    const onEnter = () => {
        if (el._tooltipTimer) clearTimeout(el._tooltipTimer);
        el._tooltipTimer = setTimeout(() => showTooltip(el, text, placement), delay);
    };
    const onLeave = () => hideTooltip(el);

    el.addEventListener('mouseenter', onEnter);
    el.addEventListener('mouseleave', onLeave);
    el.addEventListener('focus', onEnter);
    el.addEventListener('blur', onLeave);
    el.addEventListener('click', onLeave); // fecha ao clicar — evita "fantasma"

    el._tooltipHandlers = { onEnter, onLeave };
}

function detach(el) {
    hideTooltip(el);
    if (el._tooltipHandlers) {
        el.removeEventListener('mouseenter', el._tooltipHandlers.onEnter);
        el.removeEventListener('mouseleave', el._tooltipHandlers.onLeave);
        el.removeEventListener('focus', el._tooltipHandlers.onEnter);
        el.removeEventListener('blur', el._tooltipHandlers.onLeave);
        el.removeEventListener('click', el._tooltipHandlers.onLeave);
        delete el._tooltipHandlers;
    }
    if (el._originalTitle) {
        el.setAttribute('title', el._originalTitle);
        delete el._originalTitle;
    }
}

export default {
    mounted(el, binding) {
        attach(el, binding);
    },
    updated(el, binding) {
        // Se o texto mudou, re-anexa (mantém sincronizado com reativo)
        if (binding.value === binding.oldValue) return;
        detach(el);
        attach(el, binding);
    },
    beforeUnmount(el) {
        detach(el);
    },
};
