// scripts/md-to-pdf.mjs
// Converte um arquivo Markdown em PDF usando Chrome headless local (sem dependências externas).
// Uso: node scripts/md-to-pdf.mjs <input.md> <output.pdf>

import { readFile, writeFile, unlink } from 'fs/promises';
import { existsSync } from 'fs';
import { execSync } from 'child_process';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

// ====== Markdown → HTML mini-parser (sem lib externa) ======
function escapeHtml(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function mdToHtml(md) {
    const lines = md.split(/\r?\n/);
    const out = [];
    let inCode = false;
    let codeLang = '';
    let codeBuffer = [];
    let inList = false;
    let inTable = false;
    let tableHeader = null;
    let tableAligns = null;
    let para = [];

    function flushPara() {
        if (para.length) {
            out.push('<p>' + parseInline(para.join(' ')) + '</p>');
            para = [];
        }
    }
    function flushList() {
        if (inList) { out.push('</ul>'); inList = false; }
    }
    function flushTable() {
        if (inTable) { out.push('</tbody></table>'); inTable = false; tableHeader = null; tableAligns = null; }
    }
    function parseInline(s) {
        // Escapa HTML primeiro, exceto para tags já formadas por marcadores
        s = s.replace(/`([^`]+)`/g, (_, c) => `<code>${escapeHtml(c)}</code>`);
        s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        // Imagens ANTES de links (senão `![alt](src)` vira `!<a>`)
        s = s.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1">');
        s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');
        return s;
    }

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];

        // HTML block-level — passa direto sem virar <p>
        // Cobre <section>, <div>, <aside>, <figure>, <details>, <hr> etc
        if (/^<\/?(section|div|aside|figure|details|summary|article|nav|header|footer|main)\b/i.test(line.trim())) {
            flushPara(); flushList(); flushTable();
            out.push(line);
            continue;
        }

        // Bloco de código
        if (line.startsWith('```')) {
            if (inCode) {
                out.push(`<pre><code class="lang-${codeLang}">${escapeHtml(codeBuffer.join('\n'))}</code></pre>`);
                inCode = false; codeLang = ''; codeBuffer = [];
            } else {
                flushPara(); flushList(); flushTable();
                inCode = true;
                codeLang = line.substring(3).trim();
            }
            continue;
        }
        if (inCode) { codeBuffer.push(line); continue; }

        // Tabelas: detecta linha com | e separador ---
        const isTableLine = line.trim().startsWith('|') && line.trim().endsWith('|');
        if (isTableLine && !inTable) {
            const next = lines[i + 1] || '';
            if (/^\|\s*:?---+:?\s*(\|\s*:?---+:?\s*)+\|?$/.test(next.trim())) {
                flushPara(); flushList();
                const cells = line.trim().slice(1, -1).split('|').map(c => c.trim());
                const seps = next.trim().slice(1, -1).split('|').map(c => c.trim());
                tableAligns = seps.map(s => {
                    if (s.startsWith(':') && s.endsWith(':')) return 'center';
                    if (s.endsWith(':')) return 'right';
                    return 'left';
                });
                out.push('<table>');
                out.push('<thead><tr>' + cells.map((c, j) => `<th style="text-align:${tableAligns[j]}">${parseInline(c)}</th>`).join('') + '</tr></thead>');
                out.push('<tbody>');
                tableHeader = cells;
                inTable = true;
                i++; // pula o separador
                continue;
            }
        }
        if (isTableLine && inTable) {
            const cells = line.trim().slice(1, -1).split('|').map(c => c.trim());
            out.push('<tr>' + cells.map((c, j) => `<td style="text-align:${(tableAligns?.[j] || 'left')}">${parseInline(c)}</td>`).join('') + '</tr>');
            continue;
        }
        if (!isTableLine && inTable) flushTable();

        // Headings — adiciona id automático (slug do texto) pra permitir
        // links internos clicáveis no PDF (ex.: sumário aponta pra cada seção)
        const hMatch = line.match(/^(#{1,6})\s+(.+)$/);
        if (hMatch) {
            flushPara(); flushList();
            const level = hMatch[1].length;
            const text = hMatch[2];
            // Slugify: lowercase + remove acentos + troca não-alfanum por hífen
            const slug = text
                .toLowerCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[^\w\s-]/g, '')
                .trim()
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');
            out.push(`<h${level} id="${slug}">${parseInline(text)}</h${level}>`);
            continue;
        }

        // Divider
        if (/^---+$/.test(line.trim())) {
            flushPara(); flushList();
            out.push('<hr>');
            continue;
        }

        // Lista
        const listMatch = line.match(/^\s*[-*]\s+(.+)$/);
        if (listMatch) {
            flushPara();
            if (!inList) { out.push('<ul>'); inList = true; }
            out.push('<li>' + parseInline(listMatch[1]) + '</li>');
            continue;
        }
        if (inList && line.trim() === '') { flushList(); continue; }

        // Linha em branco
        if (line.trim() === '') { flushPara(); continue; }

        // Parágrafo
        para.push(line);
    }
    flushPara(); flushList(); flushTable();
    if (inCode) {
        out.push(`<pre><code>${escapeHtml(codeBuffer.join('\n'))}</code></pre>`);
    }
    return out.join('\n');
}

// ====== HTML template ======
function buildHtml(bodyHtml, title = 'Documento') {
    return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>${escapeHtml(title)}</title>
<style>
  @page {
    size: A4;
    margin: 20mm 18mm 22mm 18mm;
    @bottom-center {
      content: counter(page) " / " counter(pages);
      color: #94a3b8;
      font-size: 8pt;
      font-family: -apple-system, "Segoe UI", sans-serif;
    }
  }
  @page :first { margin: 0; @bottom-center { content: ''; } }

  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #1e293b;
    line-height: 1.6;
    font-size: 10.5pt;
  }

  /* Cabeçalhos */
  h1 { font-size: 24pt; color: #166534; margin-top: 0; margin-bottom: 12pt; font-weight: 700; }
  h2 {
    font-size: 18pt; color: #14532d; margin-top: 0; margin-bottom: 10pt;
    border-bottom: 2px solid #166534; padding-bottom: 6pt;
    page-break-before: always; page-break-after: avoid;
    font-weight: 700;
  }
  h3 {
    font-size: 13pt; color: #166534; margin-top: 18pt; margin-bottom: 6pt;
    page-break-after: avoid; break-after: avoid;
    font-weight: 600;
  }
  h4 { font-size: 11pt; color: #1f2937; margin-top: 12pt; margin-bottom: 4pt; page-break-after: avoid; font-weight: 600; }
  /* Heading + parágrafo seguinte ficam juntos */
  h2 + p, h3 + p, h4 + p { page-break-before: avoid; break-before: avoid; }

  /* Texto */
  p { margin: 5pt 0; orphans: 3; widows: 3; }
  a { color: #0369a1; text-decoration: none; }
  strong { color: #111827; font-weight: 600; }
  ul, ol { margin: 4pt 0 10pt 0; padding-left: 22pt; }
  li { margin: 3pt 0; }
  hr { border: none; border-top: 1px solid #e5e7eb; margin: 16pt 0; }

  /* Code */
  code {
    font-family: "SF Mono", "Consolas", "Monaco", monospace;
    background: #f1f5f9; padding: 1pt 5pt; border-radius: 3pt;
    font-size: 9pt; color: #be123c;
  }
  pre {
    background: #0f172a; color: #e2e8f0;
    padding: 10pt 12pt; border-radius: 6pt;
    font-size: 8.5pt; line-height: 1.45;
    page-break-inside: avoid;
  }
  pre code { background: transparent; color: inherit; padding: 0; }

  /* Tabelas */
  table {
    border-collapse: collapse; width: 100%;
    margin: 8pt 0 14pt 0; font-size: 9.5pt;
    page-break-inside: avoid;
  }
  th, td { border: 1px solid #e5e7eb; padding: 6pt 9pt; vertical-align: top; }
  th { background: #f0fdf4; color: #166534; font-weight: 600; text-align: left; }
  tr:nth-child(even) td { background: #f9fafb; }

  /* Imagens — bloco de tela inteira, com sombra suave.
     CRÍTICO: max-height força a imagem a CABER em uma página A4 retrato
     (área útil ~240mm). Sem isso, prints verticais altos (mobile 320×680px)
     viram 170×360mm e o Chrome QUEBRA AO MEIO mesmo com break-inside:avoid
     — é fallback obrigatório dele quando elemento > página inteira.
     Com max-height 210mm a imagem é redimensionada proporcionalmente
     (object-fit: contain) e cabe sempre em uma página. */
  img {
    max-width: 100%;
    max-height: 210mm;
    width: auto; height: auto;
    object-fit: contain;
    display: block;
    margin: 8pt auto;
    border: 1px solid #d1d5db; border-radius: 4pt;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    page-break-inside: avoid; break-inside: avoid;
    page-break-after: avoid; break-after: avoid;
  }
  /* Parágrafo logo após imagem — fica na mesma página da imagem */
  img + p { page-break-before: avoid; break-before: avoid; }

  /* Badge "NOVO" inline (chamado da v2.0) */
  .badge-novo {
    display: inline-block; vertical-align: middle;
    background: #16a34a; color: white;
    font-size: 8pt; font-weight: 700;
    padding: 2pt 6pt; border-radius: 10pt;
    margin-left: 6pt; letter-spacing: 0.4pt;
  }

  /* Citação */
  blockquote {
    border-left: 4px solid #166534;
    background: #f0fdf4;
    padding: 8pt 12pt;
    margin: 10pt 0;
    color: #166534;
    font-style: italic;
    page-break-inside: avoid;
  }

  /* Wrapper de seção — força h3 + img + steps a ficarem juntos */
  section.wizard, section.section-card {
    page-break-inside: avoid; break-inside: avoid;
    margin-bottom: 14pt;
  }
  section.wizard { padding-top: 4pt; }

  /* Callout boxes */
  .callout {
    border-left: 4px solid #0284c7;
    background: #f0f9ff;
    padding: 8pt 12pt;
    margin: 10pt 0;
    border-radius: 0 4pt 4pt 0;
    font-size: 10pt;
    page-break-inside: avoid;
  }
  .callout.tip { border-color: #16a34a; background: #f0fdf4; }
  .callout.warning { border-color: #ea580c; background: #fff7ed; }
  .callout.info { border-color: #0284c7; background: #f0f9ff; }
  .callout strong:first-child { display: block; margin-bottom: 3pt; color: #0c4a6e; }
  .callout.tip strong:first-child { color: #14532d; }
  .callout.warning strong:first-child { color: #7c2d12; }

  /* Boxes "Quando usar" / "Resultado" — pequenos rótulos coloridos */
  .field {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4pt;
    padding: 8pt 12pt;
    margin: 6pt 0;
    font-size: 10pt;
  }
  .field-label {
    font-size: 8pt;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    color: #64748b;
    font-weight: 600;
    margin-bottom: 3pt;
  }

  /* CAPA — primeira página */
  .cover {
    height: 297mm; width: 210mm;
    page-break-after: always;
    background: linear-gradient(135deg, #166534 0%, #14532d 60%, #ca8a04 100%);
    color: white;
    padding: 30mm 25mm;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .cover-logo {
    width: 80pt; height: 80pt;
    background: white;
    color: #166534;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36pt; font-weight: 700;
    font-family: Georgia, serif;
    margin-bottom: 18pt;
  }
  .cover-brand {
    font-size: 12pt;
    text-transform: uppercase;
    letter-spacing: 3pt;
    color: rgba(255,255,255,0.8);
    margin-bottom: 6pt;
  }
  .cover-title {
    font-size: 36pt;
    font-weight: 700;
    line-height: 1.1;
    margin: 0 0 14pt 0;
    color: white;
    border: none;
  }
  .cover-subtitle {
    font-size: 14pt;
    color: rgba(255,255,255,0.85);
    line-height: 1.5;
    max-width: 130mm;
  }
  .cover-meta {
    border-top: 1px solid rgba(255,255,255,0.3);
    padding-top: 14pt;
    font-size: 10pt;
    color: rgba(255,255,255,0.85);
    line-height: 1.7;
  }
  .cover-meta strong { color: white; }

  /* SUMÁRIO */
  .toc { page-break-after: always; }
  .toc h2 { page-break-before: auto; border-bottom: 2px solid #166534; }
  .toc-item {
    display: flex; justify-content: space-between;
    padding: 5pt 0; border-bottom: 1px dotted #cbd5e1;
    font-size: 11pt;
  }
  .toc-item.level-1 { font-weight: 600; color: #166534; padding-top: 10pt; }
  .toc-item.level-2 { padding-left: 16pt; color: #1e293b; }
  .toc-page { color: #94a3b8; font-variant-numeric: tabular-nums; }

  /* Página de capítulo (entre seções) */
  .chapter-divider {
    page-break-before: always;
    page-break-after: avoid;
    text-align: center;
    padding-top: 60mm;
  }
  .chapter-divider .chapter-num {
    font-size: 11pt;
    color: #ca8a04;
    text-transform: uppercase;
    letter-spacing: 4pt;
    margin-bottom: 8pt;
  }
  .chapter-divider .chapter-title {
    font-size: 28pt;
    color: #166534;
    font-weight: 700;
    margin: 0;
  }
  .chapter-divider .chapter-subtitle {
    font-size: 12pt;
    color: #64748b;
    margin-top: 8pt;
    max-width: 120mm;
    margin-left: auto;
    margin-right: auto;
  }
</style>
</head>
<body>
${bodyHtml}
</body>
</html>`;
}

// ====== Main ======
async function main() {
    const [,, inputPath, outputPath] = process.argv;
    if (!inputPath || !outputPath) {
        console.error('Uso: node md-to-pdf.mjs <input.md> <output.pdf>');
        process.exit(1);
    }

    const input = resolve(inputPath);
    const output = resolve(outputPath);
    if (!existsSync(input)) {
        console.error('Arquivo não encontrado:', input);
        process.exit(1);
    }

    const md = await readFile(input, 'utf8');
    // Extrai título (1ª linha com #)
    const titleMatch = md.match(/^#\s+(.+)$/m);
    const title = titleMatch ? titleMatch[1] : 'Documento';

    const body = mdToHtml(md);
    const html = buildHtml(body, title);

    const tmpHtml = output.replace(/\.pdf$/i, '.tmp.html');
    await writeFile(tmpHtml, html, 'utf8');

    // Chrome headless path (Windows)
    const chromeCandidates = [
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    ];
    const chrome = chromeCandidates.find(p => existsSync(p));
    if (!chrome) {
        console.error('Chrome/Edge não encontrado em caminhos comuns.');
        process.exit(1);
    }

    // Gera PDF
    const fileUrl = 'file:///' + tmpHtml.replace(/\\/g, '/');
    const cmd = `"${chrome}" --headless=new --disable-gpu --no-pdf-header-footer --print-to-pdf="${output}" "${fileUrl}"`;

    console.log('Gerando PDF...');
    try {
        execSync(cmd, { stdio: 'inherit' });
    } catch (err) {
        console.error('Falha ao chamar Chrome:', err.message);
        process.exit(1);
    }

    // Remove HTML temporário
    try { await unlink(tmpHtml); } catch {}

    console.log('✅ PDF gerado:', output);
}

main().catch(err => {
    console.error('Erro:', err);
    process.exit(1);
});
