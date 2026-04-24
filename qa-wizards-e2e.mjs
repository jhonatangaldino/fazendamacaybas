import { chromium } from 'playwright';
import { writeFileSync } from 'fs';
import { join } from 'path';

const BASE = 'https://fazendamacaybas.com.br';

async function login(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[type="email"]', 'qa-dono@fazendamacaybas.local');
    await page.fill('input[type="password"]', 'QADono#2026');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/admin/, { timeout: 20000 });
}

async function fromHub(page, cardNome) {
    await page.goto(`${BASE}/admin/inicio`, { waitUntil: 'networkidle' });
    await page.locator('a.hub-card', { hasText: cardNome }).first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(600);
}

const resultados = [];

async function rodarWizard(nomeCard, steps, viewport = 'desktop') {
    const opts = viewport === 'mobile'
        ? { viewport: { width: 375, height: 812 }, hasTouch: true, isMobile: true }
        : { viewport: { width: 1280, height: 900 } };

    const browser = await chromium.launch({ headless: true });
    const page = await (await browser.newContext(opts)).newPage();
    const inicio = Date.now();
    let status = 'FALHOU';
    let passosExecutados = 0;
    let evidencia = {};

    try {
        await login(page);
        await fromHub(page, nomeCard);
        evidencia = await steps(page);
        passosExecutados = evidencia.passosAtingidos ?? 0;

        // Tenta detectar a tela final "Pronto!" / sucesso
        const prontoVisivel = await page.locator('text=/Pronto|registrad[oa]|sucesso/i').count();
        status = prontoVisivel > 0 ? 'OK' : 'FALHOU';
    } catch (e) {
        evidencia.erro = e.message?.slice(0, 200) || String(e);
    }

    const slug = `${viewport}-${nomeCard.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;
    await page.screenshot({ path: join('qa-ux', 'wizards', `${slug}-final.png`), fullPage: false });

    resultados.push({ wizard: nomeCard, viewport, status, passos: passosExecutados, duracao_ms: Date.now() - inicio, ...evidencia });
    console.log(`${status.padEnd(7)} · [${viewport.padEnd(7)}] ${nomeCard.padEnd(36)} · ${JSON.stringify(evidencia).slice(0, 90)}`);

    await browser.close();
}

// =========================================================================
// PESAGEM
// =========================================================================
async function pesagem(page) {
    // Passo 1: escolher animal
    const animalCard = page.locator('button.rounded-xl', { hasText: 'QA-001' }).first();
    await animalCard.click();
    const continuar = page.locator('button', { hasText: /Continuar/ }).first();
    await continuar.click();
    await page.waitForTimeout(400);

    // Passo 2: peso + data
    const pesoInput = page.locator('input[type="number"]').first();
    await pesoInput.fill('455.7');
    await page.locator('button', { hasText: /Continuar/ }).first().click();
    await page.waitForTimeout(400);

    // Passo 3: confirmar
    await page.locator('button', { hasText: /Confirmar/ }).first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    const prontoMsg = await page.locator('text=/Peso registrado/i').count();
    const pesoNovoVisible = await page.locator('text=/455[,.]?7/').count();
    return { passosAtingidos: 4, prontoMsg, pesoNovoVisible };
}

// =========================================================================
// DESPESA
// =========================================================================
async function despesa(page) {
    // Passo 1
    await page.locator('input[type="text"]').first().fill('Combustível diesel — teste wizard');
    await page.locator('button', { hasText: /Continuar/ }).first().click();
    await page.waitForTimeout(400);

    // Passo 2: valor (InputMoney usa text normalmente)
    const valorInput = page.locator('input[inputmode="decimal"], input.font-mono, input').filter({ has: page.locator('[placeholder*="R$"]') }).first();
    // Fallback: procura o próximo input vazio grande
    const moneyInputs = page.locator('input.form-input');
    const total = await moneyInputs.count();
    // O valor monetário no wizard está na classe `text-2xl font-mono` → procura por essa classe
    const valorReal = page.locator('input.font-mono').first();
    await valorReal.fill('350,00').catch(() => {});
    if (!(await valorReal.inputValue()).trim()) {
        // fallback: primeiro input do passo
        await moneyInputs.nth(0).fill('350,00').catch(() => {});
    }

    // Clicar "Já paguei" (default mas reforça)
    const btnPago = page.locator('button', { hasText: /Já paguei/ }).first();
    if (await btnPago.count() > 0) await btnPago.click();

    await page.locator('button', { hasText: /Continuar/ }).first().click();
    await page.waitForTimeout(400);

    // Passo 3: confirmar
    await page.locator('button', { hasText: /Confirmar/ }).first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    const despesaRegistrada = await page.locator('text=/Despesa registrada/i').count();
    return { passosAtingidos: 4, despesaRegistrada };
}

// =========================================================================
// RECEITA
// =========================================================================
async function receita(page) {
    await page.locator('input[type="text"]').first().fill('Venda de leite — teste wizard');
    await page.locator('button', { hasText: /Continuar/ }).first().click();
    await page.waitForTimeout(400);

    const valorReal = page.locator('input.font-mono').first();
    await valorReal.fill('2.500,00').catch(() => {});
    const btnRecebi = page.locator('button', { hasText: /Já recebi/ }).first();
    if (await btnRecebi.count() > 0) await btnRecebi.click();

    await page.locator('button', { hasText: /Continuar/ }).first().click();
    await page.waitForTimeout(400);

    await page.locator('button', { hasText: /Confirmar/ }).first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    const receitaRegistrada = await page.locator('text=/Receita registrada/i').count();
    return { passosAtingidos: 4, receitaRegistrada };
}

// =========================================================================
// APLICACAO (adubo)
// =========================================================================
async function aplicacao(page) {
    // Passo 1: finalidade — clica "Adubar"
    const btnAdubar = page.locator('button', { hasText: /Adubar/ }).first();
    await btnAdubar.click();
    await page.locator('button', { hasText: /Continuar/ }).first().click();
    await page.waitForTimeout(400);

    // Passo 2: onde e o quê
    // Selecionar talhão
    const selTalhao = page.locator('select').first();
    await selTalhao.selectOption({ index: 1 });

    // Produto (input de texto)
    const inputProduto = page.locator('input[list="produtos-estoque"]').first();
    await inputProduto.fill('Ureia 45% QA');

    // Quantidade
    const inputQtd = page.locator('input[type="number"]').first();
    await inputQtd.fill('200');

    await page.locator('button', { hasText: /Continuar/ }).first().click();
    await page.waitForTimeout(400);

    // Passo 3: confirmar
    await page.locator('button', { hasText: /Confirmar/ }).first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    const aplicReg = await page.locator('text=/Aplicação registrada/i').count();
    return { passosAtingidos: 4, aplicReg };
}

// =========================================================================
// MANUTENCAO
// =========================================================================
async function manutencao(page) {
    // Passo 1: escolher veículo
    const vCard = page.locator('button.rounded-xl', { hasText: 'Trator QA' }).first();
    await vCard.click();
    await page.locator('button', { hasText: /Continuar/ }).first().click();
    await page.waitForTimeout(400);

    // Passo 2: tipo + descrição
    // Tipo: "Conserto" já pré-selecionado
    const descTextarea = page.locator('textarea').first();
    await descTextarea.fill('Troca de óleo do motor — teste wizard');

    await page.locator('button', { hasText: /Continuar/ }).first().click();
    await page.waitForTimeout(400);

    // Passo 3: confirmar
    await page.locator('button', { hasText: /Confirmar/ }).first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    const manReg = await page.locator('text=/Manutenção registrada/i').count();
    return { passosAtingidos: 4, manReg };
}

// =========================================================================
// EXECUCAO
// =========================================================================
for (const [card, fn] of [
    ['Registrar peso do animal', pesagem],
    ['Registrar despesa', despesa],
    ['Receber pagamento', receita],
    ['Aplicar adubo na plantação', aplicacao],
    ['Arrumar máquina', manutencao],
]) {
    await rodarWizard(card, fn, 'desktop');
}

// Mobile — 2 wizards chave
for (const [card, fn] of [
    ['Registrar peso do animal', pesagem],
    ['Registrar despesa', despesa],
]) {
    await rodarWizard(card, fn, 'mobile');
}

writeFileSync(join('qa-ux', 'wizards', 'matriz.json'), JSON.stringify(resultados, null, 2));
const ok = resultados.filter(r => r.status === 'OK').length;
console.log(`\n=== E2E WIZARDS: ${ok}/${resultados.length} OK ===`);
