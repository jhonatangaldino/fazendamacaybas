// ═══════════════════════════════════════════════════════════════════
// BLOQUEADOR · REBANHO · REGISTRO DE EVENTOS
// ═══════════════════════════════════════════════════════════════════
// Objetivo: reproduzir o problema relatado — "dono não consegue
// registrar pesagem nem outros eventos no Rebanho".
// Captura screenshots, console.error, network failures, tempo em cada passo.
// ═══════════════════════════════════════════════════════════════════

import { chromium } from 'playwright';
import { writeFileSync, mkdirSync, rmSync } from 'fs';
import { join } from 'path';

const BASE = 'https://fazendamacaybas.com.br';
const CRED = { email: 'qa-dono@fazendamacaybas.local', password: 'QADono#2026' };

const OUT = join(process.cwd(), 'qa-bloqueador');
try { rmSync(OUT, { recursive: true }); } catch {}
mkdirSync(OUT, { recursive: true });

const log = [];
const consoleErrs = [];
const networkFails = [];
function entry(step, detail = '') {
  const line = `[${new Date().toISOString().substr(11, 8)}] ${step}${detail ? ' — ' + detail : ''}`;
  console.log(line);
  log.push(line);
}

async function shot(page, name) {
  const p = join(OUT, `${name}.png`);
  await page.screenshot({ path: p, fullPage: true }).catch(() => null);
}

for (const viewport of ['desktop', 'mobile']) {
  entry(`═══ VIEWPORT ${viewport.toUpperCase()} ═══`);

  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({
    viewport: viewport === 'mobile' ? { width: 375, height: 812 } : { width: 1280, height: 800 },
    hasTouch: viewport === 'mobile',
    isMobile: viewport === 'mobile',
  });
  const page = await ctx.newPage();

  // Captura tudo que for erro
  page.on('console', msg => {
    if (msg.type() === 'error') {
      const t = msg.text();
      consoleErrs.push({ viewport, step: 'global', text: t });
      entry(`  CONSOLE.ERROR: ${t.substring(0, 120)}`);
    }
  });
  page.on('pageerror', err => {
    consoleErrs.push({ viewport, step: 'global', text: err.message });
    entry(`  PAGE.ERROR: ${err.message.substring(0, 120)}`);
  });
  page.on('response', resp => {
    if (resp.status() >= 400 && resp.url().includes('/admin')) {
      networkFails.push({ viewport, url: resp.url(), status: resp.status() });
      entry(`  NETWORK FAIL ${resp.status()}: ${resp.url().substring(0, 100)}`);
    }
  });

  try {
    // 1. LOGIN
    entry('1. Navegar para /login');
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await shot(page, `${viewport}-01-login`);

    entry('2. Preencher credenciais e entrar');
    await page.fill('input[type="email"]', CRED.email);
    await page.fill('input[type="password"]', CRED.password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/admin/, { timeout: 15000 });
    await page.waitForLoadState('networkidle');
    await shot(page, `${viewport}-02-pos-login`);
    entry(`   URL após login: ${page.url()}`);

    // 2. ABRIR REBANHO
    entry('3. Clicar em "Rebanho" no menu');
    if (viewport === 'mobile') {
      // Abrir sidebar primeiro
      await page.locator('button[aria-label="Menu"]').first().click();
      await page.waitForTimeout(500);
    }
    const rebanhoLink = page.locator('a:has-text("Rebanho")').first();
    const hasRebanho = await rebanhoLink.count() > 0;
    entry(`   Link "Rebanho" no menu visível: ${hasRebanho}`);
    if (hasRebanho) {
      await rebanhoLink.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1000);
    } else {
      await page.goto(`${BASE}/admin/rebanho/animais`, { waitUntil: 'networkidle' });
    }
    await shot(page, `${viewport}-03-rebanho`);
    entry(`   URL: ${page.url()}`);

    // 3. VERIFICAR SE HÁ ANIMAL LISTADO
    const bodyTxt = await page.textContent('body');
    const brincoNaLista = bodyTxt.match(/\b(\d{4,6})\b/);
    entry(`   Animais visíveis na página: ${brincoNaLista ? brincoNaLista[0] : 'nenhum brinco detectado'}`);

    // 4. ABRIR DETALHE DO PRIMEIRO ANIMAL (link clicável no brinco ou nome)
    entry('4. Procurar e clicar no primeiro animal para abrir detalhe');
    // Tentativa 1: link direto na tabela/card
    const animalLink = page.locator('a[href*="/rebanho/animais/"][href$="/editar"], a[href*="/rebanho/animais/"]:not([href*="/novo"]):not([href*="/editar"])').first();
    let linkCount = await animalLink.count();
    entry(`   Links diretos para animal: ${linkCount}`);

    let openedDetail = false;
    if (linkCount > 0) {
      // Prefere link que NÃO seja editar, para abrir show
      const showLink = page.locator('a[href*="/rebanho/animais/"]:not([href*="/novo"]):not([href*="/editar"]):not([href$="/rebanho/animais"])').first();
      if (await showLink.count() > 0) {
        const href = await showLink.getAttribute('href');
        entry(`   Clicando em link: ${href}`);
        await showLink.click();
        await page.waitForLoadState('networkidle');
        openedDetail = true;
      }
    }

    if (!openedDetail) {
      // Tenta clicar no brinco/nome do primeiro item
      const firstBrinco = page.locator('td, div').filter({ hasText: /^\d{4,6}$/ }).first();
      if (await firstBrinco.count() > 0) {
        entry(`   Tentando clicar no brinco na lista...`);
        await firstBrinco.click({ force: true }).catch(() => null);
        await page.waitForTimeout(1000);
        if (page.url().includes('/rebanho/animais/') && !page.url().endsWith('/animais')) {
          openedDetail = true;
        }
      }
    }

    if (!openedDetail) {
      // Último recurso: navegar direto via payload
      entry(`   Tentando buscar ID de animal via Inertia data-page...`);
      try {
        const dp = JSON.parse(await page.locator('#app').getAttribute('data-page'));
        const firstAnimal = dp.props?.animals?.data?.[0];
        if (firstAnimal) {
          entry(`   Animal encontrado: id=${firstAnimal.id} brinco=${firstAnimal.identificacao}`);
          await page.goto(`${BASE}/admin/rebanho/animais/${firstAnimal.id}`, { waitUntil: 'networkidle' });
          openedDetail = true;
        } else {
          entry(`   NENHUM ANIMAL NO PAYLOAD — precisa criar um primeiro`);
        }
      } catch (e) {
        entry(`   Falha ao extrair animal: ${e.message.substring(0, 80)}`);
      }
    }

    await shot(page, `${viewport}-04-animal-detalhe`);
    entry(`   URL detalhe: ${page.url()}`);

    if (!openedDetail) {
      entry(`❌ BLOQUEADO: não consegui abrir detalhe do animal`);
      await ctx.close(); await browser.close(); continue;
    }

    // 5. PROCURAR BOTÃO DE REGISTRAR PESAGEM/EVENTO
    entry('5. Procurar botão "Pesagem" ou "Registrar evento" na tela do animal');
    const tbody = await page.textContent('body');
    const temPesagem = tbody.includes('Pesagem') || tbody.includes('pesagem');
    const temEvento = tbody.includes('Registrar evento') || tbody.includes('Novo evento') || tbody.includes('evento');
    const temHistorico = tbody.includes('Histórico') || tbody.includes('Timeline');
    entry(`   Texto "Pesagem" presente: ${temPesagem}`);
    entry(`   Texto "evento" presente: ${temEvento}`);
    entry(`   Texto "Histórico" presente: ${temHistorico}`);

    // Listar todos os botões clicáveis visíveis
    const botoes = await page.locator('button, a.btn-primary, a.btn-outline, a.btn-secondary').all();
    entry(`   Total de botões/links interativos na tela: ${botoes.length}`);
    for (let i = 0; i < Math.min(botoes.length, 20); i++) {
      const txt = (await botoes[i].textContent().catch(() => ''))?.trim().substring(0, 60);
      if (txt) entry(`      [${i}] "${txt}"`);
    }

    // Tentar clicar em algo ligado a pesagem/evento
    let tentativas = [
      'button:has-text("Pesagem")',
      'button:has-text("Registrar pesagem")',
      'button:has-text("+ Pesagem")',
      'button:has-text("Novo evento")',
      'button:has-text("Registrar evento")',
      'a:has-text("Pesagem")',
      'button:has-text("⚖")',
    ];

    let cliqueOk = false;
    for (const sel of tentativas) {
      const b = page.locator(sel).first();
      if (await b.count() > 0) {
        entry(`   Encontrado seletor: ${sel}`);
        try {
          await b.click({ timeout: 3000 });
          cliqueOk = true;
          entry(`   Clicado com sucesso`);
          break;
        } catch (e) {
          entry(`   Falha ao clicar: ${e.message.substring(0, 80)}`);
        }
      }
    }

    if (!cliqueOk) {
      entry(`   ❌ Nenhum botão de pesagem/evento clicável encontrado na tela de detalhe do animal`);

      // Tentar voltar à listagem e ver se as ações rápidas (ícones do card) funcionam
      entry('6. Voltar para listagem e tentar ações rápidas do card');
      await page.goto(`${BASE}/admin/rebanho/animais`, { waitUntil: 'networkidle' });
      await shot(page, `${viewport}-05-lista-volta`);

      // Screenshot dos ícones
      const iconesAcao = await page.locator('button.ActionIcon, button[title], [role="button"]').all();
      entry(`   Botões com title na listagem: ${iconesAcao.length}`);
      for (let i = 0; i < Math.min(iconesAcao.length, 30); i++) {
        const t = await iconesAcao[i].getAttribute('title').catch(() => null);
        if (t) entry(`      [${i}] title="${t}"`);
      }
    }

    await shot(page, `${viewport}-06-final`);

    // 7. TENTAR REGISTRAR VIA AJAX PARA CONFIRMAR SE FLUXO BACKEND FUNCIONA
    entry('7. Tentar POST direto no endpoint de evento (usando CSRF da página)');
    const dataPage = await page.locator('#app').getAttribute('data-page').catch(() => null);
    let animalId = null;
    if (dataPage) {
      try {
        const dp = JSON.parse(dataPage);
        animalId = dp.props?.animals?.data?.[0]?.id ?? dp.props?.animal?.id;
      } catch {}
    }
    if (!animalId) {
      // pegar do banco (acabamos de ver na URL do detalhe)
      const m = (log.find(l => l.includes('/rebanho/animais/')) ?? '').match(/\/animais\/(\d+)/);
      if (m) animalId = parseInt(m[1]);
    }
    entry(`   animalId detectado: ${animalId}`);

    if (animalId) {
      const result = await page.evaluate(async (id) => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const resp = await fetch(`/admin/rebanho/animais/${id}/eventos`, {
          method: 'POST',
          headers: {
            'X-Inertia': 'true',
            'X-Inertia-Version': '1',
            'X-XSRF-TOKEN': decodeURIComponent(document.cookie.split('XSRF-TOKEN=')[1]?.split(';')[0] ?? ''),
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': csrf ?? '',
          },
          credentials: 'same-origin',
          body: new URLSearchParams({
            tipo: 'pesagem',
            data: new Date().toISOString().slice(0, 10),
            peso: '450.5',
          }).toString(),
        });
        return { status: resp.status, url: resp.url, headers: Object.fromEntries(resp.headers) };
      }, animalId);
      entry(`   POST pesagem: status=${result.status}`);
      entry(`   Response URL: ${result.url}`);
    }

  } catch (e) {
    entry(`  ❌ EXCEÇÃO no teste: ${e.message.substring(0, 200)}`);
    await shot(page, `${viewport}-error`);
  }

  await ctx.close();
  await browser.close();
}

writeFileSync(join(OUT, 'log.txt'), log.join('\n'));
writeFileSync(join(OUT, 'console-errors.json'), JSON.stringify(consoleErrs, null, 2));
writeFileSync(join(OUT, 'network-fails.json'), JSON.stringify(networkFails, null, 2));

console.log(`\n══════════════════════════════════════════════════════════════════`);
console.log(`  Console errors: ${consoleErrs.length}`);
console.log(`  Network 4xx/5xx: ${networkFails.length}`);
console.log(`  Screenshots em: ${OUT}`);
console.log(`══════════════════════════════════════════════════════════════════`);
