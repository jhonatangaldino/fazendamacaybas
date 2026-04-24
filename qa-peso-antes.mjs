import { chromium } from 'playwright';
const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await ctx.newPage();
await page.goto('https://fazendamacaybas.com.br/login', { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', 'qa-dono@fazendamacaybas.local');
await page.fill('input[type="password"]', 'QADono#2026');
await page.click('button[type="submit"]');
await page.waitForURL(/admin/);
await page.goto('https://fazendamacaybas.com.br/admin/rebanho/animais/2', { waitUntil: 'networkidle' });
await page.waitForTimeout(500);

// Captura full-page screenshot
await page.screenshot({ path: 'qa-peso-antes.png', fullPage: true });

// Extrai os valores visíveis de ganho / peso
const body = await page.textContent('body');
const ganhoMatch = body.match(/GANHO TOTAL[\s\n]+([^\n]+)/);
const pesoMatch = body.match(/PESO ATUAL[\s\n]+([^\n]+)/);
const gmdMatch = body.match(/GMD[\s\n]+([^\n]+)/);

console.log('PESO ATUAL:', pesoMatch?.[1]?.trim() ?? '?');
console.log('GANHO TOTAL:', ganhoMatch?.[1]?.trim() ?? '?');
console.log('GMD:', gmdMatch?.[1]?.trim() ?? '?');

// Pegar pesagens do payload
const dp = JSON.parse(await page.locator('#app').getAttribute('data-page'));
console.log('\nPesagens no payload (ordem):');
(dp.props?.pesagens ?? []).forEach(p => console.log(`  ${p.data} → ${p.peso}kg`));

await browser.close();
