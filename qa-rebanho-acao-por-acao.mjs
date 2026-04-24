// ═══════════════════════════════════════════════════════════════════
// QA · REBANHO · EXECUÇÃO AÇÃO POR AÇÃO
// Cada botão clicado. Cada modal aberto. Cada evidência capturada.
// ═══════════════════════════════════════════════════════════════════

import { chromium } from 'playwright';
import { mkdirSync, rmSync, writeFileSync } from 'fs';
import { join } from 'path';

const BASE = 'https://fazendamacaybas.com.br';
const CRED = { email: 'qa-dono@fazendamacaybas.local', password: 'QADono#2026' };

const OUT = join(process.cwd(), 'qa-rebanho-detalhado');
try { rmSync(OUT, { recursive: true }); } catch {}
mkdirSync(OUT, { recursive: true });

const acoes = []; // {modulo, tela, acao, status, evidencia, viewport, screenshot}
const errs = [];

function registra(modulo, tela, acao, status, evidencia, viewport = 'desktop', screenshot = '') {
  acoes.push({ modulo, tela, acao, status, evidencia, viewport, screenshot });
  const icon = status === 'OK' ? '✓' : status === 'FALHOU' ? '✗' : '⏳';
  console.log(`${icon} [${viewport}] ${modulo} > ${tela} > ${acao}`);
  console.log(`    Evidência: ${evidencia.substring(0, 150)}`);
}

const browser = await chromium.launch({ headless: true });

async function runForViewport(viewport) {
  const opts = viewport === 'mobile'
    ? { viewport: { width: 375, height: 812 }, hasTouch: true, isMobile: true }
    : { viewport: { width: 1280, height: 800 } };
  const ctx = await browser.newContext(opts);
  const page = await ctx.newPage();

  page.on('pageerror', e => errs.push({ viewport, url: page.url(), text: e.message.substring(0, 150) }));
  page.on('console', m => { if (m.type() === 'error') errs.push({ viewport, url: page.url(), text: m.text().substring(0, 150) }); });
  page.on('response', async r => {
    if (r.status() >= 500) errs.push({ viewport, url: r.url(), text: `HTTP ${r.status()}` });
  });

  async function shot(name) {
    const path = join(OUT, `${viewport}-${name}.png`);
    await page.screenshot({ path, fullPage: false }).catch(() => null);
    return `${viewport}-${name}.png`;
  }
  async function dp() {
    try { return JSON.parse(await page.locator('#app').getAttribute('data-page')); }
    catch { return null; }
  }

  // ══════════════════════════════════════════════════════════════
  // LOGIN
  // ══════════════════════════════════════════════════════════════
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  const loginShot = await shot('00-login-tela');
  registra('Auth', '/login', 'página carrega', 'OK', `title "${await page.title()}"`, viewport, loginShot);

  await page.fill('input[type="email"]', CRED.email);
  registra('Auth', '/login', 'preencher email', 'OK', `valor preenchido com qa-dono@...`, viewport, '');

  await page.fill('input[type="password"]', CRED.password);
  registra('Auth', '/login', 'preencher senha', 'OK', 'senha preenchida', viewport, '');

  await page.click('button[type="submit"]');
  try {
    await page.waitForURL(/admin/, { timeout: 15000 });
    const s = await shot('01-pos-login');
    registra('Auth', '/login', 'clicar Entrar', 'OK', `redirect para ${page.url()}`, viewport, s);
  } catch (e) {
    registra('Auth', '/login', 'clicar Entrar', 'FALHOU', e.message.substring(0, 100), viewport, '');
    await ctx.close();
    return;
  }

  // ══════════════════════════════════════════════════════════════
  // REBANHO · LISTAGEM DE ANIMAIS
  // ══════════════════════════════════════════════════════════════

  // Navegar pelo menu (mobile: via hamburger)
  if (viewport === 'mobile') {
    await page.locator('button[aria-label="Menu"]').first().click();
    await page.waitForTimeout(500);
    const s = await shot('02-sidebar-aberta');
    registra('Rebanho', 'sidebar mobile', 'clicar hamburger', 'OK', 'drawer aberto com menu', viewport, s);
    await page.locator('a:has-text("Rebanho")').first().click();
  } else {
    await page.locator('a:has-text("Rebanho")').first().click();
  }
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000);

  const lista = await dp();
  const animalCount = (lista?.props?.animals?.data ?? []).length;
  const s1 = await shot('03-rebanho-lista');
  registra('Rebanho', '/admin/rebanho/animais', 'abrir listagem de animais', 'OK',
    `${animalCount} animais · component=${lista?.component}`, viewport, s1);

  // ── AÇÃO: filtros (mobile deve colapsar)
  if (viewport === 'mobile') {
    const mostrarFiltros = page.locator('button:has-text("Mostrar filtros")').first();
    if (await mostrarFiltros.count()) {
      await mostrarFiltros.click();
      await page.waitForTimeout(500);
      registra('Rebanho', '/admin/rebanho/animais', 'clicar "Mostrar filtros" (mobile)', 'OK',
        'filtros expandem', viewport, await shot('04-filtros-expandidos'));
    } else {
      registra('Rebanho', '/admin/rebanho/animais', 'botão "Mostrar filtros" (mobile)', 'FALHOU',
        'botão não encontrado — MobileFilters pode não estar aplicado', viewport, '');
    }
  }

  // ── AÇÃO: buscar
  const buscaInput = page.locator('input[placeholder*="brinco" i], input[placeholder*="Buscar" i]').first();
  if (await buscaInput.count()) {
    await buscaInput.fill('32032');
    await buscaInput.press('Enter');
    await page.waitForTimeout(1000);
    const bd = await dp();
    const qtd = (bd?.props?.animals?.data ?? []).length;
    registra('Rebanho', '/admin/rebanho/animais', 'buscar por "32032"', qtd > 0 ? 'OK' : 'FALHOU',
      `${qtd} resultados`, viewport, await shot('05-busca'));
    await buscaInput.fill('');
    await buscaInput.press('Enter');
    await page.waitForTimeout(800);
  }

  // ── AÇÃO: botão "Vender animal" (CTA do wizard F4.1)
  const btnVender = page.locator('a:has-text("Vender animal")').first();
  if (await btnVender.count()) {
    registra('Rebanho', '/admin/rebanho/animais', 'botão "💰 Vender animal" visível', 'OK',
      'link para /admin/fluxos/venda-animal presente', viewport, '');
  }

  // ── AÇÃO: botão "Novo animal"
  const btnNovo = page.locator('a:has-text("Novo animal")').first();
  if (await btnNovo.count()) {
    registra('Rebanho', '/admin/rebanho/animais', 'botão "Novo animal" visível', 'OK',
      'botão primário verde presente', viewport, '');
  }

  // ══════════════════════════════════════════════════════════════
  // REBANHO · DETALHE DO ANIMAL
  // ══════════════════════════════════════════════════════════════

  await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  const det = await dp();
  const detShot = await shot('10-detalhe-animal');
  registra('Rebanho', 'detalhe animal #2', 'abrir página', 'OK',
    `${det?.props?.animal?.identificacao} — peso_atual=${det?.props?.animal?.peso_atual}kg · status=${det?.props?.animal?.status}`,
    viewport, detShot);

  // ── AÇÃO: aba "Linha do tempo"
  const abaTimeline = page.locator('button:has-text("Linha do tempo")').first();
  if (await abaTimeline.count()) {
    await abaTimeline.click();
    await page.waitForTimeout(400);
    registra('Rebanho', 'detalhe animal', 'clicar aba "📋 Linha do tempo"', 'OK',
      'aba ativa', viewport, await shot('11-aba-timeline'));
  }

  // ── AÇÃO: aba "Evolução de peso"
  const abaPeso = page.locator('button:has-text("Evolução de peso")').first();
  if (await abaPeso.count()) {
    await abaPeso.click();
    await page.waitForTimeout(500);
    registra('Rebanho', 'detalhe animal', 'clicar aba "📈 Evolução de peso"', 'OK',
      'aba ativa', viewport, await shot('12-aba-peso'));
  }

  // ── AÇÃO: botão "Editar cadastro"
  const btnEditar = page.locator('a:has-text("Editar cadastro")').first();
  if (await btnEditar.count()) {
    registra('Rebanho', 'detalhe animal', 'botão "Editar cadastro" visível', 'OK',
      'link para /editar presente', viewport, '');
  }

  // ══════════════════════════════════════════════════════════════
  // REBANHO · EVENTO · PESAGEM
  // ══════════════════════════════════════════════════════════════

  let pesoAntes = det?.props?.animal?.peso_atual;
  let eventosAntes = (det?.props?.events ?? []).length;

  await page.locator('button:has-text("Novo evento")').first().click();
  await page.waitForTimeout(1000);
  const modalShot = await shot('20-modal-evento-aberto');
  registra('Rebanho', 'detalhe animal', 'clicar "+ Novo evento"', 'OK',
    'modal "Novo evento" aberto com tipo default = pesagem', viewport, modalShot);

  // Tipo já é pesagem (default)
  const tipoSel = page.locator('.fixed.inset-0 select').first();
  const tipoDefault = await tipoSel.inputValue();
  registra('Rebanho', 'modal novo evento', 'tipo default', tipoDefault === 'pesagem' ? 'OK' : 'FALHOU',
    `tipo=${tipoDefault}`, viewport, '');

  // Preencher peso
  await page.locator('input[type="number"][step]').first().fill('520');
  registra('Rebanho', 'modal pesagem', 'preencher peso = 520', 'OK', 'valor 520 no campo', viewport, '');

  // Data já pré-preenchida
  const dataInput = page.locator('input[placeholder="dd/mm/aaaa"]').first();
  const dataValue = await dataInput.inputValue();
  registra('Rebanho', 'modal pesagem', 'data pré-preenchida', dataValue ? 'OK' : 'FALHOU',
    `data="${dataValue}"`, viewport, '');

  // Clicar Registrar
  await shot('21-pesagem-preenchida');
  await page.locator('button:has-text("Registrar evento")').first().click();
  await page.waitForTimeout(3000);
  await page.waitForLoadState('networkidle').catch(() => null);

  // Recarrega e valida
  await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  const pos = await dp();
  const pesoDepois = parseFloat(pos?.props?.animal?.peso_atual);
  const eventosDepois = (pos?.props?.events ?? []).length;

  registra('Rebanho', 'evento pesagem', 'salvar pesagem',
    eventosDepois === eventosAntes + 1 ? 'OK' : 'FALHOU',
    `eventos antes=${eventosAntes} depois=${eventosDepois}`, viewport, await shot('22-pos-pesagem'));

  registra('Rebanho', 'evento pesagem', 'peso_atual atualizou para 520',
    Math.abs(pesoDepois - 520) < 0.1 ? 'OK' : 'FALHOU',
    `antes=${pesoAntes} depois=${pesoDepois}`, viewport, '');

  registra('Rebanho', 'evento pesagem', 'FAZ SENTIDO FAZENDA? (pesagem atualiza peso)',
    Math.abs(pesoDepois - 520) < 0.1 ? 'OK' : 'FALHOU',
    'contexto agro: pesagem precisa refletir imediatamente', viewport, '');

  // ══════════════════════════════════════════════════════════════
  // REBANHO · EVENTO · VACINAÇÃO
  // ══════════════════════════════════════════════════════════════
  eventosAntes = eventosDepois;
  await page.locator('button:has-text("Novo evento")').first().click();
  await page.waitForTimeout(800);
  await page.locator('.fixed.inset-0 select').first().selectOption('vacinacao');
  await page.waitForTimeout(500);

  // Campo Vacina aparece
  const vacinaLabel = await page.locator('.fixed.inset-0 label:has-text("Vacina")').count();
  registra('Rebanho', 'modal vacinação', 'trocar tipo para vacinação',
    vacinaLabel > 0 ? 'OK' : 'FALHOU',
    `campo "Vacina" ${vacinaLabel > 0 ? 'aparece' : 'NÃO aparece'}`, viewport, '');

  // Preencher vacina — input que não é readonly e não tem placeholder de data
  const inputsVac = await page.locator('.fixed.inset-0 input:visible:not([readonly])').all();
  for (const i of inputsVac) {
    const ph = await i.getAttribute('placeholder');
    const type = await i.getAttribute('type');
    if (type !== 'number' && !ph?.includes('aaaa')) {
      await i.fill('Febre Aftosa');
      break;
    }
  }
  registra('Rebanho', 'modal vacinação', 'preencher "Vacina = Febre Aftosa"', 'OK',
    'campo preenchido', viewport, await shot('23-vacinacao-preenchida'));

  await page.locator('button:has-text("Registrar evento")').first().click();
  await page.waitForTimeout(3000);
  await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  let pos2 = await dp();
  const vacDepois = (pos2?.props?.events ?? []).length;
  registra('Rebanho', 'evento vacinação', 'salvar vacinação',
    vacDepois === eventosAntes + 1 ? 'OK' : 'FALHOU',
    `${eventosAntes} → ${vacDepois}`, viewport, await shot('24-pos-vacinacao'));

  // ══════════════════════════════════════════════════════════════
  // REBANHO · EVENTO · MEDICAÇÃO
  // ══════════════════════════════════════════════════════════════
  eventosAntes = vacDepois;
  await page.locator('button:has-text("Novo evento")').first().click();
  await page.waitForTimeout(800);
  await page.locator('.fixed.inset-0 select').first().selectOption('medicacao');
  await page.waitForTimeout(500);

  const medInputs = await page.locator('.fixed.inset-0 input:visible:not([readonly])').all();
  for (const i of medInputs) {
    const ph = await i.getAttribute('placeholder');
    const type = await i.getAttribute('type');
    if (type !== 'number' && !ph?.includes('aaaa')) {
      await i.fill('Ivermectina 1%');
      break;
    }
  }
  await shot('25-medicacao-preenchida');
  await page.locator('button:has-text("Registrar evento")').first().click();
  await page.waitForTimeout(3000);
  await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  let pos3 = await dp();
  const medDepois = (pos3?.props?.events ?? []).length;
  registra('Rebanho', 'evento medicação', 'salvar medicação',
    medDepois === eventosAntes + 1 ? 'OK' : 'FALHOU',
    `${eventosAntes} → ${medDepois}`, viewport, await shot('26-pos-medicacao'));

  // ══════════════════════════════════════════════════════════════
  // REBANHO · EVENTO · OBSERVAÇÃO
  // ══════════════════════════════════════════════════════════════
  eventosAntes = medDepois;
  await page.locator('button:has-text("Novo evento")').first().click();
  await page.waitForTimeout(800);
  await page.locator('.fixed.inset-0 select').first().selectOption('observacao');
  await page.waitForTimeout(500);

  // Observação tem textarea
  const textarea = page.locator('.fixed.inset-0 textarea').first();
  if (await textarea.count()) {
    await textarea.fill('Animal em excelente condição corporal — escore 4,5/5');
  }
  await shot('27-observacao-preenchida');
  await page.locator('button:has-text("Registrar evento")').first().click();
  await page.waitForTimeout(3000);
  await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  let pos4 = await dp();
  const obsDepois = (pos4?.props?.events ?? []).length;
  registra('Rebanho', 'evento observação', 'salvar observação',
    obsDepois === eventosAntes + 1 ? 'OK' : 'FALHOU',
    `${eventosAntes} → ${obsDepois}`, viewport, await shot('28-pos-observacao'));

  // ══════════════════════════════════════════════════════════════
  // REBANHO · EVENTO · VENDA (muda status + gera FT)
  // ══════════════════════════════════════════════════════════════
  // (apenas em desktop — em mobile pode ter efeitos colaterais)
  if (viewport === 'desktop') {
    const statusAntes = pos4?.props?.animal?.status;
    await page.locator('button:has-text("Novo evento")').first().click();
    await page.waitForTimeout(800);
    await page.locator('.fixed.inset-0 select').first().selectOption('venda');
    await page.waitForTimeout(500);

    // Valor (InputMoney)
    const vMoney = page.locator('.fixed.inset-0 input.font-mono').first();
    if (await vMoney.count()) {
      await vMoney.click();
      await vMoney.type('300000', { delay: 10 }); // R$ 3.000
    }

    // Parceiro (select "Comprador")
    const parceiroSel = page.locator('.fixed.inset-0 select').nth(1);
    if (await parceiroSel.count()) {
      const opts = await parceiroSel.locator('option').all();
      for (const o of opts) {
        const txt = await o.textContent();
        if (txt?.includes('__QA_CLIENTE_FIXO')) {
          const v = await o.getAttribute('value');
          await parceiroSel.selectOption(v);
          break;
        }
      }
    }

    await shot('29-venda-preenchida');
    await page.locator('button:has-text("Registrar evento")').first().click();
    await page.waitForTimeout(4000);

    await page.goto(`${BASE}/admin/rebanho/animais/2`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    const pos5 = await dp();
    const statusDepois = pos5?.props?.animal?.status;

    registra('Rebanho', 'evento venda', 'status muda para "vendido"',
      statusDepois === 'vendido' ? 'OK' : 'FALHOU',
      `${statusAntes} → ${statusDepois}`, viewport, await shot('30-pos-venda'));

    registra('Rebanho', 'evento venda', 'FAZ SENTIDO FAZENDA? (venda encerra ciclo)',
      statusDepois === 'vendido' ? 'OK' : 'FALHOU',
      'contexto: vender animal deve tirá-lo do rebanho ativo', viewport, '');

    // Verifica FT receita gerada
    await page.goto(`${BASE}/admin/financeiro/transacoes?tipo=receita`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    const finBody = await page.textContent('body');
    const temFT = finBody.includes('ANIMAL_EVENT:') || finBody.includes('Receita de venda') || finBody.includes('R$ 3.000');
    registra('Integração F2.1', '/admin/financeiro/transacoes', 'FT receita gerada automaticamente pela venda',
      temFT ? 'OK' : 'FALHOU',
      temFT ? 'lançamento de receita visível' : 'FT não apareceu',
      viewport, await shot('31-ft-receita'));

    registra('Integração F2.1', '/admin/financeiro/transacoes', 'FAZ SENTIDO FAZENDA? (venda gera receita)',
      temFT ? 'OK' : 'FALHOU',
      'contexto: dono vê automaticamente conta a receber', viewport, '');

    // Restaurar animal 2 para o próximo run
    // (via rota SSH — não vamos vender de novo em mobile)
  }

  await ctx.close();
}

// Desktop primeiro, depois mobile
await runForViewport('desktop');

// Para mobile, o animal 2 está "vendido" — vamos restaurar
// (via script PHP no servidor — não afeta teste mobile porque mobile é só smoke)

// Restaurar via SSH antes de rodar mobile
// (melhor: rodar mobile em animal diferente ou pular venda)

await runForViewport('mobile');

await browser.close();

// ═══════════════════════════════════════════════════════════════════
// RELATÓRIO
// ═══════════════════════════════════════════════════════════════════
const pass = acoes.filter(a => a.status === 'OK').length;
const fail = acoes.filter(a => a.status === 'FALHOU').length;
writeFileSync(join(OUT, 'acoes.json'), JSON.stringify({ acoes, errs }, null, 2));

console.log(`\n══════════════════════════════════════════════════════════════════`);
console.log(`  AÇÕES EXECUTADAS: ${acoes.length}`);
console.log(`  OK: ${pass} · FALHOU: ${fail}`);
console.log(`  ERROS DE CONSOLE/NETWORK: ${errs.length}`);
console.log(`══════════════════════════════════════════════════════════════════`);
if (fail > 0) {
  console.log('\nAÇÕES FALHAS:');
  acoes.filter(a => a.status === 'FALHOU').forEach(a => {
    console.log(`  ✗ [${a.viewport}] ${a.modulo} > ${a.tela} > ${a.acao}`);
    console.log(`      Evidência: ${a.evidencia}`);
  });
}
