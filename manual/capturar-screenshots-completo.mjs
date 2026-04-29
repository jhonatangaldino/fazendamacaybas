// Captura screenshots completos para o manual: forms, modais, wizards, espécies, master
import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const SS = 'manual/screenshots';
await fs.mkdir(`${SS}/desktop`, { recursive: true });
await fs.mkdir(`${SS}/mobile`, { recursive: true });
await fs.mkdir(`${SS}/master`, { recursive: true });
await fs.mkdir(`${SS}/modais`, { recursive: true });
await fs.mkdir(`${SS}/forms`, { recursive: true });
await fs.mkdir(`${SS}/especies`, { recursive: true });
await fs.mkdir(`${SS}/wizards`, { recursive: true });
await fs.mkdir(`${SS}/perfis`, { recursive: true });

const browser = await chromium.launch({ headless: true });

// =========== DESKTOP TENANT (dono) ===========
console.log('\n=== TENANT (dono) DESKTOP ===');
{
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    await page.goto('https://app.fazendamacaybas.com.br/login');
    await page.fill('input[id=email]', process.env.QA_TENANT_EMAIL ?? 'set-QA_TENANT_EMAIL-env');
    await page.fill('input[id=password]', process.env.QA_PASSWORD ?? 'set-QA_PASSWORD-env');
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});

    // FORMS de criação
    const forms = [
        { url: '/admin/rebanho/animais/novo?species_id=4', file: 'form-novo-animal-bovino.png' },
        { url: '/admin/rebanho/lotes/novo', file: 'form-novo-lote.png' },
        { url: '/admin/financeiro/transacoes/nova?tipo=despesa', file: 'form-nova-despesa.png' },
        { url: '/admin/financeiro/transacoes/nova?tipo=receita', file: 'form-nova-receita.png' },
        { url: '/admin/funcionarios/novo', file: 'form-novo-funcionario.png' },
        { url: '/admin/parceiros/novo', file: 'form-novo-parceiro.png' },
        { url: '/admin/tarefas/nova', file: 'form-nova-tarefa.png' },
    ];
    for (const f of forms) {
        await page.goto('https://app.fazendamacaybas.com.br' + f.url);
        await sleep(2000);
        await page.screenshot({ path: path.join(SS, 'forms', f.file), fullPage: false });
        console.log('  ✅ form:', f.file);
    }

    // ESPÉCIES dashboards (11 espécies)
    const especies = ['bovino', 'equino', 'suino', 'caprino', 'ovino', 'ave', 'peixe', 'cao', 'gato', 'bufalo', 'coelho'];
    for (const esp of especies) {
        await page.goto(`https://app.fazendamacaybas.com.br/admin/rebanho/especies/${esp}`);
        await sleep(2000);
        await page.screenshot({ path: path.join(SS, 'especies', `${esp}.png`), fullPage: false });
        console.log('  ✅ espécie:', esp);
    }

    // WIZARDS (rotas que existem)
    const wizards = [
        { url: '/admin/fluxos/venda-animal', file: 'wizard-venda-animal.png' },
        { url: '/admin/fluxos/aplicar-produto', file: 'wizard-aplicar-produto.png' },
        { url: '/admin/fluxos/registrar-plantio', file: 'wizard-registrar-plantio.png' },
        { url: '/admin/fluxos/saida-estoque', file: 'wizard-saida-estoque.png' },
        { url: '/admin/fluxos/manutencao', file: 'wizard-manutencao.png' },
    ];
    for (const w of wizards) {
        try {
            await page.goto('https://app.fazendamacaybas.com.br' + w.url);
            await sleep(2000);
            await page.screenshot({ path: path.join(SS, 'wizards', w.file), fullPage: false });
            console.log('  ✅ wizard:', w.file);
        } catch (e) {
            console.log('  ⚠️ wizard falhou:', w.file, e.message);
        }
    }

    // MODAL: pesagem aberto
    await page.goto('https://app.fazendamacaybas.com.br/admin/rebanho/especies/bovino');
    await sleep(2000);
    const pesar = page.locator('button, a').filter({ hasText: /^Pesar/ }).first();
    if (await pesar.count()) {
        await pesar.click();
        await sleep(1500);
        await page.screenshot({ path: path.join(SS, 'modais', 'modal-pesagem.png'), fullPage: false });
        console.log('  ✅ modal-pesagem.png');
        await page.keyboard.press('Escape');
        await sleep(500);
    }

    // MODAL: vacinação
    const vacinar = page.locator('button, a').filter({ hasText: /Vacinar/ }).first();
    if (await vacinar.count()) {
        await vacinar.click();
        await sleep(1500);
        await page.screenshot({ path: path.join(SS, 'modais', 'modal-vacinacao.png'), fullPage: false });
        console.log('  ✅ modal-vacinacao.png');
        await page.keyboard.press('Escape');
        await sleep(500);
    }

    // ANIMAL SHOW (timeline)
    await page.goto('https://app.fazendamacaybas.com.br/admin/rebanho/animais?species_id=4');
    await sleep(2000);
    const animalLink = await page.locator('a[href*="/admin/rebanho/animais/"]').first().getAttribute('href');
    if (animalLink) {
        let p2 = animalLink.startsWith('http') ? new URL(animalLink).pathname : animalLink;
        if (p2.endsWith('/novo')) {
            const links = await page.locator('a[href*="/admin/rebanho/animais/"]').all();
            for (const a of links) {
                const h = await a.getAttribute('href');
                if (h && /\/animais\/\d+/.test(h)) { p2 = h.startsWith('http') ? new URL(h).pathname : h; break; }
            }
        }
        await page.goto('https://app.fazendamacaybas.com.br' + p2);
        await sleep(2500);
        await page.screenshot({ path: path.join(SS, 'modais', 'animal-show-timeline.png'), fullPage: true });
        console.log('  ✅ animal-show-timeline.png (fullPage)');

        // Modal de confirmar delete
        const lixeira = page.locator('ol li button[title*="Remover"], ol li button[aria-label*="Remover"]').first();
        if (await lixeira.count()) {
            await lixeira.click();
            await sleep(1500);
            await page.screenshot({ path: path.join(SS, 'modais', 'modal-confirm-delete.png'), fullPage: false });
            console.log('  ✅ modal-confirm-delete.png');
            await page.keyboard.press('Escape');
            await sleep(500);
        }
    }

    // SIDEBAR mobile expandida (precisa viewport mobile)
    await ctx.close();
}

// =========== MOBILE TENANT (sidebar aberta) ===========
console.log('\n=== MOBILE sidebar aberta ===');
{
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    await page.goto('https://app.fazendamacaybas.com.br/login');
    await page.fill('input[id=email]', process.env.QA_TENANT_EMAIL ?? 'set-QA_TENANT_EMAIL-env');
    await page.fill('input[id=password]', process.env.QA_PASSWORD ?? 'set-QA_PASSWORD-env');
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});

    // Hub init com sidebar fechada (já temos)
    // Abrir sidebar mobile (hambúrguer)
    await page.goto('https://app.fazendamacaybas.com.br/admin/inicio');
    await sleep(2000);
    const hambBtn = page.locator('button[aria-label*="enu"], button[aria-label*="brir"]').first();
    if (await hambBtn.count()) {
        await hambBtn.click();
        await sleep(1000);
        await page.screenshot({ path: path.join(SS, 'mobile', 'sidebar-aberta.png'), fullPage: false });
        console.log('  ✅ sidebar-aberta.png');
    }

    // Forms mobile
    await page.goto('https://app.fazendamacaybas.com.br/admin/rebanho/animais/novo?species_id=4');
    await sleep(2000);
    await page.screenshot({ path: path.join(SS, 'mobile', 'form-novo-animal.png'), fullPage: false });
    console.log('  ✅ form-novo-animal mobile');

    // Modal pesagem mobile
    await page.goto('https://app.fazendamacaybas.com.br/admin/rebanho/especies/bovino');
    await sleep(2500);
    const pesarMob = page.locator('button, a').filter({ hasText: /^Pesar/ }).first();
    if (await pesarMob.count()) {
        await pesarMob.click();
        await sleep(1500);
        await page.screenshot({ path: path.join(SS, 'mobile', 'modal-pesagem.png'), fullPage: false });
        console.log('  ✅ modal-pesagem mobile');
    }

    await ctx.close();
}

// =========== MASTER ===========
console.log('\n=== MASTER ===');
{
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    await page.goto('https://fazendamacaybas.com.br/login');
    await page.fill('input[id=email]', process.env.QA_MASTER_EMAIL ?? 'set-QA_MASTER_EMAIL-env');
    await page.fill('input[id=password]', process.env.QA_PASSWORD ?? 'set-QA_PASSWORD-env');
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});

    const masterRotas = [
        { url: '/master/dashboard', file: 'master-dashboard.png' },
        { url: '/master/tenants', file: 'master-tenants.png' },
        { url: '/master/planos', file: 'master-planos.png' },
        { url: '/master/cobrancas', file: 'master-cobrancas.png' },
        { url: '/master/atividades', file: 'master-atividades.png' },
    ];
    for (const r of masterRotas) {
        await page.goto('https://fazendamacaybas.com.br' + r.url);
        await sleep(2000);
        await page.screenshot({ path: path.join(SS, 'master', r.file), fullPage: false });
        console.log('  ✅ master:', r.file);
    }

    await ctx.close();
}

await browser.close();
console.log('\n✅ Screenshots completos capturados em manual/screenshots/');
