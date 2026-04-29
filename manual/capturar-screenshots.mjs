// Captura todos os screenshots do manual em desktop + mobile
import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const SS = 'manual/screenshots';
await fs.mkdir(`${SS}/desktop`, { recursive: true });
await fs.mkdir(`${SS}/mobile`, { recursive: true });

const VIEWPORTS = [
    { name: 'desktop', w: 1366, h: 900, dir: 'desktop' },
    { name: 'mobile', w: 390, h: 844, dir: 'mobile' },  // iPhone-ish
];

const ROTAS = [
    // Páginas públicas
    { url: '/login', file: '01-login', loginRequired: false, desc: 'Tela de login' },

    // Pós-login (em ordem do manual)
    { url: '/admin/inicio', file: '02-inicio-hub', desc: 'Hub Início — O que você quer fazer?' },
    { url: '/admin/dashboard', file: '03-painel', desc: 'Painel — visão geral' },

    // REBANHO
    { url: '/admin/rebanho', file: '10-rebanho-hub', desc: 'Hub Rebanho' },
    { url: '/admin/rebanho/especies/bovino', file: '11-rebanho-bovino-dashboard', desc: 'Dashboard espécie Bovino' },
    { url: '/admin/rebanho/animais?species_id=4', file: '12-rebanho-animais-lista', desc: 'Lista de animais Bovino' },
    { url: '/admin/rebanho/lotes', file: '13-rebanho-lotes', desc: 'Lista de lotes' },
    { url: '/admin/rebanho/locais', file: '14-rebanho-locais', desc: 'Lista de locais (pastos)' },
    { url: '/admin/rebanho/controle-leiteiro', file: '15-rebanho-controle-leiteiro', desc: 'Controle Leiteiro' },

    // FINANCEIRO
    { url: '/admin/financeiro', file: '20-financeiro-hub', desc: 'Hub Financeiro' },
    { url: '/admin/financeiro/transacoes', file: '21-financeiro-transacoes', desc: 'Lista de transações' },
    { url: '/admin/faturas', file: '22-faturas', desc: 'Faturas do tenant' },

    // AGRÍCOLA
    { url: '/admin/agricola', file: '30-agricola-hub', desc: 'Hub Agrícola' },
    { url: '/admin/agricola/talhoes', file: '31-agricola-talhoes', desc: 'Lista de talhões' },
    { url: '/admin/agricola/plantios', file: '32-agricola-plantios', desc: 'Lista de plantios' },

    // ESTOQUE
    { url: '/admin/estoque', file: '40-estoque-hub', desc: 'Hub Estoque' },
    { url: '/admin/estoque/itens', file: '41-estoque-itens', desc: 'Lista de itens' },

    // MÁQUINAS
    { url: '/admin/maquinas', file: '50-maquinas-hub', desc: 'Hub Máquinas' },
    { url: '/admin/maquinas/veiculos', file: '51-maquinas-veiculos', desc: 'Lista de veículos' },

    // TAREFAS
    { url: '/admin/tarefas', file: '60-tarefas-lista', desc: 'Lista de tarefas' },

    // DOCUMENTOS
    { url: '/admin/documentos', file: '70-documentos-lista', desc: 'Lista de documentos' },

    // PARCEIROS
    { url: '/admin/parceiros', file: '80-parceiros-lista', desc: 'Lista de parceiros' },

    // RELATÓRIOS
    { url: '/admin/relatorios', file: '90-relatorios', desc: 'Relatórios consolidados' },

    // USUÁRIOS
    { url: '/admin/usuarios', file: '95-usuarios', desc: 'Gestão de usuários' },
];

const browser = await chromium.launch({ headless: true });

for (const vp of VIEWPORTS) {
    console.log(`\n=== ${vp.name.toUpperCase()} (${vp.w}x${vp.h}) ===`);
    const ctx = await browser.newContext({ viewport: { width: vp.w, height: vp.h }, deviceScaleFactor: 2 });
    const page = await ctx.newPage();

    for (const rota of ROTAS) {
        const filename = `${rota.file}.png`;
        const filepath = path.join(SS, vp.dir, filename);

        if (rota.loginRequired === false) {
            // Página pública
            await page.goto('https://app.fazendamacaybas.com.br' + rota.url);
            await sleep(1500);
        } else {
            // Pós-login: garante que está logado
            const cur = page.url();
            if (! cur.includes('/admin/')) {
                await page.goto('https://app.fazendamacaybas.com.br/login');
                await sleep(800);
                await page.fill('input[id=email]', process.env.QA_TENANT_EMAIL ?? 'set-QA_TENANT_EMAIL-env').catch(() => {});
                await page.fill('input[id=password]', process.env.QA_PASSWORD ?? 'set-QA_PASSWORD-env').catch(() => {});
                await page.click('button[type=submit]').catch(() => {});
                await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
            }
            await page.goto('https://app.fazendamacaybas.com.br' + rota.url);
            await sleep(2000);
        }

        await page.screenshot({ path: filepath, fullPage: false });
        console.log(`  ✅ ${filename}`);
    }

    // Bonus: screenshots de modais (somente desktop pra economizar)
    if (vp.name === 'desktop') {
        // Modal de pesagem na timeline de animal
        try {
            await page.goto('https://app.fazendamacaybas.com.br/admin/rebanho/especies/bovino');
            await sleep(2000);
            const pesar = page.locator('button, a').filter({ hasText: /^Pesar/ }).first();
            if (await pesar.count()) {
                await pesar.click();
                await sleep(1500);
                await page.screenshot({ path: path.join(SS, vp.dir, '16-modal-pesagem.png'), fullPage: false });
                console.log('  ✅ 16-modal-pesagem.png');
                await page.keyboard.press('Escape');
                await sleep(500);
            }
        } catch (e) { console.log('  ⚠️ modal pesagem:', e.message); }
    }

    await ctx.close();
}

await browser.close();
console.log('\n✅ Todos os screenshots capturados em manual/screenshots/');
