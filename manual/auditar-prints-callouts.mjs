// Auditoria sistemática dos 2 manuais:
// 1. Toda seção com .passo deve ter pelo menos 1 <img>
// 2. Todo <img> em seção com .passo deve ter callouts numerados ou mockup
//
// Output: relatório por seção do que falta.
import fs from 'node:fs';

const MANUAIS = [
    'manual/manual-fazenda-macaybas.html',
    'manual/manual-master.html',
];

function auditar(arquivo) {
    const html = fs.readFileSync(arquivo, 'utf8');
    // Parse seções (sections.pagina) com regex (manuais são HTML simples)
    const sectionsRegex = /<section\s+class="pagina[^"]*"\s+id="([^"]+)"[^>]*>([\s\S]*?)<\/section>/g;
    const result = [];
    let match;
    while ((match = sectionsRegex.exec(html)) !== null) {
        const id = match[1];
        const body = match[2];
        const tituloMatch = body.match(/<h1\s+class="titulo-secao"[^>]*>([^<]+)<\/h1>/);
        const titulo = tituloMatch ? tituloMatch[1].trim() : '(continuação)';
        const passos = (body.match(/<div\s+class="passo">/g) || []).length;
        const imgs = (body.match(/<img\s+src=/g) || []).length;
        const mockups = (body.match(/class="mockup-modal/g) || []).length;
        const callouts = (body.match(/class="callout/g) || []).length;
        const print_com_callouts = (body.match(/class="print-com-callouts"/g) || []).length;

        // Status:
        // - 'OK_no_steps': não tem passos, ok
        // - 'OK_steps_with_print_callouts': tem passos + print + callouts ✅
        // - 'OK_steps_with_mockup': tem passos + mockup CSS (substitui print)
        // - 'MISSING_PRINT': tem passos mas SEM print nem mockup ❌
        // - 'PRINT_NO_CALLOUTS': tem passos + print MAS sem callouts ⚠️

        let status;
        if (passos === 0) {
            status = imgs > 0 ? 'OK_print_sem_passos' : 'OK_no_steps';
        } else if (mockups > 0) {
            status = 'OK_mockup';
        } else if (imgs === 0) {
            status = 'MISSING_PRINT';
        } else if (callouts === 0) {
            status = 'PRINT_SEM_CALLOUTS';
        } else {
            status = 'OK_print_com_callouts';
        }

        result.push({ id, titulo, passos, imgs, mockups, callouts, status });
    }
    return result;
}

console.log('==================================================');
for (const arq of MANUAIS) {
    console.log(`\n### ${arq}`);
    const sections = auditar(arq);

    const counts = sections.reduce((acc, s) => {
        acc[s.status] = (acc[s.status] || 0) + 1;
        return acc;
    }, {});
    console.log('Resumo:', counts);

    console.log('\n## Seções com PASSOS mas SEM PRINT (precisa capturar):');
    sections.filter(s => s.status === 'MISSING_PRINT').forEach(s => {
        console.log(`  ❌ #${s.id} · ${s.titulo} (${s.passos} passos)`);
    });

    console.log('\n## Seções com PRINT mas SEM CALLOUTS (precisa numerar):');
    sections.filter(s => s.status === 'PRINT_SEM_CALLOUTS').forEach(s => {
        console.log(`  ⚠️ #${s.id} · ${s.titulo} (${s.passos} passos · ${s.imgs} imgs)`);
    });
}
