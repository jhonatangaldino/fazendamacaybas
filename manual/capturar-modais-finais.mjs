// Captura wizards/modais que faltaram: pesagem (wizard), vacinação (evento-rebanho), confirm-delete
import { chromium } from 'playwright';
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const SS = 'manual/screenshots';
const BASE = 'https://app.fazendamacaybas.com.br';

const browser = await chromium.launch({ headless: true });

async function loginTenant(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await sleep(800);
    await page.waitForSelector('input[id=email]', { timeout: 10000 });
    await page.fill('input[id=email]', '<QA_TENANT_EMAIL>');
    await page.fill('input[id=password]', '<QA_PASSWORD>');
    await Promise.all([
        page.waitForLoadState('networkidle', { timeout: 25000 }).catch(()=>{}),
        page.click('button[type=submit]'),
    ]);
    await sleep(2500);
    if (! page.url().includes('/admin/')) throw new Error('login falhou');
}

async function switchSede(page) {
    await page.goto(`${BASE}/admin/inicio`, { waitUntil: 'networkidle' });
    await sleep(700);
    await page.evaluate(async () => {
        const csrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content');
        const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.split('=')[1];
        await fetch('/admin/fazenda/trocar', {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf,'X-XSRF-TOKEN':decodeURIComponent(xsrf)},
            body: JSON.stringify({ farm_id: 79 }),
        });
    });
    await sleep(800);
}

// === DESKTOP: wizard pesagem + vacinação + animal show + delete ===
console.log('=== DESKTOP ===');
{
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    await loginTenant(page);
    await switchSede(page);

    // Pesagem (wizard)
    await page.goto(`${BASE}/admin/fluxos/pesar-animal`, { waitUntil: 'networkidle' });
    await sleep(2500);
    await page.screenshot({ path: `${SS}/modais/modal-pesagem.png`, fullPage: false });
    console.log('  ✅ modais/modal-pesagem.png (wizard)');

    // Vacinação
    await page.goto(`${BASE}/admin/fluxos/evento-rebanho?tipo=vacinacao`, { waitUntil: 'networkidle' });
    await sleep(2500);
    await page.screenshot({ path: `${SS}/modais/modal-vacinacao.png`, fullPage: false });
    console.log('  ✅ modais/modal-vacinacao.png (wizard)');

    // Animal show + tentativa de delete na timeline
    await page.goto(`${BASE}/admin/rebanho/animais?species_id=4`, { waitUntil: 'networkidle' });
    await sleep(2500);
    const linksAnimal = await page.locator('a[href*="/admin/rebanho/animais/"]').all();
    let pAnimal = null;
    for (const a of linksAnimal) {
        const h = await a.getAttribute('href');
        if (h && /\/animais\/\d+/.test(h) && !h.includes('/novo')) {
            pAnimal = h.startsWith('http') ? new URL(h).pathname : h;
            break;
        }
    }
    if (pAnimal) {
        await page.goto(BASE + pAnimal, { waitUntil: 'networkidle' });
        await sleep(3500);
        // Atualiza screenshot do show timeline
        await page.screenshot({ path: `${SS}/modais/animal-show-timeline.png`, fullPage: true });
        console.log('  ✅ modais/animal-show-timeline.png (refresh)');

        // Tenta encontrar botão de delete em qualquer evento da timeline
        // Inspect: pode ser SVG button, emoji, title, aria
        const candidatos = [
            page.locator('button[title*="emover" i]'),
            page.locator('button[title*="pagar" i]'),
            page.locator('button[aria-label*="emover" i]'),
            page.locator('button[aria-label*="pagar" i]'),
            page.locator('button.text-red-500, button.text-red-600, button.text-rose-500, button.text-rose-600'),
        ];
        let achei = false;
        for (const cand of candidatos) {
            const count = await cand.count().catch(()=>0);
            if (count > 0) {
                try {
                    await cand.first().click({ timeout: 3000 });
                    await sleep(2000);
                    await page.screenshot({ path: `${SS}/modais/modal-confirm-delete.png`, fullPage: false });
                    console.log('  ✅ modais/modal-confirm-delete.png');
                    achei = true;
                    await page.keyboard.press('Escape');
                    break;
                } catch (e) {}
            }
        }
        if (! achei) console.log('  ⚠️ delete não capturado (mockup CSS no manual cobre)');
    }

    await ctx.close();
}

// === MOBILE: wizard pesagem ===
console.log('=== MOBILE ===');
{
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    await loginTenant(page);
    await switchSede(page);

    await page.goto(`${BASE}/admin/fluxos/pesar-animal`, { waitUntil: 'networkidle' });
    await sleep(2500);
    await page.screenshot({ path: `${SS}/mobile/modal-pesagem.png`, fullPage: false });
    console.log('  ✅ mobile/modal-pesagem.png (wizard)');

    await ctx.close();
}

await browser.close();
console.log('\n✅ Modais finais capturados.');
