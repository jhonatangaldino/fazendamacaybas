/**
 * Validadores e formatadores brasileiros (CPF, CNPJ, telefone).
 *
 * CNPJ alfanumérico (Resolução CGSIM 2026):
 *   - a partir de julho/2026 a Receita emite CNPJs com letras A-Z nas 12 posições iniciais
 *   - os 2 últimos dígitos (DV) continuam numéricos
 *   - cálculo do DV usa o valor ASCII do caractere - 48 (ex: '0'=0, '1'=1, ..., 'A'=17, 'B'=18, ...)
 *
 * Referência oficial:
 *   http://www.planalto.gov.br/ccivil_03/_ato2019-2022/2021/decreto/D10854.htm
 */

export function apenasDigitos(v) {
    return (v || '').toString().replace(/\D/g, '');
}
export function apenasAlfaNum(v) {
    return (v || '').toString().toUpperCase().replace(/[^0-9A-Z]/g, '');
}

// ============ CPF ============
export function cpfMask(v) {
    const d = apenasDigitos(v).slice(0, 11);
    if (d.length <= 3) return d;
    if (d.length <= 6) return `${d.slice(0, 3)}.${d.slice(3)}`;
    if (d.length <= 9) return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6)}`;
    return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
}

export function validarCpf(v) {
    const d = apenasDigitos(v);
    if (d.length !== 11 || /^(\d)\1{10}$/.test(d)) return false;

    let soma = 0;
    for (let i = 0; i < 9; i++) soma += parseInt(d[i], 10) * (10 - i);
    let dv1 = (soma * 10) % 11;
    if (dv1 === 10) dv1 = 0;
    if (dv1 !== parseInt(d[9], 10)) return false;

    soma = 0;
    for (let i = 0; i < 10; i++) soma += parseInt(d[i], 10) * (11 - i);
    let dv2 = (soma * 10) % 11;
    if (dv2 === 10) dv2 = 0;
    return dv2 === parseInt(d[10], 10);
}

// ============ CNPJ ============

/**
 * Máscara CNPJ: funciona para numérico tradicional e alfanumérico (pós-2026).
 * Formato: XX.XXX.XXX/XXXX-DD  (X = alfanum, D = dígito)
 */
export function cnpjMask(v) {
    const s = apenasAlfaNum(v).slice(0, 14);
    const alphaPart = s.slice(0, 12);
    const numPart = s.slice(12).replace(/\D/g, '');
    const full = alphaPart + numPart;

    if (full.length <= 2) return full;
    if (full.length <= 5) return `${full.slice(0, 2)}.${full.slice(2)}`;
    if (full.length <= 8) return `${full.slice(0, 2)}.${full.slice(2, 5)}.${full.slice(5)}`;
    if (full.length <= 12) return `${full.slice(0, 2)}.${full.slice(2, 5)}.${full.slice(5, 8)}/${full.slice(8)}`;
    return `${full.slice(0, 2)}.${full.slice(2, 5)}.${full.slice(5, 8)}/${full.slice(8, 12)}-${full.slice(12)}`;
}

/**
 * Calcula DV de CNPJ (funciona para numérico e alfanumérico).
 * Usa o valor ASCII do caractere − 48 (os dígitos numéricos resultam no próprio valor).
 */
function cnpjCalcDv(digits, pesos) {
    let soma = 0;
    for (let i = 0; i < digits.length; i++) {
        const char = digits[i];
        const valor = char.charCodeAt(0) - 48;
        soma += valor * pesos[i];
    }
    const resto = soma % 11;
    return resto < 2 ? 0 : 11 - resto;
}

export function validarCnpj(v) {
    const s = apenasAlfaNum(v);
    if (s.length !== 14) return false;

    // Corpo (12 primeiras posições): aceita letras/números
    // DV (2 últimas): SEMPRE numéricos
    const corpo = s.slice(0, 12);
    const dv = s.slice(12);
    if (!/^\d{2}$/.test(dv)) return false;

    // Rejeita sequências repetidas (ex: 00000000000000)
    if (/^(.)\1{13}$/.test(s)) return false;

    const pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    const pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    const dv1 = cnpjCalcDv(corpo, pesos1);
    if (dv1 !== parseInt(dv[0], 10)) return false;

    const dv2 = cnpjCalcDv(corpo + dv[0], pesos2);
    return dv2 === parseInt(dv[1], 10);
}

/**
 * Decide automaticamente se é CPF ou CNPJ pelo tamanho.
 */
export function cpfCnpjMask(v) {
    const digits = apenasAlfaNum(v);
    return digits.length <= 11 ? cpfMask(digits) : cnpjMask(digits);
}

export function validarCpfCnpj(v) {
    const d = apenasAlfaNum(v);
    if (d.length === 11) return validarCpf(d);
    if (d.length === 14) return validarCnpj(d);
    return false;
}

// ============ Telefone BR ============
/**
 * Máscara dinâmica: 10 dígitos = fixo (##) ####-####, 11 = celular (##) #####-####.
 */
export function telefoneMask(v) {
    const d = apenasDigitos(v).slice(0, 11);
    if (d.length <= 2) return d.length ? `(${d}` : '';
    if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
    if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
    return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
}

export function validarTelefone(v) {
    const d = apenasDigitos(v);
    return d.length === 10 || d.length === 11;
}

// ============ CEP ============
export function cepMask(v) {
    const d = apenasDigitos(v).slice(0, 8);
    if (d.length <= 5) return d;
    return `${d.slice(0, 5)}-${d.slice(5)}`;
}
export function validarCep(v) {
    return apenasDigitos(v).length === 8;
}
