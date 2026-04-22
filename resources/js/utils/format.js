import dayjs from 'dayjs';

export function brl(v) {
    if (v === null || v === undefined || v === '') return 'R$ 0,00';
    const n = typeof v === 'number' ? v : parseFloat(String(v).replace(',', '.'));
    if (Number.isNaN(n)) return 'R$ 0,00';
    return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function dataBR(d) {
    if (!d) return '—';
    const x = dayjs(d);
    return x.isValid() ? x.format('DD/MM/YYYY') : '—';
}

export function dataHoraBR(d) {
    if (!d) return '—';
    const x = dayjs(d);
    return x.isValid() ? x.format('DD/MM/YYYY HH:mm') : '—';
}

export function cpfMask(v) {
    if (!v) return '';
    const d = String(v).replace(/\D/g, '').padStart(11, '0');
    return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9, 11)}`;
}

export function cnpjMask(v) {
    if (!v) return '';
    const d = String(v).replace(/\D/g, '').padStart(14, '0');
    return `${d.slice(0, 2)}.${d.slice(2, 5)}.${d.slice(5, 8)}/${d.slice(8, 12)}-${d.slice(12, 14)}`;
}

export function cpfCnpjMask(v) {
    if (!v) return '—';
    const d = String(v).replace(/\D/g, '');
    if (d.length === 14) return cnpjMask(d);
    if (d.length === 11) return cpfMask(d);
    return v;
}

export function telefoneMask(v) {
    if (!v) return '';
    const d = String(v).replace(/\D/g, '');
    if (d.length === 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7, 11)}`;
    if (d.length === 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6, 10)}`;
    return v;
}

export function cepMask(v) {
    if (!v) return '';
    const d = String(v).replace(/\D/g, '').padStart(8, '0');
    return `${d.slice(0, 5)}-${d.slice(5, 8)}`;
}
