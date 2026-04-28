// Captura prints v2 das telas que mudaram + novas features (DROVET, Dashboard Leite)
// Mantém PADRÃO já existente (1366×768 desktop, iPhone SE mobile, mesma URL/credenciais)
import { chromium, devices } from 'playwright';
import { mkdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';

const APP = 'https://app.fazendamacaybas.com.br';
const sleep = ms => new Promise(r => setTimeout(r, ms));

const DESKTOP_DIR = 'docs/manual-cliente/screenshots/desktop';
const MOBILE_DIR = 'docs/manual-cliente/screenshots/mobile';
for (const d of [DESKTOP_DIR, MOBILE_DIR]) {
    if (!existsSync(d)) await mkdir(d, { recursive: true });
}

const PRINTS = [
    // Sequência: arquivo · URL · ação opcional · espera extra
    { file: 'c14-dashboard-leiteiro.png',     url: '/admin/rebanho/controle-leiteiro' },
    { file: 'd01-animal-historico.png',       url: '/admin/rebanho/animais/78' },  // sobrescreve com tab leiteira
    { file: 'd07-animal-evolucao-leiteira.png', url: '/admin/rebanho/animais/78',
      after: async (page) => { await page.locator('button:has-text("Evolução leiteira")').click().catch(() => {}); await sleep(1500); } },
    { file: 'd08-animal-drovet-card.png',      url: '/admin/rebanho/animais/78' },  // card DROVET aparece
    { file: '02-hub.png',                      url: '/admin/inicio' },               // sobrescreve com layout novo (sidebar fixa)
    { file: 'c09-tarefas.png',                 url: '/admin/tarefas' },              // ações padronizadas horizontais
];

async function rodar(viewportName, ctxOpts, dir) {
    console.log(`\n══ ${viewportName} ══`);
    const browser = await chromium.launch({ headless: true });
    const ctx = await browser.newContext({ ...ctxOpts, locale: 'pt-BR' });
    const page = await ctx.newPage();

    await page.goto(`${APP}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[type=email]', 'joao.demo@fazendamacaybas.com.br');
    await page.fill('input[type=password]', 'DemoManual@2026');
    await Promise.all([page.waitForLoadState('networkidle').catch(()=>{}), page.click('button[type=submit]')]);
    await sleep(2500);

    for (const p of PRINTS) {
        try {
            await page.goto(`${APP}${p.url}`, { waitUntil: 'domcontentloaded' });
            await sleep(2000);
            if (p.after) await p.after(page);
            await page.screenshot({ path: `${dir}/${p.file}`, fullPage: false });
            console.log(`  ✓ ${p.file}`);
        } catch (err) {
            console.log(`  ✗ ${p.file}: ${String(err).slice(0, 60)}`);
        }
    }
    await ctx.close();
    await browser.close();
}

await rodar('Desktop 1366', { viewport: { width: 1366, height: 850 } }, DESKTOP_DIR);
await rodar('iPhone SE', { ...devices['iPhone SE'] }, MOBILE_DIR);
console.log('\n✓ Prints v2 capturados');
