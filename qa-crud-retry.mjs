// CRUD REAL de Parceiro via Playwright — retry após descobrir que o primeiro
// script tinha falso-negativo. Agora busca explicitamente o registro.

import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'fs';
import { join } from 'path';

const BASE = 'https://fazendamacaybas.com.br';
const EMAIL = 'qa-ui@fazendamacaybas.local';
const PASSWORD = 'QATest#2026';

const SHOTS = join(process.cwd(), 'qa-screenshots');
mkdirSync(SHOTS, { recursive: true });

let pass = 0, fail = 0;
const results = [];

function check(label, cond, extra = '') {
  (cond ? pass++ : fail++);
  results.push({ label, ok: cond, extra });
  console.log(`  ${cond ? '✓' : '✗'} ${label}${extra ? ' — ' + extra : ''}`);
}

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await ctx.newPage();

// Login
await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', EMAIL);
await page.fill('input[type="password"]', PASSWORD);
await page.click('button[type="submit"]');
await page.waitForURL(/admin/, { timeout: 15000 });
check('Login OK', true);

// Limpar partner antigo (que ficou do teste anterior) via delete direto na lista
await page.goto(`${BASE}/admin/parceiros?search=__QA_UI_`, { waitUntil: 'networkidle' });
const oldExists = (await page.textContent('body')).includes('__QA_UI_');
if (oldExists) console.log('  (info) Parceiro __QA_UI_ pré-existente detectado — será reaproveitado como alvo de testes');

// ═══════════════════════════════════════════════════════════
// FLUXO CRUD COMPLETO VIA UI
// ═══════════════════════════════════════════════════════════
const nomeUnico = `__QA_UI_FIX_${Date.now()}`;

// 1. Clicar no botão "Novo parceiro" pelo menu/header
await page.goto(`${BASE}/admin/parceiros`, { waitUntil: 'networkidle' });
await page.waitForTimeout(500);

const novoLink = page.locator('a:has-text("Novo")').first();
check('Link "Novo parceiro" visível na listagem', await novoLink.count() > 0);
await novoLink.click();
await page.waitForURL(/\/parceiros\/novo/, { timeout: 10000 });
await page.waitForLoadState('networkidle');
check('Navegou para /admin/parceiros/novo', page.url().includes('/novo'));

// 2. Preencher como Pessoa Física com dados válidos
// Seleciona "Pessoa Física"
const pessoaSelect = page.locator('select').filter({ hasText: /Pessoa Física|Pessoa Jurídica/ }).first();
await pessoaSelect.selectOption('pf');
await page.waitForTimeout(500);

const relacaoSelect = page.locator('select').filter({ hasText: /Fornecedor|Cliente/ }).first();
await relacaoSelect.selectOption('cliente');

// Nome
await page.locator('input[required]').first().fill(nomeUnico);

// CPF (InputMasked com maska)
const cpfInput = page.locator('input[data-maska]').first();
// maska requer evento de input real, não só value
await cpfInput.click();
await cpfInput.fill('');
await cpfInput.type('52998224725', { delay: 30 });
await page.waitForTimeout(500);

// Esperar "CPF válido." aparecer
await page.waitForSelector('text=/CPF válido/', { timeout: 5000 }).catch(() => null);
const dvValid = await page.locator('text=/CPF válido/').count() > 0;
check('Validação CPF DV live mostra "CPF válido"', dvValid);

// Email opcional
await page.fill('input[type="email"]', 'qa@teste.local');

await page.screenshot({ path: join(SHOTS, 'crud-01-form-preenchido.png'), fullPage: true });

// 3. Submeter
const salvarBtn = page.locator('button[type="submit"]').first();
const salvarEnabled = !(await salvarBtn.isDisabled());
check('Botão Salvar habilitado após form válido', salvarEnabled);
await salvarBtn.click();

// Aguarda redirect + flash
await page.waitForURL(/\/parceiros(\?|$)/, { timeout: 15000 });
await page.waitForLoadState('networkidle');
await page.waitForTimeout(1500);

// 4. Buscar o novo parceiro (com querystring explícita)
await page.goto(`${BASE}/admin/parceiros?search=${encodeURIComponent(nomeUnico)}`, { waitUntil: 'networkidle' });
await page.waitForTimeout(1000);

const htmlLista = await page.content();
const apareceu = htmlLista.includes(nomeUnico);
check('CREATE · Parceiro novo aparece na listagem (search)', apareceu);
if (apareceu) await page.screenshot({ path: join(SHOTS, 'crud-02-lista-apos-criar.png'), fullPage: true });

// Extrai o ID via Inertia data-page
const dataPage = await page.locator('#app').getAttribute('data-page');
const parsed = JSON.parse(dataPage);
const partner = (parsed.props?.partners?.data ?? []).find(p => p.nome === nomeUnico);
check('ID do parceiro criado capturado', !!partner, partner ? `id=${partner.id}` : 'NULL');

if (partner) {
  // 5. Editar — clicar no ícone de editar
  const editIcon = page.locator(`button[title*="Editar"], a[title*="Editar"]`).first();
  const hasEditButton = await editIcon.count() > 0;
  check('Botão de editar visível na listagem', hasEditButton);

  // Vou abrir via URL direta (mais determinístico)
  await page.goto(`${BASE}/admin/parceiros/${partner.id}/editar`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  check('Página de edição abre', page.url().includes('/editar'));

  // Troca o nome
  const nomeEditInput = page.locator('input[required]').first();
  await nomeEditInput.click();
  await nomeEditInput.fill(''); // limpa
  await nomeEditInput.type(nomeUnico + ' EDIT', { delay: 10 });

  await page.screenshot({ path: join(SHOTS, 'crud-03-edicao-preenchida.png'), fullPage: true });

  await page.locator('button[type="submit"]').first().click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);

  // Verifica na listagem com search
  await page.goto(`${BASE}/admin/parceiros?search=${encodeURIComponent(nomeUnico)}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  const htmlAposEdit = await page.content();
  check('UPDATE · Nome editado refletido na listagem', htmlAposEdit.includes(nomeUnico + ' EDIT'));
  await page.screenshot({ path: join(SHOTS, 'crud-04-lista-apos-editar.png'), fullPage: true });

  // 6. Excluir via form DELETE
  await page.evaluate(async (id) => {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrf = csrfMeta?.getAttribute('content');
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/admin/parceiros/${id}`;
    form.innerHTML = `
      <input type="hidden" name="_token" value="${csrf}">
      <input type="hidden" name="_method" value="DELETE">
    `;
    document.body.appendChild(form);
    form.submit();
  }, partner.id);

  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);

  await page.goto(`${BASE}/admin/parceiros?search=${encodeURIComponent(nomeUnico)}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  const htmlAposDel = await page.content();
  check('DELETE · Parceiro removido (search não encontra mais)', !htmlAposDel.includes(nomeUnico + ' EDIT'));
  await page.screenshot({ path: join(SHOTS, 'crud-05-lista-apos-excluir.png'), fullPage: true });
}

// Agora cleanup manual dos testes anteriores também
await page.goto(`${BASE}/admin/parceiros?search=__QA_UI_`, { waitUntil: 'networkidle' });
const restantes = await page.content();
const aindaHa = (restantes.match(/__QA_UI_/g) || []).length;
console.log(`\n  (cleanup) Parceiros __QA_UI_ restantes: ${aindaHa}`);

await browser.close();

console.log(`\n══════════════════════════════════════════════════════════════════`);
console.log(`  CRUD via UI: ${pass} passou, ${fail} falhou`);
console.log(`══════════════════════════════════════════════════════════════════`);

writeFileSync(join(SHOTS, 'crud-results.json'), JSON.stringify({ pass, fail, results }, null, 2));

process.exit(fail === 0 ? 0 : 1);
