/**
 * useProofAutoApprove — processa em lote comprovantes paid_pending_review.
 *
 * Para cada comprovante:
 *   1. Roda OCR via Tesseract.js (lazy import)
 *   2. Extrai valor, E2E PIX, banco, padrão
 *   3. Confere duplicata no backend
 *   4. Se TODOS os checks passam → POST aprovar com auto_aprovado=true
 *   5. Se algum falha → mantém pendente (master revisa manualmente)
 *
 * Reutilizado por:
 *   - Botão "Processar lote" na lista de cobranças (manual)
 *   - Auto-disparo no MasterLayout pós-login (futuro Fase 3.5, opt-in)
 */
import { ref } from 'vue';
import { hojeBR } from '@/utils/format.js';

const BANCOS_REGEX = [
    { nome: 'PicPay',     re: /picpay/i },
    { nome: 'Itaú',       re: /ita[uú]/i },
    { nome: 'Nubank',     re: /nubank/i },
    { nome: 'Bradesco',   re: /bradesco/i },
    { nome: 'Santander',  re: /santander/i },
    { nome: 'Banco do Brasil', re: /banco do brasil|\bbb\b/i },
    { nome: 'Caixa',      re: /caixa econ[oô]mica|caixa/i },
    { nome: 'Inter',      re: /banco inter/i },
    { nome: 'C6',         re: /\bc6 bank|c6\b/i },
    { nome: 'Mercado Pago', re: /mercado pago|mercadopago/i },
    { nome: 'Sicoob',     re: /sicoob/i },
    { nome: 'Sicredi',    re: /sicredi/i },
    { nome: 'BTG',        re: /btg pactual/i },
    { nome: 'XP',         re: /\bxp\b/i },
];

function extractValor(text) {
    const matches = [...text.matchAll(/R?\$?\s*(\d{1,3}(?:\.\d{3})*,\d{2}|\d+,\d{2})/g)];
    if (matches.length === 0) return null;
    const valores = matches.map(m => parseFloat(m[1].replace(/\./g, '').replace(',', '.')));
    return Math.max(...valores);
}
function extractE2e(text) {
    const m = text.match(/E[A-Z0-9]{31}/i);
    return m ? m[0].toUpperCase() : null;
}
function extractHintPattern(text) {
    return (text.split('\n').find(l => l.trim().length >= 5) || '').trim().slice(0, 80);
}
function detectBanco(text) {
    for (const b of BANCOS_REGEX) if (b.re.test(text)) return b.nome;
    return null;
}

async function checarDuplicata(e2e, invoiceId) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const res = await fetch(route('master.cobrancas.check-duplicate-e2e'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf || '', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ e2e_id: e2e, invoice_id: invoiceId }),
    });
    return await res.json();
}

async function aprovarFatura(invoiceId, payload) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const res = await fetch(route('master.cobrancas.approve-proof', invoiceId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf || '', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });
    return res.ok || res.redirected;
}

/**
 * Processa um único comprovante. Retorna {decisao, motivo, ocr}.
 * decisao: 'aprovado' | 'pendente' | 'erro'
 */
async function processarUm(invoice, Tesseract) {
    if (! invoice.payment_proof_url || ! invoice.payment_proof_mime?.startsWith('image/')) {
        return { decisao: 'pendente', motivo: 'PDF ou sem comprovante imagem', ocr: null };
    }
    try {
        const { data } = await Tesseract.recognize(invoice.payment_proof_url, 'por');
        const text = data.text || '';
        const ocr = {
            valor: extractValor(text),
            e2e: extractE2e(text),
            banco: detectBanco(text),
            hintPattern: extractHintPattern(text),
        };
        // CHECK 1: valor bate
        if (! ocr.valor || Math.abs(ocr.valor - invoice.valor) >= 0.01) {
            return { decisao: 'pendente', motivo: 'valor não bate', ocr };
        }
        // CHECK 2: E2E presente
        if (! ocr.e2e) {
            return { decisao: 'pendente', motivo: 'E2E não detectado', ocr };
        }
        // CHECK 3: não é duplicata
        const dup = await checarDuplicata(ocr.e2e, invoice.id);
        if (dup.duplicate) {
            return { decisao: 'pendente', motivo: 'E2E já usado em outra fatura', ocr };
        }
        // Aprova!
        const ok = await aprovarFatura(invoice.id, {
            data_pagamento: hojeBR(),
            external_payment_id: ocr.e2e,
            ocr_banco: ocr.banco,
            ocr_pattern: ocr.hintPattern,
            auto_aprovado: true,
        });
        return ok
            ? { decisao: 'aprovado', motivo: 'tudo bate', ocr }
            : { decisao: 'erro', motivo: 'POST falhou', ocr };
    } catch (e) {
        return { decisao: 'erro', motivo: e.message || 'OCR falhou', ocr: null };
    }
}

export function useProofAutoApprove() {
    const processando = ref(false);
    const total = ref(0);
    const concluidos = ref(0);
    const aprovados = ref(0);
    const pendentes = ref(0);
    const erros = ref(0);
    const detalhes = ref([]); // [{numero, decisao, motivo}]

    async function processarLote(invoices) {
        if (processando.value) return;
        const elegiveis = invoices.filter(i => i.status === 'paid_pending_review' && i.payment_proof_url);
        processando.value = true;
        total.value = elegiveis.length;
        concluidos.value = 0;
        aprovados.value = 0;
        pendentes.value = 0;
        erros.value = 0;
        detalhes.value = [];

        const Tesseract = await import('tesseract.js');
        for (const inv of elegiveis) {
            const r = await processarUm(inv, Tesseract);
            detalhes.value.push({ numero: inv.numero, ...r });
            if (r.decisao === 'aprovado') aprovados.value++;
            else if (r.decisao === 'pendente') pendentes.value++;
            else erros.value++;
            concluidos.value++;
        }
        processando.value = false;
    }

    return {
        processando, total, concluidos, aprovados, pendentes, erros, detalhes,
        processarLote,
    };
}
