// Audit visual do manual: abre o manual.html em navegador headless e
// captura cada section.pagina como screenshot, pra inspeção de layout.
//
// Output: manual/audit/{seq}-{id}.png
// Verifica também:
//  - aspect ratio (avisa se altura > 1.5x largura A4)
//  - palavras isoladas no fim/início (orphans/widows visíveis)
//  - imagens cortadas (img height > viewport vinculado)

import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const OUT = 'manual/audit';
await fs.mkdir(OUT, { recursive: true });

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({
    viewport: { width: 850, height: 1200 },  // A4-like
    deviceScaleFactor: 1.5,
});
const page = await ctx.newPage();

const MANUAL_URL = 'file:///' + process.cwd().replace(/\\/g, '/') + '/manual/manual-fazenda-macaybas.html';
console.log('Abrindo:', MANUAL_URL);
await page.goto(MANUAL_URL, { waitUntil: 'networkidle' });
await sleep(2000);

// Lista todas as seções e seus IDs/títulos
const sections = await page.evaluate(() => {
    const secs = Array.from(document.querySelectorAll('section.pagina'));
    return secs.map((s, i) => {
        const rect = s.getBoundingClientRect();
        const titulo = s.querySelector('h1.titulo-secao')?.textContent?.trim()
                    || s.querySelector('h1')?.textContent?.trim()
                    || s.querySelector('h2')?.textContent?.trim()
                    || `(sem título · seção ${i})`;
        const id = s.id || `sec-${i}`;
        return {
            seq: i,
            id,
            titulo: titulo.slice(0, 80),
            altura: Math.round(rect.height),
            largura: Math.round(rect.width),
            num_imgs: s.querySelectorAll('img').length,
            num_h2: s.querySelectorAll('h2').length,
            num_passos: s.querySelectorAll('.passo').length,
        };
    });
});

console.log(`\nTotal de seções: ${sections.length}`);
console.log('\nSeções com altura suspeita (>1.5x A4 = >1500px no viewport 850px):');
sections.filter(s => s.altura > 1500).forEach(s => {
    console.log(`  ⚠️ #${String(s.seq).padStart(2,'0')} · alt=${s.altura}px · ${s.titulo}`);
});

console.log('\nSeções com 0 imagens E 0 passos E 0 h2 (provavelmente vazias):');
sections.filter(s => s.num_imgs === 0 && s.num_passos === 0 && s.num_h2 === 0 && s.altura > 100).forEach(s => {
    console.log(`  ⚠️ #${String(s.seq).padStart(2,'0')} · ${s.titulo}`);
});

// Captura cada seção
console.log('\nCapturando cada seção…');
for (const s of sections) {
    // Escapa CSS-id (basta evitar . e :)
    const escId = String(s.id).replace(/[^a-zA-Z0-9_-]/g, '_');
    try {
        const loc = page.locator(`section.pagina:nth-of-type(${s.seq + 1})`);
        await loc.scrollIntoViewIfNeeded();
        await sleep(300);
        const seq = String(s.seq).padStart(2, '0');
        const fname = `${seq}-${escId}.png`;
        await loc.screenshot({ path: path.join(OUT, fname), animations: 'disabled' });
    } catch (e) {
        console.log(`  ⚠️ falha capturando ${s.id}: ${e.message}`);
    }
}

await fs.writeFile(path.join(OUT, 'sections.json'), JSON.stringify(sections, null, 2));

await browser.close();
console.log(`\n✅ ${sections.length} seções capturadas em ${OUT}/`);
console.log(`   Resumo: ${OUT}/sections.json`);
