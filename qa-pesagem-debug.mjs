// DEBUG PROFUNDO: capturar request/response do submit de pesagem
import { chromium } from 'playwright';
import { mkdirSync, rmSync, writeFileSync } from 'fs';
import { join } from 'path';

const BASE = 'https://fazendamacaybas.com.br';
const CRED = { email: 'qa-dono@fazendamacaybas.local', password: 'QADono#2026' };
const OUT = join(process.cwd(), 'qa-debug');
try { rmSync(OUT, { recursive: true }); } catch {}
mkdirSync(OUT, { recursive: true });

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await ctx.newPage();

const requests = [];
const errs = [];

page.on('request', req => {
  if (req.url().includes('/eventos') || req.url().includes('/animais/2/eventos')) {
    requests.push({ type: 'req', method: req.method(), url: req.url(), postData: req.postData(), headers: req.headers() });
  }
});
page.on('response', async resp => {
  if (resp.url().includes('/animais/') || resp.url().includes('/eventos') || resp.url().includes('/login')) {
    let body = '';
    try { body = await resp.text(); } catch {}
    const h = await resp.headersArray();
    requests.push({
      type: 'resp',
      status: resp.status(),
      url: resp.url(),
      location: h.find(x => x.name.toLowerCase() === 'location')?.value,
      inertia: h.find(x => x.name.toLowerCase() === 'x-inertia')?.value,
      body: body.substring(0, 2000),
    });
  }
});
page.on('pageerror', e => errs.push('PAGE: ' + e.message));
page.on('console', m => { if (m.type() === 'error') errs.push('CON: ' + m.text()); });

// LOGIN
await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', CRED.email);
await page.fill('input[type="password"]', CRED.password);
await page.click('button[type="submit"]');
await page.waitForURL(/admin/, { timeout: 15000 });

// DETALHE
await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });

// CLICAR novo evento
await page.locator('button:has-text("Novo evento")').first().click();
await page.waitForTimeout(800);

// Peso
await page.locator('input[type="number"][step]').first().fill('450.5');
await page.waitForTimeout(300);

// Submit
await page.locator('button:has-text("Registrar evento")').first().click();
await page.waitForTimeout(3000);

await page.screenshot({ path: join(OUT, 'final.png'), fullPage: true });

// Inspecionar: é que o 'data' está vindo vazio? Ver dados do form
const formData = await page.evaluate(() => {
  const dp = document.querySelector('#app')?.getAttribute('data-page');
  return dp ? JSON.parse(dp).props : null;
});

console.log('\n═══ REQUESTS/RESPONSES ═══');
for (const r of requests) {
  if (r.type === 'req') {
    console.log(`\n→ ${r.method} ${r.url}`);
    console.log(`  POST: ${r.postData?.substring(0, 500) ?? '(empty)'}`);
    console.log(`  X-Inertia: ${r.headers['x-inertia'] ?? 'no'}`);
  } else {
    console.log(`← ${r.status} ${r.url}`);
    console.log(`  Body (first 400): ${r.body.substring(0, 400)}`);
  }
}

console.log('\n═══ ERROS ═══');
errs.forEach(e => console.log('  ' + e.substring(0, 300)));

writeFileSync(join(OUT, 'requests.json'), JSON.stringify(requests, null, 2));
writeFileSync(join(OUT, 'errors.json'), JSON.stringify(errs, null, 2));

await browser.close();
