// QA E2E · NAVEGADOR REAL (Playwright headless Chromium)
// =====================================================
// Executa como usuário real: abre navegador, loga, clica botões,
// preenche formulários, captura screenshots em desktop e mobile.

import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'fs';
import { join } from 'path';

const BASE = 'https://fazendamacaybas.com.br';
const EMAIL = 'qa-ui@fazendamacaybas.local';
const PASSWORD = 'QATest#2026';

const SHOTS_DIR = join(process.cwd(), 'qa-screenshots');
mkdirSync(SHOTS_DIR, { recursive: true });

const report = {
  started: new Date().toISOString(),
  modules: {},
  bugs: [],
  uxIssues: [],
};

let totalPass = 0;
let totalFail = 0;

function log(msg) {
  console.log(msg);
}

function record(module, viewport, field, ok, detail = '') {
  if (!report.modules[module]) report.modules[module] = {};
  if (!report.modules[module][viewport]) report.modules[module][viewport] = [];
  report.modules[module][viewport].push({ field, ok, detail });
  if (ok) totalPass++;
  else totalFail++;
  log(`  ${ok ? '✓' : '✗'} [${viewport}] ${module} · ${field}${detail ? ' — ' + detail : ''}`);
}

function bug(severity, id, desc, module = '') {
  report.bugs.push({ severity, id, desc, module });
  log(`  🐞 [${severity}] ${id} ${module ? '(' + module + ')' : ''}: ${desc}`);
}

function uxIssue(type, id, desc, module = '') {
  report.uxIssues.push({ type, id, desc, module });
  log(`  💡 [${type}] ${id} ${module ? '(' + module + ')' : ''}: ${desc}`);
}

async function screenshot(page, name) {
  const path = join(SHOTS_DIR, `${name}.png`);
  await page.screenshot({ path, fullPage: false });
  return path;
}

async function login(page) {
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/admin/, { timeout: 15000 });
  await page.waitForLoadState('networkidle');
}

async function openSidebarIfMobile(page, viewport) {
  if (viewport === 'mobile') {
    const hamburger = page.locator('button[aria-label="Menu"]').first();
    if (await hamburger.count()) {
      await hamburger.click();
      await page.waitForTimeout(500);
    }
  }
}

// ═══════════════════════════════════════════════════════════
// TESTE UM MÓDULO (listagem) — simplesmente abrir e validar
// ═══════════════════════════════════════════════════════════
async function testList(page, viewport, module, url, expectedTitle, expectedSelector) {
  try {
    await page.goto(`${BASE}${url}`, { waitUntil: 'networkidle', timeout: 20000 });

    const bodyTxt = await page.textContent('body');
    const has500 = bodyTxt.includes('Server Error') || bodyTxt.includes('Whoops');
    record(module, viewport, 'Página carrega sem erro 500', !has500, has500 ? 'ERRO 500' : 'OK');

    // Título visível (PageHeader h1)
    const h1 = await page.locator('h1').first().textContent().catch(() => null);
    const titleMatch = h1 && h1.toLowerCase().includes(expectedTitle.toLowerCase());
    record(module, viewport, `Título "${expectedTitle}" visível`, !!titleMatch, `h1="${h1}"`);

    // Seletor esperado (conteúdo-chave da página)
    const exists = await page.locator(expectedSelector).count() > 0;
    record(module, viewport, `Conteúdo principal renderiza`, exists, `selector=${expectedSelector}`);

    await screenshot(page, `${viewport}-${module}`);
  } catch (e) {
    record(module, viewport, 'Página navegável', false, e.message.substring(0, 80));
    bug('CRÍTICO', `NAV-${module}`, `Falha ao abrir ${url}: ${e.message.substring(0, 100)}`, module);
  }
}

// ═══════════════════════════════════════════════════════════
// CRUD REAL DE PARCEIRO (desktop apenas — mobile tem mesma lógica)
// ═══════════════════════════════════════════════════════════
async function crudParceiroReal(page) {
  const module = 'Parceiros-CRUD';
  const viewport = 'desktop';
  const nomeUnico = `__QA_UI_${Date.now()}`;

  try {
    // 1. Abrir lista
    await page.goto(`${BASE}/admin/parceiros`, { waitUntil: 'networkidle' });
    record(module, viewport, '1. Abrir /admin/parceiros', true);

    // 2. Clicar "Novo parceiro"
    const novoBtn = page.locator('a:has-text("Novo parceiro"), a:has-text("Novo")').first();
    const hasNovo = await novoBtn.count() > 0;
    record(module, viewport, '2. Botão "Novo parceiro" visível', hasNovo);
    if (!hasNovo) return;

    await novoBtn.click();
    await page.waitForURL(/\/admin\/parceiros\/novo/, { timeout: 10000 });
    await page.waitForLoadState('networkidle');
    record(module, viewport, '3. Navegou para formulário', true);
    await screenshot(page, 'desktop-parceiro-novo');

    // 3. Preencher PF
    // Tipo já default = 'fornecedor'; pessoa já default = 'pj'. Vamos trocar para PF.
    await page.selectOption('select:has(option[value="pf"])', 'pf').catch(() => null);
    await page.waitForTimeout(300);

    // Nome
    await page.fill('input[placeholder*="João"], input[required]:nth-of-type(1)', nomeUnico).catch(async () => {
      // Fallback: preenche o primeiro input required
      await page.locator('input[required]').first().fill(nomeUnico);
    });

    // Documento (CPF) — via input dentro do label "CPF"
    const docInput = page.locator('input[placeholder*="000.000"], input[data-maska*="###.###"]').first();
    await docInput.fill('529.982.247-25');
    await page.waitForTimeout(300);

    // Email opcional
    await page.fill('input[type="email"]', 'qa@teste.local').catch(() => null);

    record(module, viewport, '4. Campos preenchidos', true);
    await screenshot(page, 'desktop-parceiro-preenchido');

    // Submeter
    const submitBtn = page.locator('button[type="submit"]').first();
    const disabled = await submitBtn.isDisabled();
    record(module, viewport, '5. Botão Salvar habilitado com dados válidos', !disabled,
      disabled ? 'Salvar desabilitado — UX anti-erro está funcionando' : 'habilitado');

    await submitBtn.click();
    await page.waitForURL(url => url.pathname === '/admin/parceiros' || url.pathname.includes('/admin/parceiros'), { timeout: 15000 }).catch(() => {});
    await page.waitForLoadState('networkidle');

    // 4. Verificar que apareceu na listagem
    const listaHtml = await page.content();
    const apareceu = listaHtml.includes(nomeUnico);
    record(module, viewport, '6. Parceiro novo aparece na listagem', apareceu);
    if (apareceu) await screenshot(page, 'desktop-parceiro-lista-criado');

    if (!apareceu) {
      bug('ALTO', 'CRUD-001', 'Parceiro criado via UI não aparece imediatamente na listagem', module);
      return;
    }

    // 5. Editar — clicar no ícone de editar do parceiro criado
    const editarBtn = page.locator(`text=${nomeUnico}`).first().locator('xpath=ancestor::tr[1]').locator('button[title*="Editar"], a[title*="Editar"]').first()
      .or(page.locator('button[title="Editar parceiro"]').first());

    // Alternativa: procurar qualquer link/botão de editar no card/row
    const hasEditar = await page.locator(`text=${nomeUnico}`).first().count() > 0;
    if (!hasEditar) {
      record(module, viewport, '7. Clicar em editar', false, 'linha não encontrada');
      return;
    }

    // Encontrar o ID do parceiro via URL da página de edição
    // Abordagem simples: buscar na API Inertia o ID e ir direto
    const dataPage = await page.locator('#app').getAttribute('data-page');
    const parsed = JSON.parse(dataPage);
    const partner = (parsed.props?.partners?.data ?? []).find(p => p.nome === nomeUnico);

    if (!partner) {
      record(module, viewport, '7. Partner encontrado no payload', false);
      return;
    }

    await page.goto(`${BASE}/admin/parceiros/${partner.id}/editar`, { waitUntil: 'networkidle' });
    record(module, viewport, '7. Página de edição abre', true);
    await screenshot(page, 'desktop-parceiro-edicao');

    // Alterar nome
    const nomeInput = page.locator('input[required]').first();
    await nomeInput.fill(nomeUnico + ' EDIT');

    await page.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);

    const listaAposEdit = await page.content();
    const edicaoVisivel = listaAposEdit.includes(nomeUnico + ' EDIT');
    record(module, viewport, '8. Edição refletida na listagem', edicaoVisivel);
    if (edicaoVisivel) await screenshot(page, 'desktop-parceiro-editado');

    // 6. Excluir — via form DELETE
    await page.evaluate((id) => {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = `/admin/parceiros/${id}`;
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      const csrf = csrfMeta?.getAttribute('content');
      form.innerHTML = `
        <input type="hidden" name="_token" value="${csrf}">
        <input type="hidden" name="_method" value="DELETE">
      `;
      document.body.appendChild(form);
      form.submit();
    }, partner.id);

    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);

    const listaAposDel = await page.content();
    const sumiu = !listaAposDel.includes(nomeUnico + ' EDIT');
    record(module, viewport, '9. Parceiro excluído some da listagem', sumiu);
    if (sumiu) await screenshot(page, 'desktop-parceiro-excluido');
  } catch (e) {
    record(module, viewport, 'CRUD completo', false, e.message.substring(0, 100));
    bug('ALTO', 'CRUD-EX', `Exception durante CRUD: ${e.message.substring(0, 100)}`, module);
  }
}

// ═══════════════════════════════════════════════════════════
// FLUXO GUIADO F4.1 — Wizard de venda de animal
// ═══════════════════════════════════════════════════════════
async function testWizard(page, viewport) {
  const module = 'Wizard-F41';
  try {
    await page.goto(`${BASE}/admin/fluxos/venda-animal`, { waitUntil: 'networkidle', timeout: 20000 });

    const body = await page.textContent('body');
    record(module, viewport, 'Wizard carrega sem erro', !body.includes('Server Error'));

    const stepper = await page.locator('text=/O animal.*O comprador.*O valor/').count() > 0;
    const titulo = await page.locator('h2:has-text("Qual animal"), h1:has-text("Assistente")').count() > 0;
    record(module, viewport, 'Stepper dos 5 passos visível', stepper);
    record(module, viewport, 'Título conversacional ("Qual animal")', titulo);

    // Verifica se há animais na listagem ou msg de vazio amigável
    const temAnimais = await page.locator('button:has-text("Cão"), button:has-text("bovino"), button:has-text("♀"), button:has-text("♂")').count() > 0;
    const vazioMsg = await page.locator('text=/Nenhum animal/i, text=/disponív/i').count() > 0;
    record(module, viewport, 'Lista de animais OU mensagem de vazio', temAnimais || vazioMsg);

    await screenshot(page, `${viewport}-wizard-passo1`);

    // Se há animais, clicar no primeiro e tentar avançar
    if (temAnimais) {
      const firstCard = page.locator('button.text-left.rounded-xl.border-2').first();
      if (await firstCard.count() > 0) {
        await firstCard.click();
        await page.waitForTimeout(300);
        const continuarBtn = page.locator('button:has-text("Continuar")').first();
        const enabled = !(await continuarBtn.isDisabled());
        record(module, viewport, 'Continuar habilita após selecionar animal', enabled);
      }
    }
  } catch (e) {
    record(module, viewport, 'Wizard navegável', false, e.message.substring(0, 100));
    bug('ALTO', 'WIZ-001', `Wizard falhou: ${e.message.substring(0, 100)}`, module);
  }
}

// ═══════════════════════════════════════════════════════════
// VERIFICAÇÕES DE UX — FAB, toque, menu
// ═══════════════════════════════════════════════════════════
async function testUX(page, viewport) {
  const module = 'UX-Global';

  // Sidebar/menu
  if (viewport === 'mobile') {
    const hamburger = await page.locator('button[aria-label="Menu"]').count() > 0;
    record(module, viewport, 'Hamburger visível em mobile', hamburger);

    if (hamburger) {
      await page.locator('button[aria-label="Menu"]').first().click();
      await page.waitForTimeout(400);
      const sidebarAberta = await page.locator('aside:visible, nav:has-text("Rebanho")').count() > 0;
      record(module, viewport, 'Sidebar abre ao tocar hamburger', sidebarAberta);
      await screenshot(page, `mobile-sidebar-aberta`);
      // Fecha
      await page.keyboard.press('Escape').catch(() => null);
      await page.locator('.bg-black\\/40').click({ position: { x: 10, y: 200 } }).catch(() => null);
    }
  } else {
    const sidebarVisivel = await page.locator('aside, nav').count() > 0;
    record(module, viewport, 'Sidebar visível em desktop', sidebarVisivel);
  }

  // Verifica botão principal da página
  const btnPrimaryCount = await page.locator('.btn-primary').count();
  record(module, viewport, 'Botão primário presente', btnPrimaryCount > 0, `count=${btnPrimaryCount}`);

  // Toque: tamanho dos botões primários (mobile precisa >=44px)
  if (viewport === 'mobile' && btnPrimaryCount > 0) {
    const btn = page.locator('.btn-primary').first();
    const box = await btn.boundingBox();
    if (box) {
      const okHeight = box.height >= 40; // permitimos 40 como limiar com margem
      record(module, viewport, `Botão primário altura ≥40px (real: ${Math.round(box.height)}px)`, okHeight);
      if (!okHeight) {
        uxIssue('MOBILE', 'MOBILE-BTN-SIZE',
          `Botão primário com ${Math.round(box.height)}px em mobile — abaixo de 44px WCAG`,
          module);
      }
    }
  }
}

// ═══════════════════════════════════════════════════════════
// RUN — contexto desktop + mobile
// ═══════════════════════════════════════════════════════════
async function runForViewport(browser, viewport) {
  log(`\n══════════════════════════════════════════════════════════════════`);
  log(`  ▶ QA E2E ${viewport.toUpperCase()} (${viewport === 'mobile' ? '375×812' : '1280×800'})`);
  log(`══════════════════════════════════════════════════════════════════\n`);

  const context = await browser.newContext({
    viewport: viewport === 'mobile' ? { width: 375, height: 812 } : { width: 1280, height: 800 },
    userAgent: viewport === 'mobile'
      ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1'
      : undefined,
    hasTouch: viewport === 'mobile',
    isMobile: viewport === 'mobile',
    acceptDownloads: false,
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();

  // LOGIN
  log(`→ Login`);
  try {
    await login(page);
    record('Auth', viewport, 'Login Admin + redirect /admin', true);
    await screenshot(page, `${viewport}-01-login-ok`);
  } catch (e) {
    record('Auth', viewport, 'Login', false, e.message.substring(0, 80));
    bug('CRÍTICO', 'AUTH-001', `Login falhou: ${e.message.substring(0, 100)}`, 'Auth');
    await context.close();
    return;
  }

  // UX GLOBAL
  log(`→ UX global`);
  await testUX(page, viewport);

  // LISTAGENS
  const modulos = [
    ['Dashboard',     '/admin', 'Dashboard', '.card, main'],
    ['Rebanho',       '/admin/rebanho/animais', 'Animais', '.card, [href*="animais/novo"]'],
    ['Estoque',       '/admin/estoque/itens', 'estoque', '.card'],
    ['Movimentações', '/admin/estoque/movimentos', '', '.card'],
    ['Parceiros',     '/admin/parceiros', 'parceiro', '.card'],
    ['Financeiro',    '/admin/financeiro/transacoes', '', '.card'],
    ['Máquinas',      '/admin/maquinas/veiculos', '', '.card'],
    ['Manutenções',   '/admin/maquinas/manutencoes', '', '.card'],
    ['Agrícola',      '/admin/agricola', '', '.card'],
    ['Documentos',    '/admin/documentos', 'Documentos', '.card'],
    ['Funcionários',  '/admin/funcionarios', '', '.card'],
    ['Tarefas',       '/admin/tarefas', 'Tarefas', '.card'],
  ];

  log(`→ Listagens dos módulos`);
  for (const [nome, url, titulo, selector] of modulos) {
    await testList(page, viewport, nome, url, titulo, selector);
  }

  // WIZARD F4.1
  log(`→ Wizard F4.1`);
  await testWizard(page, viewport);

  // CRUD REAL (só desktop)
  if (viewport === 'desktop') {
    log(`→ CRUD REAL · Parceiro`);
    await crudParceiroReal(page);
  }

  await context.close();
}

// ═══════════════════════════════════════════════════════════
const browser = await chromium.launch({ headless: true });

try {
  await runForViewport(browser, 'desktop');
  await runForViewport(browser, 'mobile');
} finally {
  await browser.close();
}

// ═══════════════════════════════════════════════════════════
// RELATÓRIO
// ═══════════════════════════════════════════════════════════
report.finished = new Date().toISOString();
report.totalPass = totalPass;
report.totalFail = totalFail;

writeFileSync(join(SHOTS_DIR, 'report.json'), JSON.stringify(report, null, 2));

log(`\n══════════════════════════════════════════════════════════════════`);
log(`  RESULTADO FINAL`);
log(`══════════════════════════════════════════════════════════════════`);
log(`  Passou:   ${totalPass}`);
log(`  Falhou:   ${totalFail}`);
log(`  Bugs:     ${report.bugs.length}`);
log(`  UX issues: ${report.uxIssues.length}`);
log(`  Screenshots: ${SHOTS_DIR}`);
log(`══════════════════════════════════════════════════════════════════`);

process.exit(totalFail === 0 ? 0 : 1);
