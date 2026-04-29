// Captura últimos faltantes: tenant detail + master CMS + dropdown avatar
import { chromium } from 'playwright';
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const SS = 'manual/screenshots';
const BASE = 'https://app.fazendamacaybas.com.br';

const browser = await chromium.launch({ headless: true });

// ======= MASTER (tenant detail pages) =======
{
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();

    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await sleep(800);
    await page.fill('input[id=email]', process.env.QA_MASTER_EMAIL);
    await page.fill('input[id=password]', process.env.QA_PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle', {timeout:25000}).catch(()=>{}), page.click('button[type=submit]')]);
    await sleep(2500);

    // Lista tenants pra pegar IDs
    await page.goto(`${BASE}/master/tenants`, { waitUntil: 'networkidle' });
    await sleep(2000);

    // Encontra o tenant 1 (master) ou 1061 (demo-manual) e usa
    const tenantId = 1061;

    const rotas = [
        { url: `/master/tenants/${tenantId}/editar`,        file: 'master/master-tenant-editar.png' },
        { url: `/master/tenants/${tenantId}/usuarios`,      file: 'master/master-tenant-usuarios.png' },
        { url: `/master/tenants/${tenantId}/assinatura`,    file: 'master/master-tenant-assinatura.png' },
        { url: `/master/tenants/${tenantId}/fazendas`,      file: 'master/master-tenant-fazendas.png' },
        { url: `/master/clientes/${tenantId}/cms`,          file: 'master/master-cliente-cms.png' },
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

    await ctx.close();
}

// ======= TENANT (avatar dropdown) =======
{
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();

    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await sleep(800);
    await page.fill('input[id=email]', process.env.QA_TENANT_EMAIL);
    await page.fill('input[id=password]', process.env.QA_PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle', {timeout:25000}).catch(()=>{}), page.click('button[type=submit]')]);
    await sleep(2500);
    await page.goto(`${BASE}/admin/inicio`, { waitUntil: 'networkidle' });
    await sleep(2500);

    // Tenta vários seletores pro avatar
    const candidatos = [
        'header [role="button"]:has-text("QA")',
        'header button:has(img)',
        'button.relative.flex.items-center',
        'header button.rounded-full',
    ];
    let achou = false;
    for (const sel of candidatos) {
        const loc = page.locator(sel).first();
        if (await loc.count().catch(()=>0)) {
            try {
                await loc.click({ timeout: 3000 });
                await sleep(1200);
                await page.screenshot({ path: `${SS}/desktop/avatar-dropdown.png`, fullPage: false });
                console.log('  ✅ desktop/avatar-dropdown.png · selector:', sel);
                achou = true;
                break;
            } catch (e) {}
        }
    }
    if (! achou) console.log('  ⚠️ avatar dropdown não capturado');

    await ctx.close();
}

await browser.close();
console.log('\n✅ Finais capturados.');
