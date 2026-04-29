// Fase 4 final: callouts master + 1 print faltante
import fs from 'node:fs';
const FILE = 'manual/manual-master.html';
let html = fs.readFileSync(FILE, 'utf8');

function sub(antes, depois, label) {
    if (! html.includes(antes)) { console.log(`⚠️ NÃO ACHOU: ${label}`); return; }
    html = html.replace(antes, depois);
    console.log(`✅ ${label}`);
}

function wrap(imgSrc, alt, legenda, callouts, lista) {
    const cd = callouts.map((c, i) => {
        const cor = c.cor || (i < 2 ? '' : i < 3 ? 'azul' : i < 4 ? 'laranja' : 'verde');
        return `            <div class="callout ${cor}" style="${c.pos}">${i + 1}</div>`;
    }).join('\n');
    const items = lista.map(x => `            <li>${x}</li>`).join('\n');
    return `<div class="caixa-print">
        <div class="print-com-callouts">
            <img src="${imgSrc}" alt="${alt}">
${cd}
        </div>
        <div class="legenda">${legenda}</div>
    </div>

    <div class="callouts-lista">
        <ol>
${items}
        </ol>
    </div>`;
}

// 8. Impersonar (entrar como cliente) — callouts
sub(
    `    <div class="caixa-print">
        <img src="screenshots/master/master-tenants.png" alt="Lista de clientes com ação Impersonar">
        <div class="legenda">Lista de clientes · cada linha tem ícone "🎭 Impersonar"</div>
    </div>`,
    wrap(
        'screenshots/master/master-tenants.png',
        'Lista de clientes com ação',
        'Lista de clientes · ícone "🎭 Impersonar" em cada linha',
        [
            { pos: 'top: 22%; left: 50%;' },
            { pos: 'top: 38%; left: 30%;' },
            { pos: 'top: 55%; right: 20%;', cor: 'azul' },
            { pos: 'top: 55%; right: 6%;', cor: 'laranja' },
        ],
        [
            '<strong>Filtros</strong>: status, plano, busca por nome.',
            '<strong>Linha do cliente</strong>: clique no nome pra ver detalhes.',
            '<strong>🎭 Impersonar</strong>: entra como o cliente. Tudo fica auditado com seu nome master.',
            '<strong>Ações</strong>: editar · usuários · CMS · suspender.',
        ]
    ),
    '8. Impersonar · callouts'
);

// 14. Validar comprovante — callouts
sub(
    `    <div class="caixa-print">
        <img src="screenshots/master/master-cobrancas.png" alt="Lista de cobranças">
        <div class="legenda">Lista de cobranças · filtre por "Em validação" pra ver os pendentes</div>
    </div>`,
    wrap(
        'screenshots/master/master-cobrancas.png',
        'Lista de cobranças',
        'Lista de cobranças · filtre por "Em validação"',
        [
            { pos: 'top: 22%; left: 50%;' },
            { pos: 'top: 38%; left: 30%;' },
            { pos: 'top: 55%; right: 20%;', cor: 'azul' },
            { pos: 'top: 88%; right: 12%;', cor: 'laranja' },
        ],
        [
            '<strong>KPIs</strong>: Total a receber · Em aberto · Atrasadas · Em validação.',
            '<strong>Filtro por status</strong>: filtre "Em validação" pra ver pendências.',
            '<strong>Linha da fatura</strong>: clique pra abrir tela de validação com comprovante anexado.',
            '<strong>+ Gerar faturas</strong>: wizard pra gerar mensalidades em lote (seção 13).',
        ]
    ),
    '14. Validar comprovante · callouts'
);

// 15. Auto-aprovação — adicionar print
sub(
    `    <p>Validar manual cada comprovante toma tempo. O sistema pode <strong>tentar aprovar sozinho</strong> via leitura automática (OCR) (lê o comprovante PIX, extrai valor + E2E ID + beneficiário, e compara com a fatura).</p>

    <div class="caixa-print">
        <img src="screenshots/master/master-cobrancas.png" alt="Tela de cobranças com config auto-aprovação">
        <div class="legenda">Configurações de cobrança · toggle de auto-aprovação fica em "Configurações"</div>
    </div>

    <h2>Como ligar</h2>`,
    `    <p>Validar manual cada comprovante toma tempo. O sistema pode <strong>tentar aprovar sozinho</strong> via leitura automática (OCR) (lê o comprovante PIX, extrai valor + E2E ID + beneficiário, e compara com a fatura).</p>

    <div class="caixa-print">
        <img src="screenshots/master/master-cobrancas.png" alt="Cobranças">
        <div class="legenda">Acesse "Configurações" no topo da tela de Cobranças pra ligar a auto-aprovação</div>
    </div>

    <h2>Como ligar</h2>
    <div class="passo">
        <div class="num">1</div>
        <div class="conteudo"><em>Cobranças → Configurações → Auto-aprovação</em>.</div>
    </div>
    <div class="passo">
        <div class="num">2</div>
        <div class="conteudo">Toggle <strong>ON</strong>. Sistema começa a processar comprovantes em background.</div>
    </div>
    <div class="passo">
        <div class="num">3</div>
        <div class="conteudo">Quando a <em>maturidade</em> (% de acertos) chegar a 70%+, sistema aprova automaticamente os de alta confiança.</div>
    </div>

    <h2>Como ligar (alternativa)</h2>`,
    '15. Auto-aprovação · print + passos'
);

// 23. Boas práticas — callouts
sub(
    `    <div class="caixa-print">
        <img src="screenshots/master/master-dashboard.png" alt="Painel Master">
        <div class="legenda">Painel Master · sua referência diária pra rotina operacional</div>
    </div>`,
    wrap(
        'screenshots/master/master-dashboard.png',
        'Painel Master',
        'Painel Master · sua referência diária',
        [
            { pos: 'top: 25%; left: 30%;' },
            { pos: 'top: 25%; left: 70%;' },
            { pos: 'top: 50%; left: 50%;', cor: 'azul' },
            { pos: 'top: 75%; left: 50%;', cor: 'laranja' },
        ],
        [
            '<strong>Clientes ativos</strong>: total de assinantes em uso.',
            '<strong>Receita Mensal Recorrente</strong>: soma das mensalidades dos clientes ativos.',
            '<strong>Comprovantes pendentes</strong>: clique pra validar (seção 14).',
            '<strong>Atividades recentes</strong>: log dos últimos eventos significativos.',
        ]
    ),
    '23. Boas práticas · callouts'
);

fs.writeFileSync(FILE, html);
console.log('\n✅ Fase 4 final salva.');
