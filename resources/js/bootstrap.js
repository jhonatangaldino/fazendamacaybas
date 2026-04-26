import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// B4.4 fix · validações HTML5 nativas em pt-BR (override das mensagens em inglês
// do browser: "Please fill out this field", "Please select an item in the list", etc.)
const validationMessagesBR = {
    valueMissing: (el) => {
        if (el.tagName === 'SELECT') return 'Selecione uma opção da lista.';
        if (el.type === 'checkbox' || el.type === 'radio') return 'Marque esta opção para continuar.';
        return 'Preencha este campo.';
    },
    typeMismatch: (el) => {
        if (el.type === 'email') return 'Digite um e-mail válido (ex.: nome@exemplo.com).';
        if (el.type === 'url') return 'Digite uma URL válida (ex.: https://exemplo.com).';
        return 'Valor inválido.';
    },
    patternMismatch: () => 'O formato está incorreto.',
    tooShort: (el) => `Use no mínimo ${el.minLength} caracteres.`,
    tooLong: (el) => `Use no máximo ${el.maxLength} caracteres.`,
    rangeUnderflow: (el) => `Valor mínimo é ${el.min}.`,
    rangeOverflow: (el) => `Valor máximo é ${el.max}.`,
    stepMismatch: () => 'Valor inválido para esse campo.',
    badInput: () => 'Valor inválido.',
};

function setMensagemValidacaoBR(el) {
    if (! el.validity) return;
    el.setCustomValidity('');
    if (el.validity.valid) return;
    for (const key of Object.keys(validationMessagesBR)) {
        if (el.validity[key]) {
            const msg = typeof validationMessagesBR[key] === 'function'
                ? validationMessagesBR[key](el)
                : validationMessagesBR[key];
            el.setCustomValidity(msg);
            return;
        }
    }
}

document.addEventListener('invalid', (e) => setMensagemValidacaoBR(e.target), true);
document.addEventListener('input', (e) => {
    if (e.target?.setCustomValidity) e.target.setCustomValidity('');
}, true);
document.addEventListener('change', (e) => {
    if (e.target?.setCustomValidity) e.target.setCustomValidity('');
}, true);
