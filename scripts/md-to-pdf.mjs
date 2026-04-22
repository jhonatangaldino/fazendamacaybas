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
        s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');
        return s;
    }

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];

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

        // Headings
        const hMatch = line.match(/^(#{1,6})\s+(.+)$/);
        if (hMatch) {
            flushPara(); flushList();
            out.push(`<h${hMatch[1].length}>${parseInline(hMatch[2])}</h${hMatch[1].length}>`);
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
  @page { size: A4; margin: 18mm 16mm; }
  * { box-sizing: border-box; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #1e293b;
    line-height: 1.55;
    font-size: 11pt;
  }
  h1 { font-size: 22pt; color: #166534; border-bottom: 3px solid #166534; padding-bottom: 6pt; margin-top: 0; }
  h2 { font-size: 16pt; color: #14532d; margin-top: 22pt; border-bottom: 1px solid #d1d5db; padding-bottom: 3pt; }
  h3 { font-size: 13pt; color: #166534; margin-top: 16pt; }
  h4 { font-size: 11pt; color: #1f2937; margin-top: 12pt; }
  p { margin: 6pt 0; }
  a { color: #0369a1; text-decoration: none; }
  strong { color: #111827; }
  code {
    font-family: "SF Mono", "Consolas", "Monaco", monospace;
    background: #f1f5f9;
    padding: 1pt 4pt;
    border-radius: 3pt;
    font-size: 9.5pt;
    color: #be123c;
  }
  pre {
    background: #0f172a;
    color: #e2e8f0;
    padding: 10pt 12pt;
    border-radius: 6pt;
    overflow-x: auto;
    font-size: 8.5pt;
    line-height: 1.45;
    page-break-inside: avoid;
  }
  pre code { background: transparent; color: inherit; padding: 0; }
  ul { margin: 4pt 0 8pt 0; padding-left: 20pt; }
  li { margin: 2pt 0; }
  hr { border: none; border-top: 2px solid #e5e7eb; margin: 18pt 0; }
  table {
    border-collapse: collapse;
    width: 100%;
    margin: 8pt 0 12pt 0;
    page-break-inside: avoid;
    font-size: 9.5pt;
  }
  th, td {
    border: 1px solid #e5e7eb;
    padding: 5pt 7pt;
    vertical-align: top;
  }
  th { background: #f0fdf4; color: #166534; font-weight: 600; text-align: left; }
  tr:nth-child(even) td { background: #f9fafb; }
  blockquote {
    border-left: 3px solid #166534;
    padding-left: 10pt;
    color: #475569;
    margin: 8pt 0;
  }
  .emoji { font-family: "Segoe UI Emoji", "Apple Color Emoji", "Noto Color Emoji"; }
  h1, h2, h3 { page-break-after: avoid; }
  table, pre, ul { page-break-inside: avoid; }
  .footer {
    position: fixed;
    bottom: 5mm;
    left: 0; right: 0;
    text-align: center;
    font-size: 8pt;
    color: #94a3b8;
  }
</style>
</head>
<body>
${bodyHtml}
<div class="footer">Fazenda Macaybas — FASE 1 de Evolução · gerado em ${new Date().toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' })}</div>
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
