// Captura wizards de eventos do rebanho que faltam no manual:
// mortalidade, movimentação, exame de toque, controle leiteiro, registrar parto
import { chromium } from 'playwright';
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const SS = 'manual/screenshots';
const BASE = 'https://app.fazendamacaybas.com.br';

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
const page = await ctx.newPage();

// Login + Sede
await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await sleep(800);
await page.waitForSelector('input[id=email]', { timeout: 10000 });
await page.fill('input[id=email]', process.env.QA_TENANT_EMAIL ?? 'set-QA_TENANT_EMAIL-env');
await page.fill('input[id=password]', process.env.QA_PASSWORD ?? 'set-QA_PASSWORD-env');
await Promise.all([page.waitForLoadState('networkidle', {timeout:25000}).catch(()=>{}), page.click('button[type=submit]')]);
await sleep(2500);
if (! page.url().includes('/admin/')) throw new Error('login falhou');
console.log('  ✅ login');

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

// Wizards de eventos
const wizards = [
    { url: '/admin/fluxos/evento-rebanho?tipo=mortalidade',         file: 'wizards/wizard-mortalidade.png' },
    { url: '/admin/fluxos/evento-rebanho?tipo=movimentacao',        file: 'wizards/wizard-mover-lote.png' },
    { url: '/admin/fluxos/evento-rebanho?tipo=movimentacao_local',  file: 'wizards/wizard-mover-local.png' },
    { url: '/admin/fluxos/evento-rebanho?tipo=medicacao',           file: 'wizards/wizard-medicar.png' },
    { url: '/admin/fluxos/evento-rebanho?tipo=vermifugacao',        file: 'wizards/wizard-vermifugar.png' },
    { url: '/admin/fluxos/exame-toque',                             file: 'wizards/wizard-exame-toque.png' },
    { url: '/admin/fluxos/controle-leiteiro',                       file: 'wizards/wizard-controle-leiteiro.png' },
    { url: '/admin/fluxos/secar-vaca',                              file: 'wizards/wizard-secar-vaca.png' },
];
for (const w of wizards) {
    try {
        await page.goto(BASE + w.url, { waitUntil: 'networkidle' });
        await sleep(2500);
        await page.screenshot({ path: `${SS}/${w.file}`, fullPage: false });
        console.log(`  ✅ ${w.file}`);
    } catch (e) {
        console.log(`  ⚠️ ${w.file}: ${e.message}`);
    }
}

// Tentar registrar-parto (pode falhar se exigir tarefa origem)
try {
    await page.goto(`${BASE}/admin/fluxos/registrar-parto`, { waitUntil: 'networkidle' });
    await sleep(2500);
    await page.screenshot({ path: `${SS}/wizards/wizard-parto.png`, fullPage: false });
    console.log('  ✅ wizards/wizard-parto.png');
} catch (e) {
    console.log('  ⚠️ wizard-parto:', e.message);
}

await browser.close();
console.log('\n✅ Eventos faltantes capturados.');
