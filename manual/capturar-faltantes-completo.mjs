// Captura TODOS screenshots faltantes (auditoria sistemática)
// usuário: trocar-senha, trocar-fazenda, ordenha, mover-local, marcar-paga, estornar, receber, ajustar
// master: criar-cliente, impersonar, reset-senha, assinatura, gerar-fatura, validar-comprovante, cms
import { chromium } from 'playwright';
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const SS = 'manual/screenshots';
const BASE = 'https://app.fazendamacaybas.com.br';

const browser = await chromium.launch({ headless: true });

// ======= TENANT (manual usuário) =======
console.log('\n=== TENANT (manual usuário) ===');
{
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();

    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await sleep(800);
    await page.waitForSelector('input[id=email]', { timeout: 10000 });
    await page.fill('input[id=email]', process.env.QA_TENANT_EMAIL ?? 'set-QA_TENANT_EMAIL-env');
    await page.fill('input[id=password]', process.env.QA_PASSWORD ?? 'set-QA_PASSWORD-env');
    await Promise.all([page.waitForLoadState('networkidle', {timeout:25000}).catch(()=>{}), page.click('button[type=submit]')]);
    await sleep(2500);
    if (! page.url().includes('/admin/')) throw new Error('login falhou');

    await page.evaluate(async () => {
        const csrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content');
        const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.split('=')[1];
        await fetch('/admin/fazenda/trocar', {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf,'X-XSRF-TOKEN':decodeURIComponent(xsrf)},
            body: JSON.stringify({ farm_id: 79 }),
        });
    });
    await sleep(800);
    console.log('  ✅ login + sede');

    const rotas = [
        // Funcionalidades de conta
        { url: '/alterar-senha',                            file: 'forms/form-trocar-senha.png' },
        { url: '/admin/fazenda/selecionar',                 file: 'desktop/farm-selecionar.png' },

        // Wizards faltantes
        { url: '/admin/fluxos/receber-mercadoria',          file: 'wizards/wizard-receber-mercadoria.png' },
        { url: '/admin/fluxos/ajustar-estoque',             file: 'wizards/wizard-ajustar-estoque.png' },
        { url: '/admin/fluxos/registrar-colheita',          file: 'wizards/wizard-registrar-colheita.png' },
        { url: '/admin/fluxos/anexar-documento',            file: 'wizards/wizard-anexar-documento.png' },

        // Animal show (linha do tempo) com vacinação ativa
        { url: '/admin/rebanho/animais',                    file: 'desktop/lista-animais-todas.png' },
        { url: '/admin/financeiro/transacoes',              file: 'desktop/financeiro-transacoes-lista.png' },
    ];
    for (const r of rotas) {
        try {
            await page.goto(BASE + r.url, { waitUntil: 'networkidle' });
            await sleep(2500);
            await page.screenshot({ path: `${SS}/${r.file}`, fullPage: false });
            console.log(`  ✅ ${r.file}`);
        } catch (e) {
            console.log(`  ⚠️ ${r.file}: ${e.message}`);
        }
    }

    // Avatar dropdown aberto (Trocar senha + Sair)
    try {
        await page.goto(`${BASE}/admin/inicio`, { waitUntil: 'networkidle' });
        await sleep(2000);
        const avatar = page.locator('header button img, header button [class*="avatar"]').first();
        if (await avatar.count()) {
            await avatar.click();
            await sleep(800);
            await page.screenshot({ path: `${SS}/desktop/avatar-dropdown.png`, fullPage: false });
            console.log('  ✅ desktop/avatar-dropdown.png');
        }
    } catch (e) { console.log('  ⚠️ avatar dropdown:', e.message); }

    await ctx.close();
}

// ======= MASTER =======
console.log('\n=== MASTER ===');
{
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();

    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await sleep(800);
    await page.waitForSelector('input[id=email]', { timeout: 10000 });
    await page.fill('input[id=email]', process.env.QA_MASTER_EMAIL ?? 'set-QA_MASTER_EMAIL-env');
    await page.fill('input[id=password]', process.env.QA_PASSWORD ?? 'set-QA_PASSWORD-env');
    await Promise.all([page.waitForLoadState('networkidle', {timeout:25000}).catch(()=>{}), page.click('button[type=submit]')]);
    await sleep(2500);
    if (! page.url().includes('/master/')) throw new Error('login master falhou');
    console.log('  ✅ login master');

    const rotasMaster = [
        { url: '/master/tenants/novo',          file: 'master/master-criar-cliente.png' },
        { url: '/master/planos/novo',           file: 'master/master-criar-plano.png' },
        { url: '/master/cobrancas/gerar',       file: 'master/master-gerar-faturas.png' },
        { url: '/master/cms',                   file: 'master/master-cms.png' },
    ];
    for (const r of rotasMaster) {
        try {
            await page.goto(BASE + r.url, { waitUntil: 'networkidle' });
            await sleep(2500);
            await page.screenshot({ path: `${SS}/${r.file}`, fullPage: false });
            console.log(`  ✅ ${r.file}`);
        } catch (e) {
            console.log(`  ⚠️ ${r.file}: ${e.message}`);
        }
    }

    // Tenant detail (com botões Impersonar, Editar, Suspender, Usuários)
    try {
        await page.goto(`${BASE}/master/tenants`, { waitUntil: 'networkidle' });
        await sleep(2200);
        const editLink = page.locator('a[href*="/master/tenants/"][href*="/editar"]').first();
        if (await editLink.count()) {
            const href = await editLink.getAttribute('href');
            await page.goto(BASE + href, { waitUntil: 'networkidle' });
            await sleep(2500);
            await page.screenshot({ path: `${SS}/master/master-tenant-editar.png`, fullPage: false });
            console.log('  ✅ master/master-tenant-editar.png');

            // Pega o ID do tenant pra acessar usuários e assinatura
            const tenantId = href.match(/\/tenants\/(\d+)/)?.[1];
            if (tenantId) {
                await page.goto(`${BASE}/master/tenants/${tenantId}/usuarios`, { waitUntil: 'networkidle' });
                await sleep(2200);
                await page.screenshot({ path: `${SS}/master/master-tenant-usuarios.png`, fullPage: false });
                console.log('  ✅ master/master-tenant-usuarios.png');

                await page.goto(`${BASE}/master/tenants/${tenantId}/assinatura`, { waitUntil: 'networkidle' });
                await sleep(2200);
                await page.screenshot({ path: `${SS}/master/master-tenant-assinatura.png`, fullPage: false });
                console.log('  ✅ master/master-tenant-assinatura.png');
            }
        }
    } catch (e) { console.log('  ⚠️ tenant detail:', e.message); }

    // Validar comprovante (pega o primeiro)
    try {
        await page.goto(`${BASE}/master/cobrancas?status=em_validacao`, { waitUntil: 'networkidle' });
        await sleep(2000);
        const validateLink = page.locator('a[href*="/master/cobrancas/"][href*="/validar"]').first();
        if (await validateLink.count()) {
            const href = await validateLink.getAttribute('href');
            await page.goto(BASE + href, { waitUntil: 'networkidle' });
            await sleep(2500);
            await page.screenshot({ path: `${SS}/master/master-validar-comprovante.png`, fullPage: false });
            console.log('  ✅ master/master-validar-comprovante.png');
        } else {
            console.log('  ⚠️ nenhum comprovante em validação para print');
        }
    } catch (e) { console.log('  ⚠️ validar comprovante:', e.message); }

    await ctx.close();
}

await browser.close();
console.log('\n✅ Captura completa.');
