// Gera 2 versões do manual cliente (desktop + mobile) a partir do template.
import { readFile, writeFile } from 'fs/promises';
import { execSync } from 'child_process';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');
const TEMPLATE = resolve(ROOT, 'docs/manual-cliente/manual-template.md');

const VARIANTS = [
    { id: 'desktop', label: 'Desktop' },
    { id: 'mobile',  label: 'Celular' },
];

const tpl = await readFile(TEMPLATE, 'utf8');

for (const v of VARIANTS) {
    const md = tpl
        .replaceAll('__IMG__', v.id)
        .replaceAll('__EDICAO__', v.label);
    const mdPath = resolve(ROOT, `docs/manual-cliente/manual-${v.id}.md`);
    const pdfPath = resolve(ROOT, `docs/manual-cliente/Manual-Cliente-${v.label}-v1.0.pdf`);
    await writeFile(mdPath, md, 'utf8');
    console.log(`\n→ Gerando ${v.label} (${v.id})...`);
    execSync(`node "${resolve(ROOT, 'scripts/md-to-pdf.mjs')}" "${mdPath}" "${pdfPath}"`, { stdio: 'inherit' });
}

console.log('\n✅ Ambos manuais gerados.');
