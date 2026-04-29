// Captura apenas o que faltou: master + modais (pesagem, vacinação, delete)
import { chromium } from 'playwright';

const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const SS = 'manual/screenshots';

const browser = await chromium.launch({ headless: true });

// =============== MASTER ===============
// Master login no subdomínio APP (não no domínio raiz). É como o QA F2 que
// funcionou faz: bare domain `fazendamacaybas.com.br` tem resolução de tenant
// que conflita com login master; já em `app.fazendamacaybas.com.br/login` o
// master loga limpo e depois acessa /master/* sem problema.
console.log('\n=== MASTER ===');
{
    const BASE = 'https://app.fazendamacaybas.com.br';
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();

    console.log('  → goto login master (app.host)...');
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await sleep(800);
    await page.waitForSelector('input[id=email]', { timeout: 15000 });
    await page.fill('input[id=email]', process.env.QA_MASTER_EMAIL ?? 'set-QA_MASTER_EMAIL-env');
    await page.fill('input[id=password]', process.env.QA_PASSWORD ?? 'set-QA_PASSWORD-env');
    await Promise.all([
        page.waitForLoadState('networkidle', { timeout: 25000 }).catch(()=>{}),
        page.click('button[type=submit]'),
    ]);
    await sleep(2500);
    const url = page.url();
    console.log(`  → URL: ${url}`);
    if (! url.includes('/master/')) {
        throw new Error(`MASTER LOGIN FALHOU. URL: ${url}`);
    }
    console.log(`    ✅ login master OK`);

    const rotas = [
        { url: '/master/dashboard',  file: 'master/master-dashboard.png' },
        { url: '/master/tenants',    file: 'master/master-tenants.png' },
        { url: '/master/planos',     file: 'master/master-planos.png' },
        { url: '/master/cobrancas',  file: 'master/master-cobrancas.png' },
        { url: '/master/atividades', file: 'master/master-atividades.png' },
        { url: '/master/manuais',    file: 'master/master-manuais.png' },
    ];
    for (const r of rotas) {
        await page.goto(BASE + r.url, { waitUntil: 'networkidle' });
        await sleep(2200);
        await page.screenshot({ path: `${SS}/${r.file}`, fullPage: false });
        console.log(`  ✅ ${r.file}`);
    }
    await ctx.close();
}

// =============== MODAIS PESAGEM/VACINAÇÃO ===============
console.log('\n=== MODAIS (Pesar/Vacinar) ===');
{
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();

    // Login admin (tenant)
    await page.goto('https://app.fazendamacaybas.com.br/login', { waitUntil: 'networkidle' });
    await sleep(800);
    await page.waitForSelector('input[id=email]', { timeout: 10000 });
    await page.fill('input[id=email]', process.env.QA_TENANT_EMAIL ?? 'set-QA_TENANT_EMAIL-env');
    await page.fill('input[id=password]', process.env.QA_PASSWORD ?? 'set-QA_PASSWORD-env');
    await page.click('button[type=submit]');
    await sleep(3500);
    if (! page.url().includes('/admin/')) throw new Error('login tenant falhou');
    console.log('    ✅ login tenant');

    // Switch fazenda Sede
    await page.goto('https://app.fazendamacaybas.com.br/admin/inicio', { waitUntil: 'networkidle' });
    await sleep(700);
    await page.evaluate(async () => {
        const csrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content');
        const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.split('=')[1];
        await fetch('/admin/fazenda/trocar', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf,'X-XSRF-TOKEN':decodeURIComponent(xsrf)},
            body: JSON.stringify({ farm_id: 79 }),
        });
    });
    await sleep(800);

    // ============ MODAL PESAGEM ============
    // Tentativa 1: dashboard espécie bovino
    await page.goto('https://app.fazendamacaybas.com.br/admin/rebanho/especies/bovino', { waitUntil: 'networkidle' });
    await sleep(3000);

    // Inspeciona botões existentes pra debug
    const botoes = await page.locator('button, a').evaluateAll(els =>
        els.map(e => e.textContent?.trim()).filter(t => t && t.length < 30)
    );
    console.log('  botões disponíveis (primeiros 20):', botoes.slice(0, 20));

    // Várias tentativas de selector
    let pesarLoc = null;
    const candidatosPesar = [
        page.getByRole('button', { name: /pesar/i }).first(),
        page.getByRole('link', { name: /pesar/i }).first(),
        page.locator('a, button').filter({ hasText: /Pesar/i }).first(),
        page.locator('[href*="pesagem"]').first(),
    ];
    for (const c of candidatosPesar) {
        if (await c.count().catch(()=>0)) { pesarLoc = c; break; }
    }

    if (pesarLoc) {
        try {
            await pesarLoc.click({ timeout: 5000 });
            await sleep(2500);
            await page.screenshot({ path: `${SS}/modais/modal-pesagem.png`, fullPage: false });
            console.log('  ✅ modais/modal-pesagem.png');
            await page.keyboard.press('Escape');
            await sleep(800);
        } catch (e) {
            console.log('  ⚠️ click pesar:', e.message);
        }
    } else {
        console.log('  ⚠️ botão Pesar não localizado');
    }

    // Modal vacinação
    await page.goto('https://app.fazendamacaybas.com.br/admin/rebanho/especies/bovino', { waitUntil: 'networkidle' });
    await sleep(2500);
    let vacinarLoc = null;
    const candidatosVac = [
        page.getByRole('button', { name: /vacinar/i }).first(),
        page.getByRole('link', { name: /vacinar/i }).first(),
        page.locator('a, button').filter({ hasText: /Vacinar/i }).first(),
    ];
    for (const c of candidatosVac) {
        if (await c.count().catch(()=>0)) { vacinarLoc = c; break; }
    }
    if (vacinarLoc) {
        try {
            await vacinarLoc.click({ timeout: 5000 });
            await sleep(2500);
            await page.screenshot({ path: `${SS}/modais/modal-vacinacao.png`, fullPage: false });
            console.log('  ✅ modais/modal-vacinacao.png');
            await page.keyboard.press('Escape');
            await sleep(800);
        } catch (e) {
            console.log('  ⚠️ click vacinar:', e.message);
        }
    } else {
        console.log('  ⚠️ botão Vacinar não localizado');
    }

    // Modal confirm-delete (animal show timeline)
    await page.goto('https://app.fazendamacaybas.com.br/admin/rebanho/animais?species_id=4', { waitUntil: 'networkidle' });
    await sleep(2500);
    const linkAn = await page.locator('a[href*="/admin/rebanho/animais/"]').all();
    let pAnimal = null;
    for (const a of linkAn) {
        const h = await a.getAttribute('href');
        if (h && /\/animais\/\d+/.test(h)) { pAnimal = h; break; }
    }
    if (pAnimal) {
        const url = pAnimal.startsWith('http') ? new URL(pAnimal).pathname : pAnimal;
        await page.goto('https://app.fazendamacaybas.com.br' + url, { waitUntil: 'networkidle' });
        await sleep(3500);
        const lixeira = page.locator('button[title*="Remover"], button[title*="Apagar"], button[aria-label*="Remover"], button[aria-label*="Apagar"]').first();
        if (await lixeira.count()) {
            try {
                await lixeira.click({ timeout: 5000 });
                await sleep(2000);
                await page.screenshot({ path: `${SS}/modais/modal-confirm-delete.png`, fullPage: false });
                console.log('  ✅ modais/modal-confirm-delete.png');
                await page.keyboard.press('Escape');
            } catch (e) {
                console.log('  ⚠️ click lixeira:', e.message);
            }
        } else {
            console.log('  ⚠️ ícone lixeira não encontrado na timeline');
        }
    }

    // Mobile modal pesagem
    await ctx.close();

    const ctxM = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
    const pageM = await ctxM.newPage();
    await pageM.goto('https://app.fazendamacaybas.com.br/login', { waitUntil: 'networkidle' });
    await sleep(800);
    await pageM.waitForSelector('input[id=email]', { timeout: 10000 });
    await pageM.fill('input[id=email]', process.env.QA_TENANT_EMAIL ?? 'set-QA_TENANT_EMAIL-env');
    await pageM.fill('input[id=password]', process.env.QA_PASSWORD ?? 'set-QA_PASSWORD-env');
    await pageM.click('button[type=submit]');
    await sleep(3500);

    await pageM.goto('https://app.fazendamacaybas.com.br/admin/rebanho/especies/bovino', { waitUntil: 'networkidle' });
    await sleep(3000);
    let pesarMob = null;
    for (const c of [
        pageM.getByRole('button', { name: /pesar/i }).first(),
        pageM.getByRole('link', { name: /pesar/i }).first(),
        pageM.locator('a, button').filter({ hasText: /Pesar/i }).first(),
    ]) { if (await c.count().catch(()=>0)) { pesarMob = c; break; } }
    if (pesarMob) {
        try {
            await pesarMob.click({ timeout: 5000 });
            await sleep(2200);
            await pageM.screenshot({ path: `${SS}/mobile/modal-pesagem.png`, fullPage: false });
            console.log('  ✅ mobile/modal-pesagem.png');
        } catch (e) {
            console.log('  ⚠️ click pesar mobile:', e.message);
        }
    }
    await ctxM.close();
}

await browser.close();
console.log('\n✅ Pendentes capturados.');
