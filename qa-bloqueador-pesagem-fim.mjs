// Teste completo do fluxo de pesagem: abrir modal → preencher peso → submeter → confirmar timeline

import { chromium } from 'playwright';
import { mkdirSync, rmSync } from 'fs';
import { join } from 'path';

const BASE = 'https://fazendamacaybas.com.br';
const CRED = { email: 'qa-dono@fazendamacaybas.local', password: 'QADono#2026' };
const OUT = join(process.cwd(), 'qa-pesagem-fim');
try { rmSync(OUT, { recursive: true }); } catch {}
mkdirSync(OUT, { recursive: true });

const errs = [];
const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await ctx.newPage();
page.on('pageerror', e => errs.push('PAGE: ' + e.message));
page.on('console', m => { if (m.type() === 'error') errs.push('CON: ' + m.text()); });

async function shot(n) { await page.screenshot({ path: join(OUT, n + '.png'), fullPage: true }); }
function log(s) { console.log(s); }

// LOGIN
await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', CRED.email);
await page.fill('input[type="password"]', CRED.password);
await page.click('button[type="submit"]');
await page.waitForURL(/admin/, { timeout: 15000 });
await page.waitForLoadState('networkidle');

// Abrir detalhe do animal 2
await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
await shot('01-detalhe-antes');

// CLICAR + Novo evento
log('▶ Clicando em "+ Novo evento"');
await page.locator('button:has-text("Novo evento")').first().click();
await page.waitForTimeout(1000);
await shot('02-modal-aberto');
log(`  Modal aberto? ${await page.locator('text=Novo evento — 32032, text=Tipo de evento').first().count() > 0}`);

// Verifica tipo default = Pesagem
const tipoSelect = page.locator('select').filter({ hasText: /Pesagem/ }).first();
const tipoSelectVal = await tipoSelect.evaluate(el => el.value).catch(() => '?');
log(`  Tipo default: ${tipoSelectVal}`);

// PREENCHE peso
log('▶ Preenchendo peso 450.5');
const pesoInput = page.locator('input[type="number"], input[inputmode="decimal"], input[inputmode="numeric"]').filter({ hasNotText: '' });
// Fallback: pega input do modal que não é data
const modalInputs = await page.locator('.fixed input, [role="dialog"] input').all();
log(`  Inputs no modal: ${modalInputs.length}`);

// Identifica qual é "Peso (kg) *"
for (let i = 0; i < modalInputs.length; i++) {
  const placeholder = await modalInputs[i].getAttribute('placeholder').catch(() => '');
  const value = await modalInputs[i].inputValue().catch(() => '');
  const step = await modalInputs[i].getAttribute('step').catch(() => '');
  const type = await modalInputs[i].getAttribute('type').catch(() => '');
  log(`    [${i}] type=${type} step=${step} placeholder="${placeholder}" value="${value}"`);
}

// Encontra input de peso (type=number com step)
const pesoField = page.locator('input[type="number"][step]').first();
if (await pesoField.count() > 0) {
  await pesoField.click();
  await pesoField.fill('450.5');
  log('  Peso preenchido');
} else {
  log('  ❌ Campo peso não encontrado');
}

await shot('03-preenchido');

// CLICAR "Registrar evento"
log('▶ Clicando "Registrar evento"');
await page.locator('button:has-text("Registrar evento")').first().click();

// Espera redirect/flash
await page.waitForTimeout(3000);
await page.waitForLoadState('networkidle').catch(() => null);
await shot('04-apos-registrar');

// Verifica estado
log(`  URL atual: ${page.url()}`);
const body = await page.textContent('body');
log(`  Modal ainda aberto? ${body.includes('Registrar evento')}`);
log(`  Pesagens na timeline: ${body.includes('450') ? 'encontrou 450' : 'NÃO ENCONTROU 450'}`);
log(`  "Total de pesagens" mudou? ${body.includes('TOTAL DE PESAGENS') ? 'presente' : 'ausente'}`);
log(`  Mensagem de sucesso visível? ${body.includes('registrada') || body.includes('sucesso') || body.includes('Pesagem')}`);

// Vai até timeline e conta
await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
await page.waitForTimeout(1000);
await shot('05-apos-refresh');

const bodyFim = await page.textContent('body');
log(`\n  PESO ATUAL: ${bodyFim.match(/PESO ATUAL\s*([\d.,\s]+kg)/i)?.[1] ?? '?'}`);
log(`  TOTAL DE PESAGENS: ${bodyFim.match(/TOTAL DE PESAGENS\s*(\d+)/i)?.[1] ?? '?'}`);
log(`  Tem "450" no body? ${bodyFim.includes('450')}`);

log(`\n═══ ERROS CAPTURADOS (${errs.length}) ═══`);
errs.slice(0, 15).forEach(e => log(`  ${e.substring(0, 200)}`));

await browser.close();
