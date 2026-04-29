// Captura forms via rotas WIZARD (não /novo) — as rotas /novo redirecionam
// pra "Tela não disponível direto" (405) porque só funcionam como modal.
import { chromium } from 'playwright';
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const SS = 'manual/screenshots';
const BASE = 'https://app.fazendamacaybas.com.br';

const browser = await chromium.launch({ headless: true });

async function loginTenant(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await sleep(800);
    await page.waitForSelector('input[id=email]', { timeout: 10000 });
    await page.fill('input[id=email]', process.env.QA_TENANT_EMAIL ?? 'set-QA_TENANT_EMAIL-env');
    await page.fill('input[id=password]', process.env.QA_PASSWORD ?? 'set-QA_PASSWORD-env');
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

console.log('=== DESKTOP — forms via wizards ===');
{
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    await loginTenant(page);
    await switchSede(page);

    // Quais URLs funcionam direto:
    // - /admin/fluxos/registrar-despesa  (wizard, GET livre)
    // - /admin/fluxos/registrar-receita  (wizard)
    // - /admin/fluxos/criar-tarefa       (wizard)
    // - /admin/fluxos/cadastrar-funcionario (wizard)
    // - /admin/fluxos/cadastrar-animal?modo=cadastro (wizard, escolhe espécie)
    const formsCorretos = [
        { url: '/admin/fluxos/registrar-despesa',                   file: 'forms/form-nova-despesa.png' },
        { url: '/admin/fluxos/registrar-receita',                   file: 'forms/form-nova-receita.png' },
        { url: '/admin/fluxos/criar-tarefa',                        file: 'forms/form-nova-tarefa.png' },
        { url: '/admin/fluxos/cadastrar-funcionario',               file: 'forms/form-novo-funcionario.png' },
        { url: '/admin/fluxos/cadastrar-animal?modo=cadastro',      file: 'forms/form-novo-animal-bovino.png' },
    ];

    for (const f of formsCorretos) {
        await page.goto(BASE + f.url, { waitUntil: 'networkidle' });
        await sleep(2500);
        await page.screenshot({ path: `${SS}/${f.file}`, fullPage: false });
        console.log(`  ✅ ${f.file}`);
    }
    await ctx.close();
}

console.log('\n=== MOBILE — form animal ===');
{
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    await loginTenant(page);
    await switchSede(page);

    await page.goto(`${BASE}/admin/fluxos/cadastrar-animal?modo=cadastro`, { waitUntil: 'networkidle' });
    await sleep(2500);
    await page.screenshot({ path: `${SS}/mobile/form-novo-animal.png`, fullPage: false });
    console.log('  ✅ mobile/form-novo-animal.png');
    await ctx.close();
}

await browser.close();
console.log('\n✅ Forms corretos capturados.');
