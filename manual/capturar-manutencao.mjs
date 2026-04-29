// Recaptura wizard-manutencao usando URL correta (arrumar-maquina)
import { chromium } from 'playwright';
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const BASE = 'https://app.fazendamacaybas.com.br';
const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
const page = await ctx.newPage();

await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await sleep(800);
await page.waitForSelector('input[id=email]', { timeout: 10000 });
await page.fill('input[id=email]', '<QA_TENANT_EMAIL>');
await page.fill('input[id=password]', '<QA_PASSWORD>');
await Promise.all([page.waitForLoadState('networkidle', {timeout: 25000}).catch(()=>{}), page.click('button[type=submit]')]);
await sleep(2500);
if (! page.url().includes('/admin/')) throw new Error('login falhou');

// Switch Sede
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

// URL correta = arrumar-maquina (não "manutencao")
await page.goto(`${BASE}/admin/fluxos/arrumar-maquina`, { waitUntil: 'networkidle' });
await sleep(2500);
await page.screenshot({ path: 'manual/screenshots/wizards/wizard-manutencao.png', fullPage: false });
console.log('✅ wizard-manutencao.png recapturado (URL correta)');

await browser.close();
