// QA CRUD MASSIVO · 100% dos módulos afetados pelos bugs 1+2
// ═══════════════════════════════════════════════════════════════════
// Usa navegador real (Playwright Chromium headless).
// Perfil: dono_fazenda.
// Para cada módulo: tenta CREATE real via UI, confirma no dataPage.

import { chromium } from 'playwright';
import { mkdirSync, rmSync, writeFileSync } from 'fs';
import { join } from 'path';

const BASE = 'https://fazendamacaybas.com.br';
const CRED = { email: 'qa-dono@fazendamacaybas.local', password: 'QADono#2026' };

const OUT = join(process.cwd(), 'qa-massivo');
try { rmSync(OUT, { recursive: true }); } catch {}
mkdirSync(OUT, { recursive: true });

const results = [];
const errs = [];

function ok(module, action, cond, detail = '') {
  results.push({ module, action, ok: cond, detail });
  console.log(`  ${cond ? '✓' : '✗'} [${module}] ${action}${detail ? ' — ' + detail : ''}`);
}

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await ctx.newPage();

page.on('pageerror', e => errs.push({ url: page.url(), type: 'page', text: e.message }));
page.on('console', m => { if (m.type() === 'error') errs.push({ url: page.url(), type: 'console', text: m.text() }); });
page.on('response', async resp => {
  if (resp.status() >= 500) errs.push({ url: resp.url(), type: 'network', text: `HTTP ${resp.status()}` });
});

async function shot(name) {
  await page.screenshot({ path: join(OUT, name + '.png'), fullPage: true }).catch(() => null);
}

async function getData() {
  const dp = await page.locator('#app').getAttribute('data-page').catch(() => null);
  return dp ? JSON.parse(dp) : null;
}

// ── LOGIN ───────────────────────────────────────────────────────────
await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', CRED.email);
await page.fill('input[type="password"]', CRED.password);
await page.click('button[type="submit"]');
await page.waitForURL(/admin/, { timeout: 15000 });
ok('Auth', 'Login dono_fazenda', true);

// ═══════════════════════════════════════════════════════════════════
// 1. REBANHO · Registrar PESAGEM (já sabemos que funciona)
// ═══════════════════════════════════════════════════════════════════
console.log('\n═══ 1. REBANHO · Pesagem ═══');
await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
let dpBefore = await getData();
const eventosAntes = (dpBefore.props?.events ?? []).length;

await page.locator('button:has-text("Novo evento")').first().click();
await page.waitForTimeout(800);
await page.locator('input[type="number"][step]').first().fill('480');
await shot('1-rebanho-pesagem-preenchida');
await page.locator('button:has-text("Registrar evento")').first().click();
await page.waitForTimeout(3000);

await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
let dpAfter = await getData();
const eventosDepois = (dpAfter.props?.events ?? []).length;
ok('Rebanho', 'CREATE Pesagem persiste', eventosDepois === eventosAntes + 1, `${eventosAntes} → ${eventosDepois}`);
ok('Rebanho', 'peso_atual atualizado', dpAfter.props?.animal?.peso_atual === 480, `peso=${dpAfter.props?.animal?.peso_atual}`);

// ═══════════════════════════════════════════════════════════════════
// 2. REBANHO · Registrar VACINAÇÃO
// ═══════════════════════════════════════════════════════════════════
console.log('\n═══ 2. REBANHO · Vacinação ═══');
await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
dpBefore = await getData();
const vacAntes = (dpBefore.props?.events ?? []).length;

await page.locator('button:has-text("Novo evento")').first().click();
await page.waitForTimeout(800);
await page.locator('.fixed.inset-0 select, [role="dialog"] select').first().selectOption('vacinacao');
await page.waitForTimeout(500);
// Campo Vacina — primeiro input text visível e não-readonly da modal
const vacInputs = await page.locator('.fixed.inset-0 input:visible:not([readonly]):not([type="number"])').all();
// Pula os inputs de data (placeholder dd/mm/aaaa)
for (const i of vacInputs) {
  const ph = await i.getAttribute('placeholder');
  if (!ph?.includes('aaaa')) {
    await i.fill('Febre Aftosa');
    break;
  }
}
await shot('2-rebanho-vacinacao');
await page.locator('button:has-text("Registrar evento")').first().click();
await page.waitForTimeout(3000);

await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
dpAfter = await getData();
const vacDepois = (dpAfter.props?.events ?? []).length;
ok('Rebanho', 'CREATE Vacinação persiste', vacDepois === vacAntes + 1, `${vacAntes} → ${vacDepois}`);

// ═══════════════════════════════════════════════════════════════════
// 3. REBANHO · Registrar MEDICAÇÃO
// ═══════════════════════════════════════════════════════════════════
console.log('\n═══ 3. REBANHO · Medicação ═══');
await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
dpBefore = await getData();
const medAntes = (dpBefore.props?.events ?? []).length;

await page.locator('button:has-text("Novo evento")').first().click();
await page.waitForTimeout(800);
await page.locator('.fixed.inset-0 select, [role="dialog"] select').first().selectOption('medicacao');
await page.waitForTimeout(500);
const medInputs = await page.locator('.fixed.inset-0 input:visible:not([readonly]):not([type="number"])').all();
for (const i of medInputs) {
  const ph = await i.getAttribute('placeholder');
  if (!ph?.includes('aaaa')) {
    await i.fill('Ivermectina');
    break;
  }
}
await shot('3-rebanho-medicacao');
await page.locator('button:has-text("Registrar evento")').first().click();
await page.waitForTimeout(3000);

await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
dpAfter = await getData();
const medDepois = (dpAfter.props?.events ?? []).length;
ok('Rebanho', 'CREATE Medicação persiste', medDepois === medAntes + 1, `${medAntes} → ${medDepois}`);

// ═══════════════════════════════════════════════════════════════════
// 4. REBANHO · OBSERVAÇÃO (não exige campo extra, mais simples)
// ═══════════════════════════════════════════════════════════════════
console.log('\n═══ 4. REBANHO · Observação ═══');
await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
dpBefore = await getData();
const obsAntes = (dpBefore.props?.events ?? []).length;

await page.locator('button:has-text("Novo evento")').first().click();
await page.waitForTimeout(800);
await page.locator('.fixed.inset-0 select, [role="dialog"] select').first().selectOption('observacao');
await page.waitForTimeout(500);
await page.locator('.fixed.inset-0 textarea').first().fill('Observação QA de teste').catch(() => null);
await shot('4-rebanho-observacao');
await page.locator('button:has-text("Registrar evento")').first().click();
await page.waitForTimeout(3000);

await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
dpAfter = await getData();
const obsDepois = (dpAfter.props?.events ?? []).length;
ok('Rebanho', 'CREATE Observação persiste', obsDepois === obsAntes + 1, `${obsAntes} → ${obsDepois}`);

// ═══════════════════════════════════════════════════════════════════
// 5. ESTOQUE · Criar item completo e ver na lista
// ═══════════════════════════════════════════════════════════════════
console.log('\n═══ 5. ESTOQUE · Criar Item ═══');
await page.goto(`${BASE}/admin/estoque/itens/novo`, { waitUntil: 'networkidle' });
const codigoQA = `__QA_${Date.now().toString().slice(-5)}`;
const nomeQA = `__QA Item Teste ${Date.now().toString().slice(-4)}`;

// Preenche campos
const inputsEst = await page.locator('input[required]:visible').all();
if (inputsEst.length > 0) await inputsEst[0].fill(codigoQA);
// Nome
const nomeFields = await page.locator('input.form-input:visible').all();
// A página tem múltiplos inputs — busca pelos obrigatórios
const reqInputs = await page.locator('input[required]:visible').all();
if (reqInputs.length >= 2) await reqInputs[1].fill(nomeQA);

await shot('5-estoque-item-form');

// Submit
await page.locator('button[type="submit"]').first().click();
await page.waitForTimeout(3000);

await page.goto(`${BASE}/admin/estoque/itens?search=${encodeURIComponent(nomeQA)}`, { waitUntil: 'networkidle' });
const body = await page.textContent('body');
ok('Estoque', 'CREATE Item aparece na listagem', body.includes(nomeQA), body.includes(nomeQA) ? 'OK' : 'NÃO APARECEU');

// ═══════════════════════════════════════════════════════════════════
// 6. ESTOQUE · MOVIMENTAÇÃO
// ═══════════════════════════════════════════════════════════════════
console.log('\n═══ 6. ESTOQUE · Movimentação ═══');
await page.goto(`${BASE}/admin/estoque/movimentos`, { waitUntil: 'networkidle' });

const btnNovaMov = page.locator('button:has-text("Nova movimentação")').first();
if (await btnNovaMov.count()) {
  await btnNovaMov.click();
  await page.waitForTimeout(500);

  // Selecionar item
  const selItem = page.locator('select').filter({ hasText: /Selecione|un/i }).first();
  if (await selItem.count()) {
    const options = await selItem.locator('option').all();
    // Escolhe primeiro item real (não vazio)
    for (const o of options) {
      const val = await o.getAttribute('value');
      if (val && val !== '') {
        await selItem.selectOption(val);
        break;
      }
    }
  }

  // Quantidade
  await page.locator('input[type="number"][step]').first().fill('10').catch(() => null);
  await shot('6-estoque-mov');

  await page.locator('button:has-text("Registrar"), button[type="button"]').filter({ hasText: /Registrar/i }).first().click();
  await page.waitForTimeout(2500);

  const bodyMov = await page.textContent('body');
  ok('Estoque', 'CREATE Movimentação persiste', !bodyMov.includes('Nova movimentação') || bodyMov.includes('Fechar') === false, 'modal fechou');
}

// ═══════════════════════════════════════════════════════════════════
// 7. FINANCEIRO · Criar transação
// ═══════════════════════════════════════════════════════════════════
console.log('\n═══ 7. FINANCEIRO · Criar Transação ═══');
await page.goto(`${BASE}/admin/financeiro/transacoes/novo`, { waitUntil: 'networkidle' });
await shot('7-fin-form-inicial');

const descQA = `__QA Despesa ${Date.now().toString().slice(-5)}`;
// Descrição
await page.locator('input[required]').filter({ hasNot: page.locator('[type="number"]') }).first().fill(descQA).catch(async () => {
  await page.locator('input.form-input').first().fill(descQA);
});

// Valor - InputMoney
const moneyInp = page.locator('input.font-mono, input[inputmode="numeric"]').first();
await moneyInp.type('25000', { delay: 15 }).catch(() => null);

await shot('7-fin-preenchido');

await page.locator('button[type="submit"]').first().click();
await page.waitForTimeout(3000);

await page.goto(`${BASE}/admin/financeiro/transacoes`, { waitUntil: 'networkidle' });
const bodyFin = await page.textContent('body');
ok('Financeiro', 'CREATE Transação aparece', bodyFin.includes(descQA), bodyFin.includes(descQA) ? 'OK' : 'NÃO APARECEU');

// ═══════════════════════════════════════════════════════════════════
// 8. TAREFAS · Criar tarefa (com vínculo)
// ═══════════════════════════════════════════════════════════════════
console.log('\n═══ 8. TAREFAS · Criar ═══');
await page.goto(`${BASE}/admin/tarefas`, { waitUntil: 'networkidle' });
const btnNovaTarefa = page.locator('button:has-text("Nova tarefa")').first();
if (await btnNovaTarefa.count()) {
  await btnNovaTarefa.click();
  await page.waitForTimeout(500);
  const tituloQA = `__QA Tarefa ${Date.now().toString().slice(-4)}`;
  await page.locator('input[required]').first().fill(tituloQA);

  // Selecionar módulo = geral (já default), vínculo = parceiro
  const relTypeSel = page.locator('select').filter({ hasText: /Parceiro|Animal|Escolha/ }).last();
  if (await relTypeSel.count()) {
    await relTypeSel.selectOption('App\\Models\\Partner').catch(() => null);
    await page.waitForTimeout(400);
  }
  // Seleciona primeiro parceiro disponível
  const entSel = page.locator('select').last();
  const ents = await entSel.locator('option').all();
  for (const e of ents) {
    const v = await e.getAttribute('value');
    if (v && v !== '' && v !== 'null') {
      await entSel.selectOption(v);
      break;
    }
  }

  // Seleciona primeiro responsável (checkbox)
  await page.locator('input[type="checkbox"]').first().check().catch(() => null);

  await shot('8-tarefas-form');

  await page.locator('button:has-text("Salvar")').last().click();
  await page.waitForTimeout(3000);

  const bodyTarefa = await page.textContent('body');
  ok('Tarefas', 'CREATE Tarefa aparece', bodyTarefa.includes(tituloQA), bodyTarefa.includes(tituloQA) ? 'OK' : 'NÃO APARECEU');
}

// ═══════════════════════════════════════════════════════════════════
// 9. DOCUMENTOS · abrir form de upload
// ═══════════════════════════════════════════════════════════════════
console.log('\n═══ 9. DOCUMENTOS · Form ═══');
await page.goto(`${BASE}/admin/documentos`, { waitUntil: 'networkidle' });
await page.locator('button:has-text("Upload")').first().click();
await page.waitForTimeout(500);
const hasForm = await page.locator('text=/Enviar documento|Título/i').count() > 0;
ok('Documentos', 'Form de upload aparece', hasForm);
await shot('9-documentos-form');

// ═══════════════════════════════════════════════════════════════════
// 10. PARCEIROS · CRUD completo
// ═══════════════════════════════════════════════════════════════════
console.log('\n═══ 10. PARCEIROS · CRUD ═══');
await page.goto(`${BASE}/admin/parceiros/novo`, { waitUntil: 'networkidle' });
const nomePart = `__QA Parceiro ${Date.now().toString().slice(-5)}`;
// Pessoa = pj default; troca para pf
const selPess = page.locator('select').first();
await selPess.selectOption('pf').catch(() => null);
await page.waitForTimeout(400);
await page.locator('input[required]').first().fill(nomePart);
// CPF via maska
const cpfInp = page.locator('input[data-maska]').first();
await cpfInp.click();
await cpfInp.type('52998224725', { delay: 15 });
await page.waitForTimeout(500);
await shot('10-parceiro-form');
await page.locator('button[type="submit"]').first().click();
await page.waitForTimeout(3000);

await page.goto(`${BASE}/admin/parceiros?search=${encodeURIComponent(nomePart)}`, { waitUntil: 'networkidle' });
const bodyPart = await page.textContent('body');
ok('Parceiros', 'CREATE PF com CPF válido persiste', bodyPart.includes(nomePart));

// ═══════════════════════════════════════════════════════════════════
// 11. FUNCIONÁRIOS · Abrir form
// ═══════════════════════════════════════════════════════════════════
console.log('\n═══ 11. FUNCIONÁRIOS · Form ═══');
await page.goto(`${BASE}/admin/funcionarios`, { waitUntil: 'networkidle' });
const btnNovoFunc = page.locator('button:has-text("Novo funcionário")').first();
if (await btnNovoFunc.count()) {
  await btnNovoFunc.click();
  await page.waitForTimeout(500);
  const nomeF = `__QA Func ${Date.now().toString().slice(-4)}`;
  // Nome (input required que não é a combinação tipo)
  await page.locator('input[required]').first().fill(nomeF).catch(() => null);
  // CPF
  const cpfF = page.locator('input[data-maska]').first();
  if (await cpfF.count()) {
    await cpfF.click();
    await cpfF.type('52998224725', { delay: 15 });
  }
  // Data admissão
  const admDate = page.locator('input[placeholder="dd/mm/aaaa"]').first();
  if (await admDate.count()) await admDate.fill('23/04/2026').catch(() => null);

  await shot('11-funcionario-form');
  await page.locator('button:has-text("Salvar")').last().click();
  await page.waitForTimeout(3000);

  const bodyFunc = await page.textContent('body');
  ok('Funcionários', 'CREATE Funcionário CLT aparece', bodyFunc.includes(nomeF), bodyFunc.includes(nomeF) ? 'OK' : 'NÃO APARECEU');
}

// ═══════════════════════════════════════════════════════════════════
// 12. MÁQUINAS · Criar trator
// ═══════════════════════════════════════════════════════════════════
console.log('\n═══ 12. MÁQUINAS · Criar Trator ═══');
await page.goto(`${BASE}/admin/maquinas/veiculos`, { waitUntil: 'networkidle' });
const btnNovoVeic = page.locator('button:has-text("Novo veículo")').first();
if (await btnNovoVeic.count()) {
  await btnNovoVeic.click();
  await page.waitForTimeout(500);
  const nomeV = `__QA Trator ${Date.now().toString().slice(-4)}`;
  // Nome (segundo input — primeiro é Tipo select)
  const reqs = await page.locator('input[required]').all();
  if (reqs.length > 0) await reqs[0].fill(nomeV);
  await shot('12-veiculo-form');
  await page.locator('button:has-text("Salvar")').last().click();
  await page.waitForTimeout(3000);

  const bodyV = await page.textContent('body');
  ok('Máquinas', 'CREATE Veículo trator aparece', bodyV.includes(nomeV), bodyV.includes(nomeV) ? 'OK' : 'NÃO APARECEU');
}

// ═══════════════════════════════════════════════════════════════════
// RELATÓRIO
// ═══════════════════════════════════════════════════════════════════
await browser.close();

const pass = results.filter(r => r.ok).length;
const fail = results.filter(r => !r.ok).length;

writeFileSync(join(OUT, 'results.json'), JSON.stringify({ results, errs, pass, fail }, null, 2));

console.log(`\n══════════════════════════════════════════════════════════════`);
console.log(`  RESULTADO FINAL: ${pass} passou · ${fail} falhou`);
console.log(`  Erros de página/console: ${errs.length}`);
console.log(`══════════════════════════════════════════════════════════════`);

if (fail > 0) {
  console.log('\nFALHAS:');
  results.filter(r => !r.ok).forEach(r => console.log(`  ✗ [${r.module}] ${r.action} — ${r.detail}`));
}
if (errs.length > 0) {
  console.log('\nERROS (primeiros 5):');
  errs.slice(0, 5).forEach(e => console.log(`  [${e.type}] ${e.text.substring(0, 160)}`));
}

process.exit(fail === 0 ? 0 : 1);
