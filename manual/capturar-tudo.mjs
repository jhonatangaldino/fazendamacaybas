// Captura TODAS as screenshots do manual com login validado.
// Uso: node manual/capturar-tudo.mjs
//
// Diferencial da versão anterior: este script VALIDA o login (jogou
// erro se URL não mudou de /login pra /admin) e usa waitUntil: 'networkidle'
// + sleep antes de fill, o padrão que funcionou nos QAs F1-F11.
//
// Login:
// - Tenant: <QA_TENANT_EMAIL> (tenant 1061 demo-manual,
//   1960 animais reais, 28 lotes, 23 transações = screenshots ricos)
// - Master: <QA_MASTER_EMAIL> (área da plataforma)

import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const SS = 'manual/screenshots';

// --- Cria estrutura de pastas ---
const SUBDIRS = ['desktop', 'mobile', 'master', 'modais', 'forms', 'especies', 'wizards', 'perfis'];
for (const d of SUBDIRS) await fs.mkdir(`${SS}/${d}`, { recursive: true });

const ADMIN_EMAIL = process.env.QA_TENANT_EMAIL ?? 'set-QA_TENANT_EMAIL-env';
const ADMIN_PWD = process.env.QA_PASSWORD ?? 'set-QA_PASSWORD-env';
const MASTER_EMAIL = process.env.QA_MASTER_EMAIL ?? 'set-QA_MASTER_EMAIL-env';
const MASTER_PWD = process.env.QA_PASSWORD ?? 'set-QA_PASSWORD-env';
const BASE_ADMIN = 'https://app.fazendamacaybas.com.br';
const BASE_MASTER = 'https://fazendamacaybas.com.br';

// --- Login validado: lança erro se redirecionamento não for pra /admin/* ---
async function loginAdmin(page) {
    console.log('  → login admin (tenant)...');
    await page.goto(`${BASE_ADMIN}/login`, { waitUntil: 'networkidle' });
    await sleep(800);
    await page.waitForSelector('input[id=email]', { timeout: 10000 });
    await page.fill('input[id=email]', ADMIN_EMAIL);
    await page.fill('input[id=password]', ADMIN_PWD);
    await Promise.all([
        page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {}),
        page.click('button[type=submit]'),
    ]);
    await sleep(2500);
    const url = page.url();
    if (! url.includes('/admin/')) {
        throw new Error(`LOGIN ADMIN FALHOU. URL final: ${url}`);
    }
    console.log(`    ✅ login admin OK · ${url}`);
}

async function loginMaster(page) {
    console.log('  → login master...');
    await page.goto(`${BASE_MASTER}/login`, { waitUntil: 'networkidle' });
    await sleep(800);
    await page.waitForSelector('input[id=email]', { timeout: 10000 });
    await page.fill('input[id=email]', MASTER_EMAIL);
    await page.fill('input[id=password]', MASTER_PWD);
    await Promise.all([
        page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {}),
        page.click('button[type=submit]'),
    ]);
    await sleep(2500);
    const url = page.url();
    if (! url.includes('/master/')) {
        throw new Error(`LOGIN MASTER FALHOU. URL final: ${url}`);
    }
    console.log(`    ✅ login master OK · ${url}`);
}

// Trocar pra fazenda Sede (id 79) pra ter catálogos completos
async function switchToSede(page) {
    await page.goto(`${BASE_ADMIN}/admin/inicio`, { waitUntil: 'networkidle' });
    await sleep(700);
    await page.evaluate(async () => {
        const csrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content');
        const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.split('=')[1];
        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (csrf) headers['X-CSRF-TOKEN'] = csrf;
        if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf);
        await fetch('/admin/fazenda/trocar', {
            method: 'POST', credentials: 'same-origin', headers,
            body: JSON.stringify({ farm_id: 79 }),
        });
    });
    await sleep(800);
}

const browser = await chromium.launch({ headless: true });

// =============== 1) PÚBLICO (login screen sem autenticar) ===============
console.log('\n=== PÚBLICO (login screens) ===');
{
    // Desktop
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    await page.goto(`${BASE_ADMIN}/login`, { waitUntil: 'networkidle' });
    await sleep(1500);
    await page.screenshot({ path: `${SS}/desktop/01-login.png`, fullPage: false });
    console.log('  ✅ desktop/01-login.png');
    await ctx.close();
}
{
    // Mobile
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    await page.goto(`${BASE_ADMIN}/login`, { waitUntil: 'networkidle' });
    await sleep(1500);
    await page.screenshot({ path: `${SS}/mobile/01-login.png`, fullPage: false });
    console.log('  ✅ mobile/01-login.png');
    await ctx.close();
}

// =============== 2) ADMIN (tenant) DESKTOP ===============
console.log('\n=== ADMIN (tenant) DESKTOP ===');
{
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    await loginAdmin(page);
    await switchToSede(page);

    // Listas / dashboards principais
    const rotas = [
        { url: '/admin/inicio',                              file: 'desktop/02-inicio-hub.png' },
        { url: '/admin/dashboard',                           file: 'desktop/03-painel.png' },
        { url: '/admin/rebanho',                             file: 'desktop/10-rebanho-hub.png' },
        { url: '/admin/rebanho/especies/bovino',             file: 'desktop/11-rebanho-bovino-dashboard.png' },
        { url: '/admin/rebanho/animais?species_id=4',        file: 'desktop/12-rebanho-animais-lista.png' },
        { url: '/admin/rebanho/lotes',                       file: 'desktop/13-rebanho-lotes.png' },
        { url: '/admin/rebanho/locais',                      file: 'desktop/14-rebanho-locais.png' },
        { url: '/admin/rebanho/controle-leiteiro',           file: 'desktop/15-rebanho-controle-leiteiro.png' },
        { url: '/admin/financeiro',                          file: 'desktop/20-financeiro-hub.png' },
        { url: '/admin/financeiro/transacoes',               file: 'desktop/21-financeiro-transacoes.png' },
        { url: '/admin/faturas',                             file: 'desktop/22-faturas.png' },
        { url: '/admin/agricola',                            file: 'desktop/30-agricola-hub.png' },
        { url: '/admin/agricola/talhoes',                    file: 'desktop/31-agricola-talhoes.png' },
        { url: '/admin/agricola/plantios',                   file: 'desktop/32-agricola-plantios.png' },
        { url: '/admin/estoque',                             file: 'desktop/40-estoque-hub.png' },
        { url: '/admin/estoque/itens',                       file: 'desktop/41-estoque-itens.png' },
        { url: '/admin/maquinas',                            file: 'desktop/50-maquinas-hub.png' },
        { url: '/admin/maquinas/veiculos',                   file: 'desktop/51-maquinas-veiculos.png' },
        { url: '/admin/tarefas',                             file: 'desktop/60-tarefas-lista.png' },
        { url: '/admin/documentos',                          file: 'desktop/70-documentos-lista.png' },
        { url: '/admin/parceiros',                           file: 'desktop/80-parceiros-lista.png' },
        { url: '/admin/relatorios',                          file: 'desktop/90-relatorios.png' },
        { url: '/admin/usuarios',                            file: 'desktop/95-usuarios.png' },
    ];
    for (const r of rotas) {
        await page.goto(BASE_ADMIN + r.url, { waitUntil: 'networkidle' });
        await sleep(2200);
        await page.screenshot({ path: `${SS}/${r.file}`, fullPage: false });
        console.log(`  ✅ ${r.file}`);
    }

    // Forms (cadastro)
    const forms = [
        { url: '/admin/rebanho/animais/novo?species_id=4',   file: 'forms/form-novo-animal-bovino.png' },
        { url: '/admin/rebanho/lotes/novo',                  file: 'forms/form-novo-lote.png' },
        { url: '/admin/financeiro/transacoes/nova?tipo=despesa', file: 'forms/form-nova-despesa.png' },
        { url: '/admin/financeiro/transacoes/nova?tipo=receita', file: 'forms/form-nova-receita.png' },
        { url: '/admin/funcionarios/novo',                   file: 'forms/form-novo-funcionario.png' },
        { url: '/admin/parceiros/novo',                      file: 'forms/form-novo-parceiro.png' },
        { url: '/admin/tarefas/nova',                        file: 'forms/form-nova-tarefa.png' },
    ];
    for (const f of forms) {
        await page.goto(BASE_ADMIN + f.url, { waitUntil: 'networkidle' });
        await sleep(2000);
        await page.screenshot({ path: `${SS}/${f.file}`, fullPage: false });
        console.log(`  ✅ ${f.file}`);
    }

    // Espécies (11 dashboards)
    const especies = ['bovino', 'equino', 'suino', 'caprino', 'ovino', 'ave', 'peixe', 'cao', 'gato', 'bufalo', 'coelho'];
    for (const esp of especies) {
        await page.goto(`${BASE_ADMIN}/admin/rebanho/especies/${esp}`, { waitUntil: 'networkidle' });
        await sleep(2200);
        await page.screenshot({ path: `${SS}/especies/${esp}.png`, fullPage: false });
        console.log(`  ✅ especies/${esp}.png`);
    }

    // Wizards (5 fluxos guiados)
    const wizards = [
        { url: '/admin/fluxos/venda-animal',                 file: 'wizards/wizard-venda-animal.png' },
        { url: '/admin/fluxos/aplicar-produto',              file: 'wizards/wizard-aplicar-produto.png' },
        { url: '/admin/fluxos/registrar-plantio',            file: 'wizards/wizard-registrar-plantio.png' },
        { url: '/admin/fluxos/saida-estoque',                file: 'wizards/wizard-saida-estoque.png' },
        { url: '/admin/fluxos/manutencao',                   file: 'wizards/wizard-manutencao.png' },
    ];
    for (const w of wizards) {
        try {
            await page.goto(BASE_ADMIN + w.url, { waitUntil: 'networkidle' });
            await sleep(2200);
            await page.screenshot({ path: `${SS}/${w.file}`, fullPage: false });
            console.log(`  ✅ ${w.file}`);
        } catch (e) {
            console.log(`  ⚠️ ${w.file}: ${e.message}`);
        }
    }

    // Modal pesagem (clica no botão "Pesar" do dashboard bovino)
    try {
        await page.goto(`${BASE_ADMIN}/admin/rebanho/especies/bovino`, { waitUntil: 'networkidle' });
        await sleep(2500);
        const pesar = page.locator('button, a').filter({ hasText: /^Pesar$/i }).first();
        if (await pesar.count()) {
            await pesar.click();
            await sleep(2000);
            await page.screenshot({ path: `${SS}/modais/modal-pesagem.png`, fullPage: false });
            console.log('  ✅ modais/modal-pesagem.png');
            await page.keyboard.press('Escape');
            await sleep(800);
        } else {
            console.log('  ⚠️ botão Pesar não encontrado');
        }
    } catch (e) {
        console.log(`  ⚠️ modal pesagem: ${e.message}`);
    }

    // Modal vacinação
    try {
        await page.goto(`${BASE_ADMIN}/admin/rebanho/especies/bovino`, { waitUntil: 'networkidle' });
        await sleep(2200);
        const vacinar = page.locator('button, a').filter({ hasText: /Vacinar/i }).first();
        if (await vacinar.count()) {
            await vacinar.click();
            await sleep(2000);
            await page.screenshot({ path: `${SS}/modais/modal-vacinacao.png`, fullPage: false });
            console.log('  ✅ modais/modal-vacinacao.png');
            await page.keyboard.press('Escape');
            await sleep(800);
        }
    } catch (e) {
        console.log(`  ⚠️ modal vacinação: ${e.message}`);
    }

    // Animal show timeline + modal de delete
    try {
        await page.goto(`${BASE_ADMIN}/admin/rebanho/animais?species_id=4`, { waitUntil: 'networkidle' });
        await sleep(2200);
        const link = await page.locator('a[href*="/admin/rebanho/animais/"]').first().getAttribute('href');
        if (link) {
            let p2 = link.startsWith('http') ? new URL(link).pathname : link;
            if (p2.endsWith('/novo')) {
                const all = await page.locator('a[href*="/admin/rebanho/animais/"]').all();
                for (const a of all) {
                    const h = await a.getAttribute('href');
                    if (h && /\/animais\/\d+/.test(h)) { p2 = h.startsWith('http') ? new URL(h).pathname : h; break; }
                }
            }
            await page.goto(BASE_ADMIN + p2, { waitUntil: 'networkidle' });
            await sleep(2800);
            await page.screenshot({ path: `${SS}/modais/animal-show-timeline.png`, fullPage: true });
            console.log('  ✅ modais/animal-show-timeline.png (fullPage)');

            const lixeira = page.locator('ol li button[title*="Remover"], ol li button[aria-label*="Remover"], ol li button[title*="Apagar"], ol li button[aria-label*="Apagar"]').first();
            if (await lixeira.count()) {
                await lixeira.click();
                await sleep(2000);
                await page.screenshot({ path: `${SS}/modais/modal-confirm-delete.png`, fullPage: false });
                console.log('  ✅ modais/modal-confirm-delete.png');
                await page.keyboard.press('Escape');
                await sleep(800);
            }
        }
    } catch (e) {
        console.log(`  ⚠️ animal show: ${e.message}`);
    }

    await ctx.close();
}

// =============== 3) ADMIN (tenant) MOBILE ===============
console.log('\n=== ADMIN (tenant) MOBILE ===');
{
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    await loginAdmin(page);
    await switchToSede(page);

    const rotas = [
        { url: '/admin/inicio',                              file: 'mobile/02-inicio-hub.png' },
        { url: '/admin/dashboard',                           file: 'mobile/03-painel.png' },
        { url: '/admin/rebanho',                             file: 'mobile/10-rebanho-hub.png' },
        { url: '/admin/rebanho/especies/bovino',             file: 'mobile/11-rebanho-bovino-dashboard.png' },
        { url: '/admin/rebanho/animais?species_id=4',        file: 'mobile/12-rebanho-animais-lista.png' },
        { url: '/admin/rebanho/lotes',                       file: 'mobile/13-rebanho-lotes.png' },
        { url: '/admin/rebanho/locais',                      file: 'mobile/14-rebanho-locais.png' },
        { url: '/admin/rebanho/controle-leiteiro',           file: 'mobile/15-rebanho-controle-leiteiro.png' },
        { url: '/admin/financeiro',                          file: 'mobile/20-financeiro-hub.png' },
        { url: '/admin/financeiro/transacoes',               file: 'mobile/21-financeiro-transacoes.png' },
        { url: '/admin/faturas',                             file: 'mobile/22-faturas.png' },
        { url: '/admin/agricola',                            file: 'mobile/30-agricola-hub.png' },
        { url: '/admin/agricola/talhoes',                    file: 'mobile/31-agricola-talhoes.png' },
        { url: '/admin/agricola/plantios',                   file: 'mobile/32-agricola-plantios.png' },
        { url: '/admin/estoque',                             file: 'mobile/40-estoque-hub.png' },
        { url: '/admin/estoque/itens',                       file: 'mobile/41-estoque-itens.png' },
        { url: '/admin/maquinas',                            file: 'mobile/50-maquinas-hub.png' },
        { url: '/admin/maquinas/veiculos',                   file: 'mobile/51-maquinas-veiculos.png' },
        { url: '/admin/tarefas',                             file: 'mobile/60-tarefas-lista.png' },
        { url: '/admin/documentos',                          file: 'mobile/70-documentos-lista.png' },
        { url: '/admin/parceiros',                           file: 'mobile/80-parceiros-lista.png' },
        { url: '/admin/relatorios',                          file: 'mobile/90-relatorios.png' },
        { url: '/admin/usuarios',                            file: 'mobile/95-usuarios.png' },
    ];
    for (const r of rotas) {
        await page.goto(BASE_ADMIN + r.url, { waitUntil: 'networkidle' });
        await sleep(1800);
        await page.screenshot({ path: `${SS}/${r.file}`, fullPage: false });
        console.log(`  ✅ ${r.file}`);
    }

    // Form mobile
    await page.goto(`${BASE_ADMIN}/admin/rebanho/animais/novo?species_id=4`, { waitUntil: 'networkidle' });
    await sleep(2000);
    await page.screenshot({ path: `${SS}/mobile/form-novo-animal.png`, fullPage: false });
    console.log('  ✅ mobile/form-novo-animal.png');

    // Sidebar aberta (hambúrguer)
    await page.goto(`${BASE_ADMIN}/admin/inicio`, { waitUntil: 'networkidle' });
    await sleep(2000);
    const hamb = page.locator('button[aria-label*="enu"], button[aria-label*="brir"], button[aria-label*="idebar"]').first();
    if (await hamb.count()) {
        await hamb.click();
        await sleep(1200);
        await page.screenshot({ path: `${SS}/mobile/sidebar-aberta.png`, fullPage: false });
        console.log('  ✅ mobile/sidebar-aberta.png');
    }

    // Modal pesagem mobile
    await page.goto(`${BASE_ADMIN}/admin/rebanho/especies/bovino`, { waitUntil: 'networkidle' });
    await sleep(2500);
    const pesarM = page.locator('button, a').filter({ hasText: /^Pesar$/i }).first();
    if (await pesarM.count()) {
        await pesarM.click();
        await sleep(1800);
        await page.screenshot({ path: `${SS}/mobile/modal-pesagem.png`, fullPage: false });
        console.log('  ✅ mobile/modal-pesagem.png');
    }

    await ctx.close();
}

// =============== 4) MASTER ===============
console.log('\n=== MASTER ===');
{
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    await loginMaster(page);

    const rotas = [
        { url: '/master/dashboard',  file: 'master/master-dashboard.png' },
        { url: '/master/tenants',    file: 'master/master-tenants.png' },
        { url: '/master/planos',     file: 'master/master-planos.png' },
        { url: '/master/cobrancas',  file: 'master/master-cobrancas.png' },
        { url: '/master/atividades', file: 'master/master-atividades.png' },
    ];
    for (const r of rotas) {
        await page.goto(BASE_MASTER + r.url, { waitUntil: 'networkidle' });
        await sleep(2000);
        await page.screenshot({ path: `${SS}/${r.file}`, fullPage: false });
        console.log(`  ✅ ${r.file}`);
    }
    await ctx.close();
}

await browser.close();
console.log('\n✅ Tudo capturado em manual/screenshots/');
