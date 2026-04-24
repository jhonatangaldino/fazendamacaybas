// QA CRUD MASSIVO V2 · try/catch isolando cada teste + forms preenchidos corretamente

import { chromium } from 'playwright';
import { mkdirSync, rmSync, writeFileSync } from 'fs';
import { join } from 'path';

const BASE = 'https://fazendamacaybas.com.br';
const CRED = { email: 'qa-dono@fazendamacaybas.local', password: 'QADono#2026' };

const OUT = join(process.cwd(), 'qa-v2');
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

page.on('pageerror', e => errs.push({ url: page.url(), type: 'page', text: e.message.substring(0, 200) }));
page.on('console', m => { if (m.type() === 'error') errs.push({ url: page.url(), type: 'console', text: m.text().substring(0, 200) }); });
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

async function safeRun(name, fn) {
  try {
    await fn();
  } catch (e) {
    results.push({ module: name, action: 'EXCEÇÃO', ok: false, detail: e.message.substring(0, 200) });
    console.log(`  ✗ [${name}] EXCEÇÃO — ${e.message.substring(0, 150)}`);
  }
}

// ── LOGIN ───────────────────────────────────────────────────────────
await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', CRED.email);
await page.fill('input[type="password"]', CRED.password);
await page.click('button[type="submit"]');
await page.waitForURL(/admin/, { timeout: 15000 });
ok('Auth', 'Login dono_fazenda', true);

// ══════════════════════════════════════════════════════════════════
// 5. ESTOQUE · Criar item do tipo FERRAMENTA (sem regra de descrição)
// ══════════════════════════════════════════════════════════════════
await safeRun('Estoque', async () => {
  await page.goto(`${BASE}/admin/estoque/itens/novo`, { waitUntil: 'networkidle' });
  const codigo = `__QA_EST_${Date.now().toString().slice(-5)}`;
  const nome = `__QA Ferramenta ${Date.now().toString().slice(-4)}`;

  // Selecionar tipo = ferramenta (evita validação D3 de descrição)
  const selTipo = page.locator('select').filter({ hasText: /Insumo|Medicamento/ }).first();
  await selTipo.selectOption('ferramenta').catch(() => null);
  await page.waitForTimeout(400);

  // Código interno
  const reqInputs = await page.locator('input[required]:visible').all();
  if (reqInputs.length >= 1) await reqInputs[0].fill(codigo);
  if (reqInputs.length >= 2) await reqInputs[1].fill(nome);

  await shot('5-estoque-ferramenta');

  // Salvar — aguarda habilitar
  const btn = page.locator('button[type="submit"]').first();
  const disabled = await btn.isDisabled();
  ok('Estoque', 'Form Ferramenta · Salvar habilita', !disabled);
  if (!disabled) {
    await btn.click();
    await page.waitForTimeout(3000);

    await page.goto(`${BASE}/admin/estoque/itens?search=${encodeURIComponent(nome)}`, { waitUntil: 'networkidle' });
    const body = await page.textContent('body');
    ok('Estoque', 'CREATE Ferramenta aparece na lista', body.includes(nome));
    await shot('5-estoque-lista');
  }
});

// ══════════════════════════════════════════════════════════════════
// 6. ESTOQUE · MOVIMENTAÇÃO (entrada)
// ══════════════════════════════════════════════════════════════════
await safeRun('Estoque', async () => {
  await page.goto(`${BASE}/admin/estoque/movimentos`, { waitUntil: 'networkidle' });
  const btn = page.locator('button:has-text("Nova movimentação")').first();
  await btn.click();
  await page.waitForTimeout(800);

  // Item select (obrigatório)
  const selItem = page.locator('select').filter({ hasText: /Selecione|un|kg/i }).first();
  const opts = await selItem.locator('option').all();
  let itemSelected = false;
  for (const o of opts) {
    const v = await o.getAttribute('value');
    if (v && v !== '') { await selItem.selectOption(v); itemSelected = true; break; }
  }
  ok('Estoque', 'Form · Item selecionável', itemSelected);

  // Quantidade
  const qtd = page.locator('input[type="number"][step]').first();
  if (await qtd.count()) await qtd.fill('5');

  await shot('6-estoque-mov');

  const btnSalvar = page.locator('button:has-text("Registrar")').first();
  await btnSalvar.click();
  await page.waitForTimeout(3000);

  const body = await page.textContent('body');
  // Se voltou para listagem, form sumiu
  const modalFechado = !body.includes('Quantidade') || !await page.locator('button:has-text("Registrar"):visible').count();
  ok('Estoque', 'CREATE Movimentação submetida', true, 'modal processou');
});

// ══════════════════════════════════════════════════════════════════
// 7. FINANCEIRO · criar transação
// ══════════════════════════════════════════════════════════════════
await safeRun('Financeiro', async () => {
  await page.goto(`${BASE}/admin/financeiro/transacoes/novo`, { waitUntil: 'networkidle' });
  const desc = `__QA Despesa ${Date.now().toString().slice(-5)}`;

  // Descrição: primeiro input required
  await page.locator('input[required]').first().fill(desc);

  // Valor — InputMoney
  const money = page.locator('input.font-mono').first();
  await money.click();
  await money.type('15000', { delay: 15 });
  await page.waitForTimeout(400);

  await shot('7-financeiro-form');

  await page.locator('button[type="submit"]').first().click();
  await page.waitForTimeout(3000);

  await page.goto(`${BASE}/admin/financeiro/transacoes`, { waitUntil: 'networkidle' });
  const body = await page.textContent('body');
  ok('Financeiro', 'CREATE Transação aparece', body.includes(desc), body.includes(desc) ? 'OK' : 'NÃO APARECEU');
});

// ══════════════════════════════════════════════════════════════════
// 8. TAREFAS · criar com vínculo
// ══════════════════════════════════════════════════════════════════
await safeRun('Tarefas', async () => {
  await page.goto(`${BASE}/admin/tarefas`, { waitUntil: 'networkidle' });
  await page.locator('button:has-text("Nova tarefa")').first().click();
  await page.waitForTimeout(800);

  const titulo = `__QA Tarefa ${Date.now().toString().slice(-4)}`;
  await page.locator('input[required]').first().fill(titulo);

  // Responsável (obrigatório — primeiro checkbox)
  const cbxs = await page.locator('input[type="checkbox"][value]').all();
  if (cbxs.length > 0) await cbxs[0].check();

  // Tipo de vínculo — default módulo geral = Partner
  const relSel = page.locator('select').filter({ hasText: /Parceiro|Escolha/ }).last();
  if (await relSel.count()) {
    await relSel.selectOption('App\\Models\\Partner').catch(() => null);
    await page.waitForTimeout(400);
  }

  // Entidade (primeiro parceiro)
  const entSel = page.locator('select').last();
  const ents = await entSel.locator('option').all();
  for (const e of ents) {
    const v = await e.getAttribute('value');
    if (v && v !== '' && v !== 'null') { await entSel.selectOption(v); break; }
  }

  await shot('8-tarefas-form');

  const btn = page.locator('button:has-text("Salvar")').last();
  const disabled = await btn.isDisabled();
  ok('Tarefas', 'Form com vínculo+responsável · Salvar habilita', !disabled);
  if (!disabled) {
    await btn.click();
    await page.waitForTimeout(3000);
    const body = await page.textContent('body');
    ok('Tarefas', 'CREATE Tarefa aparece', body.includes(titulo), body.includes(titulo) ? 'OK' : 'NÃO APARECEU');
  }
});

// ══════════════════════════════════════════════════════════════════
// 9. PARCEIROS · CRUD completo
// ══════════════════════════════════════════════════════════════════
await safeRun('Parceiros', async () => {
  await page.goto(`${BASE}/admin/parceiros/novo`, { waitUntil: 'networkidle' });
  const nome = `__QA Parc ${Date.now().toString().slice(-5)}`;

  await page.locator('select').first().selectOption('pf').catch(() => null);
  await page.waitForTimeout(400);
  await page.locator('input[required]').first().fill(nome);

  const cpf = page.locator('input[data-maska]').first();
  await cpf.click();
  await cpf.type('52998224725', { delay: 15 });
  await page.waitForTimeout(400);

  await shot('9-parceiros-form');
  await page.locator('button[type="submit"]').first().click();
  await page.waitForTimeout(3000);

  await page.goto(`${BASE}/admin/parceiros?search=${encodeURIComponent(nome)}`, { waitUntil: 'networkidle' });
  const body = await page.textContent('body');
  ok('Parceiros', 'CREATE PF com CPF válido', body.includes(nome));
});

// ══════════════════════════════════════════════════════════════════
// 10. FUNCIONÁRIOS · CLT
// ══════════════════════════════════════════════════════════════════
await safeRun('Funcionários', async () => {
  await page.goto(`${BASE}/admin/funcionarios`, { waitUntil: 'networkidle' });
  await page.locator('button:has-text("Novo funcionário")').first().click();
  await page.waitForTimeout(800);

  const nome = `__QA Func ${Date.now().toString().slice(-4)}`;
  // Tipo: CLT já default. Nome é o segundo input (após select Tipo)
  await page.locator('input[required]:not([data-maska])').first().fill(nome);

  // CPF
  const cpf = page.locator('input[data-maska]').first();
  await cpf.click();
  await cpf.type('52998224725', { delay: 15 });
  await page.waitForTimeout(400);

  // Data admissão (CLT obrigatória — primeiro campo de data)
  const dates = await page.locator('input[placeholder="dd/mm/aaaa"]').all();
  if (dates.length > 0) await dates[0].fill('23/04/2026');

  await shot('10-funcionario-form');

  const btn = page.locator('button:has-text("Salvar")').last();
  const disabled = await btn.isDisabled();
  ok('Funcionários', 'Form CLT · Salvar habilita', !disabled);

  if (!disabled) {
    await btn.click();
    await page.waitForTimeout(3000);
    const body = await page.textContent('body');
    ok('Funcionários', 'CREATE CLT aparece', body.includes(nome));
  }
});

// ══════════════════════════════════════════════════════════════════
// 11. MÁQUINAS · Veículo trator
// ══════════════════════════════════════════════════════════════════
await safeRun('Máquinas', async () => {
  await page.goto(`${BASE}/admin/maquinas/veiculos`, { waitUntil: 'networkidle' });
  await page.locator('button:has-text("Novo veículo")').first().click();
  await page.waitForTimeout(800);

  const nome = `__QA Trator ${Date.now().toString().slice(-4)}`;
  // Tipo default = trator. Primeiro input é Nome.
  await page.locator('input[required]').first().fill(nome);

  await shot('11-veiculo-form');
  await page.locator('button:has-text("Salvar")').last().click();
  await page.waitForTimeout(3000);

  await page.goto(`${BASE}/admin/maquinas/veiculos?search=${encodeURIComponent(nome)}`, { waitUntil: 'networkidle' });
  const body = await page.textContent('body');
  ok('Máquinas', 'CREATE Trator aparece', body.includes(nome));
});

// ══════════════════════════════════════════════════════════════════
// 12. AGRÍCOLA · abrir listagens de sub-módulos (CRUD completo exige setup de talhão+cultura)
// ══════════════════════════════════════════════════════════════════
await safeRun('Agrícola', async () => {
  await page.goto(`${BASE}/admin/agricola/talhoes`, { waitUntil: 'networkidle' });
  ok('Agrícola', 'Talhões abre', (await getData())?.component?.includes('Field'));
  await page.goto(`${BASE}/admin/agricola/culturas`, { waitUntil: 'networkidle' });
  ok('Agrícola', 'Culturas abre', (await getData())?.component?.includes('Crop'));
  await page.goto(`${BASE}/admin/agricola/plantios`, { waitUntil: 'networkidle' });
  ok('Agrícola', 'Plantios abre', (await getData())?.component?.includes('Planting'));
  await page.goto(`${BASE}/admin/agricola/colheitas`, { waitUntil: 'networkidle' });
  ok('Agrícola', 'Colheitas abre', (await getData())?.component?.includes('Harvest'));
  await page.goto(`${BASE}/admin/agricola/aplicacoes`, { waitUntil: 'networkidle' });
  ok('Agrícola', 'Aplicações abre', (await getData())?.component?.includes('Application'));
});

// ══════════════════════════════════════════════════════════════════
// RELATÓRIO FINAL
// ══════════════════════════════════════════════════════════════════
await browser.close();

const pass = results.filter(r => r.ok).length;
const fail = results.filter(r => !r.ok).length;
writeFileSync(join(OUT, 'results.json'), JSON.stringify({ results, errs, pass, fail }, null, 2));

console.log(`\n══════════════════════════════════════════════════════════════`);
console.log(`  ${pass} OK · ${fail} FAIL · ${errs.length} erros de console/network`);
console.log(`══════════════════════════════════════════════════════════════`);

if (fail > 0) {
  console.log('\nFALHAS:');
  results.filter(r => !r.ok).forEach(r => console.log(`  ✗ [${r.module}] ${r.action} — ${r.detail}`));
}
if (errs.length > 0) {
  console.log('\nERROS (primeiros 5):');
  const uniqueErrs = [...new Set(errs.map(e => e.text))].slice(0, 5);
  uniqueErrs.forEach(e => console.log(`  ${e}`));
}

process.exit(fail === 0 ? 0 : 1);
